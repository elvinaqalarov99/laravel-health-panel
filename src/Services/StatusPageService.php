<?php

namespace Elvinaqalarov99\StatusPage\Services;

use Carbon\Carbon;
use Elvinaqalarov99\StatusPage\Contracts\StatusPageRepositoryContract;
use Elvinaqalarov99\StatusPage\Contracts\StatusPageServiceContract;
use Elvinaqalarov99\StatusPage\Enums\HealthCheckStatus;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class StatusPageService implements StatusPageServiceContract
{
    public function __construct(private readonly StatusPageRepositoryContract $repository) {}

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    public function getServicesWithStatus($checkResults): array
    {
        $services     = $this->groupServices($checkResults);
        $latestChecks = $this->repository->getLatestByCheckNames($this->getAllCheckNames());

        return $this->hydrateLatestStatus($services, $latestChecks);
    }

    public function getOverallStatus($checkResults): string
    {
        if (!$checkResults) {
            return 'unknown';
        }

        $hasFailed  = false;
        $hasWarning = false;

        foreach ($checkResults->storedCheckResults as $result) {
            $serviceKey     = $this->getServiceKeyForCheck($result->name);
            $isDegradedOnly = $serviceKey && $this->isDegradedOnly($serviceKey);

            if ($isDegradedOnly) {
                if (in_array($result->status, [HealthCheckStatus::Failed->value, HealthCheckStatus::Warning->value])) {
                    $hasWarning = true;
                }
            } else {
                match ($result->status) {
                    HealthCheckStatus::Failed->value  => $hasFailed = true,
                    HealthCheckStatus::Warning->value => $hasWarning = true,
                    default => null,
                };
            }
        }

        return $hasFailed
            ? HealthCheckStatus::Failed->value
            : ($hasWarning ? HealthCheckStatus::Warning->value : HealthCheckStatus::Ok->value);
    }

    public function getUptimeBarData(int $days = 90): array
    {
        $startDate = now()->subDays($days - 1)->startOfDay();
        $rows      = $this->repository->getUptimeAggregation($this->getAllCheckNames(), $startDate);
        $dateRange = $this->buildDateRange($days);

        $result = [];
        foreach ($this->getEnabledServiceCheckMapping() as $serviceKey => $checkNames) {
            $dayStatuses = $this->mergeServiceDayStatuses($rows, $checkNames);
            [$segments, $okDays, $dataDays] = $this->buildUptimeSegments($dayStatuses, $dateRange);

            $result[$serviceKey] = [
                'label'      => $this->getServiceLabel($serviceKey),
                'segments'   => $segments,
                'uptime_pct' => $dataDays > 0 ? round($okDays / $dataDays * 100, 2) : null,
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

            foreach ($this->mergeNoisyIncidents($this->extractRawEvents($serviceItems), $this->getServiceLabel($serviceKey)) as $pair) {
                $incidents[] = $pair;
            }
        }

        usort($incidents, fn($a, $b) => $b['started_at']->timestamp <=> $a['started_at']->timestamp);

        return $incidents;
    }

    public function paginateIncidents(array $incidents, int $page): LengthAwarePaginator
    {
        $perPage = (int) config('status-page.incidents_per_page', 7);

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
        } catch (\Exception) {
            return null;
        }
    }

    public function getAllCheckNames(): array
    {
        return array_merge(...array_values($this->getEnabledServiceCheckMapping()));
    }

    // -------------------------------------------------------------------------
    // Config helpers
    // -------------------------------------------------------------------------

    private function getEnabledServiceCheckMapping(): array
    {
        return collect(config('status-page.services', []))
            ->filter(fn($s) => (bool) ($s['enabled'] ?? true))
            ->mapWithKeys(fn($s, $key) => [$key => $s['checks'] ?? []])
            ->all();
    }

    private function getServiceLabel(string $serviceKey): string
    {
        return config("status-page.services.{$serviceKey}.label", $serviceKey);
    }

    private function isDegradedOnly(string $serviceKey): bool
    {
        return (bool) config("status-page.services.{$serviceKey}.degraded_only", false);
    }

    private function getCheckLabel(string $checkName): string
    {
        return config("status-page.check_labels.{$checkName}", $this->formatCheckName($checkName));
    }

    // -------------------------------------------------------------------------
    // Service grouping
    // -------------------------------------------------------------------------

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
                'label'       => $this->getServiceLabel($key),
                'hide_checks' => (bool) config("status-page.services.{$key}.hide_checks", false),
                'checks'      => [],
            ];
        }

        return $services;
    }

    private function hydrateLatestStatus(array $services, Collection $latestChecks): array
    {
        foreach ($services as &$service) {
            foreach ($service['checks'] as &$check) {
                $latest = $latestChecks->get($check['check_name']);

                if ($latest) {
                    $check['status']  = $latest->status;
                    $check['summary'] = $latest->short_summary ?? $check['summary'] ?? '';
                    $check['message'] = $latest->notification_message ?? $check['message'] ?? '';
                }
            }
        }

        return $services;
    }

    private function buildCheckData($checkResult): array
    {
        return [
            'check_name' => $checkResult->name,
            'label'      => $checkResult->label ?? $this->getCheckLabel($checkResult->name),
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

    // -------------------------------------------------------------------------
    // Uptime bar data
    // -------------------------------------------------------------------------

    private function buildDateRange(int $days): array
    {
        $range = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $range[] = now()->subDays($i)->format('Y-m-d');
        }
        return $range;
    }

    private function mergeServiceDayStatuses(Collection $rows, array $checkNames): array
    {
        $dayStatuses = [];

        foreach ($checkNames as $checkName) {
            foreach ($rows->get($checkName, collect()) as $row) {
                $d       = $row->day;
                $current = $dayStatuses[$d] ?? HealthCheckStatus::Ok->value;

                $dayStatuses[$d] = match (true) {
                    $row->worst_status === HealthCheckStatus::Failed->value || $current === HealthCheckStatus::Failed->value => HealthCheckStatus::Failed->value,
                    $row->worst_status === HealthCheckStatus::Warning->value || $current === HealthCheckStatus::Warning->value => HealthCheckStatus::Warning->value,
                    default => HealthCheckStatus::Ok->value,
                };
            }
        }

        return $dayStatuses;
    }

    /** @return array{0: array, 1: int, 2: int} [segments, okDays, dataDays] */
    private function buildUptimeSegments(array $dayStatuses, array $dateRange): array
    {
        $segments = [];
        $okDays   = 0;
        $dataDays = 0;

        foreach ($dateRange as $date) {
            $status     = $dayStatuses[$date] ?? null;
            $segments[] = ['date' => $date, 'status' => $status];

            if ($status !== null) {
                $dataDays++;
                if ($status === HealthCheckStatus::Ok->value) {
                    $okDays++;
                }
            }
        }

        return [$segments, $okDays, $dataDays];
    }

    // -------------------------------------------------------------------------
    // Incident history
    // -------------------------------------------------------------------------

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

    private function mergeNoisyIncidents(array $rawEvents, string $serviceLabel): array
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

        return array_map(fn(array $pair) => ['service' => $serviceLabel] + $pair, $incidents);
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

        return ($next['started_at']->timestamp - $previous['resolved_at']->timestamp) / 60
            <= config('status-page.incident_stability_minutes', 15);
    }

    private function getFullDateRange(string $dateFilter, ?string $customDate): array
    {
        $now = Carbon::now();

        if ($customDate) {
            $end = Carbon::parse($customDate)->endOfDay();
            return [$end->copy()->startOfDay(), $end];
        }

        return match ($dateFilter) {
            'last_7_days'  => [$now->copy()->subDays(7)->startOfDay(), $now],
            'last_30_days' => [$now->copy()->subDays(30)->startOfDay(), $now],
            'today'        => [$now->copy()->startOfDay(), $now],
            'yesterday'    => [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay()],
            default        => [$now->copy()->subDays(7)->startOfDay(), $now],
        };
    }
}
