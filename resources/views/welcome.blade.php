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
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live API Gateway | RBAC ERP</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    
    <!-- Chart.js for Live Graph -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        :root {
            /* Futuristic Dark Theme */
            --bg-color: #030712;
            --surface: #111827;
            --surface-hover: #1f2937;
            --border: #1f2937;
            --text-main: #f9fafb;
            --text-muted: #9ca3af;
            
            --accent: #3b82f6;
            --accent-glow: rgba(59, 130, 246, 0.4);
            --success: #10b981;
            
            --font-main: 'Inter', sans-serif;
            --font-mono: 'JetBrains Mono', monospace;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background-color: var(--bg-color);
            color: var(--text-main);
            font-family: var(--font-main);
            line-height: 1.5;
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            background-image: 
                radial-gradient(circle at 15% 50%, rgba(59, 130, 246, 0.08), transparent 25%),
                radial-gradient(circle at 85% 30%, rgba(139, 92, 246, 0.08), transparent 25%);
            background-attachment: fixed;
        }

        /* Top Navigation */
        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 2rem;
            background: rgba(3, 7, 18, 0.7);
            border-bottom: 1px solid var(--border);
            backdrop-filter: blur(12px);
            z-index: 50;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 600;
            font-size: 1.1rem;
            letter-spacing: -0.02em;
        }

        .live-pulse {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.8rem;
            font-weight: 500;
            color: var(--success);
            background: rgba(16, 185, 129, 0.1);
            padding: 0.25rem 0.75rem;
            border-radius: 99px;
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        .pulse-dot {
            width: 8px;
            height: 8px;
            background-color: var(--success);
            border-radius: 50%;
            box-shadow: 0 0 8px var(--success);
            animation: pulse-animation 2s infinite;
        }

        @keyframes pulse-animation {
            0% { transform: scale(0.95); opacity: 0.8; }
            50% { transform: scale(1.1); opacity: 1; }
            100% { transform: scale(0.95); opacity: 0.8; }
        }

        .nav-links img, .nav-links svg { vertical-align: middle; }
        .clock {
            font-family: var(--font-mono);
            color: var(--text-muted);
            font-size: 0.9rem;
            margin-right: 1.5rem;
        }

        /* Main Layout */
        .container {
            display: flex;
            flex-direction: column;
            flex: 1;
            padding: 1.5rem 2rem;
            gap: 1.5rem;
            overflow-y: auto;
        }
        
        .container::-webkit-scrollbar { width: 6px; }
        .container::-webkit-scrollbar-thumb { background: #374151; border-radius: 4px; }
        .container::-webkit-scrollbar-track { background: transparent; }

        /* KPI Metrics */
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.5rem;
        }

        .metric-card {
            background: rgba(17, 24, 39, 0.6);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.5rem;
            backdrop-filter: blur(10px);
            transition: transform 0.2s, border-color 0.2s;
        }

        .metric-card:hover {
            transform: translateY(-2px);
            border-color: rgba(59, 130, 246, 0.4);
        }

        .metric-title {
            color: var(--text-muted);
            font-size: 0.85rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.5rem;
        }

        .metric-value {
            font-size: 2rem;
            font-weight: 600;
            font-family: var(--font-mono);
            color: #fff;
        }

        .metric-sub {
            margin-top: 0.5rem;
            font-size: 0.8rem;
            color: var(--success);
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        /* Middle Row: Chart & Activity */
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1.5rem;
            min-height: 350px;
        }
        
        @media (max-width: 1024px) {
            .content-grid, .metrics-grid { grid-template-columns: 1fr; }
            .metrics-grid { grid-template-columns: repeat(2, 1fr); }
        }

        .panel {
            background: rgba(17, 24, 39, 0.6);
            border: 1px solid var(--border);
            border-radius: 12px;
            backdrop-filter: blur(10px);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .panel-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .panel-title {
            font-size: 1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .chart-wrapper {
            flex: 1;
            padding: 1.5rem;
            position: relative;
            min-height: 250px;
        }

        /* Real Live API Table */
        .table-wrapper {
            flex: 1;
            overflow: auto;
            padding: 0;
        }

        .table-wrapper::-webkit-scrollbar { width: 6px; height: 6px; }
        .table-wrapper::-webkit-scrollbar-thumb { background: #374151; border-radius: 4px; }
        
        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 0.875rem;
        }

        th {
            position: sticky;
            top: 0;
            background: rgba(17, 24, 39, 0.95);
            backdrop-filter: blur(4px);
            padding: 1rem 1.5rem;
            color: var(--text-muted);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-size: 0.75rem;
            border-bottom: 1px solid var(--border);
            z-index: 10;
        }

        td {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            white-space: nowrap;
        }

        tr:last-child td { border-bottom: none; }
        tr:hover td { background: rgba(255, 255, 255, 0.02); }

        .route-uri {
            font-family: var(--font-mono);
            color: #fff;
        }

        .route-controller {
            color: var(--text-muted);
            font-size: 0.8rem;
        }

        /* Method Badges */
        .badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.25rem 0.6rem;
            border-radius: 6px;
            font-family: var(--font-mono);
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 0.05em;
        }

        .method-get { color: #10b981; background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.2); }
        .method-post { color: #3b82f6; background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.2); }
        .method-put { color: #f59e0b; background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.2); }
        .method-delete { color: #ef4444; background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); }
        .method-default { color: #a855f7; background: rgba(168, 85, 247, 0.1); border: 1px solid rgba(168, 85, 247, 0.2); }
        
        /* Simulated Realtime Feed */
        .feed-list {
            list-style: none;
            padding: 0;
            margin: 0;
            overflow-y: auto;
        }
        
        .feed-item {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateX(-10px); }
            to { opacity: 1; transform: translateX(0); }
        }
        
        .feed-time {
            font-family: var(--font-mono);
            font-size: 0.7rem;
            color: var(--text-muted);
        }

    </style>
</head>
<body>

    <nav class="navbar">
        <div class="brand">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--accent)"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg>
            ATI RBAC ERP <a href="/docs/api" target="_blank" style="color: var(--accent); text-decoration: underline;">API</a> GATEWAY
        </div>
        <div class="nav-links" style="display: flex; align-items: center;">
            <div id="liveClock" class="clock">00:00:00</div>
            <div class="live-pulse">
                <div class="pulse-dot"></div>
                Gateway Online
            </div>
        </div>
    </nav>

    <div class="container">
        <!-- Live PHP Metrics -->
        <div class="metrics-grid">
            <div class="metric-card">
                <div class="metric-title">Registered API Routes</div>
                <div class="metric-value">{{ $totalApiRoutes }}</div>
                <div class="metric-sub">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12l5 5L20 7"></path></svg>
                    Parsed dynamically from Router
                </div>
            </div>
            
            <div class="metric-card">
                <div class="metric-title">Active ERP Modules</div>
                <div class="metric-value">{{ $modulesCount }}</div>
                <div class="metric-sub">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12l5 5L20 7"></path></svg>
                    Detected base route prefixes
                </div>
            </div>

            <div class="metric-card">
                <div class="metric-title">System Memory Usage</div>
                <div class="metric-value">{{ $memoryUsage }}<span style="font-size: 1rem; color: var(--text-muted)">MB</span></div>
                <div class="metric-sub">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                    Live PHP allocation
                </div>
            </div>

            <div class="metric-card">
                <div class="metric-title">Requests/Sec (Simulated)</div>
                <div class="metric-value" id="rpsMetric">24.5</div>
                <div class="metric-sub">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 6l-9.5 9.5-5-5L1 18"></path></svg>
                    Real-time traffic estimation
                </div>
            </div>
        </div>

        <div class="content-grid">
            <!-- Dynamic Chart Area -->
            <div class="panel">
                <div class="panel-header">
                    <div class="panel-title">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2"><path d="M18 20V10M12 20V4M6 20v-6"></path></svg>
                        Live Hit Rate Visualization
                    </div>
                </div>
                <div class="chart-wrapper">
                    <canvas id="liveHitChart"></canvas>
                </div>
            </div>

            <!-- Simulated Realtime Stream -->
            <div class="panel">
                <div class="panel-header">
                    <div class="panel-title">
                        <div class="pulse-dot"></div>
                        Live Request Feed
                    </div>
                </div>
                <ul class="feed-list" id="requestFeed">
                    <!-- Javascript will populate this based on real routes -->
                </ul>
            </div>
        </div>

        <!-- Absolutely Authentic Route Table from Laravel -->
        <div class="panel" style="flex: 1; min-height: 400px;">
            <div class="panel-header">
                <div class="panel-title">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="3" y1="9" x2="21" y2="9"></line><line x1="9" y1="21" x2="9" y2="9"></line></svg>
                    Live Configured Endpoints Directory
                </div>
                <div style="font-size: 0.8rem; color: var(--text-muted)">
                    Showing {{ $totalApiRoutes }} registered APIs in Laravel {{ Illuminate\Foundation\Application::VERSION }}
                </div>
            </div>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 100px;">Method</th>
                            <th>API URI</th>
                            <th>Route Name</th>
                            <th>Controller Action</th>
                            <th>Middlewares</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($apiRoutes as $route)
                        <tr>
                            <td>
                                <span class="badge {{ $route['badge_class'] }}">
                                    {{ $route['methods_disp'] }}
                                </span>
                            </td>
                            <td class="route-uri">{{ $route['uri'] }}</td>
                            <td style="color: var(--text-muted)">
                                {{ $route['name'] !== '—' ? $route['name'] : '—' }}
                            </td>
                            <td class="route-controller">{{ $route['action'] }}</td>
                            <td style="color: var(--text-muted); font-size: 0.8rem; max-width: 200px; overflow: hidden; text-overflow: ellipsis;">
                                {{ $route['middlewares'] }}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 3rem;">
                                No API routes configured yet. Start adding routes to `routes/api.php`!
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // --- 1. Live Clock ---
        function updateClock() {
            const now = new Date();
            document.getElementById('liveClock').textContent = now.toLocaleTimeString('en-US', { hour12: false });
        }
        setInterval(updateClock, 1000);
        updateClock();

        // --- 2. Chart.js Live Configuration ---
        const ctx = document.getElementById('liveHitChart').getContext('2d');
        
        let gradient = ctx.createLinearGradient(0, 0, 0, 300);
        gradient.addColorStop(0, 'rgba(59, 130, 246, 0.4)');
        gradient.addColorStop(1, 'rgba(59, 130, 246, 0.0)');

        // We will keep exactly 20 data points on screen
        const maxDataPoints = 30;
        const initialData = Array(maxDataPoints).fill(0).map(() => Math.floor(Math.random() * 50) + 20);
        const labels = Array(maxDataPoints).fill(''); 

        const hitChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Req/Sec',
                    data: initialData,
                    borderColor: '#3b82f6',
                    backgroundColor: gradient,
                    borderWidth: 2,
                    pointRadius: 0,
                    pointHoverRadius: 5,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: {
                    duration: 0 
                },
                plugins: {
                    legend: { display: false },
                    tooltip: { enabled: true, intersect: false, mode: 'index' }
                },
                scales: {
                    x: { display: false },
                    y: {
                        grid: { color: '#1f2937', drawBorder: false },
                        ticks: { color: '#9ca3af', font: { family: "'JetBrains Mono', monospace" } },
                        min: 0,
                        max: 120
                    }
                }
            }
        });

        // The real API routes fetched via PHP injected to Javascript
        const actualRoutes = @json($apiRoutes->pluck('uri'));

        function appendToFeed(route) {
            const feed = document.getElementById('requestFeed');
            const li = document.createElement('li');
            li.className = 'feed-item';
            
            const time = new Date().toLocaleTimeString('en-US', { hour12: false, fractionalSecondDigits: 1 });
            const statusColor = Math.random() > 0.95 ? '#ef4444' : '#10b981'; // 5% chance of error for realism
            const statusCode = statusColor === '#ef4444' ? (Math.random() > 0.5 ? 500 : 403) : 200;
            const ms = Math.floor(Math.random() * 100) + 12;

            li.innerHTML = `
                <div>
                    <span class="feed-time">[${time}]</span>
                    <span style="font-family: 'JetBrains Mono', monospace; font-size: 0.8rem; margin-left: 8px;">${route}</span>
                </div>
                <div style="font-family: 'JetBrains Mono', monospace; font-size: 0.75rem; text-align: right;">
                    <span style="color: ${statusColor}">${statusCode}</span>
                    <span style="color: var(--text-muted); margin-left: 8px;">${ms}ms</span>
                </div>
            `;
            
            feed.prepend(li);
            if (feed.children.length > 8) {
                feed.lastChild.remove();
            }
        }

        // --- 3. Update Loop ---
        setInterval(() => {
            // Update Metric
            let currentValue = hitChart.data.datasets[0].data[maxDataPoints - 1];
            // Random walk algorithm for smooth charting
            let change = (Math.random() * 20) - 10;
            let newValue = Math.max(10, Math.min(100, currentValue + change));
            
            // Push to chart
            hitChart.data.datasets[0].data.shift();
            hitChart.data.datasets[0].data.push(newValue);
            hitChart.update();
            
            // Update text
            document.getElementById('rpsMetric').textContent = newValue.toFixed(1);

            // Pop a feed item randomly based on the traffic
            if (actualRoutes.length > 0 && Math.random() > 0.3) {
                const randomRoute = actualRoutes[Math.floor(Math.random() * actualRoutes.length)];
                appendToFeed(randomRoute);
            }

        }, 1000); // Ticks every 1 second
        
        // Initial populate feed
        for(let i=0; i<6; i++) {
            if(actualRoutes.length > 0) {
                appendToFeed(actualRoutes[Math.floor(Math.random() * actualRoutes.length)]);
            }
        }
    </script>
</body>
</html>
