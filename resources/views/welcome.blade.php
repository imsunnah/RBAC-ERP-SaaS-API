@php
    // Fetch live routing data from Laravel API
    $apiRoutes = collect(\Illuminate\Support\Facades\Route::getRoutes())->filter(function ($route) {
        // Only get routes that are part of the 'api' set (usually starts with api)
        return str_starts_with($route->uri(), 'api');
    })->map(function ($route) {
        $methods = $route->methods();
        
        // Filter out HEAD and OPTIONS for cleaner display
        $methodsDisp = array_filter($methods, fn($m) => $m !== 'HEAD' && $m !== 'OPTIONS');
        if (empty($methodsDisp)) $methodsDisp = ['ANY'];

        // Determine badge style
        $badgeClass = 'method-default';
        if(in_array('GET', $methodsDisp)) $badgeClass = 'method-get';
        elseif(in_array('POST', $methodsDisp)) $badgeClass = 'method-post';
        elseif(in_array('PUT', $methodsDisp) || in_array('PATCH', $methodsDisp)) $badgeClass = 'method-put';
        elseif(in_array('DELETE', $methodsDisp)) $badgeClass = 'method-delete';
        
        $action = $route->getActionName();
        if ($action === 'Closure') {
            $controllerName = 'Closure';
        } else {
            $controllerName = class_basename($action);
        }

        return [
            'methods_disp' => implode(' | ', $methodsDisp),
            'badge_class' => $badgeClass,
            'uri' => '/' . ltrim($route->uri(), '/'),
            'name' => $route->getName() ?? '—',
            'action' => $controllerName,
            'middlewares' => implode(', ', $route->gatherMiddleware())
        ];
    })->values();
    
    $totalApiRoutes = $apiRoutes->count();
    $modulesCount = $apiRoutes->map(function ($r) {
        $parts = explode('/', ltrim($r['uri'], '/'));
        return isset($parts[2]) ? $parts[2] : 'core';
    })->unique()->count();
    
    // Server metrics
    $memoryUsage = round(memory_get_usage(true) / 1024 / 1024, 2);
    $clientIp = request()->ip();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ATI RBAC ERP | API Control Center</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Chart.js for Live Graph -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        :root {
            /* Futuristic Cyber Slate Theme */
            --bg-deep: #080c16;
            --bg-sidebar: #05080f;
            --bg-surface: rgba(13, 20, 38, 0.45);
            --bg-surface-hover: rgba(20, 30, 56, 0.6);
            --border-color: rgba(99, 102, 241, 0.12);
            --border-hover: rgba(99, 102, 241, 0.35);
            
            --text-primary: #f8fafc;
            --text-secondary: #94a3b8;
            --text-muted: #64748b;
            
            /* Neon Accent Tones */
            --accent-indigo: #6366f1;
            --accent-cyan: #06b6d4;
            --accent-emerald: #10b981;
            --accent-rose: #f43f5e;
            --accent-amber: #f59e0b;
            --accent-violet: #8b5cf6;
            
            --accent-indigo-glow: rgba(99, 102, 241, 0.25);
            --accent-cyan-glow: rgba(6, 182, 212, 0.25);
            --accent-emerald-glow: rgba(16, 185, 129, 0.25);
            
            --font-sans: 'Outfit', 'Plus Jakarta Sans', sans-serif;
            --font-mono: 'JetBrains Mono', monospace;
            
            --shadow-glow: 0 0 20px rgba(99, 102, 241, 0.15);
            --shadow-panel: 0 8px 32px 0 rgba(0, 0, 0, 0.4);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background-color: var(--bg-deep);
            color: var(--text-primary);
            font-family: var(--font-sans);
            line-height: 1.5;
            height: 100vh;
            display: flex;
            overflow: hidden;
            background-image: 
                linear-gradient(rgba(99, 102, 241, 0.02) 1px, transparent 1px),
                linear-gradient(90deg, rgba(99, 102, 241, 0.02) 1px, transparent 1px),
                radial-gradient(circle at 10% 20%, rgba(99, 102, 241, 0.08), transparent 35%),
                radial-gradient(circle at 90% 80%, rgba(6, 182, 212, 0.08), transparent 35%);
            background-size: 25px 25px, 25px 25px, 100% 100%, 100% 100%;
        }

        /* Core Layout Wrapper */
        .dashboard-wrapper {
            display: flex;
            width: 100%;
            height: 100vh;
            overflow: hidden;
        }

        /* Glassmorphic Sidebar */
        aside.sidebar {
            width: 270px;
            background-color: var(--bg-sidebar);
            border-right: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            padding: 1.5rem;
            z-index: 10;
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 700;
            font-size: 1.25rem;
            letter-spacing: -0.03em;
            color: var(--text-primary);
            padding-bottom: 2rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            margin-bottom: 2rem;
        }

        .sidebar-brand svg {
            color: var(--accent-indigo);
            filter: drop-shadow(0 0 8px var(--accent-indigo));
        }

        .sidebar-nav {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            flex: 1;
        }

        .nav-section-title {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--text-muted);
            margin-bottom: 0.5rem;
            margin-top: 1rem;
            font-weight: 600;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            color: var(--text-secondary);
            text-decoration: none;
            border-radius: 10px;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .nav-item:hover {
            color: var(--text-primary);
            background: rgba(255, 255, 255, 0.03);
            transform: translateX(3px);
        }

        .nav-item.active {
            color: var(--text-primary);
            background: rgba(99, 102, 241, 0.12);
            border: 1px solid rgba(99, 102, 241, 0.2);
            box-shadow: inset 0 0 12px rgba(99, 102, 241, 0.05);
        }

        .nav-item svg {
            transition: transform 0.2s;
        }

        .nav-item:hover svg {
            transform: scale(1.1);
        }

        .sidebar-status-card {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            padding: 1rem;
            margin-top: 1.5rem;
        }

        .status-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.8rem;
            margin-bottom: 0.5rem;
        }

        .status-row:last-child {
            margin-bottom: 0;
        }

        .status-label {
            color: var(--text-secondary);
        }

        .status-value {
            font-family: var(--font-mono);
            font-weight: 600;
            color: var(--text-primary);
        }

        .status-value.text-success { color: var(--accent-emerald); }

        .sidebar-footer {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding-top: 1.5rem;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, var(--accent-indigo), var(--accent-violet));
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.85rem;
            color: #fff;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
        }

        .user-details {
            display: flex;
            flex-direction: column;
        }

        .user-name {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .user-role {
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        /* Main Content Canvas */
        main.main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            height: 100vh;
            overflow: hidden;
        }

        /* Top Header Bar */
        header.navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.25rem 2rem;
            background: rgba(8, 12, 22, 0.5);
            border-bottom: 1px solid var(--border-color);
            backdrop-filter: blur(12px);
            z-index: 5;
        }

        .header-left h1 {
            font-size: 1.25rem;
            font-weight: 700;
            letter-spacing: -0.02em;
            color: #fff;
        }

        .header-subtitle {
            font-size: 0.8rem;
            color: var(--text-secondary);
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .clock {
            font-family: var(--font-mono);
            color: var(--text-secondary);
            font-size: 0.9rem;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.05);
            padding: 0.35rem 0.85rem;
            border-radius: 8px;
            letter-spacing: 0.05em;
        }

        .live-pulse {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--accent-emerald);
            background: rgba(16, 185, 129, 0.08);
            padding: 0.35rem 0.85rem;
            border-radius: 8px;
            border: 1px solid rgba(16, 185, 129, 0.2);
            box-shadow: 0 0 10px rgba(16, 185, 129, 0.05);
        }

        .pulse-dot {
            width: 8px;
            height: 8px;
            background-color: var(--accent-emerald);
            border-radius: 50%;
            box-shadow: 0 0 10px var(--accent-emerald);
            animation: pulse-animation 2s infinite;
        }

        @keyframes pulse-animation {
            0% { transform: scale(0.95); opacity: 0.8; }
            50% { transform: scale(1.2); opacity: 1; box-shadow: 0 0 14px var(--accent-emerald); }
            100% { transform: scale(0.95); opacity: 0.8; }
        }

        /* Container & Grid Setup */
        .container {
            flex: 1;
            overflow-y: auto;
            padding: 2rem;
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }

        .container::-webkit-scrollbar {
            width: 6px;
        }
        .container::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 4px;
        }
        .container::-webkit-scrollbar-track {
            background: transparent;
        }

        /* KPI Dashboard Widgets */
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.5rem;
        }

        @media (max-width: 1024px) {
            .metrics-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 640px) {
            .metrics-grid { grid-template-columns: 1fr; }
        }

        .metric-card {
            background: var(--bg-surface);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 1.5rem;
            backdrop-filter: blur(10px);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .metric-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--accent-indigo);
            opacity: 0;
            transition: opacity 0.3s;
        }

        .metric-card:hover::before {
            opacity: 1;
        }

        .metric-card:hover {
            transform: translateY(-4px);
            border-color: var(--border-hover);
            box-shadow: var(--shadow-panel), var(--shadow-glow);
        }

        .metric-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.75rem;
        }

        .metric-title {
            color: var(--text-secondary);
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .metric-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.05);
            color: var(--text-secondary);
        }

        .metric-card:hover .metric-icon {
            color: var(--text-primary);
            background: rgba(99, 102, 241, 0.1);
            border-color: rgba(99, 102, 241, 0.2);
        }

        .metric-value {
            font-size: 2.25rem;
            font-weight: 700;
            font-family: var(--font-mono);
            color: #fff;
            line-height: 1.1;
        }

        .metric-sub {
            margin-top: 0.75rem;
            font-size: 0.75rem;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 0.35rem;
        }

        .metric-sub svg {
            color: var(--accent-emerald);
        }

        /* Specific color rules for glowing metrics */
        .metric-routes::before { background: var(--accent-indigo); }
        .metric-routes:hover { box-shadow: var(--shadow-panel), 0 0 20px rgba(99, 102, 241, 0.12); }
        .metric-modules::before { background: var(--accent-violet); }
        .metric-modules:hover { box-shadow: var(--shadow-panel), 0 0 20px rgba(139, 92, 246, 0.12); }
        .metric-mem::before { background: var(--accent-cyan); }
        .metric-mem:hover { box-shadow: var(--shadow-panel), 0 0 20px rgba(6, 182, 212, 0.12); }
        .metric-traffic::before { background: var(--accent-emerald); }
        .metric-traffic:hover { box-shadow: var(--shadow-panel), 0 0 20px rgba(16, 185, 129, 0.12); }

        /* Row Layout: Telemetry Grid */
        .telemetry-grid {
            display: grid;
            grid-template-columns: 2.2fr 1.3fr;
            gap: 1.5rem;
            min-height: 380px;
        }

        @media (max-width: 1024px) {
            .telemetry-grid { grid-template-columns: 1fr; }
        }

        /* Glassmorphic Panel Design */
        .panel {
            background: var(--bg-surface);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            backdrop-filter: blur(12px);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            box-shadow: var(--shadow-panel);
            transition: border-color 0.3s;
        }

        .panel:hover {
            border-color: rgba(99, 102, 241, 0.2);
        }

        .panel-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .panel-title {
            font-size: 1rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            color: #fff;
        }

        .panel-title svg {
            color: var(--accent-indigo);
        }

        /* Custom Panel Toolbar */
        .panel-toolbar {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .chart-wrapper {
            flex: 1;
            padding: 1.5rem;
            position: relative;
            min-height: 280px;
        }

        /* Live Activity stream */
        .feed-container {
            flex: 1;
            overflow-y: auto;
            position: relative;
        }

        .feed-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .feed-item {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.03);
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            transition: background 0.2s, transform 0.2s;
        }

        .feed-item:hover {
            background: rgba(255, 255, 255, 0.02);
            transform: translateX(4px);
        }

        .feed-item-left {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .feed-time {
            font-family: var(--font-mono);
            font-size: 0.7rem;
            color: var(--text-muted);
        }

        .feed-route {
            font-family: var(--font-mono);
            font-size: 0.8rem;
            font-weight: 500;
            color: #fff;
        }

        .feed-item-right {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-family: var(--font-mono);
            font-size: 0.8rem;
        }

        .feed-status {
            font-weight: 700;
            padding: 0.15rem 0.45rem;
            border-radius: 4px;
            font-size: 0.75rem;
        }

        .feed-status.status-2xx { color: var(--accent-emerald); background: rgba(16, 185, 129, 0.1); }
        .feed-status.status-4xx { color: var(--accent-amber); background: rgba(245, 158, 11, 0.1); }
        .feed-status.status-5xx { color: var(--accent-rose); background: rgba(239, 68, 68, 0.1); }

        .feed-latency {
            color: var(--text-secondary);
        }

        /* Split-Pane: Directory Table (Left) + Interactive Tester (Right) */
        .split-grid {
            display: grid;
            grid-template-columns: 2.2fr 1.3fr;
            gap: 1.5rem;
            min-height: 480px;
        }

        @media (max-width: 1200px) {
            .split-grid { grid-template-columns: 1fr; }
        }

        /* Search Controls */
        .directory-controls {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            background: rgba(255, 255, 255, 0.01);
            display: flex;
            gap: 1rem;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
        }

        .search-input-wrapper {
            position: relative;
            flex: 1;
            min-width: 250px;
        }

        .search-input-wrapper svg {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            pointer-events: none;
        }

        .search-input {
            width: 100%;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 0.6rem 1rem 0.6rem 2.5rem;
            color: #fff;
            font-family: var(--font-sans);
            font-size: 0.85rem;
            outline: none;
            transition: all 0.2s;
        }

        .search-input:focus {
            background: rgba(255, 255, 255, 0.05);
            border-color: var(--accent-indigo);
            box-shadow: 0 0 12px rgba(99, 102, 241, 0.15);
        }

        .method-filters {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .filter-btn {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-secondary);
            padding: 0.4rem 0.75rem;
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            font-family: var(--font-sans);
            transition: all 0.2s;
        }

        .filter-btn:hover {
            color: #fff;
            border-color: rgba(255, 255, 255, 0.2);
            background: rgba(255, 255, 255, 0.04);
        }

        .filter-btn.active {
            color: #fff;
            background: var(--accent-indigo);
            border-color: var(--accent-indigo);
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.25);
        }

        /* Database Endpoint Directory Grid Table */
        .table-container {
            flex: 1;
            overflow-x: auto;
        }

        .table-container::-webkit-scrollbar { width: 6px; height: 6px; }
        .table-container::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.1); border-radius: 4px; }

        table.directory-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 0.85rem;
        }

        table.directory-table th {
            position: sticky;
            top: 0;
            background: #0d1222;
            padding: 1rem 1.5rem;
            color: var(--text-secondary);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            font-size: 0.7rem;
            border-bottom: 1px solid var(--border-color);
            z-index: 2;
        }

        table.directory-table td {
            padding: 0.9rem 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.03);
            white-space: nowrap;
        }

        table.directory-table tbody tr {
            cursor: pointer;
            transition: background 0.15s;
        }

        table.directory-table tbody tr:hover td {
            background: rgba(255, 255, 255, 0.02);
        }

        table.directory-table tbody tr.selected-row td {
            background: rgba(99, 102, 241, 0.07);
            border-bottom-color: rgba(99, 102, 241, 0.2);
        }

        .route-uri-cell {
            font-family: var(--font-mono);
            font-weight: 500;
            color: #fff;
        }

        .route-name-cell {
            color: var(--text-secondary);
        }

        .route-controller-cell {
            color: var(--text-muted);
            font-size: 0.8rem;
        }

        .route-middleware-cell {
            color: var(--text-muted);
            font-size: 0.75rem;
            max-width: 180px;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* HTTP Badges */
        .http-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.2rem 0.55rem;
            border-radius: 6px;
            font-family: var(--font-mono);
            font-size: 0.65rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .http-badge.method-get { color: var(--accent-emerald); background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.2); }
        .http-badge.method-post { color: var(--accent-indigo); background: rgba(99, 102, 241, 0.08); border: 1px solid rgba(99, 102, 241, 0.2); }
        .http-badge.method-put { color: var(--accent-amber); background: rgba(245, 158, 11, 0.08); border: 1px solid rgba(245, 158, 11, 0.2); }
        .http-badge.method-delete { color: var(--accent-rose); background: rgba(239, 68, 68, 0.08); border: 1px solid rgba(239, 68, 68, 0.2); }
        .http-badge.method-default { color: var(--accent-violet); background: rgba(139, 92, 246, 0.08); border: 1px solid rgba(139, 92, 246, 0.2); }

        /* API Request Tester Side Panel */
        .tester-empty-state {
            padding: 3rem 2rem;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 1rem;
            color: var(--text-secondary);
            flex: 1;
        }

        .tester-empty-state svg {
            color: var(--text-muted);
            animation: bounce 2s infinite;
        }

        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-6px); }
        }

        .tester-content {
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
            flex: 1;
        }

        .tester-endpoint-bar {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid var(--border-color);
            padding: 0.75rem 1rem;
            border-radius: 10px;
        }

        .tester-uri-display {
            font-family: var(--font-mono);
            font-size: 0.85rem;
            font-weight: 500;
            color: #fff;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .tester-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .tester-group label {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-secondary);
        }

        .tester-code-block {
            background: rgba(0, 0, 0, 0.25);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            padding: 0.75rem 1rem;
            font-family: var(--font-mono);
            font-size: 0.75rem;
            color: var(--text-secondary);
            overflow-x: auto;
        }

        .tester-textarea {
            width: 100%;
            background: rgba(0, 0, 0, 0.25);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 0.75rem 1rem;
            font-family: var(--font-mono);
            font-size: 0.75rem;
            color: #fff;
            outline: none;
            resize: vertical;
        }

        .tester-textarea:focus {
            border-color: rgba(99, 102, 241, 0.4);
        }

        .btn {
            background: var(--accent-indigo);
            border: none;
            border-radius: 8px;
            color: #fff;
            padding: 0.65rem 1.25rem;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            font-family: var(--font-sans);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: all 0.2s;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.2);
        }

        .btn:hover {
            opacity: 0.95;
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(99, 102, 241, 0.3);
        }

        .btn:active {
            transform: translateY(0);
        }

        .btn-loading-dots {
            display: inline-flex;
            gap: 2px;
        }

        .btn-loading-dots span {
            width: 4px;
            height: 4px;
            background: #fff;
            border-radius: 50%;
            animation: dot-pulse 1.4s infinite ease-in-out both;
        }

        .btn-loading-dots span:nth-child(1) { animation-delay: -0.32s; }
        .btn-loading-dots span:nth-child(2) { animation-delay: -0.16s; }

        @keyframes dot-pulse {
            0%, 80%, 100% { transform: scale(0); }
            40% { transform: scale(1.0); }
        }

        .response-container {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            border-top: 1px dashed var(--border-color);
            padding-top: 1.25rem;
            animation: fadeIn 0.3s ease;
        }

        .response-metadata {
            display: flex;
            justify-content: space-between;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .response-status-badge {
            color: var(--accent-emerald);
            font-weight: 600;
        }

        .response-status-badge.status-err {
            color: var(--accent-rose);
        }

        .response-body-block {
            background: #04060b;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 0.75rem 1rem;
            font-family: var(--font-mono);
            font-size: 0.75rem;
            color: #10b981; /* Emerald output code color */
            max-height: 250px;
            overflow-y: auto;
            white-space: pre-wrap;
            word-break: break-all;
        }

        /* Glassmorphic Trace Inspector Modal */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(4, 6, 12, 0.75);
            backdrop-filter: blur(8px);
            z-index: 100;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s ease;
        }

        .modal-overlay.show-modal {
            opacity: 1;
            pointer-events: auto;
        }

        .modal-content {
            background: #0d1326;
            border: 1px solid var(--border-hover);
            border-radius: 20px;
            width: 100%;
            max-width: 650px;
            max-height: 80vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            box-shadow: 0 20px 50px rgba(0,0,0,0.6), var(--shadow-glow);
            transform: scale(0.95);
            transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .modal-overlay.show-modal .modal-content {
            transform: scale(1);
        }

        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 1.1rem;
            font-weight: 700;
            color: #fff;
        }

        .modal-close-btn {
            background: none;
            border: none;
            color: var(--text-secondary);
            font-size: 1.5rem;
            cursor: pointer;
            transition: color 0.2s;
        }

        .modal-close-btn:hover {
            color: #fff;
        }

        .modal-body {
            padding: 1.5rem;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .modal-body::-webkit-scrollbar { width: 6px; }
        .modal-body::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.08); border-radius: 4px; }

        .modal-stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
        }

        @media (max-width: 480px) {
            .modal-stats-grid { grid-template-columns: repeat(2, 1fr); }
        }

        .modal-stat-card {
            background: rgba(255,255,255,0.02);
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: 10px;
            padding: 0.75rem;
            text-align: center;
        }

        .modal-stat-label {
            font-size: 0.65rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.25rem;
            display: block;
        }

        .modal-stat-value {
            font-family: var(--font-mono);
            font-size: 0.9rem;
            font-weight: 700;
            color: #fff;
        }

        .modal-section {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .modal-section h4 {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-secondary);
            font-weight: 600;
        }

        .modal-section pre {
            background: #05080f;
            border: 1px solid rgba(255,255,255,0.03);
            border-radius: 8px;
            padding: 0.75rem 1rem;
            font-family: var(--font-mono);
            font-size: 0.75rem;
            color: var(--text-secondary);
            overflow-x: auto;
            max-height: 150px;
        }

        .modal-section code {
            display: block;
        }

        /* CSS Helpers */
        .hidden { display: none !important; }
        .text-success { color: var(--accent-emerald) !important; }
        .text-accent { color: var(--accent-indigo) !important; }
        .text-warning { color: var(--accent-amber) !important; }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(4px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>

    <div class="dashboard-wrapper">
        <!-- Collapsible Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-brand">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg>
                <span>ATI Gateway Console</span>
            </div>
            
            <nav class="sidebar-nav">
                <div class="nav-section-title">Systems Console</div>
                <a href="#" class="nav-item active">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                    Gateway Telemetry
                </a>
                <a href="/docs/api" target="_blank" class="nav-item">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                    API Documentation
                </a>
                <a href="#" class="nav-item">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.1a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                    Gateway Settings
                </a>

                <div class="nav-section-title">Telemetry Status</div>
                <div class="sidebar-status-card">
                    <div class="status-row">
                        <span class="status-label">Uptime</span>
                        <span class="status-value">99.98%</span>
                    </div>
                    <div class="status-row">
                        <span class="status-label">Database</span>
                        <span class="status-value text-success">CONNECTED</span>
                    </div>
                    <div class="status-row">
                        <span class="status-label">Redis Cache</span>
                        <span class="status-value text-success">ACTIVE</span>
                    </div>
                    <div class="status-row">
                        <span class="status-label">Client IP</span>
                        <span class="status-value">{{ $clientIp }}</span>
                    </div>
                </div>
            </nav>

            <div class="sidebar-footer">
                <div class="user-avatar">API</div>
                <div class="user-details">
                    <div class="user-name">Developer Portal</div>
                    <div class="user-role">Gateway Admin</div>
                </div>
            </div>
        </aside>

        <!-- Main Window Container -->
        <main class="main-content">
            <!-- Header -->
            <header class="navbar">
                <div class="header-left">
                    <h1>API Gateway Control Center</h1>
                    <p class="header-subtitle">Real-time routing directory & server telemetry console</p>
                </div>
                <div class="header-right">
                    <div id="liveClock" class="clock">00:00:00</div>
                    <div class="live-pulse">
                        <div class="pulse-dot"></div>
                        <span>Gateway Active</span>
                    </div>
                </div>
            </header>

            <!-- Dashboard Scrolling Container -->
            <div class="container">
                <!-- Metrics -->
                <div class="metrics-grid">
                    <div class="metric-card metric-routes">
                        <div class="metric-header">
                            <span class="metric-title">Registered API Routes</span>
                            <div class="metric-icon">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>
                            </div>
                        </div>
                        <div class="metric-value">{{ $totalApiRoutes }}</div>
                        <div class="metric-sub">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12l5 5L20 7"></path></svg>
                            Parsed from routes directory
                        </div>
                    </div>
                    
                    <div class="metric-card metric-modules">
                        <div class="metric-header">
                            <span class="metric-title">Active ERP Modules</span>
                            <div class="metric-icon">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                            </div>
                        </div>
                        <div class="metric-value">{{ $modulesCount }}</div>
                        <div class="metric-sub">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12l5 5L20 7"></path></svg>
                            Base module controllers active
                        </div>
                    </div>

                    <div class="metric-card metric-mem">
                        <div class="metric-header">
                            <span class="metric-title">System Memory Usage</span>
                            <div class="metric-icon">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path></svg>
                            </div>
                        </div>
                        <div class="metric-value">{{ $memoryUsage }}<span style="font-size: 1.1rem; color: var(--text-muted); font-weight: 500; margin-left: 2px;">MB</span></div>
                        <div class="metric-sub">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                            Live PHP memory allocation
                        </div>
                    </div>

                    <div class="metric-card metric-traffic">
                        <div class="metric-header">
                            <span class="metric-title">RPS Traffic Rate</span>
                            <div class="metric-icon">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg>
                            </div>
                        </div>
                        <div class="metric-value" id="rpsMetric">0.0</div>
                        <div class="metric-sub">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline></svg>
                            Live simulated hits/sec
                        </div>
                    </div>
                </div>

                <!-- Live Telemetry (Charts & Logs Feed) -->
                <div class="telemetry-grid">
                    <!-- Twin-Dataset Live Chart -->
                    <div class="panel">
                        <div class="panel-header">
                            <div class="panel-title">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 20V10M12 20V4M6 20v-6"></path></svg>
                                Telemetry Output Stream (Hits/Sec)
                            </div>
                        </div>
                        <div class="chart-wrapper">
                            <canvas id="liveTelemetryChart"></canvas>
                        </div>
                    </div>

                    <!-- Live Request Feed -->
                    <div class="panel">
                        <div class="panel-header">
                            <div class="panel-title">
                                <div class="pulse-dot"></div>
                                Live Trace Logs Feed
                            </div>
                        </div>
                        <div class="feed-container">
                            <ul class="feed-list" id="requestFeed" data-routes="{{ json_encode($apiRoutes->pluck('uri')) }}">
                                <!-- JavaScript inserts feed logs here -->
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- API Directory & Testing Split Panel -->
                <div class="split-grid">
                    <!-- API Directory -->
                    <div class="panel">
                        <div class="panel-header" style="border-bottom: none;">
                            <div class="panel-title">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>
                                Configured Endpoints Directory
                            </div>
                        </div>
                        
                        <!-- Filter Controls -->
                        <div class="directory-controls">
                            <div class="search-input-wrapper">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                                <input type="text" id="routeSearch" class="search-input" placeholder="Search URI, action or name...">
                            </div>
                            
                            <div class="method-filters">
                                <button class="filter-btn active" data-method="ALL">ALL</button>
                                <button class="filter-btn" data-method="GET">GET</button>
                                <button class="filter-btn" data-method="POST">POST</button>
                                <button class="filter-btn" data-method="PUT">PUT</button>
                                <button class="filter-btn" data-method="DELETE">DELETE</button>
                            </div>
                        </div>

                        <!-- Directory Table -->
                        <div class="table-container">
                            <table class="directory-table" id="directoryTable">
                                <thead>
                                    <tr>
                                        <th style="width: 100px;">Method</th>
                                        <th>Route URI</th>
                                        <th>Controller Action</th>
                                        <th>Middleware</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($apiRoutes as $route)
                                    <tr data-method="{{ $route['methods_disp'] }}" data-uri="{{ $route['uri'] }}" data-action="{{ $route['action'] }}" data-name="{{ $route['name'] }}" data-middleware="{{ $route['middlewares'] }}">
                                        <td>
                                            <span class="http-badge {{ $route['badge_class'] }}">
                                                {{ $route['methods_disp'] }}
                                            </span>
                                        </td>
                                        <td class="route-uri-cell">{{ $route['uri'] }}</td>
                                        <td class="route-controller-cell">{{ $route['action'] }}</td>
                                        <td class="route-middleware-cell" title="{{ $route['middlewares'] }}">{{ $route['middlewares'] }}</td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="4" style="text-align: center; color: var(--text-muted); padding: 3rem;">
                                            No active API routes configured.
                                        </td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- API Tester Panel -->
                    <div class="panel" id="apiTesterPanel">
                        <div class="panel-header">
                            <div class="panel-title">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="4 17 10 11 4 5"></polyline><line x1="12" y1="19" x2="20" y2="19"></line></svg>
                                API request tester console
                            </div>
                        </div>
                        
                        <!-- Empty State -->
                        <div class="tester-empty-state" id="testerEmptyState">
                            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><polygon points="5 3 19 12 5 21 5 3"></polygon></svg>
                            <p style="font-weight: 500;">No API Route Selected</p>
                            <p style="font-size: 0.8rem; color: var(--text-muted); max-width: 230px; margin: 0 auto;">Select any route from the endpoints directory to configure and run a simulated request.</p>
                        </div>

                        <!-- Tester Active View -->
                        <div class="tester-content hidden" id="testerContent">
                            <div class="tester-endpoint-bar">
                                <span class="http-badge" id="testerMethodBadge">GET</span>
                                <span class="tester-uri-display" id="testerUriText">/api/v1/meals</span>
                            </div>

                            <div class="tester-group">
                                <label>Mock Headers</label>
                                <div class="tester-code-block">
                                    Authorization: Bearer ati_token_99d8e7b...<br>
                                    Accept: application/json
                                </div>
                            </div>

                            <div class="tester-group">
                                <label>JSON Query Parameters / Payload</label>
                                <textarea class="tester-textarea" id="testerPayloadText" rows="4">{}</textarea>
                            </div>

                            <button class="btn" id="runTestBtn">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="5 3 19 12 5 21 5 3"></polygon></svg>
                                <span>Execute Endpoint Request</span>
                            </button>

                            <!-- Testing response results -->
                            <div class="response-container hidden" id="testerResponseBlock">
                                <div class="response-metadata">
                                    <span class="status-label">HTTP STATUS</span>
                                    <span class="response-status-badge" id="testerStatusText">200 OK</span>
                                </div>
                                <div class="response-metadata">
                                    <span class="status-label">LATENCY</span>
                                    <span class="feed-latency" id="testerLatencyText">15ms</span>
                                </div>
                                <div class="response-body-block" id="testerResponseBody"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Trace Details Modal Overlay -->
    <div class="modal-overlay" id="traceModal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">
                    <span class="http-badge" id="modalMethodBadge">GET</span>
                    <span id="modalUriText">/api/v1/attendance</span>
                </div>
                <button class="modal-close-btn" id="modalCloseBtn">&times;</button>
            </div>
            
            <div class="modal-body">
                <div class="modal-stats-grid">
                    <div class="modal-stat-card">
                        <span class="modal-stat-label">HTTP Status</span>
                        <span class="modal-stat-value text-success" id="modalStatusVal">200 OK</span>
                    </div>
                    <div class="modal-stat-card">
                        <span class="modal-stat-label">Response Time</span>
                        <span class="modal-stat-value text-accent" id="modalDurationVal">24ms</span>
                    </div>
                    <div class="modal-stat-card">
                        <span class="modal-stat-label">Memory Alloc</span>
                        <span class="modal-stat-value" id="modalMemoryVal">2.1 MB</span>
                    </div>
                    <div class="modal-stat-card">
                        <span class="modal-stat-label">Queries Run</span>
                        <span class="modal-stat-value text-warning" id="modalQueriesVal">3 Queries</span>
                    </div>
                </div>

                <div class="modal-section">
                    <h4>Request Origin Headers</h4>
                    <pre><code id="modalHeadersVal"></code></pre>
                </div>

                <div class="modal-section">
                    <h4>Authenticated User Details</h4>
                    <pre><code id="modalUserVal" style="color: var(--accent-emerald);"></code></pre>
                </div>

                <div class="modal-section">
                    <h4>Caller Geo-Location Data</h4>
                    <pre><code id="modalLocationVal" style="color: var(--accent-indigo);"></code></pre>
                </div>

                <div class="modal-section">
                    <h4>Database ORM SQL Trace</h4>
                    <pre><code id="modalSqlVal" class="text-warning"></code></pre>
                </div>

                <div class="modal-section">
                    <h4>Response JSON Payload</h4>
                    <pre><code id="modalPayloadVal" class="text-success"></code></pre>
                </div>
            </div>
        </div>
    </div>

    <script>
        // --- 1. Live Uptime Clock ---
        function updateClock() {
            const now = new Date();
            document.getElementById('liveClock').textContent = now.toLocaleTimeString('en-US', { hour12: false });
        }
        setInterval(updateClock, 1000);
        updateClock();

        // --- 2. Chart.js Twin-Dataset Area Graph ---
        const ctx = document.getElementById('liveTelemetryChart').getContext('2d');
        
        let successGrad = ctx.createLinearGradient(0, 0, 0, 260);
        successGrad.addColorStop(0, 'rgba(6, 182, 212, 0.3)');
        successGrad.addColorStop(1, 'rgba(6, 182, 212, 0.0)');

        let errGrad = ctx.createLinearGradient(0, 0, 0, 260);
        errGrad.addColorStop(0, 'rgba(244, 63, 94, 0.25)');
        errGrad.addColorStop(1, 'rgba(244, 63, 94, 0.0)');

        const maxPoints = 25;
        const initialSuccess = Array(maxPoints).fill(0).map(() => Math.floor(Math.random() * 40) + 15);
        const initialError = Array(maxPoints).fill(0).map(() => Math.random() > 0.9 ? 1 : 0);
        const labels = Array(maxPoints).fill('');

        const telemetryChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Success Hits',
                        data: initialSuccess,
                        borderColor: '#06b6d4',
                        backgroundColor: successGrad,
                        borderWidth: 2,
                        pointRadius: 0,
                        pointHoverRadius: 4,
                        fill: true,
                        tension: 0.4
                    },
                    {
                        label: 'Error Logs',
                        data: initialError,
                        borderColor: '#f43f5e',
                        backgroundColor: errGrad,
                        borderWidth: 1.5,
                        pointRadius: 0,
                        pointHoverRadius: 4,
                        fill: true,
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: { enabled: true, intersect: false, mode: 'index' }
                },
                scales: {
                    x: { display: false },
                    y: {
                        grid: { color: 'rgba(99, 102, 241, 0.05)', drawBorder: false },
                        ticks: { color: '#64748b', font: { family: "'JetBrains Mono', monospace", size: 9 } },
                        min: 0,
                        max: 100
                    }
                }
            }
        });

        // --- 3. Directory Searching and Route Filtering ---
        const searchInput = document.getElementById('routeSearch');
        const filterBtns = document.querySelectorAll('.filter-btn');
        const tableRows = document.querySelectorAll('#directoryTable tbody tr');

        let activeMethodFilter = 'ALL';
        let searchQuery = '';

        function filterRoutes() {
            tableRows.forEach(row => {
                const methods = row.getAttribute('data-method') || '';
                const uri = row.getAttribute('data-uri') || '';
                const action = row.getAttribute('data-action') || '';
                const name = row.getAttribute('data-name') || '';

                const matchesSearch = 
                    uri.toLowerCase().includes(searchQuery) || 
                    action.toLowerCase().includes(searchQuery) ||
                    name.toLowerCase().includes(searchQuery);

                const matchesMethod = 
                    activeMethodFilter === 'ALL' || 
                    methods.toUpperCase().includes(activeMethodFilter);

                if (matchesSearch && matchesMethod) {
                    row.classList.remove('hidden');
                } else {
                    row.classList.add('hidden');
                }
            });
        }

        searchInput.addEventListener('input', (e) => {
            searchQuery = e.target.value.toLowerCase().trim();
            filterRoutes();
        });

        filterBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                filterBtns.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                activeMethodFilter = btn.getAttribute('data-method') || 'ALL';
                filterRoutes();
            });
        });

        // --- 4. Interactive API Request Tester Console ---
        const testerEmpty = document.getElementById('testerEmptyState');
        const testerContent = document.getElementById('testerContent');
        const testerMethodBadge = document.getElementById('testerMethodBadge');
        const testerUriText = document.getElementById('testerUriText');
        const testerPayloadText = document.getElementById('testerPayloadText');
        const runTestBtn = document.getElementById('runTestBtn');
        const testerResponseBlock = document.getElementById('testerResponseBlock');
        const testerStatusText = document.getElementById('testerStatusText');
        const testerLatencyText = document.getElementById('testerLatencyText');
        const testerResponseBody = document.getElementById('testerResponseBody');

        let selectedRoute = null;

        tableRows.forEach(row => {
            row.addEventListener('click', () => {
                tableRows.forEach(r => r.classList.remove('selected-row'));
                row.classList.add('selected-row');

                selectedRoute = {
                    method: row.getAttribute('data-method'),
                    uri: row.getAttribute('data-uri'),
                    action: row.getAttribute('data-action')
                };

                // Switch panel view
                testerEmpty.classList.add('hidden');
                testerContent.classList.remove('hidden');
                testerResponseBlock.classList.add('hidden');

                // Bind details
                testerUriText.textContent = selectedRoute.uri;
                testerMethodBadge.textContent = selectedRoute.method;
                
                // Style badge
                testerMethodBadge.className = 'http-badge';
                const badgeClass = row.querySelector('.http-badge').className;
                testerMethodBadge.className = badgeClass;

                // Populate default body payload
                if (selectedRoute.method.includes('POST') || selectedRoute.method.includes('PUT') || selectedRoute.method.includes('PATCH')) {
                    if (selectedRoute.uri.includes('meal')) {
                        testerPayloadText.value = JSON.stringify({
                            day_of_week: "Monday",
                            menu_details: "Beef bhuna, Daal, Basmati rice",
                            cost: 85.00
                        }, null, 2);
                    } else if (selectedRoute.uri.includes('attendance')) {
                        testerPayloadText.value = JSON.stringify({
                            employee_id: 104,
                            punch_time: new Date().toISOString().slice(0, 19).replace('T', ' '),
                            punch_type: "Check-In"
                        }, null, 2);
                    } else {
                        testerPayloadText.value = JSON.stringify({
                            name: "New Entry",
                            active: true
                        }, null, 2);
                    }
                } else {
                    testerPayloadText.value = '{}';
                }
            });
        });

        // Run Test Action
        runTestBtn.addEventListener('click', () => {
            if (!selectedRoute) return;

            // Animate button
            runTestBtn.disabled = true;
            const originalBtnHtml = runTestBtn.innerHTML;
            runTestBtn.innerHTML = `
                <div class="btn-loading-dots">
                    <span></span><span></span><span></span>
                </div>
                <span>Invoking Gateway Target...</span>
            `;

            testerResponseBlock.classList.add('hidden');

            setTimeout(() => {
                // Revert button
                runTestBtn.disabled = false;
                runTestBtn.innerHTML = originalBtnHtml;

                // Generate Mock Response
                const latency = Math.floor(Math.random() * 85) + 12;
                testerLatencyText.textContent = `${latency}ms`;

                const isSuccess = Math.random() > 0.05; // 95% success rate for manual hits
                let responseJson = {};

                if (isSuccess) {
                    testerStatusText.textContent = '200 OK';
                    testerStatusText.className = 'response-status-badge';

                    if (selectedRoute.uri.includes('meal')) {
                        responseJson = {
                            success: true,
                            message: "Meal data retrieved successfully",
                            timestamp: new Date().toISOString(),
                            data: {
                                id: Math.floor(Math.random() * 10) + 1,
                                day_of_week: "Monday",
                                menu: "Veggies, Lentil Soup & Steamed Rice",
                                rate: 75.00,
                                active: 1
                            }
                        };
                    } else if (selectedRoute.uri.includes('attendance')) {
                        responseJson = {
                            success: true,
                            message: "User attendance registered successfully",
                            timestamp: new Date().toISOString(),
                            data: {
                                attendance_id: 9024,
                                employee_id: 45,
                                stamp: "2026-08-09 14:10:22",
                                verified: true
                            }
                        };
                    } else {
                        responseJson = {
                            success: true,
                            gateway: "ONLINE",
                            endpoint: selectedRoute.uri,
                            handler: selectedRoute.action,
                            result: "Standard response payload generated successfully.",
                            authenticated: true
                        };
                    }
                } else {
                    testerStatusText.textContent = '400 Bad Request';
                    testerStatusText.className = 'response-status-badge status-err';
                    responseJson = {
                        success: false,
                        error: "MALFORMED_QUERY_PAYLOAD",
                        message: "The request body validation failed.",
                        details: {
                            field: "parameters",
                            issue: "JSON contains syntax anomalies"
                        }
                    };
                }

                // Render output code
                testerResponseBody.textContent = JSON.stringify(responseJson, null, 2);
                testerResponseBlock.classList.remove('hidden');
            }, 600); // Realistic network delay
        });

        // --- 5. Simulated Realtime Live Trace Logs Feed & Modal ---
        const actualRoutes = JSON.parse(document.getElementById('requestFeed').getAttribute('data-routes') || '[]');
        const feed = document.getElementById('requestFeed');
        
        // Modal elements
        const traceModal = document.getElementById('traceModal');
        const modalMethodBadge = document.getElementById('modalMethodBadge');
        const modalUriText = document.getElementById('modalUriText');
        const modalStatusVal = document.getElementById('modalStatusVal');
        const modalDurationVal = document.getElementById('modalDurationVal');
        const modalMemoryVal = document.getElementById('modalMemoryVal');
        const modalQueriesVal = document.getElementById('modalQueriesVal');
        const modalHeadersVal = document.getElementById('modalHeadersVal');
        const modalUserVal = document.getElementById('modalUserVal');
        const modalLocationVal = document.getElementById('modalLocationVal');
        const modalSqlVal = document.getElementById('modalSqlVal');
        const modalPayloadVal = document.getElementById('modalPayloadVal');
        const modalCloseBtn = document.getElementById('modalCloseBtn');

        // Close modal event
        modalCloseBtn.addEventListener('click', () => {
            traceModal.classList.remove('show-modal');
        });
        traceModal.addEventListener('click', (e) => {
            if (e.target === traceModal) {
                traceModal.classList.remove('show-modal');
            }
        });

        // Map method to badge classes
        function getMethodBadgeClass(method) {
            const m = method.toUpperCase();
            if (m.includes('GET')) return 'method-get';
            if (m.includes('POST')) return 'method-post';
            if (m.includes('PUT') || m.includes('PATCH')) return 'method-put';
            if (m.includes('DELETE')) return 'method-delete';
            return 'method-default';
        }

        // Insert new log line helper
        function appendToFeed(route, latencyVal, errStatus) {
            const li = document.createElement('li');
            li.className = 'feed-item';
            
            const time = new Date().toLocaleTimeString('en-US', { hour12: false, fractionalSecondDigits: 1 });
            const method = Math.random() > 0.7 ? 'POST' : 'GET';
            
            let status = '200';
            let statusClass = 'status-2xx';
            
            if (errStatus) {
                status = Math.random() > 0.5 ? '500' : '403';
                statusClass = status === '500' ? 'status-5xx' : 'status-4xx';
            }

            const clientIp = "10.0.12." + Math.floor(Math.random() * 254 + 1);

            li.innerHTML = `
                <div class="feed-item-left">
                    <span class="feed-time">[${time}]</span>
                    <span class="feed-ip" style="color: var(--accent-indigo); font-size: 0.75rem; margin-right: 8px;">${clientIp}</span>
                    <span class="feed-route"><span class="http-badge ${getMethodBadgeClass(method)}" style="font-size: 0.6rem; padding: 0.1rem 0.3rem; margin-right: 5px;">${method}</span>${route}</span>
                </div>
                <div class="feed-item-right">
                    <span class="feed-status ${statusClass}">${status}</span>
                    <span class="feed-latency">${latencyVal}ms</span>
                </div>
            `;

            // Prepare rich modal details mapping to this request log
            const headersMock = {
                "Host": "api.rbac-erp.internal",
                "User-Agent": "ATI-Gateway-Client/v2.5 (Windows NT 10.0)",
                "Accept": "application/json",
                "Authorization": "Bearer ati_token_" + Math.random().toString(36).substring(2, 10) + "...",
                "X-Forwarded-For": clientIp,
                "Cache-Control": "no-cache"
            };

            let sqlTraceMock = "SELECT * FROM `users` WHERE `id` = 1 LIMIT 1;\nSELECT * FROM `sessions` WHERE `id` = 'session_id' LIMIT 1;";
            let payloadMock = {};
            
            if (status === '200') {
                payloadMock = { success: true, status: status, route: route };
                if (route.includes('meal')) {
                    sqlTraceMock = "SELECT * FROM `hrm_weekly_meals` WHERE `day_of_week` = 'Monday' AND `active` = 1 LIMIT 1;\nSELECT * FROM `hrm_weekly_meals_details` WHERE `meal_id` = 10;";
                    payloadMock = {
                        success: true,
                        day: "Monday",
                        menu: "Steamed Rice with Mutton Curry & Daal",
                        price: 95.00
                    };
                } else if (route.includes('attendance')) {
                    sqlTraceMock = "SELECT * FROM `employees` WHERE `employee_id` = 102 LIMIT 1;\nINSERT INTO `hrm_attendances` (`employee_id`, `punch_time`, `punch_type`) VALUES (102, NOW(), 'Check-In');";
                    payloadMock = {
                        success: true,
                        punch: "registered",
                        device: "ZKTeco_ADMS_1",
                        log_time: new Date().toISOString()
                    };
                }
            } else {
                payloadMock = { 
                    success: false, 
                    error: status === '403' ? 'UNAUTHORIZED_ACCESS' : 'INTERNAL_SERVER_ERROR',
                    message: status === '403' ? 'Invalid or expired authentication token.' : 'An unexpected exception occurred during request processing.',
                    route: route
                };
            }

            const firstNames = ["Ahmed", "Rafiq", "Sayed", "Jamal", "Tariq", "Fatima", "Ayesha"];
            const lastNames = ["Hasan", "Islam", "Rahman", "Uddin", "Chowdhury", "Begum", "Akter"];
            const roles = ["System Admin", "HR Manager", "Accountant", "Employee", "Sales Executive"];
            const mockUser = {
                "User ID": "EMP-" + Math.floor(Math.random() * 9000 + 1000),
                "Name": firstNames[Math.floor(Math.random() * firstNames.length)] + " " + lastNames[Math.floor(Math.random() * lastNames.length)],
                "Role": roles[Math.floor(Math.random() * roles.length)],
                "Email": "user" + Math.floor(Math.random() * 999) + "@rbac-erp.internal",
                "Last Active": new Date().toISOString()
            };

            const memoryAlloc = (Math.random() * 1.5 + 1.2).toFixed(2) + " MB";
            const queryCount = Math.floor(Math.random() * 4) + 2;

            // Generate mock location data
            const cities = ["Dhaka", "Chittagong", "Sylhet", "New York", "London", "Tokyo", "Berlin", "Dubai", "Singapore"];
            const countries = ["Bangladesh", "Bangladesh", "Bangladesh", "USA", "UK", "Japan", "Germany", "UAE", "Singapore"];
            const isps = ["Link3 Technologies", "Amber IT", "Carnival Internet", "Comcast", "BT Group", "NTT", "Deutsche Telekom", "Etisalat", "Singtel"];
            const randIdx = Math.floor(Math.random() * cities.length);
            const locationMock = {
                "IP Address": clientIp,
                "City": cities[randIdx],
                "Country": countries[randIdx],
                "ISP / Provider": isps[randIdx],
                "Latitude": (Math.random() * 180 - 90).toFixed(4),
                "Longitude": (Math.random() * 360 - 180).toFixed(4),
                "Timezone": "UTC" + (Math.random() > 0.5 ? "+" : "-") + Math.floor(Math.random() * 12) + ":00"
            };

            // Bind click to open detailed modal
            li.addEventListener('click', () => {
                modalMethodBadge.textContent = method;
                modalMethodBadge.className = 'http-badge ' + getMethodBadgeClass(method);
                modalUriText.textContent = route;
                
                modalStatusVal.textContent = status === '200' ? '200 OK' : `${status} Server Error`;
                modalStatusVal.className = 'modal-stat-value ' + (status === '200' ? 'text-success' : 'text-danger');

                modalDurationVal.textContent = `${latencyVal}ms`;
                modalMemoryVal.textContent = memoryAlloc;
                modalQueriesVal.textContent = `${queryCount} Queries`;

                modalHeadersVal.textContent = JSON.stringify(headersMock, null, 2);
                modalUserVal.textContent = JSON.stringify(mockUser, null, 2);
                modalLocationVal.textContent = JSON.stringify(locationMock, null, 2);
                modalSqlVal.textContent = sqlTraceMock;
                modalPayloadVal.textContent = JSON.stringify(payloadMock, null, 2);

                traceModal.classList.add('show-modal');
            });
            
            feed.prepend(li);
            if (feed.children.length > 9) {
                feed.lastChild.remove();
            }
        }

        // --- 6. Live Update Telemetry Update Loop ---
        setInterval(() => {
            // Random walk telemetry calculation
            let currentSuccess = telemetryChart.data.datasets[0].data[maxPoints - 1];
            let successChange = (Math.random() * 16) - 8;
            let newSuccess = Math.max(10, Math.min(90, Math.round(currentSuccess + successChange)));

            let errChange = Math.random() > 0.85 ? Math.floor(Math.random() * 4) : 0;

            // Push values to Chart
            telemetryChart.data.datasets[0].data.shift();
            telemetryChart.data.datasets[0].data.push(newSuccess);

            telemetryChart.data.datasets[1].data.shift();
            telemetryChart.data.datasets[1].data.push(errChange);

            telemetryChart.update();
            
            // Update the dashboard header metric text
            document.getElementById('rpsMetric').textContent = (newSuccess + errChange).toFixed(1);

            // Pop simulated list trace items
            if (actualRoutes.length > 0 && Math.random() > 0.3) {
                const randomRoute = actualRoutes[Math.floor(Math.random() * actualRoutes.length)];
                const latency = Math.floor(Math.random() * 60) + 10;
                const errChance = Math.random() > 0.95; // 5% simulated traffic fail rate
                appendToFeed(randomRoute, latency, errChance);
            }

        }, 1000); // 1-second refresh frequency
        
        // Initial populate of 6 logs in list feed
        for(let i = 0; i < 6; i++) {
            if(actualRoutes.length > 0) {
                const randomRoute = actualRoutes[Math.floor(Math.random() * actualRoutes.length)];
                const latency = Math.floor(Math.random() * 60) + 10;
                appendToFeed(randomRoute, latency, false);
            }
        }
    </script>
</body>
</html>
