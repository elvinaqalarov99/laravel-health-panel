@php
    $color      = config('status-page.brand_color', '#2563eb');
    $colorHex   = ltrim($color, '#');
    $colorR     = hexdec(substr($colorHex, 0, 2));
    $colorG     = hexdec(substr($colorHex, 2, 2));
    $colorB     = hexdec(substr($colorHex, 4, 2));
    $logoUrl    = config('status-page.brand_logo_url');
    $faviconUrl = config('status-page.brand_favicon_url');
    $domain     = config('status-page.brand_domain');

    $heroStatus = match($overallStatus) {
        'warning' => ['cls' => 'warning', 'text' => 'Partial Service Disruption'],
        'failed'  => ['cls' => 'down',    'text' => 'Major Service Outage'],
        default   => ['cls' => 'ok',      'text' => 'All Systems Operational'],
    };
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Status — {{ config('app.name') }}</title>
    <meta name="description"
          content="Live system status for {{ config('app.name') }} — current uptime, incidents, and service health.">
    <meta name="robots" content="index,follow,max-snippet:-1,max-image-preview:large">
    <link rel="canonical" href="{{ url()->current() }}">

    @if($faviconUrl)
        <link rel="icon" type="image/x-icon" href="{{ $faviconUrl }}">
    @endif
    <meta name="theme-color" content="{{ $color }}">

    <meta property="og:type" content="website">
    <meta property="og:title" content="System Status — {{ config('app.name') }}">
    <meta property="og:description" content="Check the current uptime and service health for {{ config('app.name') }}.">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:site_name" content="{{ config('app.name') }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="System Status — {{ config('app.name') }}">

    <style>
        :root {
            --brand: {{ $color }};
            --brand-rgb: {{ $colorR }}, {{ $colorG }},{{ $colorB }};
        }

        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Helvetica Neue', Arial, sans-serif;
            background: #f8fafc;
            color: #0f172a;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        a { color: inherit; text-decoration: none; }

        .container { max-width: 820px; margin: 0 auto; padding: 0 24px; }

        /* ── Page Header ──────────────────────────────────── */
        .page-header {
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
            padding: 16px 0;
        }
        .page-header .container { text-align: center; }
        .page-header-logo-text {
            font-size: 18px;
            font-weight: 700;
            color: #0f172a;
            letter-spacing: -0.02em;
        }

        /* ── Status Hero ──────────────────────────────────── */
        .status-hero { padding: 32px 0; }
        .status-hero--ok      { background: var(--brand); }
        .status-hero--warning { background: #d97706; }
        .status-hero--down    { background: #dc2626; }

        .status-hero-inner {
            display: flex;
            align-items: center;
            gap: 18px;
        }

        .status-hero-icon {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.22);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            flex-shrink: 0;
        }

        .status-hero-title {
            font-size: 24px;
            font-weight: 700;
            color: #fff;
            letter-spacing: -0.02em;
            line-height: 1.2;
        }

        .status-hero-time {
            font-size: 13px;
            color: rgba(255, 255, 255, 0.78);
            margin-top: 5px;
        }

        /* ── Main ─────────────────────────────────────────── */
        .main { flex: 1; padding: 36px 0 80px; }

        /* ── Section ──────────────────────────────────────── */
        .section { margin-bottom: 36px; }

        .section-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 14px;
            gap: 12px;
        }

        .section-label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #64748b;
        }

        .history-link {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 12px;
            font-weight: 500;
            color: #64748b;
            padding: 5px 10px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            background: #fff;
            transition: background 0.15s, color 0.15s;
            white-space: nowrap;
        }
        .history-link:hover { background: #f1f5f9; color: #334155; }

        /* ── Services Card ────────────────────────────────── */
        .services-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            overflow: hidden;
        }

        /* ── Service Item ─────────────────────────────────── */
        .svc-item { border-bottom: 1px solid #f1f5f9; }
        .svc-item:last-child { border-bottom: none; }

        .svc-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 20px;
        }

        .svc-name { font-size: 15px; font-weight: 500; color: #0f172a; }

        /* ── Status badge ─────────────────────────────────── */
        .svc-status {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            font-size: 13px;
            font-weight: 500;
        }
        .svc-status--operational { color: var(--brand); }
        .svc-status--degraded    { color: #d97706; }
        .svc-status--outage      { color: #dc2626; }

        .svc-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: currentColor;
            flex-shrink: 0;
        }

        @keyframes pulse-brand {
            0%   { box-shadow: 0 0 0 0 rgba(var(--brand-rgb), 0.45); }
            70%  { box-shadow: 0 0 0 6px rgba(var(--brand-rgb), 0); }
            100% { box-shadow: 0 0 0 0 rgba(var(--brand-rgb), 0); }
        }
        .svc-dot--pulse { animation: pulse-brand 2.5s ease-in-out infinite; }

        /* ── Sub-checks ───────────────────────────────────── */
        .sub-toggle {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 8px 20px 10px;
            font-size: 12px;
            color: #94a3b8;
            cursor: pointer;
            user-select: none;
            border-top: 1px solid #f8fafc;
            transition: color 0.15s;
        }
        .sub-toggle:hover   { color: #64748b; }
        .sub-toggle--active { color: #64748b; }

        .sub-toggle-icon {
            font-size: 8px;
            display: inline-block;
            transition: transform 0.2s;
        }
        .sub-toggle-icon.open { transform: rotate(90deg); }

        .sub-body {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-out;
        }
        .sub-body.open {
            max-height: 1200px;
            transition: max-height 0.35s ease-in;
        }

        .sub-list { padding: 4px 20px 10px; border-top: 1px solid #f1f5f9; }

        .sub-check {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 9px 0;
            border-bottom: 1px solid #f8fafc;
        }
        .sub-check:last-child { border-bottom: none; }
        .sub-check-name { font-size: 13px; color: #374151; }

        .check-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 500;
            white-space: nowrap;
        }
        .check-badge-dot {
            width: 5px;
            height: 5px;
            border-radius: 50%;
            background: currentColor;
            flex-shrink: 0;
        }
        .check-badge--ok      { background: rgba(var(--brand-rgb), 0.08); color: var(--brand); }
        .check-badge--failed  { background: #fef2f2; color: #dc2626; }
        .check-badge--warning { background: #fffbeb; color: #d97706; }

        /* ── Active Incidents Banner ──────────────────────── */
        .active-banner {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            background: #fff7ed;
            border: 1px solid #fed7aa;
            border-radius: 10px;
            padding: 14px 18px;
            margin-bottom: 24px;
        }
        .active-banner--outage {
            background: #fef2f2;
            border-color: #fecaca;
        }
        .active-banner-icon {
            flex-shrink: 0;
            margin-top: 1px;
            color: #d97706;
        }
        .active-banner--outage .active-banner-icon { color: #dc2626; }
        .active-banner-body { flex: 1; min-width: 0; }
        .active-banner-title {
            font-size: 14px;
            font-weight: 600;
            color: #92400e;
            line-height: 1.3;
        }
        .active-banner--outage .active-banner-title { color: #991b1b; }
        .active-banner-services {
            font-size: 13px;
            color: #b45309;
            margin-top: 3px;
        }
        .active-banner--outage .active-banner-services { color: #b91c1c; }
        .active-banner-link {
            flex-shrink: 0;
            font-size: 12px;
            font-weight: 500;
            color: #d97706;
            padding: 4px 10px;
            border: 1px solid #fed7aa;
            border-radius: 6px;
            white-space: nowrap;
            align-self: center;
            transition: background 0.15s;
        }
        .active-banner--outage .active-banner-link {
            color: #dc2626;
            border-color: #fecaca;
        }
        .active-banner-link:hover { background: rgba(0,0,0,0.04); }

        /* ── Footer ───────────────────────────────────────── */
        .page-footer {
            background: #fff;
            border-top: 1px solid #e2e8f0;
            padding: 40px 0 48px;
            text-align: center;
        }
        .page-footer-logo { height: 22px; opacity: 0.5; display: inline-block; transition: opacity 0.2s; }
        .page-footer-logo:hover { opacity: 0.8; }
        .page-footer-name { font-size: 13px; color: #94a3b8; }
        .page-footer-domain {
            display: block;
            margin-top: 10px;
            font-size: 13px;
            color: #94a3b8;
            transition: color 0.15s;
        }
        .page-footer-domain:hover { color: #475569; }

        /* ── Responsive ───────────────────────────────────── */
        @media (max-width: 640px) {
            .container { padding: 0 16px; }
            .status-hero { padding: 24px 0; }
            .status-hero-title { font-size: 20px; }
            .status-hero-icon { width: 44px; height: 44px; }
            .status-hero-inner { gap: 14px; }
            .svc-row { padding: 14px 16px; }
            .sub-toggle { padding-left: 16px; padding-right: 16px; }
            .sub-list { padding: 4px 16px 10px; }
            .section-head { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>

{{-- Page Header --}}
<header class="page-header">
    <div class="container">
        @if($logoUrl)
            @if($domain)
                <a href="https://{{ $domain }}/">
                    <img src="{{ $logoUrl }}" alt="{{ config('app.name') }}" style="height:30px;display:inline-block;">
                </a>
            @else
                <img src="{{ $logoUrl }}" alt="{{ config('app.name') }}" style="height:30px;display:inline-block;">
            @endif
        @else
            <span class="page-header-logo-text">{{ config('app.name') }}</span>
        @endif
    </div>
</header>

{{-- Status Hero --}}
<div class="status-hero status-hero--{{ $heroStatus['cls'] }}">
    <div class="container">
        <div class="status-hero-inner">
            <div class="status-hero-icon">
                @if($heroStatus['cls'] === 'ok')
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                         stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg>
                @elseif($heroStatus['cls'] === 'warning')
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                         stroke-linecap="round" stroke-linejoin="round">
                        <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                        <line x1="12" y1="9" x2="12" y2="13"/>
                        <line x1="12" y1="17" x2="12.01" y2="17"/>
                    </svg>
                @else
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                         stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="15" y1="9" x2="9" y2="15"/>
                        <line x1="9" y1="9" x2="15" y2="15"/>
                    </svg>
                @endif
            </div>
            <div>
                <div class="status-hero-title">{{ $heroStatus['text'] }}</div>
                @if($lastChecked)
                    <div class="status-hero-time">
                        Updated
                        <span class="js-time" data-ts="{{ $lastChecked->toIso8601String() }}">{{ $lastChecked->format('Y-m-d H:i') }} UTC</span>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Main --}}
<main class="main">
    <div class="container">

        {{-- Active Incidents Banner --}}
        @php
            $outageServices   = array_filter($services, fn($s) => ($s['status'] ?? '') === 'outage');
            $degradedServices = array_filter($services, fn($s) => ($s['status'] ?? '') === 'degraded');
            $hasActiveIncident = !empty($outageServices) || !empty($degradedServices);
            $bannerSeverity    = !empty($outageServices) ? 'outage' : 'degraded';
            $affectedNames     = array_column(
                !empty($outageServices) ? $outageServices : $degradedServices,
                'name'
            );
        @endphp
        @if($hasActiveIncident)
            <div class="active-banner {{ $bannerSeverity === 'outage' ? 'active-banner--outage' : '' }}">
                <span class="active-banner-icon">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                        <line x1="12" y1="9" x2="12" y2="13"/>
                        <line x1="12" y1="17" x2="12.01" y2="17"/>
                    </svg>
                </span>
                <div class="active-banner-body">
                    <div class="active-banner-title">
                        {{ $bannerSeverity === 'outage' ? 'Active Outage' : 'Service Degradation' }}
                    </div>
                    <div class="active-banner-services">
                        Affected: {{ implode(', ', $affectedNames) }}
                    </div>
                </div>
                <a href="{{ route('status-page.history') }}" class="active-banner-link">View details</a>
            </div>
        @endif

        {{-- Current Status --}}
        <section class="section">
            <div class="section-head">
                <h2 class="section-label">Current Status</h2>
                <a href="{{ route('status-page.history') }}" class="history-link">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"/>
                        <polyline points="12 6 12 12 16 14"/>
                    </svg>
                    View history
                </a>
            </div>

            <div class="services-card">
                @foreach($services as $serviceKey => $service)
                    @php
                        $svcStatus     = $service['status'] ?? 'operational';
                        $visibleChecks = array_values(array_filter($service['checks'] ?? [], fn($c) => ($c['status'] ?? '') !== 'skipped'));
                        $hasSubChecks  = !empty($visibleChecks) && empty($service['hide_checks']);
                        $subOpen       = $hasSubChecks && $svcStatus !== 'operational';
                    @endphp
                    <div class="svc-item">
                        <div class="svc-row">
                            <span class="svc-name">{{ $service['name'] }}</span>
                            <span class="svc-status svc-status--{{ $svcStatus }}">
                                <span class="svc-dot {{ $svcStatus === 'operational' ? 'svc-dot--pulse' : '' }}"></span>
                                @if($svcStatus === 'operational')
                                    Operational
                                @elseif($svcStatus === 'degraded')
                                    Degraded
                                @else
                                    Outage
                                @endif
                            </span>
                        </div>

                        @if($hasSubChecks)
                            <div class="sub-toggle {{ $svcStatus !== 'operational' ? 'sub-toggle--active' : '' }}"
                                 onclick="toggleChecks(this)">
                                <span class="sub-toggle-icon {{ $subOpen ? 'open' : '' }}">▶</span>
                                <span>{{ count($visibleChecks) }} {{ count($visibleChecks) === 1 ? 'service' : 'services' }}</span>
                            </div>
                            <div class="sub-body {{ $subOpen ? 'open' : '' }}">
                                <div class="sub-list">
                                    @foreach($visibleChecks as $chk)
                                        @php $cs = in_array($chk['status'] ?? '', ['ok','failed','warning']) ? $chk['status'] : 'ok'; @endphp
                                        <div class="sub-check">
                                            <span class="sub-check-name">{{ $chk['label'] ?? $chk['check_name'] }}</span>
                                            <span class="check-badge check-badge--{{ $cs }}">
                                                <span class="check-badge-dot"></span>
                                                {{ $cs === 'ok' ? 'Operational' : ucfirst($cs) }}
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>

    </div>
</main>

{{-- Footer --}}
<footer class="page-footer">
    <div class="container">
        @if($logoUrl)
            @if($domain)
                <a href="https://{{ $domain }}/">
                    <img src="{{ $logoUrl }}" alt="{{ config('app.name') }}" class="page-footer-logo">
                </a>
                <a href="https://{{ $domain }}/" class="page-footer-domain">www.{{ $domain }}</a>
            @else
                <img src="{{ $logoUrl }}" alt="{{ config('app.name') }}" class="page-footer-logo">
            @endif
        @else
            <span class="page-footer-name">{{ config('app.name') }}</span>
        @endif
    </div>
</footer>

<script>
    (function () {
        'use strict';

        function updateTimestamps() {
            document.querySelectorAll('.js-time[data-ts]').forEach(function (el) {
                const ts = el.getAttribute('data-ts');
                if (!ts) return;
                try {
                    el.textContent = new Date(ts).toLocaleString('en-US', {
                        weekday: 'short', month: 'short', day: 'numeric', year: 'numeric',
                        hour: '2-digit', minute: '2-digit', hour12: false,
                        timeZone: 'UTC', timeZoneName: 'short'
                    });
                } catch (e) {}
            });
        }

        window.toggleChecks = function (el) {
            const icon = el.querySelector('.sub-toggle-icon');
            const body = el.nextElementSibling;
            if (body && body.classList.contains('sub-body')) {
                const open = body.classList.toggle('open');
                if (icon) icon.classList.toggle('open', open);
            }
        };

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', updateTimestamps);
        } else {
            updateTimestamps();
        }

        setTimeout(function () { location.reload(); }, 60000);
    })();
</script>
</body>
</html>
