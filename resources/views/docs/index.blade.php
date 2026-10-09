<!DOCTYPE html>
<html lang="id" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PPOB Engine — Developer Portal & API Specification</title>
    <meta name="description" content="Spesifikasi API Lengkap & Interactive Developer Portal PPOB Backend (OpenAPI 3.1 & Swagger Explorer)">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Swagger UI CSS -->
    <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5.18.2/swagger-ui.css">

    <style>
        :root {
            --bg-base: #0a0e17;
            --bg-surface: #111827;
            --bg-surface-elevated: #1f2937;
            --bg-card: rgba(17, 24, 39, 0.75);
            --border-subtle: rgba(255, 255, 255, 0.08);
            --border-focus: rgba(99, 102, 241, 0.5);
            --primary: #6366f1;
            --primary-hover: #4f46e5;
            --primary-glow: rgba(99, 102, 241, 0.25);
            --accent-cyan: #06b6d4;
            --accent-emerald: #10b981;
            --accent-amber: #f59e0b;
            --accent-rose: #f43f5e;
            --text-main: #f9fafb;
            --text-muted: #9ca3af;
            --text-dim: #6b7280;
            --code-bg: #0d1117;
            --font-sans: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            --font-mono: 'JetBrains Mono', monospace;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background-color: var(--bg-base);
            color: var(--text-main);
            font-family: var(--font-sans);
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
            background-image: 
                radial-gradient(circle at 15% 10%, rgba(99, 102, 241, 0.12) 0%, transparent 40%),
                radial-gradient(circle at 85% 20%, rgba(6, 182, 212, 0.08) 0%, transparent 40%),
                radial-gradient(circle at 50% 90%, rgba(139, 92, 246, 0.08) 0%, transparent 50%);
        }

        /* Top Header */
        header.site-header {
            position: sticky;
            top: 0;
            z-index: 50;
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            background: rgba(10, 14, 23, 0.85);
            border-bottom: 1px solid var(--border-subtle);
            padding: 0.75rem 1.5rem;
        }

        .header-inner {
            max-width: 1500px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1.5rem;
            flex-wrap: wrap;
        }

        .brand-section {
            display: flex;
            align-items: center;
            gap: 0.875rem;
        }

        .brand-logo {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background: linear-gradient(135deg, #6366f1 0%, #06b6d4 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 0 16px var(--primary-glow);
            color: #fff;
            font-size: 1.25rem;
            font-weight: 800;
        }

        .brand-text h1 {
            font-size: 1.125rem;
            font-weight: 700;
            letter-spacing: -0.02em;
            background: linear-gradient(90deg, #ffffff, #cbd5e1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .brand-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            font-size: 0.725rem;
            font-weight: 600;
            padding: 0.15rem 0.5rem;
            border-radius: 9999px;
            background: rgba(99, 102, 241, 0.15);
            color: #a5b4fc;
            border: 1px solid rgba(99, 102, 241, 0.3);
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .nav-tabs {
            display: flex;
            gap: 0.5rem;
            background: rgba(31, 41, 55, 0.6);
            padding: 0.25rem;
            border-radius: 10px;
            border: 1px solid var(--border-subtle);
        }

        .tab-btn {
            background: transparent;
            border: none;
            color: var(--text-muted);
            font-family: var(--font-sans);
            font-size: 0.875rem;
            font-weight: 600;
            padding: 0.45rem 1rem;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 0.45rem;
        }

        .tab-btn:hover {
            color: var(--text-main);
            background: rgba(255, 255, 255, 0.05);
        }

        .tab-btn.active {
            background: var(--primary);
            color: #ffffff;
            box-shadow: 0 0 12px var(--primary-glow);
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            font-size: 0.825rem;
            font-weight: 600;
            padding: 0.5rem 0.875rem;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .btn-action-primary {
            background: linear-gradient(135deg, var(--primary) 0%, #4f46e5 100%);
            color: #ffffff;
            border: 1px solid rgba(255, 255, 255, 0.15);
            box-shadow: 0 2px 8px var(--primary-glow);
        }

        .btn-action-primary:hover {
            opacity: 0.95;
            transform: translateY(-1px);
        }

        .btn-action-secondary {
            background: rgba(255, 255, 255, 0.06);
            color: var(--text-main);
            border: 1px solid var(--border-subtle);
        }

        .btn-action-secondary:hover {
            background: rgba(255, 255, 255, 0.12);
            border-color: rgba(255, 255, 255, 0.2);
        }

        /* Banner Notification */
        .spec-banner {
            background: linear-gradient(90deg, rgba(99, 102, 241, 0.1), rgba(6, 182, 212, 0.1));
            border-bottom: 1px solid var(--border-subtle);
            padding: 0.5rem 1.5rem;
            font-size: 0.8125rem;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .spec-banner-inner {
            max-width: 1500px;
            margin: 0 auto;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .spec-url-box {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(0, 0, 0, 0.35);
            padding: 0.2rem 0.6rem;
            border-radius: 6px;
            border: 1px solid var(--border-subtle);
            font-family: var(--font-mono);
            font-size: 0.775rem;
            color: var(--accent-cyan);
        }

        /* Main Content */
        main.main-content {
            flex: 1;
            max-width: 1500px;
            width: 100%;
            margin: 0 auto;
            padding: 1.5rem;
        }

        .view-panel {
            display: none;
        }

        .view-panel.active {
            display: block;
            animation: fadeIn 0.25s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(6px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Swagger UI Overrides */
        #swagger-ui-container {
            background: var(--bg-surface);
            border-radius: 14px;
            border: 1px solid var(--border-subtle);
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
            padding: 1.5rem;
        }

        /* Modern Dark Theme for Swagger UI */
        .swagger-ui {
            color: var(--text-main);
            font-family: var(--font-sans);
        }

        .swagger-ui .info {
            margin: 1.5rem 0 2rem 0;
        }

        .swagger-ui .info .title {
            color: #ffffff;
            font-weight: 800;
            font-family: var(--font-sans);
            letter-spacing: -0.02em;
        }

        .swagger-ui .info p,
        .swagger-ui .info li,
        .swagger-ui .info table {
            color: var(--text-muted);
            font-size: 0.925rem;
        }

        .swagger-ui .scheme-container {
            background: rgba(31, 41, 55, 0.5) !important;
            box-shadow: none !important;
            border-radius: 10px;
            border: 1px solid var(--border-subtle);
            padding: 1rem !important;
            margin-bottom: 2rem;
        }

        .swagger-ui .filter .operation-filter-input {
            background: #0d1117 !important;
            color: #f9fafb !important;
            border: 1px solid var(--border-subtle) !important;
            border-radius: 8px !important;
            padding: 0.6rem 1rem !important;
        }

        .swagger-ui .btn.authorize {
            background: linear-gradient(135deg, var(--accent-emerald), #059669) !important;
            color: #ffffff !important;
            border-color: transparent !important;
            border-radius: 8px !important;
            font-weight: 700;
        }

        .swagger-ui .btn.authorize svg {
            fill: #ffffff !important;
        }

        .swagger-ui .opblock-tag {
            color: #f3f4f6 !important;
            border-bottom: 1px solid var(--border-subtle) !important;
            font-weight: 700;
            font-size: 1.15rem;
            padding: 1rem 0;
        }

        .swagger-ui .opblock-tag small {
            color: var(--text-muted) !important;
        }

        .swagger-ui .opblock {
            border-radius: 10px !important;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2) !important;
            margin: 0 0 1rem 0 !important;
            border: 1px solid var(--border-subtle) !important;
            background: rgba(31, 41, 55, 0.4) !important;
        }

        .swagger-ui .opblock .opblock-summary {
            padding: 0.65rem 1rem !important;
        }

        .swagger-ui .opblock .opblock-summary-method {
            border-radius: 6px !important;
            font-weight: 700 !important;
            font-family: var(--font-mono) !important;
            min-width: 70px;
            text-align: center;
        }

        .swagger-ui .opblock .opblock-summary-path,
        .swagger-ui .opblock .opblock-summary-path__deprecated {
            color: #f9fafb !important;
            font-family: var(--font-mono) !important;
            font-size: 0.9rem !important;
        }

        .swagger-ui .opblock .opblock-summary-description {
            color: var(--text-muted) !important;
            font-size: 0.85rem !important;
        }

        .swagger-ui .opblock-body {
            background: #0f172a !important;
            border-top: 1px solid var(--border-subtle);
        }

        .swagger-ui table thead tr td,
        .swagger-ui table thead tr th {
            color: var(--text-muted) !important;
            border-bottom: 1px solid var(--border-subtle) !important;
        }

        .swagger-ui .tab li button.tablinks {
            color: var(--text-muted) !important;
        }

        .swagger-ui .tab li button.tablinks.active {
            color: #ffffff !important;
        }

        .swagger-ui .response-col_status {
            color: #ffffff !important;
            font-weight: 700;
        }

        .swagger-ui .model-box,
        .swagger-ui section.models {
            background: rgba(31, 41, 55, 0.4) !important;
            border: 1px solid var(--border-subtle) !important;
            border-radius: 10px !important;
        }

        .swagger-ui section.models .model-container {
            background: transparent !important;
        }

        .swagger-ui .model-title {
            color: #f9fafb !important;
        }

        .swagger-ui .prop-type {
            color: var(--accent-cyan) !important;
        }

        .swagger-ui .prop-format {
            color: var(--text-dim) !important;
        }

        .swagger-ui input[type="text"],
        .swagger-ui textarea {
            background: #090d16 !important;
            color: #f9fafb !important;
            border: 1px solid var(--border-subtle) !important;
            border-radius: 6px !important;
        }

        .swagger-ui .btn.execute {
            background: linear-gradient(135deg, var(--primary) 0%, #4338ca 100%) !important;
            color: #ffffff !important;
            border-color: transparent !important;
            border-radius: 6px !important;
            font-weight: 700;
        }

        .swagger-ui .btn.cancel {
            background: rgba(239, 68, 68, 0.2) !important;
            color: #f87171 !important;
            border-color: rgba(239, 68, 68, 0.3) !important;
            border-radius: 6px !important;
        }

        /* Documentation & Specs Tab Styling */
        .docs-grid {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 2rem;
            align-items: start;
        }

        @media (max-width: 1024px) {
            .docs-grid {
                grid-template-columns: 1fr;
            }
        }

        .docs-sidebar {
            background: var(--bg-surface);
            border: 1px solid var(--border-subtle);
            border-radius: 12px;
            padding: 1.25rem;
            position: sticky;
            top: 5rem;
        }

        .docs-sidebar h3 {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--text-dim);
            margin-bottom: 0.75rem;
        }

        .docs-nav-link {
            display: block;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.875rem;
            padding: 0.45rem 0.75rem;
            border-radius: 6px;
            transition: all 0.15s ease;
            margin-bottom: 0.25rem;
        }

        .docs-nav-link:hover {
            color: var(--text-main);
            background: rgba(255, 255, 255, 0.05);
        }

        .docs-nav-link.active {
            color: #ffffff;
            background: var(--primary);
            font-weight: 600;
        }

        .docs-content {
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }

        .doc-section {
            background: var(--bg-surface);
            border: 1px solid var(--border-subtle);
            border-radius: 14px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.25);
        }

        .doc-section h2 {
            font-size: 1.5rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            margin-bottom: 0.5rem;
            color: #ffffff;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .doc-section p {
            color: var(--text-muted);
            font-size: 0.95rem;
            margin-bottom: 1.25rem;
        }

        .badge-tag {
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.2rem 0.5rem;
            border-radius: 6px;
            background: rgba(99, 102, 241, 0.15);
            color: #a5b4fc;
            border: 1px solid rgba(99, 102, 241, 0.25);
        }

        /* Code Blocks */
        .code-container {
            background: var(--code-bg);
            border: 1px solid var(--border-subtle);
            border-radius: 10px;
            overflow: hidden;
            margin: 1rem 0;
            font-family: var(--font-mono);
        }

        .code-header {
            background: rgba(255, 255, 255, 0.03);
            border-bottom: 1px solid var(--border-subtle);
            padding: 0.5rem 1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.775rem;
            color: var(--text-dim);
        }

        .code-copy-btn {
            background: transparent;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            font-size: 0.75rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.25rem;
            transition: color 0.15s;
        }

        .code-copy-btn:hover {
            color: var(--accent-cyan);
        }

        .code-body {
            padding: 1rem;
            overflow-x: auto;
            font-size: 0.85rem;
            color: #e2e8f0;
            line-height: 1.6;
        }

        /* Cards Grid */
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.25rem;
            margin: 1.25rem 0;
        }

        .feature-card {
            background: rgba(31, 41, 55, 0.4);
            border: 1px solid var(--border-subtle);
            border-radius: 10px;
            padding: 1.25rem;
            transition: all 0.2s ease;
        }

        .feature-card:hover {
            border-color: rgba(99, 102, 241, 0.4);
            transform: translateY(-2px);
        }

        .feature-card h4 {
            font-size: 1rem;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 0.4rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .feature-card p {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-bottom: 0;
        }

        /* Tables */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.875rem;
            margin: 1rem 0;
        }

        .data-table th {
            text-align: left;
            padding: 0.75rem 1rem;
            background: rgba(31, 41, 55, 0.6);
            color: var(--text-muted);
            font-weight: 600;
            border-bottom: 1px solid var(--border-subtle);
        }

        .data-table td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            color: var(--text-main);
        }

        .data-table tr:hover td {
            background: rgba(255, 255, 255, 0.02);
        }

        /* Export Tab */
        .export-hero {
            text-align: center;
            padding: 2.5rem 1rem 3rem 1rem;
            max-width: 800px;
            margin: 0 auto;
        }

        .export-hero h2 {
            font-size: 2.25rem;
            font-weight: 800;
            letter-spacing: -0.03em;
            background: linear-gradient(135deg, #ffffff 0%, #94a3b8 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.75rem;
        }

        .export-hero p {
            color: var(--text-muted);
            font-size: 1.05rem;
        }

        .download-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }

        .download-card {
            background: var(--bg-surface);
            border: 1px solid var(--border-subtle);
            border-radius: 16px;
            padding: 2rem;
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: all 0.25s ease;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        }

        .download-card:hover {
            border-color: rgba(99, 102, 241, 0.5);
            transform: translateY(-4px);
            box-shadow: 0 12px 30px rgba(99, 102, 241, 0.15);
        }

        .download-card-badge {
            position: absolute;
            top: 1.25rem;
            right: 1.25rem;
            font-size: 0.725rem;
            font-weight: 700;
            text-transform: uppercase;
            padding: 0.25rem 0.6rem;
            border-radius: 9999px;
            background: rgba(6, 182, 212, 0.15);
            color: var(--accent-cyan);
            border: 1px solid rgba(6, 182, 212, 0.3);
        }

        .download-card-icon {
            width: 52px;
            height: 52px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            margin-bottom: 1.25rem;
        }

        .icon-yaml {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.2), rgba(249, 115, 22, 0.2));
            border: 1px solid rgba(239, 68, 68, 0.4);
            color: #fb923c;
        }

        .icon-postman {
            background: linear-gradient(135deg, rgba(249, 115, 22, 0.2), rgba(234, 88, 12, 0.2));
            border: 1px solid rgba(249, 115, 22, 0.4);
            color: #f97316;
        }

        .icon-swagger {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.2), rgba(5, 150, 105, 0.2));
            border: 1px solid rgba(16, 185, 129, 0.4);
            color: #34d399;
        }

        .download-card h3 {
            font-size: 1.25rem;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 0.5rem;
        }

        .download-card p {
            color: var(--text-muted);
            font-size: 0.875rem;
            margin-bottom: 1.5rem;
            line-height: 1.5;
        }

        .download-card-actions {
            display: flex;
            flex-direction: column;
            gap: 0.6rem;
        }

        /* Toast Notification */
        #toast {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            background: rgba(16, 185, 129, 0.95);
            color: #ffffff;
            font-weight: 600;
            font-size: 0.875rem;
            padding: 0.75rem 1.25rem;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.4);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            z-index: 999;
        }

        #toast.show {
            transform: translateY(0);
            opacity: 1;
        }

        /* Footer */
        footer.site-footer {
            border-top: 1px solid var(--border-subtle);
            background: rgba(10, 14, 23, 0.95);
            padding: 2rem 1.5rem;
            margin-top: auto;
            text-align: center;
            font-size: 0.85rem;
            color: var(--text-dim);
        }

        footer.site-footer a {
            color: var(--text-muted);
            text-decoration: none;
            transition: color 0.15s;
        }

        footer.site-footer a:hover {
            color: var(--primary);
        }
    </style>
</head>
<body>

    <!-- Header Navigation -->
    <header class="site-header">
        <div class="header-inner">
            <div class="brand-section">
                <div class="brand-logo">⚡</div>
                <div class="brand-text">
                    <h1>PPOB Backend Engine</h1>
                    <div style="display: flex; align-items: center; gap: 0.5rem; margin-top: 0.15rem;">
                        <span class="brand-badge">{{ $appVersion }}</span>
                        <span class="brand-badge" style="background: rgba(6, 182, 212, 0.15); color: #67e8f9; border-color: rgba(6, 182, 212, 0.3);">OpenAPI 3.1</span>
                    </div>
                </div>
            </div>

            <!-- Tab Switcher -->
            <div class="nav-tabs" role="tablist">
                <button class="tab-btn active" id="tab-swagger" onclick="switchTab('swagger')">
                    <span>🚀</span> Interactive Swagger UI
                </button>
                <button class="tab-btn" id="tab-specs" onclick="switchTab('specs')">
                    <span>📘</span> Developer Guides & Specs
                </button>
                <button class="tab-btn" id="tab-export" onclick="switchTab('export')">
                    <span>📥</span> Export & Tools
                </button>
            </div>

            <!-- Header Quick Actions -->
            <div class="header-actions">
                <a href="/docs/v2" class="btn-action btn-action-secondary" style="border-color: rgba(217, 119, 87, 0.4); color: #e48667;" title="Beralih ke tampilan Claude Docs v2">
                    <span>✨</span> Claude Docs v2
                </a>
                <button class="btn-action btn-action-secondary" onclick="copySpecUrl()">
                    <span>📋</span> Salin Link YAML
                </button>
                <a href="{{ $yamlDownloadUrl }}" class="btn-action btn-action-primary" download>
                    <span>⬇️</span> Download .yaml
                </a>
            </div>
        </div>
    </header>

    <!-- Sub-header Spec Banner -->
    <div class="spec-banner">
        <div class="spec-banner-inner">
            <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
                <span>🎯 <strong>Endpoint Spek Resmi:</strong></span>
                <div class="spec-url-box" id="spec-url-display">{{ $swaggerSpecUrl }}</div>
            </div>
            <div style="display: flex; align-items: center; gap: 1rem;">
                <span>Target: <code>B2C Mobile App</code> &amp; <code>B2B Open API Partner</code></span>
            </div>
        </div>
    </div>

    <!-- Main Content Tabs -->
    <main class="main-content">

        <!-- ========================================== -->
        <!-- TAB 1: SWAGGER UI INTERACTIVE PLAYGROUND   -->
        <!-- ========================================== -->
        <section id="panel-swagger" class="view-panel active">
            <div id="swagger-ui-container">
                <div id="swagger-ui"></div>
            </div>
        </section>

        <!-- ========================================== -->
        <!-- TAB 2: DEVELOPER GUIDES & SPECIFICATIONS   -->
        <!-- ========================================== -->
        <section id="panel-specs" class="view-panel">
            <div class="docs-grid">
                
                <!-- Sidebar Nav -->
                <aside class="docs-sidebar">
                    <h3>Navigasi Panduan</h3>
                    <a href="#section-overview" class="docs-nav-link active">1. Arsitektur &amp; Ikhtisar</a>
                    <a href="#section-auth" class="docs-nav-link">2. Autentikasi (B2C &amp; B2B)</a>
                    <a href="#section-envelopes" class="docs-nav-link">3. Standar Envelope Respon</a>
                    <a href="#section-money" class="docs-nav-link">4. Standar Nominal Uang (Cents)</a>
                    <a href="#section-security" class="docs-nav-link">5. Keamanan PIN Transaksi</a>
                    <a href="#section-failover" class="docs-nav-link">6. Multi-Supplier Failover</a>
                    <a href="#section-webhooks" class="docs-nav-link">7. Webhook &amp; Exponential Retry</a>
                </aside>

                <!-- Content Body -->
                <div class="docs-content">

                    <!-- Section 1 -->
                    <article id="section-overview" class="doc-section">
                        <h2>1. Arsitektur &amp; Ikhtisar Sistem <span class="badge-tag">Core Architecture</span></h2>
                        <p>PPOB Backend Engine dirancang untuk melayani dua profil konsumen utama dengan arsitektur Domain-Driven Design (DDD):</p>
                        
                        <div class="cards-grid">
                            <div class="feature-card">
                                <h4>📱 B2C Consumer (Mobile App)</h4>
                                <p>Menggunakan autentikasi <strong>Sanctum Bearer Token</strong>. Mendukung registrasi OTP via WhatsApp, otorisasi transaksi dengan <strong>PIN Challenge-Token</strong>, pengecekan mutasi saldo, deteksi prefix operator seluler, dan push notification via token FCM.</p>
                            </div>
                            <div class="feature-card">
                                <h4>🏢 B2B Open API (Partner)</h4>
                                <p>Menggunakan autentikasi <strong>X-Api-Key + X-Signature (HMAC-SHA256)</strong>, validasi IP Whitelist, timestamp window 300 detik, isolasi rate limit per-partner, serta async webhook callback dengan engine retry otomatis.</p>
                            </div>
                        </div>

                        <p>Semua transaksi bersifat <strong>idempotent</strong> dengan validasi header <code>Idempotency-Key</code> untuk mencegah double-charge saat terjadi interupsi jaringan.</p>
                    </article>

                    <!-- Section 2 -->
                    <article id="section-auth" class="doc-section">
                        <h2>2. Spesifikasi Autentikasi <span class="badge-tag">Security</span></h2>
                        <p>Sistem membedakan alur autentikasi berdasarkan channel request:</p>

                        <h3 style="font-size: 1.1rem; color: #fff; margin: 1.25rem 0 0.5rem 0;">A. Mobile Consumer (Bearer Token)</h3>
                        <p>Sertakan token Sanctum pada header HTTP Authorization di setiap pemanggilan endpoint terproteksi:</p>
                        <div class="code-container">
                            <div class="code-header">
                                <span>HTTP Header</span>
                                <button class="code-copy-btn" onclick="copySnippet('auth-mobile-header')">Salin</button>
                            </div>
                            <div class="code-body" id="auth-mobile-header">Authorization: Bearer 1|qwertysanctumtokenexample123456789</div>
                        </div>

                        <h3 style="font-size: 1.1rem; color: #fff; margin: 1.5rem 0 0.5rem 0;">B. Partner Open API (API Key &amp; HMAC Signature)</h3>
                        <p>Setiap request dari mitra bisnis wajib menyertakan 4 header keamanan:</p>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Header</th>
                                    <th>Tipe</th>
                                    <th>Keterangan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><code>X-Api-Key</code></td>
                                    <td>String</td>
                                    <td>API Key publik milik mitra.</td>
                                </tr>
                                <tr>
                                    <td><code>X-Signature</code></td>
                                    <td>String (Hex)</td>
                                    <td>HMAC-SHA256 dari string kanonikal menggunakan API Secret mitra.</td>
                                </tr>
                                <tr>
                                    <td><code>X-Timestamp</code></td>
                                    <td>String (ISO 8601)</td>
                                    <td>Waktu pengiriman (toleransi drift maksimal 300 detik).</td>
                                </tr>
                                <tr>
                                    <td><code>Idempotency-Key</code></td>
                                    <td>String (UUID)</td>
                                    <td>Kunci idempotensi unik untuk setiap transaksi pembayaran.</td>
                                </tr>
                            </tbody>
                        </table>

                        <p style="margin-top: 1rem;"><strong>Format String Kanonikal Signature:</strong></p>
                        <div class="code-container">
                            <div class="code-header">
                                <span>Canonical Formula</span>
                            </div>
                            <div class="code-body">STRING_TO_SIGN = strtoupper($method) + "\n" + $path + "\n" + $rawBody + "\n" + $timestamp</div>
                        </div>

                        <p><strong>Contoh Kode Pembuatan Signature (PHP &amp; Node.js):</strong></p>
                        <div class="code-container">
                            <div class="code-header">
                                <span>PHP (hash_hmac)</span>
                                <button class="code-copy-btn" onclick="copySnippet('php-signature-snippet')">Salin</button>
                            </div>
                            <div class="code-body" id="php-signature-snippet">$method    = 'POST';
$path      = '/api/partner/transactions';
$rawBody   = json_encode(['partner_ref' => 'REF-001', 'sku_code' => 'TSEL20K', 'customer_number' => '081234567890']);
$timestamp = gmdate('Y-m-d\TH:i:s\Z');
$payload   = "{$method}\n{$path}\n{$rawBody}\n{$timestamp}";
$signature = hash_hmac('sha256', $payload, $apiSecret);</div>
                        </div>
                    </article>

                    <!-- Section 3 -->
                    <article id="section-envelopes" class="doc-section">
                        <h2>3. Standar Envelope Respon <span class="badge-tag">Consistent JSON</span></h2>
                        <p>Seluruh respon API mengikuti struktur envelope seragam dari kelas <code>ApiResponse</code>:</p>

                        <div class="cards-grid">
                            <div class="feature-card">
                                <h4>✅ Respon Sukses (200 / 201 / 202)</h4>
                                <div class="code-container" style="margin: 0.5rem 0;">
                                    <div class="code-body">{
  "success": true,
  "data": { ... },
  "meta": null
}</div>
                                </div>
                            </div>
                            <div class="feature-card">
                                <h4>❌ Respon Error (400 / 401 / 422 / 429 / 500)</h4>
                                <div class="code-container" style="margin: 0.5rem 0;">
                                    <div class="code-body">{
  "success": false,
  "error_code": "INSUFFICIENT_BALANCE",
  "message": "Saldo tidak mencukupi",
  "errors": null
}</div>
                                </div>
                            </div>
                        </div>

                        <p style="margin-top: 1rem;"><strong>Katalog Error Code Utama:</strong></p>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Error Code</th>
                                    <th>HTTP Status</th>
                                    <th>Penyebab &amp; Penanganan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><code>INSUFFICIENT_BALANCE</code></td>
                                    <td>422 Unprocessable</td>
                                    <td>Saldo wallet (konsumen atau mitra) tidak mencukupi untuk nominal transaksi.</td>
                                </tr>
                                <tr>
                                    <td><code>INVALID_PIN_TOKEN</code></td>
                                    <td>422 Unprocessable</td>
                                    <td>Token otorisasi PIN tidak valid, telah dipakai, atau kadaluwarsa (maks 5 menit).</td>
                                </tr>
                                <tr>
                                    <td><code>PARTNER_UNAUTHORIZED</code></td>
                                    <td>401 Unauthorized</td>
                                    <td>API Key tidak ditemukan atau signature HMAC tidak cocok.</td>
                                </tr>
                                <tr>
                                    <td><code>PARTNER_FORBIDDEN_IP</code></td>
                                    <td>403 Forbidden</td>
                                    <td>IP pengirim request tidak terdaftar pada whitelist mitra.</td>
                                </tr>
                                <tr>
                                    <td><code>RATE_LIMIT_EXCEEDED</code></td>
                                    <td>429 Too Many Req</td>
                                    <td>Batas request per-menit (RPM) mitra atau percobaan PIN/OTP terlampaui.</td>
                                </tr>
                                <tr>
                                    <td><code>OPERATOR_NOT_FOUND</code></td>
                                    <td>404 Not Found</td>
                                    <td>Prefix nomor seluler tidak dikenali di daftar operator Indonesia.</td>
                                </tr>
                            </tbody>
                        </table>
                    </article>

                    <!-- Section 4 -->
                    <article id="section-money" class="doc-section">
                        <h2>4. Standar Nominal Uang (Cents) <span class="badge-tag">Financial Precision</span></h2>
                        <p>Untuk menghindari <em>floating point round-off error</em> pada perhitungan akuntansi keuangan, <strong>seluruh nilai nominal uang di sistem disimpan dan dikomputasikan dalam satuan sen (cents)</strong>:</p>
                        
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Nominal Rupiah Riil</th>
                                    <th>Nilai API (cents / integer)</th>
                                    <th>Format Tampilan (display string)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Rp 5.000</td>
                                    <td><code>500000</code></td>
                                    <td><code>"Rp 5.000,00"</code></td>
                                </tr>
                                <tr>
                                    <td>Rp 20.200</td>
                                    <td><code>2020000</code></td>
                                    <td><code>"Rp 20.200,00"</code></td>
                                </tr>
                                <tr>
                                    <td>Rp 100.000</td>
                                    <td><code>10000000</code></td>
                                    <td><code>"Rp 100.000,00"</code></td>
                                </tr>
                            </tbody>
                        </table>
                        <p>Setiap payload respon secara otomatis menyertakan atribut display (misal <code>balance</code> dan <code>balance_display</code>) untuk kemudahan integrasi UI aplikasi mobile.</p>
                    </article>

                    <!-- Section 5 -->
                    <article id="section-security" class="doc-section">
                        <h2>5. Alur Keamanan PIN Transaksi (2-Step Flow) <span class="badge-tag">Zero Exposure</span></h2>
                        <p>Aplikasi mobile <strong>tidak pernah mengirimkan PIN 6-digit secara mentah</strong> pada saat eksekusi pembelian produk. Proses verifikasi dipisahkan menjadi dua langkah:</p>
                        
                        <div class="cards-grid">
                            <div class="feature-card">
                                <h4>Step 1: Challenge &amp; Verify PIN</h4>
                                <p>Mobile app memanggil <code>POST /security/pin/challenge</code> untuk mendapatkan ID tantangan, lalu memanggil <code>POST /security/pin/verify</code> dengan PIN. Sistem menerbitkan <strong>Pin Verification Token</strong> (valid 5 menit, one-time use).</p>
                            </div>
                            <div class="feature-card">
                                <h4>Step 2: Eksekusi Transaksi</h4>
                                <p>Mobile app memanggil <code>POST /transactions</code> dengan menyertakan header <code>X-Pin-Token: {token}</code>. Middleware <code>PinTokenMiddleware</code> mengonsumsi token tersebut seketika (marked as used).</p>
                            </div>
                        </div>
                    </article>

                    <!-- Section 6 -->
                    <article id="section-failover" class="doc-section">
                        <h2>6. Multi-Supplier Failover &amp; Circuit Breaker <span class="badge-tag">ADR-004 &amp; ADR-005</span></h2>
                        <p>Jika supplier utama (misal: Digiflazz) mengalami gangguan atau pemadaman rute, <code>SupplierRoutingService</code> secara cerdas mengevaluasi rute cadangan (misal: VIP Payment / BukaOlshop):</p>
                        <ul style="color: var(--text-muted); margin-left: 1.5rem; margin-bottom: 1rem;">
                            <li><strong>Circuit Breaker:</strong> Otomatis membuka sirkuit (trip) saat rasio kegagalan mencapai ambang batas, mengalihkan rute ke supplier berikutnya.</li>
                            <li><strong>Preferensi Mitra:</strong> Mitra B2B dapat mengatur opsi <code>allow_failover</code> dan <code>failover_policy</code> (none / same_category / any).</li>
                            <li><strong>Queue Isolation:</strong> Transaksi diproses dalam antrean terisolasi berdasarkan nama provider (contoh: <code>ppob_digiflazz</code>).</li>
                        </ul>
                    </article>

                    <!-- Section 7 -->
                    <article id="section-webhooks" class="doc-section">
                        <h2>7. Webhook Delivery &amp; Exponential Retry <span class="badge-tag">ADR-007 &amp; ADR-008</span></h2>
                        <p>Untuk transaksi B2B yang diproses secara asinkron (status respon <code>202 Accepted</code>), sistem mengirimkan status terminal (success / failed) ke <code>callback_url</code> mitra melalui background job <code>DeliverWebhookJob</code>.</p>
                        
                        <p><strong>Jadwal Percobaan Ulang (Exponential Backoff):</strong></p>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Percobaan (Attempt)</th>
                                    <th>Penundaan (Delay)</th>
                                    <th>Aksi Jika Gagal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Attempt 1</td>
                                    <td>Langsung (0 detik)</td>
                                    <td>Catat response code &amp; jadwalkan attempt 2</td>
                                </tr>
                                <tr>
                                    <td>Attempt 2</td>
                                    <td>+1 Menit</td>
                                    <td>Jadwalkan attempt 3</td>
                                </tr>
                                <tr>
                                    <td>Attempt 3</td>
                                    <td>+5 Menit</td>
                                    <td>Jadwalkan attempt 4</td>
                                </tr>
                                <tr>
                                    <td>Attempt 4</td>
                                    <td>+30 Menit</td>
                                    <td>Jadwalkan attempt 5</td>
                                </tr>
                                <tr>
                                    <td>Attempt 5</td>
                                    <td>+2 Jam</td>
                                    <td>Tandai <code>failed_permanent</code> &amp; trigger alert devops</td>
                                </tr>
                            </tbody>
                        </table>
                    </article>

                </div>
            </div>
        </section>

        <!-- ========================================== -->
        <!-- TAB 3: EXPORT & TOOLS INTEGRATION HUB      -->
        <!-- ========================================== -->
        <section id="panel-export" class="view-panel">
            <div class="export-hero">
                <h2>Ekspor &amp; Integrasi Alat Pengembang</h2>
                <p>Unduh spesifikasi resmi dalam format OpenAPI YAML atau koleksi siap pakai Postman untuk pengujian instan di lingkungan kerja Anda.</p>
            </div>

            <div class="download-cards">

                <!-- Card 1: OpenAPI YAML -->
                <div class="download-card">
                    <span class="download-card-badge">OpenAPI 3.1</span>
                    <div>
                        <div class="download-card-icon icon-yaml">📄</div>
                        <h3>OpenAPI 3.1 YAML Spec</h3>
                        <p>File spesifikasi lengkap dengan seluruh schema DTO, enum status, request body, query parameters, dan header keamanan.</p>
                    </div>
                    <div class="download-card-actions">
                        <a href="{{ $yamlDownloadUrl }}" class="btn-action btn-action-primary" download style="justify-content: center;">
                            <span>⬇️</span> Download openapi.yaml
                        </a>
                        <button class="btn-action btn-action-secondary" onclick="copySpecUrl()" style="justify-content: center;">
                            <span>📋</span> Salin URL Raw YAML
                        </button>
                    </div>
                </div>

                <!-- Card 2: Swagger Editor -->
                <div class="download-card">
                    <span class="download-card-badge">Cloud Viewer</span>
                    <div>
                        <div class="download-card-icon icon-swagger">🌐</div>
                        <h3>Swagger Editor Online</h3>
                        <p>Buka dan visualisasikan spesifikasi ini langsung di aplikasi resmi Swagger Editor secara online tanpa perlu instalasi tambahan.</p>
                    </div>
                    <div class="download-card-actions">
                        <a href="https://editor.swagger.io/?url={{ urlencode($swaggerSpecUrl) }}" target="_blank" rel="noopener noreferrer" class="btn-action btn-action-primary" style="justify-content: center; background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                            <span>🚀</span> Buka di Swagger Editor
                        </a>
                        <a href="https://editor-next.swagger.io/?url={{ urlencode($swaggerSpecUrl) }}" target="_blank" rel="noopener noreferrer" class="btn-action btn-action-secondary" style="justify-content: center;">
                            <span>✨</span> Buka di Swagger Next UI
                        </a>
                    </div>
                </div>

                <!-- Card 3: Postman Collection -->
                <div class="download-card">
                    <span class="download-card-badge">v2.1 Ready</span>
                    <div>
                        <div class="download-card-icon icon-postman">📮</div>
                        <h3>Postman Collection &amp; Env</h3>
                        <p>Koleksi API terorganisir per folder domain (Auth, Mobile Dashboard, Prefix Operator, Wallet Channels, Transaksi) beserta Environment lokal.</p>
                    </div>
                    <div class="download-card-actions">
                        <a href="{{ $postmanCollectionUrl }}" class="btn-action btn-action-primary" download style="justify-content: center; background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);">
                            <span>📦</span> Download Postman Collection
                        </a>
                        <a href="{{ $postmanEnvironmentUrl }}" class="btn-action btn-action-secondary" download style="justify-content: center;">
                            <span>⚙️</span> Download Postman Environment
                        </a>
                    </div>
                </div>

            </div>

            <!-- Import Instructions -->
            <article class="doc-section">
                <h2>Langkah Cepat Import ke Tools Pilihan Anda</h2>
                <div class="cards-grid">
                    <div class="feature-card">
                        <h4>1. Postman</h4>
                        <p>Buka Postman &rarr; Klik tombol <strong>Import</strong> di pojok kiri atas &rarr; Drag file <code>PPOB_Backend.postman_collection.json</code> atau masukkan URL spec: <code style="word-break: break-all;">{{ $swaggerSpecUrl }}</code>.</p>
                    </div>
                    <div class="feature-card">
                        <h4>2. Insomnia / Bruno</h4>
                        <p>Buka Insomnia / Bruno &rarr; Pilih <strong>Create &rarr; Import from File/URL</strong> &rarr; Masukkan file <code>openapi.yaml</code>. Semua endpoint otomatis terpetakan lengkap dengan body mock.</p>
                    </div>
                    <div class="feature-card">
                        <h4>3. Swagger CLI / Generator</h4>
                        <p>Gunakan generator SDK klien otomatis: <br><code>npx @openapitools/openapi-generator-cli generate -i {{ $swaggerSpecUrl }} -g typescript-axios -o ./client</code>.</p>
                    </div>
                </div>
            </article>
        </section>

    </main>

    <!-- Footer -->
    <footer class="site-footer">
        <p>&copy; {{ date('Y') }} PPOB Backend Engine. Dokumentasi Spesifikasi API Resmi &amp; Swagger Hub Portal.</p>
        <p style="margin-top: 0.5rem; font-size: 0.8rem;">
            Dibangun dengan Laravel 12 &bull; OpenAPI 3.1 &bull; Postman v2.1 &bull; Versi Rilis: <code>{{ $appVersion }}</code>
        </p>
    </footer>

    <!-- Copy Toast -->
    <div id="toast">
        <span>✅</span>
        <span id="toast-message">Tersalin ke papan klip!</span>
    </div>

    <!-- Scripts -->
    <script src="https://unpkg.com/swagger-ui-dist@5.18.2/swagger-ui-bundle.js"></script>
    <script src="https://unpkg.com/swagger-ui-dist@5.18.2/swagger-ui-standalone-preset.js"></script>
    <script>
        // Tab Switcher
        function switchTab(tabName) {
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.view-panel').forEach(panel => panel.classList.remove('active'));

            const activeBtn = document.getElementById('tab-' + tabName);
            const activePanel = document.getElementById('panel-' + tabName);

            if (activeBtn && activePanel) {
                activeBtn.classList.add('active');
                activePanel.classList.add('active');
            }

            // Sync URL hash
            history.replaceState(null, null, '#' + tabName);
        }

        // Initialize from hash if present
        window.addEventListener('DOMContentLoaded', () => {
            const hash = window.location.hash.replace('#', '');
            if (hash === 'specs' || hash === 'export') {
                switchTab(hash);
            }

            // Initialize Swagger UI
            window.ui = SwaggerUIBundle({
                url: "{{ $swaggerSpecUrl }}",
                dom_id: '#swagger-ui',
                deepLinking: true,
                displayRequestDuration: true,
                filter: true,
                showExtensions: true,
                showCommonExtensions: true,
                docExpansion: 'list',
                defaultModelsExpandDepth: 2,
                presets: [
                    SwaggerUIBundle.presets.apis,
                    SwaggerUIStandalonePreset
                ],
                plugins: [
                    SwaggerUIBundle.plugins.DownloadUrl
                ],
                layout: "BaseLayout"
            });
        });

        // Copy Spec URL Helper
        function copySpecUrl() {
            const url = "{{ $swaggerSpecUrl }}";
            navigator.clipboard.writeText(url).then(() => {
                showToast("URL spesifikasi OpenAPI berhasil disalin!");
            });
        }

        // Copy Snippet Helper
        function copySnippet(elementId) {
            const el = document.getElementById(elementId);
            if (el) {
                navigator.clipboard.writeText(el.innerText).then(() => {
                    showToast("Kode berhasil disalin!");
                });
            }
        }

        // Toast Helper
        function showToast(message) {
            const toast = document.getElementById('toast');
            const msgEl = document.getElementById('toast-message');
            msgEl.innerText = message;
            toast.classList.add('show');
            setTimeout(() => {
                toast.classList.remove('show');
            }, 3000);
        }
    </script>
</body>
</html>
