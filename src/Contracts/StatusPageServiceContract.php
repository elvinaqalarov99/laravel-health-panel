<?php

namespace Elvinaqalarov99\StatusPage\Contracts;

use Illuminate\Pagination\LengthAwarePaginator;

interface StatusPageServiceContract
{
    public function getServicesWithStatus($checkResults): array;

    public function getOverallStatus($checkResults): string;

    public function getUptimeBarData(int $days = 90): array;

    public function getIncidentHistory(string $dateFilter, ?string $customDate): array;

    public function paginateIncidents(array $incidents, int $page): LengthAwarePaginator;

    public function sanitizeCustomDate(?string $value): ?string;

    public function getAllCheckNames(): array;
}
