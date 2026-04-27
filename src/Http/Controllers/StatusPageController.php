<?php

namespace Elvinaqalarov99\StatusPage\Http\Controllers;

use Elvinaqalarov99\StatusPage\Contracts\StatusPageServiceContract;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Spatie\Health\ResultStores\ResultStore;

class StatusPageController extends Controller
{
    public function __construct(private readonly StatusPageServiceContract $service) {}

    public function index(Request $request, ResultStore $resultStore): View
    {
        $checkResults = $resultStore->latestResults();

        return view('status-page::status', [
            'services'      => $this->service->getServicesWithStatus($checkResults),
            'overallStatus' => $this->service->getOverallStatus($checkResults),
            'lastChecked'   => ($checkResults?->finishedAt ?? now())->copy()->utc(),
        ]);
    }

    public function history(Request $request): View
    {
        $dateFilter = $request->input('date_range', 'today');
        $customDate = $this->service->sanitizeCustomDate($request->input('custom_date'));
        $page       = max(1, (int) $request->input('page', 1));

        $incidents = $this->service->getIncidentHistory($dateFilter, $customDate);
        $paginator = $this->service->paginateIncidents($incidents, $page);

        return view('status-page::history', [
            'incidentsByDate' => $paginator->items(),
            'hasIncidents'    => !empty($incidents),
            'dateFilter'      => $dateFilter,
            'customDate'      => $customDate,
            'page'            => $paginator->currentPage(),
            'totalPages'      => $paginator->lastPage(),
            'uptimeData'      => $this->service->getUptimeBarData(
                (int) config('status-page.history_retention_days', 90)
            ),
        ]);
    }
}
