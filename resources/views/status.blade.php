<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name') }} — Status</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-950 text-gray-100 min-h-screen font-sans antialiased">

<div class="max-w-3xl mx-auto px-4 py-16">

    {{-- Header --}}
    <div class="mb-10">
        <h1 class="text-2xl font-semibold tracking-tight">{{ config('app.name') }} Status</h1>
        <p class="mt-1 text-sm text-gray-400">
            Last checked: {{ $lastChecked->format('M j, Y · H:i') }} UTC
        </p>
    </div>

    {{-- Overall status banner --}}
    @php
        $bannerMap = [
            'ok'      => ['bg' => 'bg-emerald-950 border-emerald-800', 'dot' => 'bg-emerald-400', 'text' => 'All Systems Operational'],
            'warning' => ['bg' => 'bg-yellow-950 border-yellow-800',  'dot' => 'bg-yellow-400',  'text' => 'Partial System Degradation'],
            'failed'  => ['bg' => 'bg-red-950 border-red-800',        'dot' => 'bg-red-500',      'text' => 'System Disruption Detected'],
            'unknown' => ['bg' => 'bg-gray-900 border-gray-700',      'dot' => 'bg-gray-500',     'text' => 'Status Unknown'],
        ];
        $banner = $bannerMap[$overallStatus] ?? $bannerMap['unknown'];
    @endphp
    <div class="flex items-center gap-3 px-5 py-4 rounded-xl border {{ $banner['bg'] }} mb-8">
        <span class="w-2.5 h-2.5 rounded-full {{ $banner['dot'] }}"></span>
        <span class="font-medium">{{ $banner['text'] }}</span>
    </div>

    {{-- Services --}}
    <div class="space-y-3">
        @forelse ($services as $serviceKey => $service)
            @php
                $worstStatus = collect($service['checks'])->pluck('status')
                    ->reduce(fn($carry, $s) => match(true) {
                        $carry === 'failed' || $s === 'failed' => 'failed',
                        $carry === 'warning' || $s === 'warning' => 'warning',
                        default => 'ok',
                    }, 'ok');

                $dotMap = ['ok' => 'bg-emerald-400', 'warning' => 'bg-yellow-400', 'failed' => 'bg-red-500'];
                $dot = $dotMap[$worstStatus] ?? 'bg-gray-500';
                $statusLabel = ['ok' => 'Operational', 'warning' => 'Degraded', 'failed' => 'Outage'][$worstStatus] ?? 'Unknown';
            @endphp
            <div class="flex items-center justify-between px-5 py-4 rounded-xl bg-gray-900 border border-gray-800">
                <div class="flex items-center gap-3">
                    <span class="w-2 h-2 rounded-full {{ $dot }}"></span>
                    <span class="text-sm font-medium">{{ $service['label'] }}</span>
                </div>
                <span class="text-xs text-gray-400">{{ $statusLabel }}</span>
            </div>
        @empty
            <p class="text-sm text-gray-500 text-center py-8">No services configured.</p>
        @endforelse
    </div>

    {{-- History link --}}
    <div class="mt-10 text-center">
        <a href="{{ route('status-page.history') }}"
           class="text-sm text-gray-400 hover:text-gray-200 transition-colors">
            View incident history →
        </a>
    </div>

</div>

</body>
</html>
