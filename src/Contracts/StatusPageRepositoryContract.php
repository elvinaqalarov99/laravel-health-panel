<?php

namespace Elvinaqalarov99\StatusPage\Contracts;

use Carbon\Carbon;
use Illuminate\Support\Collection;

interface StatusPageRepositoryContract
{
    public function getLatestByCheckNames(array $checkNames): Collection;

    public function getUptimeAggregation(array $checkNames, Carbon $startDate): Collection;

    public function getHistoryRange(array $checkNames, Carbon $start, Carbon $end, int $limit = 100000): Collection;
}
