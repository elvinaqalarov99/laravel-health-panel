<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name') }} — Incident History</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-950 text-gray-100 min-h-screen font-sans antialiased">

<div class="max-w-3xl mx-auto px-4 py-16">

    {{-- Header --}}
    <div class="mb-10">
        <a href="{{ route('status-page.index') }}" class="text-sm text-gray-500 hover:text-gray-300 transition-colors">← Current status</a>
        <h1 class="mt-4 text-2xl font-semibold tracking-tight">Incident History</h1>
    </div>

    {{-- Uptime bars --}}
    @if (!empty($uptimeData))
        <div class="mb-10 space-y-4">
            <h2 class="text-sm font-medium text-gray-400 uppercase tracking-wider">90-Day Uptime</h2>
            @foreach ($uptimeData as $serviceKey => $data)
                <div>
                    <div class="flex justify-between items-center mb-1">
                        <span class="text-sm text-gray-300">{{ $data['label'] }}</span>
                        <span class="text-xs text-gray-500">
                            {{ $data['uptime_pct'] !== null ? number_format($data['uptime_pct'], 2).'%' : '—' }}
                        </span>
                    </div>
                    <div class="flex gap-px h-7 rounded overflow-hidden">
                        @foreach ($data['segments'] as $segment)
                            @php
                                $color = match($segment['status']) {
                                    'ok'      => 'bg-emerald-500',
                                    'warning' => 'bg-yellow-400',
                                    'failed'  => 'bg-red-500',
                                    default   => 'bg-gray-700',
                                };
                            @endphp
                            <div class="flex-1 {{ $color }}" title="{{ $segment['date'] }}"></div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Date filter --}}
    <form method="GET" class="flex flex-wrap gap-2 mb-8">
        @foreach (['today' => 'Today', 'yesterday' => 'Yesterday', 'last_7_days' => '7 days', 'last_30_days' => '30 days'] as $value => $label)
            <a href="{{ request()->fullUrlWithQuery(['date_range' => $value, 'page' => 1]) }}"
               class="px-3 py-1.5 rounded-lg text-sm transition-colors
                      {{ $dateFilter === $value ? 'bg-gray-700 text-white' : 'bg-gray-900 text-gray-400 hover:text-white border border-gray-800' }}">
                {{ $label }}
            </a>
        @endforeach
    </form>

    {{-- Incident list --}}
    @if ($hasIncidents)
        @foreach ($incidentsByDate as $date => $incidents)
            <div class="mb-8">
                <h3 class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-3">
                    {{ \Carbon\Carbon::parse($date)->format('M j, Y') }}
                </h3>
                <div class="space-y-2">
                    @foreach ($incidents as $incident)
                        @php
                            $isOngoing = $incident['resolved_at'] === null;
                            $dotColor  = $incident['status'] === 'failed' ? 'bg-red-500' : 'bg-yellow-400';
                            $badge     = $isOngoing ? 'Ongoing' : $incident['resolved_at']->format('H:i') . ' UTC';
                        @endphp
                        <div class="px-5 py-4 rounded-xl bg-gray-900 border border-gray-800">
                            <div class="flex items-start justify-between gap-4">
                                <div class="flex items-center gap-3">
                                    <span class="mt-0.5 w-2 h-2 rounded-full flex-shrink-0 {{ $dotColor }}"></span>
                                    <div>
                                        <p class="text-sm font-medium">{{ $incident['service'] }}</p>
                                        <p class="text-xs text-gray-500 mt-0.5">
                                            Started {{ $incident['started_at']->format('H:i') }} UTC
                                            @if (!$isOngoing)
                                                · Resolved {{ $badge }}
                                            @endif
                                        </p>
                                    </div>
                                </div>
                                @if ($isOngoing)
                                    <span class="flex-shrink-0 text-xs font-medium text-red-400 bg-red-950 border border-red-900 px-2.5 py-0.5 rounded-full">
                                        Ongoing
                                    </span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach

        {{-- Pagination --}}
        @if ($totalPages > 1)
            <div class="flex justify-center gap-2 mt-8">
                @if ($page > 1)
                    <a href="{{ request()->fullUrlWithQuery(['page' => $page - 1]) }}"
                       class="px-4 py-2 rounded-lg text-sm bg-gray-900 border border-gray-800 text-gray-400 hover:text-white transition-colors">
                        ← Newer
                    </a>
                @endif
                <span class="px-4 py-2 text-sm text-gray-500">{{ $page }} / {{ $totalPages }}</span>
                @if ($page < $totalPages)
                    <a href="{{ request()->fullUrlWithQuery(['page' => $page + 1]) }}"
                       class="px-4 py-2 rounded-lg text-sm bg-gray-900 border border-gray-800 text-gray-400 hover:text-white transition-colors">
                        Older →
                    </a>
                @endif
            </div>
        @endif

    @else
        <div class="text-center py-16">
            <p class="text-gray-500 text-sm">No incidents recorded for this period.</p>
        </div>
    @endif

</div>

</body>
</html>
