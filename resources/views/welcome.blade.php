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

        /* Project Info View Styles */
        .project-hero-card {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.12), rgba(6, 182, 212, 0.12));
            border: 1px solid rgba(99, 102, 241, 0.28);
            border-radius: 16px;
            padding: 1.75rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1.5rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
            margin-bottom: 1.5rem;
        }

        .project-hero-left {
            display: flex;
            align-items: center;
            gap: 1.25rem;
        }

        .project-avatar-lg {
            width: 56px;
            height: 56px;
            background: linear-gradient(135deg, #6366F1, #06B6D4);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 1.4rem;
            color: #fff;
            box-shadow: 0 6px 20px rgba(99, 102, 241, 0.4);
            flex-shrink: 0;
        }

        .project-meta-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.25rem;
            margin-bottom: 1.5rem;
        }

        .project-info-card {
            background: var(--bg-surface);
            border: 1px solid var(--border-color);
            border-radius: 14px;
            padding: 1.25rem;
            backdrop-filter: blur(12px);
            transition: all 0.2s ease;
        }

        .project-info-card:hover {
            border-color: rgba(99, 102, 241, 0.35);
            transform: translateY(-2px);
        }

        .info-card-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
            font-weight: 600;
            margin-bottom: 0.35rem;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        .info-card-value {
            font-size: 1.15rem;
            font-weight: 700;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Link Rows in Project Info */
        .project-links-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(360px, 1fr));
            gap: 1.25rem;
            margin-bottom: 1.5rem;
        }

        .link-portal-card {
            background: var(--bg-surface);
            border: 1px solid var(--border-color);
            border-radius: 14px;
            padding: 1.25rem 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            backdrop-filter: blur(12px);
            transition: all 0.2s ease;
        }

        .link-portal-card:hover {
            border-color: rgba(6, 182, 212, 0.4);
            box-shadow: 0 8px 24px rgba(6, 182, 212, 0.1);
        }

        .link-portal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .link-portal-title {
            font-size: 0.92rem;
            font-weight: 700;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .link-url-box {
            display: flex;
            align-items: center;
            background: rgba(5, 8, 15, 0.8);
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: 8px;
            padding: 6px 10px;
            gap: 8px;
        }

        .link-url-text {
            font-family: var(--font-mono);
            font-size: 0.78rem;
            color: var(--accent-cyan);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            flex: 1;
        }

        .link-action-btn {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.12);
            color: var(--text-primary);
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.72rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: all 0.15s ease;
            text-decoration: none;
            flex-shrink: 0;
        }

        .link-action-btn:hover {
            background: var(--accent-indigo);
            border-color: var(--accent-indigo);
            color: #fff;
        }

        .link-action-btn-primary {
            background: linear-gradient(135deg, #0284C7, #3B82F6);
            border: none;
            color: #fff;
        }

        .link-action-btn-primary:hover {
            opacity: 0.9;
        }

        /* Toast Popup */
        .toast-notification {
            position: fixed;
            bottom: 24px;
            right: 24px;
            background: rgba(16, 185, 129, 0.95);
            color: #fff;
            padding: 10px 18px;
            border-radius: 30px;
            font-size: 0.82rem;
            font-weight: 600;
            z-index: 10000;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
            display: flex;
            align-items: center;
            gap: 8px;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .toast-notification.show {
            transform: translateY(0);
            opacity: 1;
        }

        /* DFD Diagram Styles */
        .dfd-hero-card {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.12), rgba(99, 102, 241, 0.12));
            border: 1px solid rgba(16, 185, 129, 0.28);
            border-radius: 16px;
            padding: 1.5rem 1.75rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
        }

        .dfd-switch-bar {
            display: flex;
            background: rgba(5, 8, 15, 0.7);
            padding: 4px;
            border-radius: 30px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            gap: 4px;
        }

        .dfd-tab-btn {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.78rem;
            font-weight: 600;
            cursor: pointer;
            border: none;
            background: transparent;
            color: var(--text-muted);
            transition: all 0.2s ease;
        }

        .dfd-tab-btn.active {
            background: var(--accent-emerald);
            color: #000;
            font-weight: 700;
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.4);
        }

        .dfd-canvas-card {
            background: #060913;
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 1.5rem;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.4);
            position: relative;
            overflow: hidden;
        }

        .dfd-canvas-card svg {
            width: 100%;
            height: auto;
            display: block;
        }

        .dfd-process-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.25rem;
            margin-bottom: 2rem;
        }

        .dfd-proc-card {
            background: var(--bg-surface);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1.25rem;
            backdrop-filter: blur(12px);
            transition: transform 0.2s, border-color 0.2s;
        }

        .dfd-proc-card:hover {
            transform: translateY(-2px);
            border-color: rgba(16, 185, 129, 0.4);
        }

        .dfd-proc-num {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 26px;
            height: 26px;
            border-radius: 50%;
            background: rgba(16, 185, 129, 0.2);
            color: #34D399;
            font-weight: 800;
            font-size: 0.75rem;
            font-family: var(--font-mono);
            border: 1px solid rgba(16, 185, 129, 0.4);
            margin-right: 6px;
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
                <a href="javascript:void(0)" onclick="switchMainTab('telemetry')" id="navTelemetry" class="nav-item active">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                    <span>Gateway Telemetry</span>
                </a>
                <a href="javascript:void(0)" onclick="switchMainTab('project-info')" id="navProjectInfo" class="nav-item">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--accent-indigo);"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                    <span>Project Info</span>
                    <span style="font-size: 0.65rem; background: var(--accent-indigo); color: #fff; font-weight: 700; padding: 1px 6px; border-radius: 10px; margin-left: auto;">StaffO</span>
                </a>
                <a href="{{ url('/docs/api') }}" target="_blank" class="nav-item">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                    API Documentation
                </a>
                <a href="{{ url('/er_diagram.html') }}" target="_blank" class="nav-item" style="border-left: 3px solid var(--accent-cyan); background: rgba(6, 182, 212, 0.08); color: var(--text-primary);">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--accent-cyan);"><circle cx="18" cy="5" r="3"></circle><circle cx="6" cy="12" r="3"></circle><circle cx="18" cy="19" r="3"></circle><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line></svg>
                    <span>ER Diagram Studio</span>
                    <span style="font-size: 0.65rem; background: var(--accent-cyan); color: #000; font-weight: 700; padding: 1px 6px; border-radius: 10px; margin-left: auto;">Interactive</span>
                </a>
                <a href="{{ url('/database_architecture_pdf.html') }}" target="_blank" class="nav-item">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                    <span>ERD PDF Document</span>
                    <span style="font-size: 0.65rem; background: var(--accent-rose); color: #fff; font-weight: 700; padding: 1px 6px; border-radius: 10px; margin-left: auto;">PDF</span>
                </a>
                <a href="javascript:void(0)" onclick="switchMainTab('dfd')" id="navDfd" class="nav-item">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--accent-emerald);"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg>
                    <span>Data Flow (DFD)</span>
                    <span style="font-size: 0.65rem; background: var(--accent-emerald); color: #000; font-weight: 700; padding: 1px 6px; border-radius: 10px; margin-left: auto;">L0 & L1</span>
                </a>
                <a href="{{ url('/data_flow_diagram_pdf.html') }}" target="_blank" class="nav-item">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--accent-emerald);"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                    <span>Data Flow (DFD) PDF</span>
                    <span style="font-size: 0.65rem; background: var(--accent-emerald); color: #000; font-weight: 700; padding: 1px 6px; border-radius: 10px; margin-left: auto;">PDF</span>
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

            <!-- Dashboard Scrolling Container (Telemetry View) -->
            <div class="container" id="telemetryView">
                <!-- Interactive ER Diagram & Architecture Banner -->
                <div style="background: linear-gradient(135deg, rgba(14, 165, 233, 0.1), rgba(99, 102, 241, 0.1)); border: 1px solid rgba(56, 189, 248, 0.25); border-radius: 16px; padding: 1.25rem 1.5rem; display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap; box-shadow: 0 8px 30px rgba(0, 0, 0, 0.2);">
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <div style="width: 44px; height: 44px; background: linear-gradient(135deg, #0EA5E9, #6366F1); border-radius: 10px; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.15rem; color: #fff; box-shadow: 0 4px 14px rgba(14, 165, 233, 0.4);">ER</div>
                        <div>
                            <div style="font-weight: 700; font-size: 1rem; color: #fff; display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                                <span>Multi-Tenancy Master ER Diagram & Architecture</span>
                                <span style="font-size: 0.7rem; background: rgba(56, 189, 248, 0.18); color: #38BDF8; border: 1px solid rgba(56, 189, 248, 0.35); padding: 2px 8px; border-radius: 12px; font-weight: 600;">38 Entities • 8 Subsystems</span>
                            </div>
                            <div style="font-size: 0.8rem; color: #94A3B8; margin-top: 2px;">Full interactive single-page canvas with smooth pan/zoom, foreign key tracing, model inspectors, and data dictionary.</div>
                        </div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                        <a href="{{ url('/er_diagram.html') }}" target="_blank" style="text-decoration: none; background: linear-gradient(135deg, #0284C7, #3B82F6); color: #fff; font-weight: 600; font-size: 0.82rem; padding: 8px 16px; border-radius: 8px; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 4px 14px rgba(14, 165, 233, 0.35);">
                            <span>🌐 Open ER Diagram Studio</span>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
                        </a>
                        <a href="{{ url('/database_architecture_pdf.html') }}" target="_blank" style="text-decoration: none; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.12); color: #F8FAFC; font-weight: 600; font-size: 0.82rem; padding: 8px 14px; border-radius: 8px; display: inline-flex; align-items: center; gap: 6px; cursor: pointer;">
                            <span>📄 PDF Documentation</span>
                        </a>
                    </div>
                </div>

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

            <!-- Project Info Container View -->
            <div class="container" id="projectInfoView" style="display: none; animation: fadeIn 0.25s ease;">
                
                <!-- Project Hero Card -->
                <div class="project-hero-card">
                    <div class="project-hero-left">
                        <div class="project-avatar-lg">SO</div>
                        <div>
                            <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                                <h2 style="font-size: 1.45rem; font-weight: 800; color: #fff; letter-spacing: -0.02em;">StaffO Enterprise ERP & RBAC</h2>
                                <span style="background: rgba(16, 185, 129, 0.2); color: #34D399; border: 1px solid rgba(16, 185, 129, 0.4); font-size: 0.72rem; font-weight: 700; padding: 2px 10px; border-radius: 20px; text-transform: uppercase;">Active Engineering</span>
                            </div>
                            <p style="color: var(--text-secondary); font-size: 0.85rem; margin-top: 4px;">Next-generation multi-tenant workforce management, biometric attendance & granular permission control platform.</p>
                        </div>
                    </div>
                    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                        <a href="javascript:void(0)" onclick="switchMainTab('dfd')" class="link-action-btn" style="padding: 8px 14px; font-size: 0.82rem; border-color: rgba(16, 185, 129, 0.4); color: #34D399; background: rgba(16, 185, 129, 0.1);">
                            <span>📊 Data Flow (DFD)</span>
                        </a>
                        <a href="{{ url('/er_diagram.html') }}" target="_blank" class="link-action-btn link-action-btn-primary" style="padding: 8px 16px; font-size: 0.82rem;">
                            <span>🌐 Open ER Diagram Studio</span>
                        </a>
                        <a href="{{ url('/database_architecture_pdf.html') }}" target="_blank" class="link-action-btn" style="padding: 8px 14px; font-size: 0.82rem;">
                            <span>📄 View Architecture PDF</span>
                        </a>
                    </div>
                </div>

                <!-- Core Project Metadata Grid -->
                <div class="project-meta-grid">
                    <!-- Client Name -->
                    <div class="project-info-card">
                        <div class="info-card-label">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                            Client Name
                        </div>
                        <div class="info-card-value" style="color: var(--accent-cyan);">
                            <span>StaffO</span>
                        </div>
                    </div>

                    <!-- Project Name -->
                    <div class="project-info-card">
                        <div class="info-card-label">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg>
                            Project Name
                        </div>
                        <div class="info-card-value" style="color: var(--accent-indigo);">
                            <span>StaffO</span>
                        </div>
                    </div>

                    <!-- Project Start Date -->
                    <div class="project-info-card">
                        <div class="info-card-label">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                            Project Start Date
                        </div>
                        <div class="info-card-value" style="color: var(--accent-emerald);">
                            <span>29-07-2026</span>
                        </div>
                    </div>

                    <!-- Development Dept -->
                    <div class="project-info-card">
                        <div class="info-card-label">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 18 22 12 16 6"></polyline><polyline points="8 6 2 12 8 18"></polyline></svg>
                            Development Dept
                        </div>
                        <div class="info-card-value" style="color: var(--accent-amber);">
                            <span>PHP</span>
                            <span style="font-size: 0.65rem; background: rgba(245, 158, 11, 0.15); color: #F59E0B; border: 1px solid rgba(245, 158, 11, 0.3); padding: 1px 6px; border-radius: 6px;">Laravel 11/12</span>
                        </div>
                    </div>
                </div>

                <!-- Section Title: Environments & Project Links -->
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem; margin-top: 0.5rem;">
                    <div style="font-size: 1.05rem; font-weight: 700; color: #fff; display: flex; align-items: center; gap: 8px;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--accent-cyan);"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path></svg>
                        Environments & Deployment Links
                    </div>
                    <span style="font-size: 0.75rem; color: var(--text-muted);">Click button to open or copy URL</span>
                </div>

                <!-- Environments & Links Grid -->
                <div class="project-links-grid">
                    
                    <!-- 1. Project Link -->
                    <div class="link-portal-card">
                        <div class="link-portal-header">
                            <div class="link-portal-title">
                                <span style="font-size: 1.1rem;">🌐</span>
                                <span>Project Link (Front-End)</span>
                            </div>
                            <span style="font-size: 0.65rem; background: rgba(6, 182, 212, 0.15); color: var(--accent-cyan); padding: 2px 8px; border-radius: 12px; font-weight: 600;">Active Dev / Host</span>
                        </div>
                        <div class="link-url-box">
                            <input type="text" id="projectLinkInput" class="link-url-text" style="background: transparent; border: none; outline: none; width: 100%;" value="http://192.168.0.202:3020/login" readonly>
                        </div>
                        <div style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 2px;">
                            <button class="link-action-btn" onclick="copyToClipboard('projectLinkInput')">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                                Copy
                            </button>
                            <a href="http://192.168.0.202:3020/login" target="_blank" class="link-action-btn link-action-btn-primary">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
                                Open
                            </a>
                        </div>
                    </div>

                    <!-- 2. UI/UX Link -->
                    <div class="link-portal-card">
                        <div class="link-portal-header">
                            <div class="link-portal-title">
                                <span style="font-size: 1.1rem;">🎨</span>
                                <span>UI/UX Link (Figma / Design)</span>
                            </div>
                            <span style="font-size: 0.65rem; background: rgba(192, 132, 252, 0.15); color: #C084FC; padding: 2px 8px; border-radius: 12px; font-weight: 600;">Design System</span>
                        </div>
                        <div class="link-url-box">
                            <input type="text" id="uiuxLinkInput" class="link-url-text" style="background: transparent; border: none; outline: none; width: 100%; color: #C084FC;" value="https://www.figma.com/proto/OV0idHjpixYBYtoaAZbHAA/Human-Resource-Management--HRM--Drug-International-Limited?node-id=3353-20517&t=XxqtZPig7XYf49pO-0&scaling=min-zoom&content-scaling=fixed&page-id=9%3A11&starting-point-node-id=3353%3A20517" placeholder="https://www.figma.com/file/staffo-design">
                        </div>
                        <div style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 2px;">
                            <button class="link-action-btn" onclick="copyToClipboard('uiuxLinkInput')">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                                Copy
                            </button>
                            <a href="https://www.figma.com" target="_blank" class="link-action-btn link-action-btn-primary" id="uiuxOpenBtn">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
                                Open UI/UX
                            </a>
                        </div>
                    </div>

                    <!-- 3. QA Link -->
                    <div class="link-portal-card">
                        <div class="link-portal-header">
                            <div class="link-portal-title">
                                <span style="font-size: 1.1rem;">🧪</span>
                                <span>QA Link (Quality Assurance)</span>
                            </div>
                            <span style="font-size: 0.65rem; background: rgba(251, 191, 36, 0.15); color: #FBBF24; padding: 2px 8px; border-radius: 12px; font-weight: 600;">Staging Test</span>
                        </div>
                        <div class="link-url-box">
                            <input type="text" id="qaLinkInput" class="link-url-text" style="background: transparent; border: none; outline: none; width: 100%; color: #FBBF24;" value="http://192.168.0.202:3020/login" placeholder="http://192.168.0.202:3020/login">
                        </div>
                        <div style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 2px;">
                            <button class="link-action-btn" onclick="copyToClipboard('qaLinkInput')">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                                Copy
                            </button>
                            <a href="http://192.168.0.202:3020/login" target="_blank" class="link-action-btn link-action-btn-primary" id="qaOpenBtn">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
                                Open QA
                            </a>
                        </div>
                    </div>

                    <!-- 4. UAT Link -->
                    <div class="link-portal-card">
                        <div class="link-portal-header">
                            <div class="link-portal-title">
                                <span style="font-size: 1.1rem;">👥</span>
                                <span>UAT Link (User Acceptance Testing)</span>
                            </div>
                            <span style="font-size: 0.65rem; background: rgba(16, 185, 129, 0.15); color: #34D399; padding: 2px 8px; border-radius: 12px; font-weight: 600;">Client Acceptance</span>
                        </div>
                        <div class="link-url-box">
                            <input type="text" id="uatLinkInput" class="link-url-text" style="background: transparent; border: none; outline: none; width: 100%; color: #34D399;" value="http://192.168.0.202:3020/login" placeholder="http://192.168.0.202:3020/login">
                        </div>
                        <div style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 2px;">
                            <button class="link-action-btn" onclick="copyToClipboard('uatLinkInput')">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                                Copy
                            </button>
                            <a href="http://192.168.0.202:3020/login" target="_blank" class="link-action-btn link-action-btn-primary" id="uatOpenBtn">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
                                Open UAT
                            </a>
                        </div>
                    </div>

                </div>

                <!-- Technical Architectural Overview Cards -->
                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.25rem; margin-bottom: 2rem;">
                    
                    <!-- Subsystems Matrix -->
                    <div class="panel" style="margin-bottom: 0;">
                        <div class="panel-header">
                            <div class="panel-title">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                                <span>Core Subsystems & Modules Deliverables</span>
                            </div>
                            <span style="font-size: 0.72rem; color: var(--accent-cyan); font-weight: 600;">8 Core Domains • 38 Entities</span>
                        </div>
                        <div style="padding: 1.25rem; display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px;">
                            <div style="background: rgba(255,255,255,0.02); border: 1px solid var(--border-color); border-radius: 8px; padding: 10px 12px;">
                                <div style="font-weight: 700; font-size: 0.82rem; color: var(--accent-cyan); display: flex; align-items: center; gap: 6px;">🏢 Multi-Tenancy</div>
                                <div style="font-size: 0.72rem; color: var(--text-muted); margin-top: 2px;">Company & Organization multi-tenant isolation, subscriptions & plans.</div>
                            </div>
                            <div style="background: rgba(255,255,255,0.02); border: 1px solid var(--border-color); border-radius: 8px; padding: 10px 12px;">
                                <div style="font-weight: 700; font-size: 0.82rem; color: var(--accent-indigo); display: flex; align-items: center; gap: 6px;">🛡️ RBAC & Permissions</div>
                                <div style="font-size: 0.72rem; color: var(--text-muted); margin-top: 2px;">Hierarchical roles, closure inheritance & dynamic menu guard trees.</div>
                            </div>
                            <div style="background: rgba(255,255,255,0.02); border: 1px solid var(--border-color); border-radius: 8px; padding: 10px 12px;">
                                <div style="font-weight: 700; font-size: 0.82rem; color: var(--accent-emerald); display: flex; align-items: center; gap: 6px;">👥 HRM Employee 360</div>
                                <div style="font-size: 0.72rem; color: var(--text-muted); margin-top: 2px;">50+ demographics, education, experience, family & emergency sub-profiles.</div>
                            </div>
                            <div style="background: rgba(255,255,255,0.02); border: 1px solid var(--border-color); border-radius: 8px; padding: 10px 12px;">
                                <div style="font-weight: 700; font-size: 0.82rem; color: var(--accent-amber); display: flex; align-items: center; gap: 6px;">⏱️ ZKTeco ADMS Biometrics</div>
                                <div style="font-size: 0.72rem; color: var(--text-muted); margin-top: 2px;">Hardware push protocol, shift rosters & automated attendance evaluation.</div>
                            </div>
                            <div style="background: rgba(255,255,255,0.02); border: 1px solid var(--border-color); border-radius: 8px; padding: 10px 12px;">
                                <div style="font-weight: 700; font-size: 0.82rem; color: var(--accent-rose); display: flex; align-items: center; gap: 6px;">🏖️ Leave Management</div>
                                <div style="font-size: 0.72rem; color: var(--text-muted); margin-top: 2px;">Yearly quotas, multi-level application workflow & holiday calendar.</div>
                            </div>
                            <div style="background: rgba(255,255,255,0.02); border: 1px solid var(--border-color); border-radius: 8px; padding: 10px 12px;">
                                <div style="font-weight: 700; font-size: 0.82rem; color: var(--accent-purple); display: flex; align-items: center; gap: 6px;">🍽️ Cafeteria & Meals</div>
                                <div style="font-size: 0.72rem; color: var(--text-muted); margin-top: 2px;">Weekly chef menus, employee lunch bookings & counter validation.</div>
                            </div>
                        </div>
                    </div>

                    <!-- Tech Stack Summary -->
                    <div class="panel" style="margin-bottom: 0;">
                        <div class="panel-header">
                            <div class="panel-title">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 18 22 12 16 6"></polyline><polyline points="8 6 2 12 8 18"></polyline></svg>
                                <span>Technology Stack</span>
                            </div>
                        </div>
                        <div style="padding: 1.25rem; display: flex; flex-direction: column; gap: 10px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.8rem; border-bottom: 1px solid rgba(255,255,255,0.04); padding-bottom: 6px;">
                                <span style="color: var(--text-muted);">Backend Language</span>
                                <span style="font-weight: 700; color: #fff; font-family: var(--font-mono);">PHP 8.2+</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.8rem; border-bottom: 1px solid rgba(255,255,255,0.04); padding-bottom: 6px;">
                                <span style="color: var(--text-muted);">Framework</span>
                                <span style="font-weight: 700; color: var(--accent-rose); font-family: var(--font-mono);">Laravel 11/12</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.8rem; border-bottom: 1px solid rgba(255,255,255,0.04); padding-bottom: 6px;">
                                <span style="color: var(--text-muted);">Database</span>
                                <span style="font-weight: 700; color: var(--accent-cyan); font-family: var(--font-mono);">MySQL 8.0</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.8rem; border-bottom: 1px solid rgba(255,255,255,0.04); padding-bottom: 6px;">
                                <span style="color: var(--text-muted);">Caching & Queues</span>
                                <span style="font-weight: 700; color: var(--accent-amber); font-family: var(--font-mono);">Redis 7.x</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.8rem;">
                                <span style="color: var(--text-muted);">Hardware Protocol</span>
                                <span style="font-weight: 700; color: var(--accent-emerald); font-family: var(--font-mono);">ZKTeco ADMS Push</span>
                            </div>
                        </div>
                    </div>

                </div>

            </div>

            <!-- Data Flow Diagram (DFD) Container View -->
            <div class="container" id="dfdView" style="display: none; animation: fadeIn 0.25s ease;">
                
                <!-- DFD Hero Header -->
                <div class="dfd-hero-card">
                    <div style="display: flex; align-items: center; gap: 1.25rem;">
                        <div class="project-avatar-lg" style="background: linear-gradient(135deg, #10B981, #06B6D4); box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);">DFD</div>
                        <div>
                            <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                                <h2 style="font-size: 1.4rem; font-weight: 800; color: #fff; letter-spacing: -0.02em;">System Data Flow Architecture (DFD)</h2>
                                <span style="background: rgba(16, 185, 129, 0.2); color: #34D399; border: 1px solid rgba(16, 185, 129, 0.4); font-size: 0.72rem; font-weight: 700; padding: 2px 10px; border-radius: 20px; text-transform: uppercase;">Architecture Model</span>
                            </div>
                            <p style="color: var(--text-secondary); font-size: 0.85rem; margin-top: 4px;">Comprehensive data flow analysis detailing external actors, operational transformation processes, and multi-tenant data stores.</p>
                        </div>
                    </div>
                    
                    <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                        <!-- Level Switcher Tabs -->
                        <div class="dfd-switch-bar">
                            <button class="dfd-tab-btn active" id="dfdTabL0" onclick="switchDfdLevel('l0')">🌐 Level 0: Context</button>
                            <button class="dfd-tab-btn" id="dfdTabL1" onclick="switchDfdLevel('l1')">⚙️ Level 1: Processes</button>
                            <button class="dfd-tab-btn" id="dfdTabDict" onclick="switchDfdLevel('dict')">📖 DFD Dictionary</button>
                        </div>
                        <a href="{{ url('/data_flow_diagram_pdf.html') }}" target="_blank" class="link-action-btn link-action-btn-primary" style="padding: 6px 14px; font-size: 0.78rem; border-radius: 20px; background: linear-gradient(135deg, #059669, #0284C7);">
                            <span>📄 Download DFD PDF</span>
                        </a>
                    </div>
                </div>

                <!-- ========================================================
                     DFD LEVEL 0: CONTEXT LEVEL DIAGRAM CANVAS
                     ======================================================== -->
                <div class="dfd-canvas-card" id="dfdCanvasL0">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; border-bottom: 1px solid rgba(255,255,255,0.06); padding-bottom: 10px;">
                        <div style="font-weight: 700; font-size: 0.95rem; color: #fff; display: flex; align-items: center; gap: 8px;">
                            <span style="color: var(--accent-emerald);">●</span>
                            <span>DFD Level 0: Global Context Diagram (System Boundaries & External Entities)</span>
                        </div>
                        <span style="font-size: 0.72rem; background: rgba(16, 185, 129, 0.15); color: #34D399; padding: 2px 8px; border-radius: 10px; font-weight: 600;">Context Model</span>
                    </div>

                    <svg viewBox="0 0 1000 620" xmlns="http://www.w3.org/2000/svg" style="background:#050811; border-radius:10px;">
                        <defs>
                            <!-- Gradients -->
                            <linearGradient id="dfdCenterGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                                <stop offset="0%" stop-color="#0284C7"/>
                                <stop offset="50%" stop-color="#4F46E5"/>
                                <stop offset="100%" stop-color="#059669"/>
                            </linearGradient>
                            <linearGradient id="entityGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                                <stop offset="0%" stop-color="#1E293B"/>
                                <stop offset="100%" stop-color="#0F172A"/>
                            </linearGradient>
                            <marker id="arrowCyan" markerWidth="8" markerHeight="8" refX="6" refY="3" orient="auto">
                                <path d="M0,0 L0,6 L8,3 z" fill="#38BDF8"/>
                            </marker>
                            <marker id="arrowEmerald" markerWidth="8" markerHeight="8" refX="6" refY="3" orient="auto">
                                <path d="M0,0 L0,6 L8,3 z" fill="#34D399"/>
                            </marker>
                            <marker id="arrowAmber" markerWidth="8" markerHeight="8" refX="6" refY="3" orient="auto">
                                <path d="M0,0 L0,6 L8,3 z" fill="#FBBF24"/>
                            </marker>
                            <marker id="arrowRose" markerWidth="8" markerHeight="8" refX="6" refY="3" orient="auto">
                                <path d="M0,0 L0,6 L8,3 z" fill="#FB7185"/>
                            </marker>
                            <marker id="arrowPurple" markerWidth="8" markerHeight="8" refX="6" refY="3" orient="auto">
                                <path d="M0,0 L0,6 L8,3 z" fill="#C084FC"/>
                            </marker>
                        </defs>

                        <!-- DATA FLOW ARROWS -->
                        <!-- 1. Super Admin <-> 0.0 -->
                        <path d="M 230 100 C 350 100, 370 240, 410 270" stroke="#38BDF8" stroke-width="2" fill="none" marker-end="url(#arrowCyan)"/>
                        <text x="250" y="160" fill="#38BDF8" font-size="9" font-family="'JetBrains Mono', monospace">Tenant Config, Roles, Plans</text>
                        
                        <path d="M 400 300 C 340 280, 270 200, 230 135" stroke="#94A3B8" stroke-dasharray="3" stroke-width="1.5" fill="none" marker-end="url(#arrowCyan)"/>
                        <text x="210" y="220" fill="#94A3B8" font-size="8.5" font-family="'JetBrains Mono', monospace">Audit Trails & Telemetry Logs</text>

                        <!-- 2. HR Managers <-> 0.0 -->
                        <path d="M 770 100 C 650 100, 630 240, 590 270" stroke="#34D399" stroke-width="2" fill="none" marker-end="url(#arrowEmerald)"/>
                        <text x="630" y="160" fill="#34D399" font-size="9" font-family="'JetBrains Mono', monospace">Employee Onboarding, Rosters, Approvals</text>

                        <path d="M 600 300 C 660 280, 730 200, 770 135" stroke="#94A3B8" stroke-dasharray="3" stroke-width="1.5" fill="none" marker-end="url(#arrowEmerald)"/>
                        <text x="660" y="220" fill="#94A3B8" font-size="8.5" font-family="'JetBrains Mono', monospace">Daily Attendance & Leave Quota Reports</text>

                        <!-- 3. ZKTeco ADMS Devices <-> 0.0 -->
                        <path d="M 120 370 L 370 340" stroke="#FBBF24" stroke-width="2.5" fill="none" marker-end="url(#arrowAmber)"/>
                        <text x="140" y="335" fill="#FBBF24" font-size="9" font-weight="bold" font-family="'JetBrains Mono', monospace">Raw Punches (Push HTTP /iclock/cdata)</text>

                        <path d="M 370 365 L 120 395" stroke="#94A3B8" stroke-dasharray="3" stroke-width="1.5" fill="none" marker-end="url(#arrowAmber)"/>
                        <text x="150" y="415" fill="#94A3B8" font-size="8.5" font-family="'JetBrains Mono', monospace">Heartbeat Sync Acks & Commands (OK)</text>

                        <!-- 4. Employees & Staff <-> 0.0 -->
                        <path d="M 380 500 C 420 450, 440 430, 470 410" stroke="#FB7185" stroke-width="2" fill="none" marker-end="url(#arrowRose)"/>
                        <text x="260" y="480" fill="#FB7185" font-size="9" font-family="'JetBrains Mono', monospace">Login Credentials, Leave Request, Meal Booking</text>

                        <path d="M 500 410 C 500 450, 480 480, 440 510" stroke="#94A3B8" stroke-dasharray="3" stroke-width="1.5" fill="none" marker-end="url(#arrowRose)"/>
                        <text x="510" y="480" fill="#94A3B8" font-size="8.5" font-family="'JetBrains Mono', monospace">JWT Bearer Token, Balance, QR Meal Token</text>

                        <!-- 5. Cafeteria Staff <-> 0.0 -->
                        <path d="M 880 370 L 630 340" stroke="#C084FC" stroke-width="2" fill="none" marker-end="url(#arrowPurple)"/>
                        <text x="660" y="335" fill="#C084FC" font-size="9" font-family="'JetBrains Mono', monospace">Weekly Lunch Menus & Pricing</text>

                        <path d="M 630 365 L 880 395" stroke="#94A3B8" stroke-dasharray="3" stroke-width="1.5" fill="none" marker-end="url(#arrowPurple)"/>
                        <text x="660" y="415" fill="#94A3B8" font-size="8.5" font-family="'JetBrains Mono', monospace">Booked Headcount & QR Token Validation</text>

                        <!-- CENTRAL PROCESS BUBBLE: 0.0 StaffO ERP Gateway -->
                        <g transform="translate(500, 330)">
                            <circle r="125" fill="#0A1020" stroke="url(#dfdCenterGrad)" stroke-width="3.5" filter="drop-shadow(0 0 25px rgba(99, 102, 241, 0.4))"/>
                            <circle r="118" fill="none" stroke="rgba(255,255,255,0.08)" stroke-width="1" stroke-dasharray="4"/>
                            
                            <text x="0" y="-55" text-anchor="middle" fill="#38BDF8" font-size="13" font-weight="800" font-family="'JetBrains Mono', monospace">0.0</text>
                            <text x="0" y="-30" text-anchor="middle" fill="#FFFFFF" font-size="14" font-weight="800" font-family="'Outfit', sans-serif">StaffO ERP Core Engine</text>
                            <text x="0" y="-10" text-anchor="middle" fill="#94A3B8" font-size="10.5">& API Gateway System</text>
                            
                            <line x1="-80" y1="5" x2="80" y2="5" stroke="rgba(255,255,255,0.15)"/>
                            
                            <text x="0" y="24" text-anchor="middle" fill="#34D399" font-size="9" font-weight="600" font-family="'JetBrains Mono', monospace">• Multi-Tenant RBAC</text>
                            <text x="0" y="40" text-anchor="middle" fill="#FBBF24" font-size="9" font-weight="600" font-family="'JetBrains Mono', monospace">• ADMS Push Ingestion</text>
                            <text x="0" y="56" text-anchor="middle" fill="#FB7185" font-size="9" font-weight="600" font-family="'JetBrains Mono', monospace">• Employee 360 & Leaves</text>
                            <text x="0" y="72" text-anchor="middle" fill="#C084FC" font-size="9" font-weight="600" font-family="'JetBrains Mono', monospace">• Weekly Cafeteria Meals</text>
                        </g>

                        <!-- EXTERNAL ENTITIES (RECTANGLES) -->
                        <!-- Entity 1: Super Admin / Tenant Admin -->
                        <g transform="translate(60, 50)">
                            <rect width="170" height="90" rx="8" fill="url(#entityGrad)" stroke="#38BDF8" stroke-width="1.8"/>
                            <rect width="170" height="24" rx="8" fill="#0284C7"/>
                            <text x="12" y="16" fill="#FFF" font-size="10.5" font-weight="bold">👨‍💼 Super / Tenant Admin</text>
                            <text x="12" y="44" fill="#94A3B8" font-size="8.5">• Organization Setup</text>
                            <text x="12" y="58" fill="#94A3B8" font-size="8.5">• Role & Permission Matrix</text>
                            <text x="12" y="72" fill="#94A3B8" font-size="8.5">• Subscription Licensing</text>
                        </g>

                        <!-- Entity 2: HR Operations & Managers -->
                        <g transform="translate(770, 50)">
                            <rect width="170" height="90" rx="8" fill="url(#entityGrad)" stroke="#34D399" stroke-width="1.8"/>
                            <rect width="170" height="24" rx="8" fill="#059669"/>
                            <text x="12" y="16" fill="#FFF" font-size="10.5" font-weight="bold">👥 HR Operations / Mgr</text>
                            <text x="12" y="44" fill="#94A3B8" font-size="8.5">• Employee Onboarding</text>
                            <text x="12" y="58" fill="#94A3B8" font-size="8.5">• Shift Rostering & Rules</text>
                            <text x="12" y="72" fill="#94A3B8" font-size="8.5">• Leave Approval Decision</text>
                        </g>

                        <!-- Entity 3: ZKTeco ADMS Biometrics Hardware -->
                        <g transform="translate(20, 340)">
                            <rect width="170" height="90" rx="8" fill="url(#entityGrad)" stroke="#FBBF24" stroke-width="1.8"/>
                            <rect width="170" height="24" rx="8" fill="#D97706"/>
                            <text x="12" y="16" fill="#FFF" font-size="10.5" font-weight="bold">📟 ZKTeco ADMS Terminals</text>
                            <text x="12" y="44" fill="#94A3B8" font-size="8.5">• Biometric Finger/Face/Card</text>
                            <text x="12" y="58" fill="#94A3B8" font-size="8.5">• HTTP Push Stream Data</text>
                            <text x="12" y="72" fill="#94A3B8" font-size="8.5">• Device Serial & Heartbeat</text>
                        </g>

                        <!-- Entity 4: Employees & Staff -->
                        <g transform="translate(320, 510)">
                            <rect width="190" height="85" rx="8" fill="url(#entityGrad)" stroke="#FB7185" stroke-width="1.8"/>
                            <rect width="190" height="24" rx="8" fill="#E11D48"/>
                            <text x="12" y="16" fill="#FFF" font-size="10.5" font-weight="bold">👤 Employees & Staff</text>
                            <text x="12" y="44" fill="#94A3B8" font-size="8.5">• Profile & Demographics View</text>
                            <text x="12" y="58" fill="#94A3B8" font-size="8.5">• Leave Quotas & Application</text>
                            <text x="12" y="72" fill="#94A3B8" font-size="8.5">• Lunch Meal Reservation</text>
                        </g>

                        <!-- Entity 5: Cafeteria Staff & Chefs -->
                        <g transform="translate(810, 340)">
                            <rect width="170" height="90" rx="8" fill="url(#entityGrad)" stroke="#C084FC" stroke-width="1.8"/>
                            <rect width="170" height="24" rx="8" fill="#7E22CE"/>
                            <text x="12" y="16" fill="#FFF" font-size="10.5" font-weight="bold">🍳 Cafeteria Chefs & Staff</text>
                            <text x="12" y="44" fill="#94A3B8" font-size="8.5">• Publish Weekly Menu</text>
                            <text x="12" y="58" fill="#94A3B8" font-size="8.5">• Lunch Headcount Forecast</text>
                            <text x="12" y="72" fill="#94A3B8" font-size="8.5">• Token Consumption Scan</text>
                        </g>
                    </svg>
                </div>

                <!-- ========================================================
                     DFD LEVEL 1: SYSTEM DECOMPOSITION PROCESS DIAGRAM CANVAS
                     ======================================================== -->
                <div class="dfd-canvas-card" id="dfdCanvasL1" style="display: none;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; border-bottom: 1px solid rgba(255,255,255,0.06); padding-bottom: 10px;">
                        <div style="font-weight: 700; font-size: 0.95rem; color: #fff; display: flex; align-items: center; gap: 8px;">
                            <span style="color: var(--accent-cyan);">●</span>
                            <span>DFD Level 1: Subsystem Process Decomposition & Data Store Flow</span>
                        </div>
                        <span style="font-size: 0.72rem; background: rgba(6, 182, 212, 0.15); color: var(--accent-cyan); padding: 2px 8px; border-radius: 10px; font-weight: 600;">Process Decomposed Model</span>
                    </div>

                    <svg viewBox="0 0 1000 680" xmlns="http://www.w3.org/2000/svg" style="background:#050811; border-radius:10px;">
                        <!-- Process 1.0 <-> Stores D1, D2, D7 -->
                        <path d="M 230 90 L 370 90" stroke="#38BDF8" stroke-width="2" fill="none" marker-end="url(#arrowCyan)"/>
                        <path d="M 450 130 L 450 200" stroke="#38BDF8" stroke-width="1.8" fill="none" marker-end="url(#arrowCyan)"/>
                        <path d="M 450 90 L 590 90" stroke="#818CF8" stroke-width="2" fill="none" marker-end="url(#arrowCyan)"/>

                        <!-- Process 2.0 <-> Store D3 -->
                        <path d="M 170 290 L 260 290" stroke="#34D399" stroke-width="2" fill="none" marker-end="url(#arrowEmerald)"/>
                        <path d="M 340 330 L 340 400" stroke="#34D399" stroke-width="1.8" fill="none" marker-end="url(#arrowEmerald)"/>

                        <!-- Process 3.0 <-> Store D4 -->
                        <path d="M 770 290 L 680 290" stroke="#FBBF24" stroke-width="2" fill="none" marker-end="url(#arrowAmber)"/>
                        <path d="M 600 330 L 600 400" stroke="#FBBF24" stroke-width="1.8" fill="none" marker-end="url(#arrowAmber)"/>

                        <!-- Process 4.0 <-> Store D5 -->
                        <path d="M 260 520 L 370 520" stroke="#FB7185" stroke-width="2" fill="none" marker-end="url(#arrowRose)"/>

                        <!-- Process 5.0 <-> Store D6 -->
                        <path d="M 680 520 L 570 520" stroke="#C084FC" stroke-width="2" fill="none" marker-end="url(#arrowPurple)"/>

                        <!-- Process 1.0 Bubble -->
                        <g transform="translate(450, 90)">
                            <circle r="45" fill="#1E293B" stroke="#0284C7" stroke-width="2"/>
                            <text x="0" y="-12" text-anchor="middle" fill="#38BDF8" font-size="11" font-weight="bold" font-family="'JetBrains Mono', monospace">1.0</text>
                            <text x="0" y="6" text-anchor="middle" fill="#FFF" font-size="8.5" font-weight="bold">Tenant Auth &</text>
                            <text x="0" y="18" text-anchor="middle" fill="#FFF" font-size="8.5" font-weight="bold">RBAC Check</text>
                        </g>

                        <!-- Process 2.0 Bubble -->
                        <g transform="translate(170, 290)">
                            <circle r="45" fill="#1E293B" stroke="#059669" stroke-width="2"/>
                            <text x="0" y="-12" text-anchor="middle" fill="#34D399" font-size="11" font-weight="bold" font-family="'JetBrains Mono', monospace">2.0</text>
                            <text x="0" y="6" text-anchor="middle" fill="#FFF" font-size="8.5" font-weight="bold">HRM Employee</text>
                            <text x="0" y="18" text-anchor="middle" fill="#FFF" font-size="8.5" font-weight="bold">360 Lifecycle</text>
                        </g>

                        <!-- Process 3.0 Bubble -->
                        <g transform="translate(770, 290)">
                            <circle r="45" fill="#1E293B" stroke="#D97706" stroke-width="2"/>
                            <text x="0" y="-12" text-anchor="middle" fill="#FBBF24" font-size="11" font-weight="bold" font-family="'JetBrains Mono', monospace">3.0</text>
                            <text x="0" y="6" text-anchor="middle" fill="#FFF" font-size="8.5" font-weight="bold">ADMS Push &</text>
                            <text x="0" y="18" text-anchor="middle" fill="#FFF" font-size="8.5" font-weight="bold">Shift Evaluation</text>
                        </g>

                        <!-- Process 4.0 Bubble -->
                        <g transform="translate(170, 520)">
                            <circle r="45" fill="#1E293B" stroke="#E11D48" stroke-width="2"/>
                            <text x="0" y="-12" text-anchor="middle" fill="#FB7185" font-size="11" font-weight="bold" font-family="'JetBrains Mono', monospace">4.0</text>
                            <text x="0" y="6" text-anchor="middle" fill="#FFF" font-size="8.5" font-weight="bold">Leave Quotas &</text>
                            <text x="0" y="18" text-anchor="middle" fill="#FFF" font-size="8.5" font-weight="bold">Workflow Approval</text>
                        </g>

                        <!-- Process 5.0 Bubble -->
                        <g transform="translate(770, 520)">
                            <circle r="45" fill="#1E293B" stroke="#7E22CE" stroke-width="2"/>
                            <text x="0" y="-12" text-anchor="middle" fill="#C084FC" font-size="11" font-weight="bold" font-family="'JetBrains Mono', monospace">5.0</text>
                            <text x="0" y="6" text-anchor="middle" fill="#FFF" font-size="8.5" font-weight="bold">Cafeteria Menu &</text>
                            <text x="0" y="18" text-anchor="middle" fill="#FFF" font-size="8.5" font-weight="bold">Meal Booking</text>
                        </g>

                        <!-- Process 6.0 Bubble -->
                        <g transform="translate(470, 600)">
                            <circle r="45" fill="#1E293B" stroke="#6366F1" stroke-width="2"/>
                            <text x="0" y="-12" text-anchor="middle" fill="#818CF8" font-size="11" font-weight="bold" font-family="'JetBrains Mono', monospace">6.0</text>
                            <text x="0" y="6" text-anchor="middle" fill="#FFF" font-size="8.5" font-weight="bold">Audit Logging &</text>
                            <text x="0" y="18" text-anchor="middle" fill="#FFF" font-size="8.5" font-weight="bold">Live Telemetry</text>
                        </g>

                        <!-- DATA STORES (Open End Double Bar Cards) -->
                        <!-- D1 Multi-Tenancy Store -->
                        <g transform="translate(40, 65)">
                            <rect width="180" height="50" fill="#0B132B" stroke="#0284C7" stroke-width="1.5"/>
                            <line x1="40" y1="0" x2="40" y2="50" stroke="#0284C7" stroke-width="1.5"/>
                            <text x="15" y="30" fill="#38BDF8" font-size="10" font-weight="bold" font-family="'JetBrains Mono', monospace">D1</text>
                            <text x="50" y="24" fill="#FFF" font-size="9" font-weight="bold">Multi-Tenancy Store</text>
                            <text x="50" y="38" fill="#94A3B8" font-size="7.5">companies, orgs, plans</text>
                        </g>

                        <!-- D2 RBAC Matrix -->
                        <g transform="translate(600, 65)">
                            <rect width="180" height="50" fill="#0B132B" stroke="#6366F1" stroke-width="1.5"/>
                            <line x1="40" y1="0" x2="40" y2="50" stroke="#6366F1" stroke-width="1.5"/>
                            <text x="15" y="30" fill="#818CF8" font-size="10" font-weight="bold" font-family="'JetBrains Mono', monospace">D2</text>
                            <text x="50" y="24" fill="#FFF" font-size="9" font-weight="bold">RBAC Permissions Matrix</text>
                            <text x="50" y="38" fill="#94A3B8" font-size="7.5">roles, perms, hierarchy</text>
                        </g>

                        <!-- D3 Employee 360 Store -->
                        <g transform="translate(260, 265)">
                            <rect width="180" height="50" fill="#0B132B" stroke="#059669" stroke-width="1.5"/>
                            <line x1="40" y1="0" x2="40" y2="50" stroke="#059669" stroke-width="1.5"/>
                            <text x="15" y="30" fill="#34D399" font-size="10" font-weight="bold" font-family="'JetBrains Mono', monospace">D3</text>
                            <text x="50" y="24" fill="#FFF" font-size="9" font-weight="bold">Employee 360 Store</text>
                            <text x="50" y="38" fill="#94A3B8" font-size="7.5">employees, family, docs</text>
                        </g>

                        <!-- D4 Attendance & ADMS Store -->
                        <g transform="translate(500, 265)">
                            <rect width="180" height="50" fill="#0B132B" stroke="#D97706" stroke-width="1.5"/>
                            <line x1="40" y1="0" x2="40" y2="50" stroke="#D97706" stroke-width="1.5"/>
                            <text x="15" y="30" fill="#FBBF24" font-size="10" font-weight="bold" font-family="'JetBrains Mono', monospace">D4</text>
                            <text x="50" y="24" fill="#FFF" font-size="9" font-weight="bold">ADMS Attendance Store</text>
                            <text x="50" y="38" fill="#94A3B8" font-size="7.5">devices, raw_punches, shifts</text>
                        </g>

                        <!-- D5 Leave & Holiday Store -->
                        <g transform="translate(370, 495)">
                            <rect width="180" height="50" fill="#0B132B" stroke="#E11D48" stroke-width="1.5"/>
                            <line x1="40" y1="0" x2="40" y2="50" stroke="#E11D48" stroke-width="1.5"/>
                            <text x="15" y="30" fill="#FB7185" font-size="10" font-weight="bold" font-family="'JetBrains Mono', monospace">D5</text>
                            <text x="50" y="24" fill="#FFF" font-size="9" font-weight="bold">Leave & Holiday Store</text>
                            <text x="50" y="38" fill="#94A3B8" font-size="7.5">balances, applications, days</text>
                        </g>

                        <!-- D6 Cafeteria Store -->
                        <g transform="translate(580, 495)">
                            <rect width="180" height="50" fill="#0B132B" stroke="#7E22CE" stroke-width="1.5"/>
                            <line x1="40" y1="0" x2="40" y2="50" stroke="#7E22CE" stroke-width="1.5"/>
                            <text x="15" y="30" fill="#C084FC" font-size="10" font-weight="bold" font-family="'JetBrains Mono', monospace">D6</text>
                            <text x="50" y="24" fill="#FFF" font-size="9" font-weight="bold">Cafeteria Meals Store</text>
                            <text x="50" y="38" fill="#94A3B8" font-size="7.5">weekly_meals, selections</text>
                        </g>

                        <!-- D7 Security & Audit Store -->
                        <g transform="translate(370, 200)">
                            <rect width="180" height="50" fill="#0B132B" stroke="#6366F1" stroke-width="1.5"/>
                            <line x1="40" y1="0" x2="40" y2="50" stroke="#6366F1" stroke-width="1.5"/>
                            <text x="15" y="30" fill="#818CF8" font-size="10" font-weight="bold" font-family="'JetBrains Mono', monospace">D7</text>
                            <text x="50" y="24" fill="#FFF" font-size="9" font-weight="bold">Security & Audit Store</text>
                            <text x="50" y="38" fill="#94A3B8" font-size="7.5">users, audit_logs, logins</text>
                        </g>
                    </svg>
                </div>

                <!-- ========================================================
                     DFD PROCESS & DATA STORE DICTIONARY
                     ======================================================== -->
                <div id="dfdDictionarySection">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem;">
                        <div style="font-size: 1.05rem; font-weight: 700; color: #fff; display: flex; align-items: center; gap: 8px;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--accent-emerald);"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg>
                            Process Specification & Data Flow Dictionary
                        </div>
                    </div>

                    <div class="dfd-process-grid">
                        
                        <!-- P1.0 -->
                        <div class="dfd-proc-card">
                            <div style="display: flex; align-items: center; margin-bottom: 8px;">
                                <span class="dfd-proc-num">1.0</span>
                                <span style="font-weight: 700; color: var(--accent-cyan); font-size: 0.9rem;">Tenant Auth & RBAC Check</span>
                            </div>
                            <div style="font-size: 0.78rem; color: var(--text-secondary); margin-bottom: 10px;">
                                Intercepts inbound API requests, resolves tenant from <code>X-ORG-ID</code> header, authenticates JWT bearer token, and enforces closure-tree role permissions.
                            </div>
                            <div style="font-size: 0.72rem; font-family: var(--font-mono); color: var(--text-muted);">
                                <strong>Stores:</strong> D1 (Multi-Tenancy), D2 (RBAC), D7 (Users)
                            </div>
                        </div>

                        <!-- P2.0 -->
                        <div class="dfd-proc-card">
                            <div style="display: flex; align-items: center; margin-bottom: 8px;">
                                <span class="dfd-proc-num">2.0</span>
                                <span style="font-weight: 700; color: var(--accent-emerald); font-size: 0.9rem;">HRM Employee 360 Lifecycle</span>
                            </div>
                            <div style="font-size: 0.78rem; color: var(--text-secondary); margin-bottom: 10px;">
                                Manages core employee biographical profiles, reporting manager hierarchy, department lookups, and 6 nested child profiles (education, family, experience, documents).
                            </div>
                            <div style="font-size: 0.72rem; font-family: var(--font-mono); color: var(--text-muted);">
                                <strong>Stores:</strong> D3 (Employee 360 Store)
                            </div>
                        </div>

                        <!-- P3.0 -->
                        <div class="dfd-proc-card">
                            <div style="display: flex; align-items: center; margin-bottom: 8px;">
                                <span class="dfd-proc-num">3.0</span>
                                <span style="font-weight: 700; color: var(--accent-amber); font-size: 0.9rem;">ADMS Ingestion & Shift Eval</span>
                            </div>
                            <div style="font-size: 0.78rem; color: var(--text-secondary); margin-bottom: 10px;">
                                Receives realtime HTTP push packets from ZKTeco biometric terminals, decodes biometric punches, matches scheduled shift rosters & computes daily in/out/overtime.
                            </div>
                            <div style="font-size: 0.72rem; font-family: var(--font-mono); color: var(--text-muted);">
                                <strong>Stores:</strong> D4 (Biometrics & Attendance Store)
                            </div>
                        </div>

                        <!-- P4.0 -->
                        <div class="dfd-proc-card">
                            <div style="display: flex; align-items: center; margin-bottom: 8px;">
                                <span class="dfd-proc-num">4.0</span>
                                <span style="font-weight: 700; color: var(--accent-rose); font-size: 0.9rem;">Leave Quotas & Workflow</span>
                            </div>
                            <div style="font-size: 0.78rem; color: var(--text-secondary); margin-bottom: 10px;">
                                Calculates yearly allocated vs consumed quotas per leave type, validates balance sufficiency, handles multi-level manager approvals & adjusts holiday calendars.
                            </div>
                            <div style="font-size: 0.72rem; font-family: var(--font-mono); color: var(--text-muted);">
                                <strong>Stores:</strong> D5 (Leave & Holiday Store)
                            </div>
                        </div>

                        <!-- P5.0 -->
                        <div class="dfd-proc-card">
                            <div style="display: flex; align-items: center; margin-bottom: 8px;">
                                <span class="dfd-proc-num">5.0</span>
                                <span style="font-weight: 700; color: var(--accent-purple); font-size: 0.9rem;">Cafeteria Menu & Meal Booking</span>
                            </div>
                            <div style="font-size: 0.78rem; color: var(--text-secondary); margin-bottom: 10px;">
                                Allows cafeteria chefs to configure weekly menus with cutoff times, enables staff to reserve lunch tokens, and handles barcode scanning at service counters.
                            </div>
                            <div style="font-size: 0.72rem; font-family: var(--font-mono); color: var(--text-muted);">
                                <strong>Stores:</strong> D6 (Cafeteria Meals Store)
                            </div>
                        </div>

                        <!-- P6.0 -->
                        <div class="dfd-proc-card">
                            <div style="display: flex; align-items: center; margin-bottom: 8px;">
                                <span class="dfd-proc-num">6.0</span>
                                <span style="font-weight: 700; color: var(--accent-indigo); font-size: 0.9rem;">Security Auditing & Telemetry</span>
                            </div>
                            <div style="font-size: 0.78rem; color: var(--text-secondary); margin-bottom: 10px;">
                                Logs user authentication history, captures model CRUD mutations with old vs new JSON snapshots, and monitors gateway latency & endpoint error rates.
                            </div>
                            <div style="font-size: 0.72rem; font-family: var(--font-mono); color: var(--text-muted);">
                                <strong>Stores:</strong> D7 (Security & Audit Store)
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

        // --- 7. Tab Switcher: Gateway Telemetry vs Project Info vs DFD ---
        function switchMainTab(tab) {
            const telemetryView = document.getElementById('telemetryView');
            const projectInfoView = document.getElementById('projectInfoView');
            const dfdView = document.getElementById('dfdView');
            
            const navTelemetry = document.getElementById('navTelemetry');
            const navProjectInfo = document.getElementById('navProjectInfo');
            const navDfd = document.getElementById('navDfd');

            // Hide all
            if (telemetryView) telemetryView.style.display = 'none';
            if (projectInfoView) projectInfoView.style.display = 'none';
            if (dfdView) dfdView.style.display = 'none';

            if (navTelemetry) navTelemetry.classList.remove('active');
            if (navProjectInfo) navProjectInfo.classList.remove('active');
            if (navDfd) navDfd.classList.remove('active');

            if (tab === 'project-info') {
                if (projectInfoView) projectInfoView.style.display = 'block';
                if (navProjectInfo) navProjectInfo.classList.add('active');
            } else if (tab === 'dfd') {
                if (dfdView) dfdView.style.display = 'block';
                if (navDfd) navDfd.classList.add('active');
            } else {
                if (telemetryView) telemetryView.style.display = 'block';
                if (navTelemetry) navTelemetry.classList.add('active');
            }
        }

        // --- 8. DFD Level Switcher ---
        function switchDfdLevel(level) {
            const canvasL0 = document.getElementById('dfdCanvasL0');
            const canvasL1 = document.getElementById('dfdCanvasL1');
            const dictSection = document.getElementById('dfdDictionarySection');
            
            const btnL0 = document.getElementById('dfdTabL0');
            const btnL1 = document.getElementById('dfdTabL1');
            const btnDict = document.getElementById('dfdTabDict');

            [btnL0, btnL1, btnDict].forEach(btn => { if(btn) btn.classList.remove('active'); });

            if (level === 'l1') {
                if (canvasL0) canvasL0.style.display = 'none';
                if (canvasL1) canvasL1.style.display = 'block';
                if (dictSection) dictSection.style.display = 'block';
                if (btnL1) btnL1.classList.add('active');
            } else if (level === 'dict') {
                if (canvasL0) canvasL0.style.display = 'none';
                if (canvasL1) canvasL1.style.display = 'none';
                if (dictSection) dictSection.style.display = 'block';
                if (btnDict) btnDict.classList.add('active');
                dictSection.scrollIntoView({ behavior: 'smooth' });
            } else {
                if (canvasL0) canvasL0.style.display = 'block';
                if (canvasL1) canvasL1.style.display = 'none';
                if (dictSection) dictSection.style.display = 'block';
                if (btnL0) btnL0.classList.add('active');
            }
        }

        // --- 8. Copy to Clipboard with Toast Notification ---
        function copyToClipboard(elementId) {
            const inputEl = document.getElementById(elementId);
            if (!inputEl) return;
            
            inputEl.select();
            inputEl.setSelectionRange(0, 99999);
            navigator.clipboard.writeText(inputEl.value).then(() => {
                showToast('Copied: ' + inputEl.value);
            }).catch(() => {
                showToast('Link copied!');
            });
        }

        function showToast(message) {
            let toast = document.getElementById('appToast');
            if (!toast) {
                toast = document.createElement('div');
                toast.id = 'appToast';
                toast.className = 'toast-notification';
                document.body.appendChild(toast);
            }
            toast.innerHTML = '<span>✅</span> ' + message;
            toast.classList.add('show');
            setTimeout(() => {
                toast.classList.remove('show');
            }, 2500);
        }

        // Wire dynamic links to open buttons
        ['uiux', 'qa', 'uat'].forEach(prefix => {
            const input = document.getElementById(prefix + 'LinkInput');
            const btn = document.getElementById(prefix + 'OpenBtn');
            if (input && btn) {
                input.addEventListener('input', () => {
                    btn.href = input.value;
                });
            }
        });
    </script>
</body>
</html>
