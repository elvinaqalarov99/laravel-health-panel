<?php

namespace Elvinaqalarov99\StatusPage\Repositories;

use Carbon\Carbon;
use Elvinaqalarov99\StatusPage\Contracts\StatusPageRepositoryContract;
use Elvinaqalarov99\StatusPage\Enums\HealthCheckStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StatusPageRepository implements StatusPageRepositoryContract
{
    private function model(): string
    {
        return config('status-page.model');
    }

    /**
     * Fetch the single latest row per check_name using MAX(id) — two small queries
     * instead of one unbounded full-table scan.
     */
    public function getLatestByCheckNames(array $checkNames): Collection
    {
        $model = $this->model();

        $latestIds = $model::query()
            ->selectRaw('MAX(id) as id')
            ->whereIn('check_name', $checkNames)
            ->groupBy('check_name')
            ->pluck('id');

        return $model::query()
            ->whereIn('id', $latestIds)
            ->get(['id', 'check_name', 'status', 'short_summary', 'notification_message'])
            ->keyBy('check_name');
    }

    /**
     * Worst status per check per UTC day — fully aggregated in MySQL.
     */
    public function getUptimeAggregation(array $checkNames, Carbon $startDate): Collection
    {
        $failed  = HealthCheckStatus::Failed->value;
        $warning = HealthCheckStatus::Warning->value;
        $ok      = HealthCheckStatus::Ok->value;

        return $this->model()::query()
            ->selectRaw(
                "DATE(created_at) as day, check_name,
                CASE WHEN SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) > 0 THEN ?
                     WHEN SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) > 0 THEN ?
                     ELSE ? END as worst_status",
                [$failed, $failed, $warning, $warning, $ok]
            )
            ->whereIn('check_name', $checkNames)
            ->whereIn('status', [$ok, $warning, $failed])
            ->where('created_at', '>=', $startDate)
            ->groupByRaw('day, check_name')
            ->get()
            ->groupBy('check_name');
    }

    /**
     * Chronological history rows for a date window — only the columns needed for
     * incident detection, with a hard row cap to prevent OOM on wide date ranges.
     */
    public function getHistoryRange(
        array $checkNames,
        Carbon $start,
        Carbon $end,
        int $limit = 100000,
    ): Collection {
        return $this->model()::query()
            ->select(['id', 'check_name', 'status', 'created_at'])
            ->whereIn('check_name', $checkNames)
            ->whereIn('status', [
                HealthCheckStatus::Ok->value,
                HealthCheckStatus::Failed->value,
                HealthCheckStatus::Warning->value,
            ])
            ->where('created_at', '>=', $start)
            ->where('created_at', '<=', $end)
            ->orderBy('created_at', 'asc')
            ->limit($limit)
            ->get();
    }

    public function getStatusTransitions(array $checkNames, Carbon $start, Carbon $end): Collection
    {
        if (empty($checkNames)) {
            return collect();
        }

        $statuses           = [HealthCheckStatus::Ok->value, HealthCheckStatus::Warning->value, HealthCheckStatus::Failed->value];
        $namePlaceholders   = implode(',', array_fill(0, count($checkNames), '?'));
        $statusPlaceholders = implode(',', array_fill(0, count($statuses), '?'));

        $rows = DB::select("
            SELECT check_name, status, created_at
            FROM (
                SELECT
                    check_name,
                    status,
                    created_at,
                    LAG(status) OVER (PARTITION BY check_name ORDER BY created_at) AS prev_status
                FROM health_check_result_history_items
                WHERE check_name IN ({$namePlaceholders})
                  AND status     IN ({$statusPlaceholders})
                  AND created_at >= ?
                  AND created_at <= ?
            ) AS t
            WHERE prev_status IS NULL OR status != prev_status
            ORDER BY created_at ASC
        ", [...$checkNames, ...$statuses, $start->toDateTimeString(), $end->toDateTimeString()]);

        return collect($rows)->map(fn($row) => (object) [
            'check_name' => $row->check_name,
            'status'     => $row->status,
            'created_at' => Carbon::parse($row->created_at),
        ]);
    }
}
