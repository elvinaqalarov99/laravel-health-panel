<?php

namespace Elvinaqalarov99\StatusPage\Services;

use Carbon\Carbon;
use Elvinaqalarov99\StatusPage\Contracts\StatusPageRepositoryContract;
use Elvinaqalarov99\StatusPage\Contracts\StatusPageServiceContract;
use Elvinaqalarov99\StatusPage\Enums\HealthCheckStatus;
use Exception;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

readonly class StatusPageService implements StatusPageServiceContract
{
    private const INCIDENT_STABILITY_MINUTES = 15;

    public function __construct(private StatusPageRepositoryContract $repository) {}

    public function getServicesWithStatus($checkResults): array
    {
        $services     = $this->groupServices($checkResults);
        $latestChecks = $this->repository->getLatestByCheckNames($this->getAllCheckNames());
        $services     = $this->hydrateLatestStatus($services, $latestChecks);

        foreach ($services as &$service) {
            $service['status'] = $this->resolveServiceStatusFromChecks($service['checks']);
        }
        unset($service);

        return $services;
    }

    public function getOverallStatus($checkResults): string
    {
        if (!$checkResults) {
            return HealthCheckStatus::Unknown->value;
        }

        $hasFailed  = false;
        $hasWarning = false;

        foreach ($checkResults->storedCheckResults as $checkResult) {
            $status    = $checkResult->status;
            $checkName = $checkResult->name;

            if (in_array($checkName, config('health.degraded_only', []), true)) {
                if ($status === HealthCheckStatus::Failed->value || $status === HealthCheckStatus::Warning->value) {
                    $hasWarning = true;
                }
            } else {
                if ($status === HealthCheckStatus::Failed->value) {
                    $hasFailed = true;
                } elseif ($status === HealthCheckStatus::Warning->value) {
                    $hasWarning = true;
                }
            }
        }

        return $hasFailed
            ? HealthCheckStatus::Failed->value
            : ($hasWarning ? HealthCheckStatus::Warning->value : HealthCheckStatus::Ok->value);
    }

    public function getUptimeBarData(int $days = 90): array
    {
        $startDate    = now()->subDays($days - 1)->startOfDay();
        $endDate      = now();
        $today        = now()->format('Y-m-d');
        $rows         = $this->repository->getUptimeAggregation($this->getAllCheckNames(), $startDate);
        $transitions  = $this->repository->getStatusTransitions($this->getAllCheckNames(), $startDate, $endDate);
        $latestChecks = $this->repository->getLatestByCheckNames($this->getAllCheckNames());

        $dateRange = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $dateRange[] = now()->subDays($i)->format('Y-m-d');
        }

        $result = [];
        foreach ($this->getEnabledServiceCheckMapping() as $serviceKey => $checkNames) {
            $dayStatuses = $this->mergeServiceDayStatuses($rows, $checkNames);
            $segments    = $this->buildUptimeSegments($dayStatuses, $dateRange);

            // Today's bar uses live status so a resolved incident no longer shows as outage.
            // Past days keep the historical worst-status (red = there was an outage that day).
            $liveStatus = $this->resolveCurrentServiceStatus($latestChecks, $checkNames);
            $lastIdx    = array_key_last($segments);

            if ($liveStatus !== null && isset($segments[$lastIdx]) && $segments[$lastIdx]['date'] === $today) {
                $segments[$lastIdx]['status'] = $liveStatus;
            }

            $result[$serviceKey] = [
                'name'       => $this->getServiceLabel($serviceKey),
                'segments'   => $segments,
                'uptime_pct' => $this->calculateTimeBasedUptime($transitions, $checkNames, $endDate),
            ];
        }

        return $result;
    }

    public function getIncidentHistory(string $dateFilter, ?string $customDate): array
    {
        [$startDate, $endDate] = $this->getFullDateRange($dateFilter, $customDate);

        $allItems  = $this->repository->getHistoryRange($this->getAllCheckNames(), $startDate, $endDate);
        $incidents = [];

        foreach ($this->getEnabledServiceCheckMapping() as $serviceKey => $checkNames) {
            $serviceItems = $allItems->whereIn('check_name', $checkNames);

            if ($serviceItems->isEmpty()) {
                continue;
            }

            $rawEvents = $this->extractRawEvents($serviceItems);

            foreach ($this->mergeNoisyIncidents($rawEvents, $this->getServiceLabel($serviceKey)) as $pair) {
                $incidents[] = $pair;
            }
        }

        usort($incidents, fn($a, $b) => $b['started_at']->timestamp <=> $a['started_at']->timestamp);

        return $incidents;
    }

    public function paginateIncidents(array $incidents, int $page): LengthAwarePaginator
    {
        $perPage = (int) config('health.incidents_per_page', 7);

        $groupedByDate = collect($incidents)
            ->groupBy(fn($i) => $i['started_at']->format('Y-m-d'))
            ->map(fn($group) => $group->sortByDesc(fn($i) => $i['started_at']->timestamp)->values())
            ->sortKeysDesc();

        $total     = $groupedByDate->count();
        $lastPage  = max(1, (int) ceil($total / $perPage));
        $page      = min($page, $lastPage);
        $pageDates = $groupedByDate->keys()->slice(($page - 1) * $perPage, $perPage)->values();

        return new LengthAwarePaginator(
            items: $groupedByDate->only($pageDates->all()),
            total: $total,
            perPage: $perPage,
            currentPage: $page,
        );
    }

    public function sanitizeCustomDate(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        try {
            $parsed = Carbon::createFromFormat('Y-m-d', $value);
            if (!$parsed || $parsed->year < 2000 || $parsed->year > 2100) {
                return null;
            }
            return $parsed->format('Y-m-d');
        } catch (Exception) {
            return null;
        }
    }

    public function getAllCheckNames(): array
    {
        return array_merge(...array_values($this->getEnabledServiceCheckMapping()));
    }

    private function getEnabledServiceCheckMapping(): array
    {
        $disabledChecks = array_keys(array_filter(
            config('health.checks', []),
            fn($enabled) => !$enabled,
        ));

        if (empty($disabledChecks)) {
            return config('health.mappings', []);
        }

        $filtered = [];
        foreach (config('health.mappings', []) as $service => $checks) {
            $enabled = array_values(array_diff($checks, $disabledChecks));
            if (!empty($enabled)) {
                $filtered[$service] = $enabled;
            }
        }

        return $filtered;
    }

    private function groupServices($checkResults): array
    {
        if (!$checkResults) {
            return [];
        }

        $services = $this->initializeServices();

        foreach ($checkResults->storedCheckResults as $checkResult) {
            $serviceKey = $this->getServiceKeyForCheck($checkResult->name);

            if ($serviceKey) {
                $services[$serviceKey]['checks'][] = $this->buildCheckData($checkResult);
            }
        }

        return array_filter($services, fn($service) => !empty($service['checks']));
    }

    private function initializeServices(): array
    {
        $services = [];

        foreach (array_keys($this->getEnabledServiceCheckMapping()) as $key) {
            $services[$key] = [
                'name'        => $this->getServiceLabel($key),
                'checks'      => [],
                'hide_checks' => true,
            ];
        }

        return $services;
    }

    private function hydrateLatestStatus(array $services, Collection $latestChecks): array
    {
        foreach ($services as &$service) {
            foreach ($service['checks'] as &$check) {
                $checkName   = $check['check_name'] ?? $this->getCheckNameFromLabel($check['name'], $check['label'] ?? null);
                $latestCheck = $latestChecks->get($checkName);

                if ($latestCheck) {
                    $check['status']  = $latestCheck->status;
                    $check['summary'] = $latestCheck->short_summary ?? $check['summary'] ?? '';
                    $check['message'] = $latestCheck->notification_message ?? $check['message'] ?? '';
                }
            }
        }

        return $services;
    }

    private function buildCheckData($checkResult): array
    {
        $checkName = $checkResult->name;

        return [
            'name'       => $this->formatCheckName($checkName),
            'check_name' => $checkName,
            'label'      => $checkResult->label ?? config('health.labels', [])[$checkName] ?? $this->formatCheckName($checkName),
            'status'     => $checkResult->status,
            'summary'    => $checkResult->shortSummary ?? '',
            'message'    => $checkResult->notificationMessage ?? '',
        ];
    }

    private function getServiceKeyForCheck(string $checkName): ?string
    {
        foreach ($this->getEnabledServiceCheckMapping() as $serviceKey => $checkNames) {
            if (in_array($checkName, $checkNames, true)) {
                return $serviceKey;
            }
        }

        return null;
    }

    private function formatCheckName(string $name): string
    {
        return str($name)->replace('_', ' ')->title()->toString();
    }

    private function getServiceLabel(string $serviceKey): string
    {
        return config('health.service_names', [])[$serviceKey] ?? $serviceKey;
    }

    private function getCheckNameFromLabel(string $name, ?string $label): string
    {
        if ($label) {
            foreach (config('health.labels', []) as $checkName => $checkLabel) {
                if ($checkLabel === $label) {
                    return $checkName;
                }
            }
        }

        $formattedName = str($name)->replace(' ', '')->toString();

        foreach (config('health.labels', []) as $checkName => $checkLabel) {
            if (str($checkName)->replace('_', ' ')->title()->toString() === $name) {
                return $checkName;
            }
        }

        return $formattedName;
    }

    private function mergeServiceDayStatuses(Collection $rows, array $checkNames): array
    {
        $dayStatuses = [];

        foreach ($checkNames as $checkName) {
            foreach ($rows->get($checkName, collect()) as $row) {
                $d       = $row->day;
                $current = $dayStatuses[$d] ?? HealthCheckStatus::Ok->value;

                if ($row->worst_status === HealthCheckStatus::Failed->value || $current === HealthCheckStatus::Failed->value) {
                    $dayStatuses[$d] = HealthCheckStatus::Failed->value;
                } elseif ($row->worst_status === HealthCheckStatus::Warning->value || $current === HealthCheckStatus::Warning->value) {
                    $dayStatuses[$d] = HealthCheckStatus::Warning->value;
                } else {
                    $dayStatuses[$d] = HealthCheckStatus::Ok->value;
                }
            }
        }

        return $dayStatuses;
    }

    private function buildUptimeSegments(array $dayStatuses, array $dateRange): array
    {
        return array_map(
            fn($date) => ['date' => $date, 'status' => $dayStatuses[$date] ?? null],
            $dateRange,
        );
    }

    private function calculateTimeBasedUptime(
        Collection $transitions,
        array $checkNames,
        Carbon $windowEnd,
    ): ?float {
        $serviceTransitions = $transitions->filter(
            fn($row) => in_array($row->check_name, $checkNames, true),
        );

        if ($serviceTransitions->isEmpty()) {
            return null;
        }

        $rawEvents = $this->extractRawEvents($serviceTransitions);
        $incidents = $this->foldEventsIntoPairs($rawEvents);

        $monitoringStart = $serviceTransitions->min(fn($row) => $row->created_at->timestamp);
        $totalSeconds    = $windowEnd->timestamp - $monitoringStart;

        if ($totalSeconds <= 0) {
            return 100.0;
        }

        $downtimeSeconds = 0;
        foreach ($incidents as $incident) {
            $start            = max($incident['started_at']->timestamp, $monitoringStart);
            $end              = min(($incident['resolved_at'] ?? $windowEnd)->timestamp, $windowEnd->timestamp);
            $downtimeSeconds += max(0, $end - $start);
        }

        return round(max(0.0, min(100.0, ($totalSeconds - $downtimeSeconds) / $totalSeconds * 100)), 2);
    }

    private function extractRawEvents(Collection $serviceItems): array
    {
        $checkStatuses = [];
        $inFailure     = false;
        $rawEvents     = [];

        foreach ($serviceItems as $item) {
            $checkStatuses[$item->check_name] = $item->status === HealthCheckStatus::Skipped->value
                ? HealthCheckStatus::Ok->value
                : $item->status;

            $serviceStatus = $this->resolveServiceStatus($checkStatuses);
            $isFailing     = $serviceStatus !== HealthCheckStatus::Ok->value;

            if ($isFailing && !$inFailure) {
                $inFailure   = true;
                $rawEvents[] = ['status' => $serviceStatus, 'at' => $item->created_at];
            } elseif (!$isFailing && $inFailure) {
                $inFailure   = false;
                $rawEvents[] = ['status' => HealthCheckStatus::Resolved->value, 'at' => $item->created_at];
            }
        }

        return $rawEvents;
    }

    private function resolveServiceStatus(array $checkStatuses): string
    {
        $status = HealthCheckStatus::Ok->value;

        foreach ($checkStatuses as $s) {
            if ($s === HealthCheckStatus::Failed->value) {
                return HealthCheckStatus::Failed->value;
            }
            if ($s === HealthCheckStatus::Warning->value) {
                $status = HealthCheckStatus::Warning->value;
            }
        }

        return $status;
    }

    private function resolveServiceStatusFromChecks(array $checks): string
    {
        $degradedOnly = config('health.degraded_only', []);
        $worstStatus  = 'operational';

        foreach ($checks as $check) {
            $status = $check['status'] ?? '';

            if ($status === HealthCheckStatus::Skipped->value) {
                continue;
            }

            $isDegradedOnly = in_array($check['check_name'] ?? '', $degradedOnly, true);

            if ($status === HealthCheckStatus::Failed->value && ! $isDegradedOnly) {
                return 'outage';
            }

            if ($status === HealthCheckStatus::Failed->value || $status === HealthCheckStatus::Warning->value) {
                $worstStatus = 'degraded';
            }
        }

        return $worstStatus;
    }

    private function resolveCurrentServiceStatus(Collection $latestChecks, array $checkNames): ?string
    {
        $statuses = [];

        foreach ($checkNames as $checkName) {
            $check = $latestChecks->get($checkName);
            if ($check && $check->status !== HealthCheckStatus::Skipped->value) {
                $statuses[] = $check->status;
            }
        }

        return $statuses ? $this->resolveServiceStatus($statuses) : null;
    }

    private function mergeNoisyIncidents(array $rawEvents, string $serviceName): array
    {
        $incidents = [];

        foreach ($this->foldEventsIntoPairs($rawEvents) as $pair) {
            $lastKey = array_key_last($incidents);

            if ($lastKey !== null && $this->withinStabilityWindow($incidents[$lastKey], $pair)) {
                $incidents[$lastKey]['resolved_at'] = $pair['resolved_at'];
                continue;
            }

            $incidents[] = $pair;
        }

        return array_map(
            fn(array $pair) => ['service' => $serviceName] + $pair,
            $incidents,
        );
    }

    private function foldEventsIntoPairs(array $rawEvents): array
    {
        $pairs      = [];
        $openStart  = null;
        $openStatus = null;

        foreach ($rawEvents as $event) {
            if ($event['status'] === HealthCheckStatus::Resolved->value) {
                if ($openStart !== null) {
                    $pairs[]    = ['status' => $openStatus, 'started_at' => $openStart, 'resolved_at' => $event['at']];
                    $openStart  = null;
                    $openStatus = null;
                }
                continue;
            }

            if ($openStart === null) {
                $openStart  = $event['at'];
                $openStatus = $event['status'];
            }
        }

        if ($openStart !== null) {
            $pairs[] = ['status' => $openStatus, 'started_at' => $openStart, 'resolved_at' => null];
        }

        return $pairs;
    }

    private function withinStabilityWindow(array $previous, array $next): bool
    {
        if ($previous['resolved_at'] === null) {
            return false;
        }

        $gapMinutes = ($next['started_at']->timestamp - $previous['resolved_at']->timestamp) / 60;

        return $gapMinutes <= self::INCIDENT_STABILITY_MINUTES;
    }

    private function getFullDateRange(string $dateFilter, ?string $customDate): array
    {
        $now = Carbon::now();

        if ($customDate) {
            $endDate = Carbon::parse($customDate)->endOfDay();
            return [$endDate->copy()->startOfDay(), $endDate];
        }

        return match ($dateFilter) {
            'last_30_days' => [$now->copy()->subDays(30)->startOfDay(), $now],
            'today'        => [$now->copy()->startOfDay(), $now],
            'yesterday'    => [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay()],
            default        => [$now->copy()->subDays(7)->startOfDay(), $now],
        };
    }
}
