<!DOCTYPE html>
<html lang="id" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PPOB Engine — API Documentation (v2 Claude Edition)</title>
    <meta name="description" content="Dokumentasi Spesifikasi API Resmi PPOB Backend terinspirasi dari desain editorial Claude Docs (Anthropic)">

    <!-- Google Fonts: Plus Jakarta Sans & JetBrains Mono -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:ital,wght@0,400;0,500;0,600;0,700;1,400&family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400&display=swap" rel="stylesheet">

    <style>
        :root {
            /* Claude Dark (Default) */
            --bg-page: #181816;
            --bg-sidebar: #141412;
            --bg-surface: #201E1D;
            --bg-surface-elevated: #2A2825;
            --bg-code: #121211;
            --border-color: rgba(255, 255, 255, 0.08);
            --border-hover: rgba(255, 255, 255, 0.16);
            --text-heading: #EDEBE8;
            --text-body: #C5C0B8;
            --text-muted: #8E8980;
            --text-dim: #646059;
            --accent: #D97757; /* Anthropic Terracotta */
            --accent-hover: #E48667;
            --accent-bg: rgba(217, 119, 87, 0.12);
            --accent-border: rgba(217, 119, 87, 0.28);
            --method-get: #10B981;
            --method-get-bg: rgba(16, 185, 129, 0.12);
            --method-post: #3B82F6;
            --method-post-bg: rgba(59, 130, 246, 0.12);
            --method-put: #F59E0B;
            --method-put-bg: rgba(245, 158, 11, 0.12);
            --method-delete: #EF4444;
            --method-delete-bg: rgba(239, 68, 68, 0.12);
            --font-sans: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            --font-mono: 'JetBrains Mono', monospace;
        }

        html.light {
            /* Claude Light (Parchment Warmth) */
            --bg-page: #FAF8F5;
            --bg-sidebar: #F4F1EB;
            --bg-surface: #FFFFFF;
            --bg-surface-elevated: #F9F7F4;
            --bg-code: #201E1D;
            --border-color: #E8E4DC;
            --border-hover: #D4CECE;
            --text-heading: #1F1E1D;
            --text-body: #4A4742;
            --text-muted: #79746C;
            --text-dim: #A6A097;
            --accent: #C15F3C;
            --accent-hover: #A95030;
            --accent-bg: rgba(193, 95, 60, 0.08);
            --accent-border: rgba(193, 95, 60, 0.22);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background-color: var(--bg-page);
            color: var(--text-body);
            font-family: var(--font-sans);
            line-height: 1.65;
            -webkit-font-smoothing: antialiased;
            overflow-x: hidden;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        /* Top Navbar */
        nav.claude-nav {
            position: sticky;
            top: 0;
            z-index: 100;
            background: var(--bg-page);
            border-bottom: 1px solid var(--border-color);
            padding: 0.65rem 1.75rem;
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
        }

        .nav-container {
            max-width: 1720px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1.5rem;
        }

        .nav-brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
            color: var(--text-heading);
        }

        .brand-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: var(--accent);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.05rem;
            font-weight: 700;
            box-shadow: 0 2px 10px rgba(217, 119, 87, 0.3);
        }

        .brand-title {
            font-size: 1.05rem;
            font-weight: 700;
            letter-spacing: -0.02em;
            color: var(--text-heading);
        }

        .brand-badge {
            font-size: 0.7rem;
            font-weight: 600;
            padding: 0.15rem 0.5rem;
            border-radius: 9999px;
            background: var(--accent-bg);
            color: var(--accent);
            border: 1px solid var(--accent-border);
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        /* Nav Search Bar */
        .search-box {
            position: relative;
            max-width: 480px;
            width: 100%;
        }

        .search-input {
            width: 100%;
            background: var(--bg-surface);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 0.45rem 2.25rem 0.45rem 2.5rem;
            color: var(--text-heading);
            font-family: var(--font-sans);
            font-size: 0.875rem;
            transition: all 0.2s ease;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px var(--accent-bg);
        }

        .search-icon {
            position: absolute;
            left: 0.85rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 0.85rem;
            pointer-events: none;
        }

        .search-shortcut {
            position: absolute;
            right: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            font-family: var(--font-mono);
            font-size: 0.7rem;
            color: var(--text-dim);
            background: var(--bg-surface-elevated);
            padding: 0.15rem 0.4rem;
            border-radius: 4px;
            border: 1px solid var(--border-color);
            pointer-events: none;
        }

        /* Nav Action Buttons */
        .nav-actions {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .btn-claude {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            font-size: 0.825rem;
            font-weight: 600;
            padding: 0.45rem 0.875rem;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.15s ease;
            border: 1px solid transparent;
        }

        .btn-claude-primary {
            background: var(--accent);
            color: #ffffff;
        }

        .btn-claude-primary:hover {
            background: var(--accent-hover);
        }

        .btn-claude-secondary {
            background: var(--bg-surface);
            border-color: var(--border-color);
            color: var(--text-heading);
        }

        .btn-claude-secondary:hover {
            border-color: var(--border-hover);
            background: var(--bg-surface-elevated);
        }

        .theme-toggle-btn {
            background: var(--bg-surface);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 0.45rem 0.65rem;
            color: var(--text-muted);
            cursor: pointer;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.15s;
        }

        .theme-toggle-btn:hover {
            color: var(--text-heading);
            border-color: var(--border-hover);
        }

        /* Main Tri-Column Layout */
        .layout-container {
            max-width: 1720px;
            width: 100%;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 280px minmax(0, 1fr) 330px;
            gap: 0;
            flex: 1;
        }

        @media (max-width: 1280px) {
            .layout-container {
                grid-template-columns: 260px minmax(0, 1fr);
            }
            .column-toc {
                display: none !important;
            }
        }

        @media (max-width: 860px) {
            .layout-container {
                grid-template-columns: 1fr;
            }
            .column-sidebar {
                display: none;
            }
        }

        /* 1. Left Sidebar */
        aside.column-sidebar {
            position: sticky;
            top: 53px;
            height: calc(100vh - 53px);
            overflow-y: auto;
            background: var(--bg-sidebar);
            border-right: 1px solid var(--border-color);
            padding: 1.5rem 1.25rem 3rem 1.25rem;
            scrollbar-width: thin;
        }

        .nav-group {
            margin-bottom: 1.5rem;
        }

        .nav-group-title {
            font-size: 0.725rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--text-dim);
            margin-bottom: 0.6rem;
            padding-left: 0.5rem;
        }

        .nav-item-link {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
            padding: 0.4rem 0.65rem;
            border-radius: 6px;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.15s ease;
            margin-bottom: 0.15rem;
        }

        .nav-item-link:hover {
            color: var(--text-heading);
            background: var(--bg-surface);
        }

        .nav-item-link.active {
            color: var(--accent);
            background: var(--accent-bg);
            font-weight: 600;
        }

        .method-badge {
            font-family: var(--font-mono);
            font-size: 0.65rem;
            font-weight: 700;
            padding: 0.1rem 0.35rem;
            border-radius: 4px;
            text-transform: uppercase;
        }

        .method-get { background: var(--method-get-bg); color: var(--method-get); }
        .method-post { background: var(--method-post-bg); color: var(--method-post); }
        .method-put { background: var(--method-put-bg); color: var(--method-put); }
        .method-delete { background: var(--method-delete-bg); color: var(--method-delete); }

        /* 2. Center Content Column */
        main.column-content {
            padding: 2.5rem 3rem 5rem 3rem;
            max-width: 900px;
            margin: 0 auto;
            width: 100%;
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.8rem;
            color: var(--text-dim);
            margin-bottom: 1.25rem;
        }

        .breadcrumb span {
            color: var(--text-muted);
        }

        .page-header {
            margin-bottom: 2.5rem;
            padding-bottom: 1.75rem;
            border-bottom: 1px solid var(--border-color);
        }

        .page-header h1 {
            font-size: 2.35rem;
            font-weight: 800;
            letter-spacing: -0.03em;
            color: var(--text-heading);
            margin-bottom: 0.75rem;
            line-height: 1.2;
        }

        .page-header p.lead {
            font-size: 1.05rem;
            color: var(--text-muted);
            line-height: 1.6;
        }

        /* Claude Admonitions / Callout Boxes */
        .claude-callout {
            border-radius: 10px;
            padding: 1.15rem 1.25rem;
            margin: 1.5rem 0;
            display: flex;
            gap: 0.85rem;
            font-size: 0.9rem;
            line-height: 1.55;
            border: 1px solid;
        }

        .claude-callout-icon {
            font-size: 1.15rem;
            line-height: 1;
            flex-shrink: 0;
            margin-top: 0.1rem;
        }

        .callout-note {
            background: rgba(59, 130, 246, 0.08);
            border-color: rgba(59, 130, 246, 0.25);
            color: #93c5fd;
        }
        html.light .callout-note {
            background: #eff6ff;
            border-color: #bfdbfe;
            color: #1e40af;
        }

        .callout-warning {
            background: var(--accent-bg);
            border-color: var(--accent-border);
            color: #fca5a5;
        }
        html.light .callout-warning {
            background: #fff7ed;
            border-color: #ffedd5;
            color: #9a3412;
        }

        .callout-tip {
            background: rgba(16, 185, 129, 0.08);
            border-color: rgba(16, 185, 129, 0.25);
            color: #6ee7b7;
        }
        html.light .callout-tip {
            background: #f0fdf4;
            border-color: #bbf7d0;
            color: #166534;
        }

        .callout-title {
            font-weight: 700;
            margin-bottom: 0.25rem;
            display: block;
        }

        /* Section Cards & Typography */
        section.doc-section {
            margin-bottom: 3.5rem;
            scroll-margin-top: 5rem;
        }

        section.doc-section h2 {
            font-size: 1.6rem;
            font-weight: 700;
            letter-spacing: -0.02em;
            color: var(--text-heading);
            margin-bottom: 0.85rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        section.doc-section h3 {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--text-heading);
            margin: 1.75rem 0 0.65rem 0;
        }

        p {
            margin-bottom: 1rem;
            color: var(--text-body);
        }

        /* Clean API Endpoint Card */
        .endpoint-card {
            background: var(--bg-surface);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            margin: 1.5rem 0;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.15);
        }

        .endpoint-header {
            padding: 1rem 1.25rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            background: var(--bg-surface-elevated);
            border-bottom: 1px solid var(--border-color);
        }

        .endpoint-path-wrap {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-family: var(--font-mono);
            font-size: 0.925rem;
            font-weight: 600;
            color: var(--text-heading);
        }

        .endpoint-body {
            padding: 1.25rem;
        }

        .params-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
            margin: 0.75rem 0 1.25rem 0;
        }

        .params-table th {
            text-align: left;
            padding: 0.5rem 0.75rem;
            color: var(--text-muted);
            border-bottom: 1px solid var(--border-color);
            font-weight: 600;
        }

        .params-table td {
            padding: 0.65rem 0.75rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.04);
            color: var(--text-body);
            vertical-align: top;
        }

        .param-name {
            font-family: var(--font-mono);
            font-weight: 600;
            color: var(--accent);
        }

        .param-badge {
            font-size: 0.65rem;
            padding: 0.1rem 0.35rem;
            border-radius: 4px;
            background: rgba(239, 68, 68, 0.15);
            color: #ef4444;
            margin-left: 0.35rem;
            font-family: var(--font-sans);
        }

        /* Multi-language Code Container */
        .code-block {
            background: var(--bg-code);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            overflow: hidden;
            margin: 1rem 0;
            font-family: var(--font-mono);
        }

        .code-nav {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: rgba(255, 255, 255, 0.02);
            border-bottom: 1px solid var(--border-color);
            padding: 0.25rem 0.5rem;
        }

        .code-tabs {
            display: flex;
            gap: 0.25rem;
        }

        .code-tab-btn {
            background: transparent;
            border: none;
            color: var(--text-muted);
            font-family: var(--font-sans);
            font-size: 0.775rem;
            font-weight: 600;
            padding: 0.4rem 0.75rem;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.15s;
        }

        .code-tab-btn:hover {
            color: var(--text-heading);
        }

        .code-tab-btn.active {
            color: var(--accent);
            background: var(--accent-bg);
        }

        .btn-copy-code {
            background: transparent;
            border: none;
            color: var(--text-muted);
            font-size: 0.75rem;
            cursor: pointer;
            padding: 0.35rem 0.65rem;
            display: flex;
            align-items: center;
            gap: 0.35rem;
            transition: color 0.15s;
        }

        .btn-copy-code:hover {
            color: var(--text-heading);
        }

        pre.code-content {
            padding: 1.15rem;
            overflow-x: auto;
            font-size: 0.825rem;
            line-height: 1.6;
            color: #e5e5e5;
        }

        /* 3. Right Sidebar ("On this page") */
        aside.column-toc {
            position: sticky;
            top: 53px;
            height: calc(100vh - 53px);
            overflow-y: auto;
            border-left: 1px solid var(--border-color);
            padding: 2.25rem 1.5rem 3rem 1.5rem;
            font-size: 0.825rem;
        }

        .toc-title {
            font-size: 0.725rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--text-dim);
            margin-bottom: 0.85rem;
        }

        .toc-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .toc-link {
            color: var(--text-muted);
            text-decoration: none;
            transition: color 0.15s;
            display: block;
            line-height: 1.4;
        }

        .toc-link:hover {
            color: var(--accent);
        }

        .toc-link.active {
            color: var(--accent);
            font-weight: 600;
        }

        .toc-tools-box {
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            gap: 0.65rem;
        }

        .tool-link-btn {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 0.75rem;
            border-radius: 8px;
            background: var(--bg-surface);
            border: 1px solid var(--border-color);
            color: var(--text-heading);
            text-decoration: none;
            font-size: 0.8rem;
            font-weight: 600;
            transition: all 0.15s ease;
        }

        .tool-link-btn:hover {
            border-color: var(--accent);
            color: var(--accent);
        }

        /* Toast */
        #toast {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            background: var(--accent);
            color: #ffffff;
            font-weight: 600;
            font-size: 0.85rem;
            padding: 0.65rem 1.15rem;
            border-radius: 8px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.4);
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
            z-index: 9999;
        }

        #toast.show {
            transform: translateY(0);
            opacity: 1;
        }
    </style>
</head>
<body>

    <!-- Top Navigation Bar -->
    <nav class="claude-nav">
        <div class="nav-container">
            <!-- Brand -->
            <a href="/docs/v2" class="nav-brand">
                <div class="brand-icon">⚡</div>
                <div>
                    <span class="brand-title">PPOB Engine</span>
                    <span class="brand-badge" style="margin-left: 0.4rem;">v2 Editorial</span>
                </div>
            </a>

            <!-- Search -->
            <div class="search-box">
                <span class="search-icon">🔍</span>
                <input type="text" id="claude-search" class="search-input" placeholder="Cari endpoint, konsep, atau panduan..." onkeyup="filterDocs(this.value)">
                <span class="search-shortcut">⌘K</span>
            </div>

            <!-- Actions -->
            <div class="nav-actions">
                <a href="/docs" class="btn-claude btn-claude-secondary" title="Kembali ke tampilan Swagger Classic v1">
                    <span>🔄</span> Swagger v1
                </a>
                <a href="{{ $yamlDownloadUrl }}" class="btn-claude btn-claude-primary" download>
                    <span>⬇️</span> openapi.yaml
                </a>
                <button class="theme-toggle-btn" onclick="toggleTheme()" title="Toggle Dark/Light Mode">
                    <span id="theme-icon">☀️</span>
                </button>
            </div>
        </div>
    </nav>

    <!-- Layout Container (Tri-Column) -->
    <div class="layout-container">

        <!-- 1. LEFT SIDEBAR (Hierarchy) -->
        <aside class="column-sidebar" id="sidebar-nav">
            
            <div class="nav-group">
                <div class="nav-group-title">Getting Started</div>
                <a href="#overview" class="nav-item-link active"><span>1. Ikhtisar &amp; Arsitektur</span></a>
                <a href="#authentication" class="nav-item-link"><span>2. Autentikasi (B2C &amp; B2B)</span></a>
                <a href="#response-envelopes" class="nav-item-link"><span>3. Standar Envelope Respon</span></a>
                <a href="#money-standard" class="nav-item-link"><span>4. Standar Nominal Uang</span></a>
            </div>

            <div class="nav-group">
                <div class="nav-group-title">Core Security &amp; Engine</div>
                <a href="#security-pin" class="nav-item-link"><span>Keamanan PIN 2-Step</span></a>
                <a href="#multi-supplier" class="nav-item-link"><span>Multi-Supplier Failover</span></a>
                <a href="#webhooks" class="nav-item-link"><span>Async Webhook Delivery</span></a>
            </div>

            <div class="nav-group">
                <div class="nav-group-title">Mobile App APIs (B2C)</div>
                <a href="#ep-home" class="nav-item-link">
                    <span>Home Dashboard</span>
                    <span class="method-badge method-get">GET</span>
                </a>
                <a href="#ep-operator-prefix" class="nav-item-link">
                    <span>Deteksi Operator Prefix</span>
                    <span class="method-badge method-get">GET</span>
                </a>
                <a href="#ep-products" class="nav-item-link">
                    <span>Katalog Produk</span>
                    <span class="method-badge method-get">GET</span>
                </a>
                <a href="#ep-wallet-channels" class="nav-item-link">
                    <span>Topup Payment Channels</span>
                    <span class="method-badge method-get">GET</span>
                </a>
                <a href="#ep-wallet-mutations" class="nav-item-link">
                    <span>Riwayat Mutasi Saldo</span>
                    <span class="method-badge method-get">GET</span>
                </a>
                <a href="#ep-auth-profile" class="nav-item-link">
                    <span>Update Profil User</span>
                    <span class="method-badge method-put">PUT</span>
                </a>
                <a href="#ep-auth-fcm" class="nav-item-link">
                    <span>Registrasi FCM Token</span>
                    <span class="method-badge method-post">POST</span>
                </a>
            </div>

            <div class="nav-group">
                <div class="nav-group-title">Partner Open API (B2B)</div>
                <a href="#ep-partner-balance" class="nav-item-link">
                    <span>Cek Saldo Deposit</span>
                    <span class="method-badge method-get">GET</span>
                </a>
                <a href="#ep-partner-tx" class="nav-item-link">
                    <span>Create Transaksi Mitra</span>
                    <span class="method-badge method-post">POST</span>
                </a>
            </div>

            <div class="nav-group">
                <div class="nav-group-title">Ekspor &amp; Integrasi</div>
                <a href="#export-section" class="nav-item-link"><span>Pusat Unduhan Tooling</span></a>
            </div>

        </aside>

        <!-- 2. CENTER CONTENT (Editorial Content) -->
        <main class="column-content">

            <!-- Breadcrumbs -->
            <div class="breadcrumb">
                <span>Docs</span>
                <span>/</span>
                <span>Developer Guide</span>
                <span>/</span>
                <span style="color: var(--accent);">PPOB Engine API</span>
            </div>

            <!-- Header -->
            <div class="page-header">
                <h1>Dokumentasi Spesifikasi PPOB Engine</h1>
                <p class="lead">Panduan integrasi lengkap untuk Mobile App B2C dan Partner Open API B2B. Meliputi standar autentikasi HMAC-SHA256, komputasi presisi sen (cents), dan failover supplier otomatis.</p>
            </div>

            <!-- SECTION 1: OVERVIEW -->
            <section id="overview" class="doc-section">
                <h2>1. Ikhtisar &amp; Arsitektur Sistem</h2>
                <p>Platform PPOB Backend dibangun dengan pendekatan Domain-Driven Design (DDD) untuk menangani transaksi pulsa, paket data, token PLN, e-wallet, dan tagihan berkala secara masif, aman, dan tanpa downtime.</p>

                <!-- Callout Note -->
                <div class="claude-callout callout-note">
                    <div class="claude-callout-icon">💡</div>
                    <div>
                        <strong class="callout-title">Desain Berbasis Idempotensi</strong>
                        Semua endpoint pembayaran wajib menyertakan header <code>Idempotency-Key</code> (UUID v4) guna menjamin tidak terjadinya *double-charging* saat terjadi gangguan konektivitas jaringan.
                    </div>
                </div>

                <p>Sistem membedakan dua profil akses secara ketat:</p>
                <ul style="margin-left: 1.5rem; margin-bottom: 1.25rem;">
                    <li><strong>B2C Consumer (Mobile App):</strong> Autentikasi dengan token Sanctum, otorisasi transaksi dengan PIN token 2-step (Zero Exposure), dan deteksi otomatis operator dari prefix nomor seluler.</li>
                    <li><strong>B2B Open API (Mitra Bisnis):</strong> Autentikasi mesin-ke-mesin menggunakan API Key, signature HMAC-SHA256, IP Whitelist, dan respon asinkron <code>202 Accepted</code> dengan engine webhook retry otomatis.</li>
                </ul>
            </section>

            <!-- SECTION 2: AUTHENTICATION -->
            <section id="authentication" class="doc-section">
                <h2>2. Spesifikasi Autentikasi</h2>
                
                <h3>A. Mobile App (Sanctum Bearer Token)</h3>
                <p>Setelah pengguna melakukan verifikasi OTP WhatsApp via <code>POST /auth/otp/verify</code>, sertakan Bearer Token di header setiap pemanggilan endpoint terproteksi:</p>
                <div class="code-block">
                    <div class="code-nav">
                        <span style="font-size: 0.75rem; color: var(--text-muted); padding-left: 0.5rem;">Header Format</span>
                        <button class="btn-copy-code" onclick="copySnippetText('Authorization: Bearer 1|sanctum_token_example')">Salin</button>
                    </div>
                    <pre class="code-content">Authorization: Bearer 1|abc123sanctumtokenxyz987</pre>
                </div>

                <h3>B. Partner Open API (HMAC-SHA256 Signature)</h3>
                <p>Mitra bisnis wajib menyertakan 4 header keamanan pada setiap request HTTP:</p>
                <table class="params-table">
                    <thead>
                        <tr>
                            <th>Header</th>
                            <th>Tipe</th>
                            <th>Deskripsi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><span class="param-name">X-Api-Key</span></td>
                            <td>String</td>
                            <td>API Key publik milik mitra bisnis Anda.</td>
                        </tr>
                        <tr>
                            <td><span class="param-name">X-Signature</span></td>
                            <td>String (Hex)</td>
                            <td>HMAC-SHA256 dari string kanonikal menggunakan API Secret mitra.</td>
                        </tr>
                        <tr>
                            <td><span class="param-name">X-Timestamp</span></td>
                            <td>String (ISO 8601)</td>
                            <td>Timestamp saat request dikirim (maksimal selisih 300 detik).</td>
                        </tr>
                        <tr>
                            <td><span class="param-name">Idempotency-Key</span></td>
                            <td>String (UUID)</td>
                            <td>Kunci idempotensi unik per transaksi.</td>
                        </tr>
                    </tbody>
                </table>

                <!-- Callout Warning -->
                <div class="claude-callout callout-warning">
                    <div class="claude-callout-icon">⚠️</div>
                    <div>
                        <strong class="callout-title">Formula Kanonikal Signature</strong>
                        <code>STRING_TO_SIGN = METHOD + "\n" + PATH + "\n" + RAW_JSON_BODY + "\n" + TIMESTAMP</code>
                        <br>Pastikan urutan baris dan huruf besar pada HTTP method (contoh: <code>POST</code>) sesuai secara presisi.
                    </div>
                </div>

                <!-- Code Block with Language Tabs -->
                <div class="code-block" id="signature-sample">
                    <div class="code-nav">
                        <div class="code-tabs">
                            <button class="code-tab-btn active" onclick="switchCodeLang(this, 'php')">PHP</button>
                            <button class="code-tab-btn" onclick="switchCodeLang(this, 'node')">Node.js</button>
                            <button class="code-tab-btn" onclick="switchCodeLang(this, 'python')">Python</button>
                            <button class="code-tab-btn" onclick="switchCodeLang(this, 'curl')">cURL</button>
                        </div>
                        <button class="btn-copy-code" onclick="copyActiveCodeBlock()">Salin Kode</button>
                    </div>
                    <pre class="code-content" id="code-display">// PHP HMAC-SHA256 Signature
$method    = 'POST';
$path      = '/api/partner/transactions';
$rawBody   = json_encode([
    'partner_ref'     => 'REF-' . time(),
    'sku_code'        => 'TSEL20K',
    'customer_number' => '081234567890',
]);
$timestamp = gmdate('Y-m-d\TH:i:s\Z');
$payload   = "{$method}\n{$path}\n{$rawBody}\n{$timestamp}";
$signature = hash_hmac('sha256', $payload, $apiSecret);</pre>
                </div>
            </section>

            <!-- SECTION 3: RESPONSE ENVELOPES -->
            <section id="response-envelopes" class="doc-section">
                <h2>3. Standar Envelope Respon JSON</h2>
                <p>Seluruh respon aplikasi menggunakan format seragam kelas <code>ApiResponse</code> untuk memudahkan proses serialisasi di sisi mobile app maupun backend mitra.</p>

                <div class="endpoint-card">
                    <div class="endpoint-header">
                        <span style="font-weight: 700; color: var(--method-get);">Contoh Respon Sukses (200 OK)</span>
                    </div>
                    <div class="endpoint-body">
                        <pre style="font-family: var(--font-mono); font-size: 0.825rem; color: #a7f3d0;">{
  "success": true,
  "data": {
    "id": 105,
    "user_tier": "gold",
    "balance": 15000000,
    "balance_display": "Rp 150.000,00"
  },
  "meta": null
}</pre>
                    </div>
                </div>

                <div class="endpoint-card">
                    <div class="endpoint-header">
                        <span style="font-weight: 700; color: #f87171;">Contoh Respon Error (422 Unprocessable)</span>
                    </div>
                    <div class="endpoint-body">
                        <pre style="font-family: var(--font-mono); font-size: 0.825rem; color: #fca5a5;">{
  "success": false,
  "error_code": "INSUFFICIENT_BALANCE",
  "message": "Saldo dompet tidak mencukupi untuk melakukan transaksi ini.",
  "errors": null
}</pre>
                    </div>
                </div>
            </section>

            <!-- SECTION 4: MONEY STANDARD -->
            <section id="money-standard" class="doc-section">
                <h2>4. Standar Nominal Uang (Cents Precision)</h2>
                <p>Sistem <strong>tidak pernah menggunakan floating-point</strong> untuk angka mata uang. Seluruh angka nominal direpresentasikan dalam satuan **sen (cents)** menggunakan Value Object <code>Money</code>:</p>
                
                <table class="params-table">
                    <thead>
                        <tr>
                            <th>Nominal Riil</th>
                            <th>Nilai API (Integer / Cents)</th>
                            <th>Field Display (String)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Rp 10.000</td>
                            <td><code>1000000</code></td>
                            <td><code>"Rp 10.000,00"</code></td>
                        </tr>
                        <tr>
                            <td>Rp 25.500</td>
                            <td><code>2550000</code></td>
                            <td><code>"Rp 25.500,00"</code></td>
                        </tr>
                        <tr>
                            <td>Rp 500.000</td>
                            <td><code>50000000</code></td>
                            <td><code>"Rp 500.000,00"</code></td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <!-- SECTION 5: MOBILE CONVENIENCE APIS -->
            <section id="ep-home" class="doc-section">
                <h2>Mobile App APIs: Home &amp; Deteksi Operator</h2>

                <!-- Endpoint: GET /home -->
                <div class="endpoint-card">
                    <div class="endpoint-header">
                        <div class="endpoint-path-wrap">
                            <span class="method-badge method-get">GET</span>
                            <span>/api/v1/home</span>
                        </div>
                        <span style="font-size: 0.775rem; color: var(--text-dim);">Bearer Auth</span>
                    </div>
                    <div class="endpoint-body">
                        <p style="font-size: 0.875rem; margin-bottom: 0.75rem;">Mengambil ringkasan dashboard mobile pengguna: profil akun, saldo dompet terkini, daftar kategori produk aktif, dan 5 mutasi/transaksi terkini dalam satu panggilan ringan.</p>
                        <div class="code-block" style="margin-bottom: 0;">
                            <div class="code-nav"><span style="font-size: 0.75rem; color: var(--text-muted); padding-left: 0.5rem;">Respon 200 OK</span></div>
                            <pre class="code-content">{
  "success": true,
  "data": {
    "user": { "id": 1, "name": "Budi Santoso", "phone": "081234567890", "tier": "gold" },
    "wallet": { "balance": 45000000, "balance_display": "Rp 450.000,00" },
    "categories": [ { "id": 1, "name": "Pulsa", "code": "pulsa" }, { "id": 2, "name": "Paket Data", "code": "data" } ],
    "recent_transactions": [ ... 5 transaksi terakhir ... ]
  }
}</pre>
                        </div>
                    </div>
                </div>

                <!-- Endpoint: GET /products/operator-prefix -->
                <div id="ep-operator-prefix" class="endpoint-card" style="margin-top: 2rem;">
                    <div class="endpoint-header">
                        <div class="endpoint-path-wrap">
                            <span class="method-badge method-get">GET</span>
                            <span>/api/v1/products/operator-prefix</span>
                        </div>
                        <span style="font-size: 0.775rem; color: var(--text-dim);">Bearer Auth</span>
                    </div>
                    <div class="endpoint-body">
                        <p style="font-size: 0.875rem; margin-bottom: 0.75rem;">Mendeteksi operator seluler secara real-time saat pengguna mengetik nomor telepon pada aplikasi mobile dan langsung menyajikan produk pulsa/data dengan harga tier pengguna.</p>
                        <table class="params-table">
                            <thead>
                                <tr><th>Parameter Query</th><th>Tipe</th><th>Keterangan</th></tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><span class="param-name">phone</span> <span class="param-badge">Required</span></td>
                                    <td>String</td>
                                    <td>Nomor handphone input pengguna (contoh: <code>08123456789</code> atau <code>628123456789</code>).</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Endpoint: GET /wallet/channels -->
                <div id="ep-wallet-channels" class="endpoint-card" style="margin-top: 2rem;">
                    <div class="endpoint-header">
                        <div class="endpoint-path-wrap">
                            <span class="method-badge method-get">GET</span>
                            <span>/api/v1/wallet/channels</span>
                        </div>
                        <span style="font-size: 0.775rem; color: var(--text-dim);">Bearer Auth</span>
                    </div>
                    <div class="endpoint-body">
                        <p style="font-size: 0.875rem; margin-bottom: 0.75rem;">Menampilkan daftar kanal topup saldo yang tersedia: Virtual Account (BCA, Mandiri, BRI, BNI), QRIS &amp; E-Wallet, dan Transfer Bank Manual beserta biaya admin dan batas minimum/maksimum.</p>
                    </div>
                </div>

            </section>

            <!-- SECTION 6: EXPORT & TOOLS -->
            <section id="export-section" class="doc-section">
                <h2>Pusat Unduhan Tooling &amp; SDK</h2>
                <p>Unduh spesifikasi resmi dalam format OpenAPI 3.1 YAML atau gunakan koleksi Postman yang telah dikonfigurasi.</p>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1rem; margin: 1.5rem 0;">
                    
                    <div style="background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: 10px; padding: 1.5rem; display: flex; flex-direction: column; justify-content: space-between;">
                        <div>
                            <span style="font-size: 1.5rem;">📄</span>
                            <h4 style="font-size: 1.05rem; color: var(--text-heading); margin: 0.5rem 0 0.25rem 0;">OpenAPI 3.1 YAML</h4>
                            <p style="font-size: 0.825rem; color: var(--text-muted); margin-bottom: 1rem;">File spesifikasi standar industri untuk Swagger Editor, Insomnia, dan auto-generator client SDK.</p>
                        </div>
                        <a href="{{ $yamlDownloadUrl }}" class="btn-claude btn-claude-primary" download style="justify-content: center;">
                            ⬇️ Unduh openapi.yaml
                        </a>
                    </div>

                    <div style="background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: 10px; padding: 1.5rem; display: flex; flex-direction: column; justify-content: space-between;">
                        <div>
                            <span style="font-size: 1.5rem;">📮</span>
                            <h4 style="font-size: 1.05rem; color: var(--text-heading); margin: 0.5rem 0 0.25rem 0;">Postman Collection</h4>
                            <p style="font-size: 0.825rem; color: var(--text-muted); margin-bottom: 1rem;">Koleksi lengkap seluruh endpoint beserta Environment variabel lokal.</p>
                        </div>
                        <a href="{{ $postmanCollectionUrl }}" class="btn-claude btn-claude-secondary" download style="justify-content: center;">
                            📦 Unduh Koleksi Postman
                        </a>
                    </div>

                </div>
            </section>

        </main>

        <!-- 3. RIGHT SIDEBAR (On this page & Quick Tools) -->
        <aside class="column-toc">
            <div class="toc-title">Di Halaman Ini</div>
            <ul class="toc-list">
                <li><a href="#overview" class="toc-link active">1. Ikhtisar &amp; Arsitektur</a></li>
                <li><a href="#authentication" class="toc-link">2. Autentikasi (B2C &amp; B2B)</a></li>
                <li><a href="#response-envelopes" class="toc-link">3. Standar Envelope Respon</a></li>
                <li><a href="#money-standard" class="toc-link">4. Standar Nominal Uang</a></li>
                <li><a href="#ep-home" class="toc-link">5. Home Dashboard Mobile</a></li>
                <li><a href="#ep-operator-prefix" class="toc-link">6. Deteksi Operator Prefix</a></li>
                <li><a href="#ep-wallet-channels" class="toc-link">7. Topup Payment Channels</a></li>
                <li><a href="#export-section" class="toc-link">8. Unduhan Tooling &amp; SDK</a></li>
            </ul>

            <div class="toc-tools-box">
                <div class="toc-title">Aksi Cepat</div>
                <a href="{{ $yamlDownloadUrl }}" class="tool-link-btn" download>
                    <span>📄</span> Unduh File .yaml
                </a>
                <a href="https://editor.swagger.io/?url={{ urlencode($swaggerSpecUrl) }}" target="_blank" rel="noopener noreferrer" class="tool-link-btn">
                    <span>🌐</span> Buka di Swagger Editor
                </a>
                <a href="/docs" class="tool-link-btn">
                    <span>⚡</span> Buka Swagger UI (v1)
                </a>
            </div>
        </aside>

    </div>

    <!-- Toast Notification -->
    <div id="toast">
        <span id="toast-text">Tersalin ke papan klip!</span>
    </div>

    <script>
@verbatim
        // Multi-language code snippets
        const codeSnippets = {
            php: `// PHP Sample
$method    = 'POST';
$path      = '/api/partner/transactions';
$rawBody   = json_encode([
    'partner_ref'     => 'REF-' . time(),
    'sku_code'        => 'TSEL20K',
    'customer_number' => '081234567890',
]);
$timestamp = gmdate('Y-m-d\\TH:i:s\\Z');
$payload   = "{$method}\\n{$path}\\n{$rawBody}\\n{$timestamp}";
$signature = hash_hmac('sha256', $payload, $apiSecret);`,
            node: `const crypto = require('crypto');

const method    = 'POST';
const path      = '/api/partner/transactions';
const rawBody   = JSON.stringify({
    partner_ref: 'REF-' + Date.now(),
    sku_code: 'TSEL20K',
    customer_number: '081234567890'
});
const timestamp = new Date().toISOString();
const payload   = \`\${method}\\n\${path}\\n\${rawBody}\\n\${timestamp}\`;
const signature = crypto.createHmac('sha256', apiSecret).update(payload).digest('hex');`,
            python: `import hmac
import hashlib
import json
from datetime import datetime, timezone

method    = "POST"
path      = "/api/partner/transactions"
raw_body  = json.dumps({
    "partner_ref": "REF-123456",
    "sku_code": "TSEL20K",
    "customer_number": "081234567890"
})
timestamp = datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ")
payload   = f"{method}\\n{path}\\n{raw_body}\\n{timestamp}"
signature = hmac.new(api_secret.encode(), payload.encode(), hashlib.sha256).hexdigest()`,
            curl: `curl -X POST https://api.ppob.example.com/api/partner/transactions \\
  -H "Content-Type: application/json" \\
  -H "X-Api-Key: pk_live_abc123" \\
  -H "X-Signature: c8f3...hex..." \\
  -H "X-Timestamp: 2026-10-09T15:30:00Z" \\
  -H "Idempotency-Key: \$(uuidgen)" \\
  -d '{"partner_ref":"REF-001","sku_code":"TSEL20K","customer_number":"081234567890"}'`
        };
@endverbatim

        let currentLang = 'php';

        function switchCodeLang(btn, lang) {
            document.querySelectorAll('.code-tab-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            currentLang = lang;
            document.getElementById('code-display').innerText = codeSnippets[lang];
        }

        function copyActiveCodeBlock() {
            copySnippetText(codeSnippets[currentLang]);
        }

        function copySnippetText(text) {
            navigator.clipboard.writeText(text).then(() => {
                showToast("Kode berhasil disalin!");
            });
        }

        function showToast(msg) {
            const toast = document.getElementById('toast');
            document.getElementById('toast-text').innerText = msg;
            toast.classList.add('show');
            setTimeout(() => toast.classList.remove('show'), 2500);
        }

        // Theme Toggle
        function toggleTheme() {
            const html = document.documentElement;
            const isDark = html.classList.contains('dark');
            if (isDark) {
                html.classList.remove('dark');
                html.classList.add('light');
                document.getElementById('theme-icon').innerText = '🌙';
                localStorage.setItem('claude_theme', 'light');
            } else {
                html.classList.remove('light');
                html.classList.add('dark');
                document.getElementById('theme-icon').innerText = '☀️';
                localStorage.setItem('claude_theme', 'dark');
            }
        }

        // Init Theme
        if (localStorage.getItem('claude_theme') === 'light') {
            document.documentElement.classList.remove('dark');
            document.documentElement.classList.add('light');
            document.getElementById('theme-icon').innerText = '🌙';
        }

        // Filter Sidebar by Search
        function filterDocs(query) {
            const q = query.toLowerCase().trim();
            document.querySelectorAll('#sidebar-nav .nav-item-link').forEach(link => {
                const text = link.innerText.toLowerCase();
                if (text.includes(q)) {
                    link.style.display = 'flex';
                } else {
                    link.style.display = 'none';
                }
            });
        }

        // Scrollspy for TOC
        window.addEventListener('scroll', () => {
            const sections = document.querySelectorAll('section.doc-section');
            let current = '';
            sections.forEach(sec => {
                const top = sec.offsetTop - 120;
                if (window.pageYOffset >= top) {
                    current = sec.getAttribute('id');
                }
            });

            document.querySelectorAll('.toc-link').forEach(a => {
                a.classList.remove('active');
                if (a.getAttribute('href') === '#' + current) {
                    a.classList.add('active');
                }
            });
        });

        // Shortcut ⌘K / Ctrl+K
        window.addEventListener('keydown', (e) => {
            if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
                e.preventDefault();
                document.getElementById('claude-search').focus();
            }
        });
    </script>
</body>
</html>
