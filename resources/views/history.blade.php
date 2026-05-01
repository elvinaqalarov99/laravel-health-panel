@php
    use Carbon\Carbon;
    $color      = config('status-page.brand_color', '#2563eb');
    $colorHex   = ltrim($color, '#');
    $colorR     = hexdec(substr($colorHex, 0, 2));
    $colorG     = hexdec(substr($colorHex, 2, 2));
    $colorB     = hexdec(substr($colorHex, 4, 2));
    $logoUrl    = config('status-page.brand_logo_url');
    $faviconUrl = config('status-page.brand_favicon_url');
    $domain     = config('status-page.brand_domain');

    $filterLabel = match(true) {
        (bool) $customDate              => Carbon::parse($customDate)->format('M d, Y'),
        $dateFilter === 'last_7_days'   => 'Last 7 days',
        $dateFilter === 'last_30_days'  => 'Last 30 days',
        $dateFilter === 'yesterday'     => 'Yesterday',
        default                         => 'Today',
    };
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Incident History — {{ config('app.name') }}</title>
    <meta name="description"
          content="Incident history for {{ config('app.name') }} — past service disruptions and outages.">
    <meta name="robots" content="index,follow">
    <link rel="canonical" href="{{ url()->current() }}">

    @if($faviconUrl)
        <link rel="icon" type="image/x-icon" href="{{ $faviconUrl }}">
    @endif
    <meta name="theme-color" content="{{ $color }}">

    <style>
        :root {
            --brand: {{ $color }};
            --brand-rgb: {{ $colorR }}, {{ $colorG }}, {{ $colorB }};
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

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

        /* ── Page Header ─────────────────────────────────── */
        .page-header {
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
            padding: 16px 0;
        }
        .page-header-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .page-header-logo { height: 30px; display: inline-block; }
        .page-header-logo-text {
            font-size: 18px;
            font-weight: 700;
            color: #0f172a;
            letter-spacing: -0.02em;
        }
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 13px;
            color: #64748b;
            transition: color 0.15s;
        }
        .back-link:hover { color: #334155; }

        /* ── Page Title ──────────────────────────────────── */
        .page-title-bar {
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
            padding: 28px 0 24px;
        }
        .page-title { font-size: 22px; font-weight: 700; color: #0f172a; letter-spacing: -0.02em; }
        .page-subtitle { font-size: 14px; color: #64748b; margin-top: 4px; }

        /* ── Main ────────────────────────────────────────── */
        .main { flex: 1; padding: 36px 0 80px; }

        /* ── Uptime bars ─────────────────────────────────── */
        .uptime-section { margin-bottom: 32px; }

        .uptime-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            overflow: hidden;
        }

        .uptime-row {
            padding: 25px 20px 10px;
            border-bottom: 1px solid #f1f5f9;
        }
        .uptime-row:last-of-type { border-bottom: none; }

        .uptime-row-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        .uptime-row-left {
            display: flex;
            align-items: center;
            gap: 7px;
            min-width: 0;
        }

        .uptime-icon {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .uptime-icon--ok      { background: #16a34a; }
        .uptime-icon--warning { background: #d97706; }
        .uptime-icon--failed  { background: #dc2626; }
        .uptime-icon--empty   { background: #94a3b8; }
        .uptime-icon svg { display: block; }

        .uptime-name {
            font-size: 13.5px;
            font-weight: 600;
            color: #0f172a;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .uptime-info-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            border: 1.5px solid #cbd5e1;
            background: none;
            cursor: default;
            flex-shrink: 0;
            color: #94a3b8;
            font-size: 9.5px;
            font-weight: 700;
            font-style: italic;
            line-height: 1;
            position: relative;
        }
        .uptime-info-btn:hover .uptime-info-tip { opacity: 1; pointer-events: auto; }
        .uptime-info-tip {
            position: absolute;
            bottom: calc(100% + 6px);
            left: 50%;
            transform: translateX(-50%);
            background: #1e293b;
            color: #f8fafc;
            font-size: 11px;
            font-weight: 400;
            font-style: normal;
            padding: 4px 8px;
            border-radius: 5px;
            white-space: nowrap;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.15s;
            z-index: 200;
        }
        .uptime-info-tip::after {
            content: '';
            position: absolute;
            top: 100%;
            left: 50%;
            transform: translateX(-50%);
            border: 4px solid transparent;
            border-top-color: #1e293b;
        }

        .uptime-pct-label {
            font-size: 13px;
            font-weight: 500;
            color: #94a3b8;
            white-space: nowrap;
            flex-shrink: 0;
        }

        .uptime-bar-wrap {
            display: flex;
            gap: 2px;
            height: 32px;
            align-items: stretch;
        }

        .uptime-seg {
            flex: 1;
            border-radius: 3px;
            min-width: 0;
            cursor: default;
            transition: filter 0.1s;
        }
        .uptime-seg:hover { filter: brightness(0.82); }
        .uptime-seg--ok      { background: #16a34a; }
        .uptime-seg--warning { background: #d97706; }
        .uptime-seg--failed  { background: #dc2626; }
        .uptime-seg--empty   { background: #e2e8f0; }

        .uptime-axis {
            display: flex;
            justify-content: space-between;
            padding: 6px 20px 12px;
        }
        .uptime-axis span { font-size: 10.5px; color: #94a3b8; }

        /* Floating tooltip */
        #uptipTip {
            position: absolute;
            background: #1e293b;
            color: #f8fafc;
            padding: 6px 10px;
            border-radius: 6px;
            font-size: 12px;
            line-height: 1.55;
            pointer-events: none;
            z-index: 500;
            white-space: nowrap;
            box-shadow: 0 4px 12px rgba(0,0,0,.18);
            display: none;
        }

        /* ── Date Filter ─────────────────────────────────── */
        .filter-bar {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }
        .filter-label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            color: #94a3b8;
            margin-right: 4px;
        }
        .filter-btn {
            padding: 5px 12px;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
            color: #374151;
            cursor: pointer;
            transition: background 0.15s, border-color 0.15s, color 0.15s;
            white-space: nowrap;
        }
        .filter-btn:hover { background: #f1f5f9; }
        .filter-btn.active {
            background: var(--brand);
            border-color: var(--brand);
            color: #fff;
        }
        .filter-custom { position: relative; }
        .filter-custom-btn { display: inline-flex; align-items: center; gap: 5px; }
        .filter-custom-dropdown {
            position: absolute;
            top: calc(100% + 6px);
            right: 0;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            box-shadow: 0 4px 16px rgba(0,0,0,.08);
            min-width: 200px;
            z-index: 100;
            display: none;
            padding: 12px 14px;
        }
        .filter-custom-dropdown.open { display: block; }
        .filter-custom-dropdown label {
            display: block;
            font-size: 11px;
            color: #94a3b8;
            margin-bottom: 6px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .filter-custom-dropdown input {
            width: 100%;
            padding: 6px 9px;
            border: 1px solid #d1d5db;
            border-radius: 5px;
            font-size: 13px;
            color: #374151;
        }
        .filter-custom-dropdown input:focus { outline: none; border-color: var(--brand); }

        /* ── Empty state ─────────────────────────────────── */
        .incidents-empty {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 56px 24px;
            text-align: center;
            color: #64748b;
            font-size: 15px;
        }
        .incidents-empty-icon { display: block; margin: 0 auto 14px; color: #cbd5e1; }
        .incidents-empty-title { font-weight: 600; color: #0f172a; margin-bottom: 6px; }

        /* ── Incidents card ──────────────────────────────── */
        .incidents-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            overflow: hidden;
        }

        .incident-group { border-bottom: 1px solid #f1f5f9; }
        .incident-group:last-child { border-bottom: none; }
        .incident-date {
            padding: 10px 20px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            color: #64748b;
            background: #f8fafc;
            border-bottom: 1px solid #f1f5f9;
        }

        .incident-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 14px 20px;
            border-bottom: 1px solid #f8fafc;
            cursor: pointer;
            transition: background 0.12s;
            user-select: none;
        }
        .incident-item:last-child { border-bottom: none; }
        .incident-item:hover { background: #fafbfc; }

        .incident-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-top: 6px;
            flex-shrink: 0;
        }
        .incident-dot--failed  { background: #dc2626; }
        .incident-dot--warning { background: #d97706; }

        .incident-body { flex: 1; min-width: 0; }
        .incident-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }
        .incident-service { font-size: 14px; font-weight: 500; color: #0f172a; }
        .incident-meta {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
        }
        .incident-label {
            font-size: 11.5px;
            font-weight: 500;
            padding: 1px 7px;
            border-radius: 4px;
        }
        .incident-label--failed  { background: #fee2e2; color: #dc2626; }
        .incident-label--warning { background: #fef3c7; color: #d97706; }
        .incident-time { font-size: 12px; color: #94a3b8; white-space: nowrap; }

        .incident-chevron {
            flex-shrink: 0;
            color: #cbd5e1;
            margin-top: 3px;
            transition: transform 0.2s ease, color 0.15s;
        }
        .incident-item.open .incident-chevron { transform: rotate(180deg); color: #94a3b8; }

        .incident-timeline {
            overflow: hidden;
            max-height: 0;
            transition: max-height 0.25s ease, padding 0.25s ease;
        }
        .incident-item.open .incident-timeline { max-height: 200px; padding-top: 14px; }

        .tl-entry {
            display: flex;
            align-items: flex-start;
            gap: 0;
            position: relative;
        }

        .tl-track {
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 20px;
            flex-shrink: 0;
            margin-right: 10px;
        }
        .tl-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            flex-shrink: 0;
            margin-top: 5px;
        }
        .tl-dot--failed   { background: #dc2626; }
        .tl-dot--warning  { background: #d97706; }
        .tl-dot--resolved { background: #16a34a; }
        .tl-dot--ongoing  { background: #d97706; box-shadow: 0 0 0 3px rgba(217, 119, 6, 0.2); }
        .tl-line {
            width: 1px;
            flex: 1;
            min-height: 10px;
            background: #e2e8f0;
            margin: 3px 0;
        }

        .tl-content { padding-bottom: 12px; }
        .tl-label { font-size: 12px; font-weight: 600; color: #374151; }
        .tl-time  { font-size: 11.5px; color: #94a3b8; margin-top: 1px; }

        /* ── Pagination ──────────────────────────────────── */
        .pagination {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 20px;
        }
        .page-nav-btn {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 6px 14px;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
            color: #374151;
            text-decoration: none;
            transition: background 0.15s;
        }
        .page-nav-btn:hover { background: #f1f5f9; }
        .page-nav-info { font-size: 12px; color: #94a3b8; }
        .page-nav-spacer { width: 80px; }

        /* ── Footer ──────────────────────────────────────── */
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

        /* ── Responsive ──────────────────────────────────── */
        @media (max-width: 640px) {
            .container { padding: 0 16px; }
            .incident-item { padding: 12px 16px; }
            .incident-date { padding: 9px 16px; }
            .incident-header { flex-wrap: wrap; }
        }
    </style>
</head>
<body>

{{-- Page Header --}}
<header class="page-header">
    <div class="container">
        <div class="page-header-inner">
            @if($logoUrl)
                @if($domain)
                    <a href="https://{{ $domain }}/">
                        <img src="{{ $logoUrl }}" alt="{{ config('app.name') }}" class="page-header-logo">
                    </a>
                @else
                    <img src="{{ $logoUrl }}" alt="{{ config('app.name') }}" class="page-header-logo">
                @endif
            @else
                <span class="page-header-logo-text">{{ config('app.name') }}</span>
            @endif
            <a href="{{ route('status-page.index') }}" class="back-link">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="15 18 9 12 15 6"/>
                </svg>
                Current status
            </a>
        </div>
    </div>
</header>

{{-- Page Title --}}
<div class="page-title-bar">
    <div class="container">
        <div class="page-title">Incident History</div>
        <div class="page-subtitle">Past service disruptions for {{ config('app.name') }}</div>
    </div>
</div>

{{-- Main --}}
<main class="main">
    <div class="container">

        {{-- 90-day Uptime Bars --}}
        <div class="uptime-section">
            <div class="uptime-card">
                @foreach($uptimeData as $serviceKey => $data)
                    @php
                        $lastSeg    = end($data['segments']);
                        $iconStatus = $lastSeg['status'] ?? 'empty';
                        $pct        = $data['uptime_pct'];
                        $pctLabel   = $pct !== null ? $pct . '% uptime' : 'No data';
                    @endphp
                    <div class="uptime-row">
                        <div class="uptime-row-header">
                            <div class="uptime-row-left">
                                <span class="uptime-icon uptime-icon--{{ $iconStatus }}">
                                    @if($iconStatus === 'ok')
                                        <svg width="10" height="10" viewBox="0 0 10 10" fill="none">
                                            <path d="M2 5.5L4 7.5L8 3" stroke="#fff" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    @elseif($iconStatus === 'warning')
                                        <svg width="10" height="10" viewBox="0 0 10 10" fill="none">
                                            <path d="M5 3v3M5 7.5v.5" stroke="#fff" stroke-width="1.6" stroke-linecap="round"/>
                                        </svg>
                                    @elseif($iconStatus === 'failed')
                                        <svg width="10" height="10" viewBox="0 0 10 10" fill="none">
                                            <path d="M3 3l4 4M7 3l-4 4" stroke="#fff" stroke-width="1.6" stroke-linecap="round"/>
                                        </svg>
                                    @else
                                        <svg width="10" height="10" viewBox="0 0 10 10" fill="none">
                                            <circle cx="5" cy="5" r="2" fill="#fff" opacity=".5"/>
                                        </svg>
                                    @endif
                                </span>
                                <span class="uptime-name">{{ $data['name'] }}</span>
                                <span class="uptime-info-btn" tabindex="-1">
                                    i
                                    <span class="uptime-info-tip">Past 90 days</span>
                                </span>
                            </div>
                            <span class="uptime-pct-label">{{ $pctLabel }}</span>
                        </div>

                        <div class="uptime-bar-wrap">
                            @foreach($data['segments'] as $seg)
                                <span class="uptime-seg uptime-seg--{{ $seg['status'] ?? 'empty' }}"
                                      data-date="{{ $seg['date'] }}"
                                      data-status="{{ $seg['status'] ?? '' }}"></span>
                            @endforeach
                        </div>
                    </div>
                @endforeach

                <div class="uptime-axis">
                    <span>90 days ago</span>
                    <span>Today</span>
                </div>
            </div>
        </div>

        {{-- Date Filter --}}
        <div class="filter-bar" id="filterBar">
            <span class="filter-label">Period</span>

            <button class="filter-btn {{ $dateFilter === 'today' && !$customDate ? 'active' : '' }}"
                    onclick="setFilter('today')">Today</button>

            <button class="filter-btn {{ $dateFilter === 'yesterday' && !$customDate ? 'active' : '' }}"
                    onclick="setFilter('yesterday')">Yesterday</button>

            <button class="filter-btn {{ $dateFilter === 'last_7_days' && !$customDate ? 'active' : '' }}"
                    onclick="setFilter('last_7_days')">Last 7 days</button>

            <button class="filter-btn {{ $dateFilter === 'last_30_days' && !$customDate ? 'active' : '' }}"
                    onclick="setFilter('last_30_days')">Last 30 days</button>

            <div class="filter-custom" id="customContainer">
                <button class="filter-btn filter-custom-btn {{ $customDate ? 'active' : '' }}"
                        onclick="toggleCustom()">
                    {{ $customDate ? Carbon::parse($customDate)->format('M d, Y') : 'Custom date' }}
                    <svg width="9" height="9" viewBox="0 0 10 10" fill="none">
                        <path d="M2 3.5L5 6.5L8 3.5" stroke="currentColor" stroke-width="1.5"
                              stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>
                <div class="filter-custom-dropdown" id="customDropdown">
                    <label for="customDateInput">Select date</label>
                    <input type="date" id="customDateInput" value="{{ $customDate }}">
                    <button onclick="setCustomDate(document.getElementById('customDateInput').value)"
                            style="margin-top:8px;width:100%;padding:5px 10px;background:var(--brand);color:#fff;border:none;border-radius:5px;font-size:12px;font-weight:600;cursor:pointer;">
                        Apply
                    </button>
                </div>
            </div>
        </div>

        {{-- Incidents --}}
        @if(!$hasIncidents)
            <div class="incidents-empty">
                <svg class="incidents-empty-icon" width="40" height="40" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <div class="incidents-empty-title">No incidents reported</div>
                <p>All systems were operational during this period.</p>
            </div>
        @else
            <div class="incidents-card">
                @foreach($incidentsByDate as $date => $incidents)
                    <div class="incident-group">
                        <div class="incident-date">{{ Carbon::parse($date)->format('F j, Y') }}</div>

                        @foreach($incidents as $incident)
                            @php
                                $statusKey   = $incident['status'];
                                $statusLabel = $statusKey === 'failed' ? 'Outage' : 'Degraded';
                                $startedAt   = $incident['started_at'];
                                $resolvedAt  = $incident['resolved_at'] ?? null;

                                $durationLabel = null;
                                if ($resolvedAt) {
                                    $secs = max(0, $resolvedAt->timestamp - $startedAt->timestamp);
                                    $h    = (int) ($secs / 3600);
                                    $m    = (int) (($secs % 3600) / 60);
                                    $durationLabel = $h > 0 ? "{$h}h {$m}m" : ($m > 0 ? "{$m}m" : '<1m');
                                }
                            @endphp

                            <div class="incident-item" onclick="toggleIncident(this)">
                                <span class="incident-dot incident-dot--{{ $statusKey }}"></span>

                                <div class="incident-body">
                                    <div class="incident-header">
                                        <div class="incident-service">{{ $incident['service'] }}</div>
                                        <div class="incident-meta">
                                            <span class="incident-label incident-label--{{ $statusKey }}">
                                                {{ $statusLabel }}
                                            </span>
                                            @if($durationLabel)
                                                <span class="incident-time" title="Duration">{{ $durationLabel }}</span>
                                            @endif
                                            <span class="incident-time js-time-short"
                                                  data-ts="{{ $startedAt->toIso8601String() }}">
                                                {{ $startedAt->format('H:i') }} UTC
                                            </span>
                                        </div>
                                    </div>

                                    <div class="incident-timeline">

                                        <div class="tl-entry">
                                            <div class="tl-track">
                                                <span class="tl-dot tl-dot--{{ $statusKey }}"></span>
                                                @if($resolvedAt)
                                                    <div class="tl-line"></div>
                                                @endif
                                            </div>
                                            <div class="tl-content">
                                                <div class="tl-label">{{ $statusLabel }} detected</div>
                                                <div class="tl-time js-time-full"
                                                     data-ts="{{ $startedAt->toIso8601String() }}">
                                                    {{ $startedAt->format('D, M j, Y, H:i') }} UTC
                                                </div>
                                            </div>
                                        </div>

                                        @if($resolvedAt)
                                            <div class="tl-entry">
                                                <div class="tl-track">
                                                    <span class="tl-dot tl-dot--resolved"></span>
                                                </div>
                                                <div class="tl-content">
                                                    <div class="tl-label">
                                                        Resolved
                                                        @if($durationLabel)
                                                            <span style="font-weight:400;color:#94a3b8;margin-left:6px;">after {{ $durationLabel }}</span>
                                                        @endif
                                                    </div>
                                                    <div class="tl-time js-time-full"
                                                         data-ts="{{ $resolvedAt->toIso8601String() }}">
                                                        {{ $resolvedAt->format('D, M j, Y, H:i') }} UTC
                                                    </div>
                                                </div>
                                            </div>
                                        @else
                                            <div class="tl-entry">
                                                <div class="tl-track">
                                                    <span class="tl-dot tl-dot--ongoing"></span>
                                                </div>
                                                <div class="tl-content">
                                                    <div class="tl-label" style="color:#d97706;">Investigating</div>
                                                    <div class="tl-time">Incident is being monitored</div>
                                                </div>
                                            </div>
                                        @endif

                                    </div>
                                </div>

                                <svg class="incident-chevron" width="14" height="14" viewBox="0 0 24 24"
                                     fill="none" stroke="currentColor" stroke-width="2"
                                     stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="6 9 12 15 18 9"/>
                                </svg>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        @endif

        @if($totalPages > 1)
            <div class="pagination">
                @if($page < $totalPages)
                    <a href="{{ request()->fullUrlWithQuery(['page' => $page + 1]) }}" class="page-nav-btn">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="15 18 9 12 15 6"/>
                        </svg>
                        Older
                    </a>
                @else
                    <span class="page-nav-spacer"></span>
                @endif

                <span class="page-nav-info">Page {{ $page }} of {{ $totalPages }}</span>

                @if($page > 1)
                    <a href="{{ request()->fullUrlWithQuery(['page' => $page - 1]) }}" class="page-nav-btn">
                        Newer
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="9 18 15 12 9 6"/>
                        </svg>
                    </a>
                @else
                    <span class="page-nav-spacer"></span>
                @endif
            </div>
        @endif

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

{{-- Floating tooltip for uptime bar segments --}}
<div id="uptipTip"></div>

<script>
    (function () {
        'use strict';

        /* ── Uptime bar tooltip ───────────────────────────── */
        var tip = document.getElementById('uptipTip');

        var statusLabel = {
            'ok':      '<span style="color:#4ade80">&#9679;</span> Operational',
            'warning': '<span style="color:#fbbf24">&#9679;</span> Degraded',
            'failed':  '<span style="color:#f87171">&#9679;</span> Outage',
            '':        '<span style="color:#64748b">&#9679;</span> No data',
        };

        function fmtDay(dateStr) {
            try {
                return new Date(dateStr + 'T00:00:00Z').toLocaleDateString('en-US', {
                    month: 'short', day: 'numeric', year: 'numeric', timeZone: 'UTC'
                });
            } catch(e) { return dateStr; }
        }

        function moveTip(e) {
            var x = e.clientX + window.pageXOffset;
            var y = e.clientY + window.pageYOffset;
            tip.style.left = (x + 14) + 'px';
            tip.style.top  = (y - 46) + 'px';
        }

        document.querySelectorAll('.uptime-seg').forEach(function(seg) {
            seg.addEventListener('mouseenter', function(e) {
                var date   = seg.getAttribute('data-date');
                var status = seg.getAttribute('data-status');
                tip.innerHTML = '<strong>' + fmtDay(date) + '</strong><br>' + (statusLabel[status] || statusLabel['']);
                tip.style.display = 'block';
                moveTip(e);
            });
            seg.addEventListener('mousemove', moveTip);
            seg.addEventListener('mouseleave', function() { tip.style.display = 'none'; });
        });

        function fmt(ts, short) {
            try {
                var d = new Date(ts);
                if (short) {
                    return d.toLocaleString('en-US', {
                        hour: '2-digit', minute: '2-digit', hour12: false, timeZone: 'UTC'
                    }) + ' UTC';
                }
                return d.toLocaleString('en-US', {
                    weekday: 'short', month: 'short', day: 'numeric', year: 'numeric',
                    hour: '2-digit', minute: '2-digit', hour12: false,
                    timeZone: 'UTC', timeZoneName: 'short'
                });
            } catch (e) { return ''; }
        }

        function updateTimestamps() {
            document.querySelectorAll('.js-time-short[data-ts]').forEach(function (el) {
                var r = fmt(el.getAttribute('data-ts'), true);
                if (r) el.textContent = r;
            });
            document.querySelectorAll('.js-time-full[data-ts]').forEach(function (el) {
                var r = fmt(el.getAttribute('data-ts'), false);
                if (r) el.textContent = r;
            });
        }

        window.toggleIncident = function (el) { el.classList.toggle('open'); };

        window.setFilter = function (r) {
            var p = new URLSearchParams(location.search);
            p.set('date_range', r);
            p.delete('custom_date');
            p.delete('page');
            location.search = p.toString();
        };

        window.toggleCustom = function () {
            document.getElementById('customDropdown').classList.toggle('open');
        };

        window.setCustomDate = function (d) {
            if (!d) return;
            var p = new URLSearchParams(location.search);
            p.set('custom_date', d);
            p.delete('date_range');
            p.delete('page');
            location.search = p.toString();
        };

        document.addEventListener('click', function (e) {
            var c = document.getElementById('customContainer');
            var d = document.getElementById('customDropdown');
            if (c && d && !c.contains(e.target)) d.classList.remove('open');
        });

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', updateTimestamps);
        } else {
            updateTimestamps();
        }
    })();
</script>
</body>
</html>
