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

        html {
            scroll-behavior: smooth;
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
            scroll-margin-top: 5rem;
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

        /* Modal Interactive Tester */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.75);
            backdrop-filter: blur(4px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10000;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.2s ease;
            padding: 1rem;
        }

        .modal-overlay.open {
            opacity: 1;
            pointer-events: auto;
        }

        .modal-card {
            background: var(--bg-surface);
            border: 1px solid var(--border-color);
            border-radius: 14px;
            max-width: 780px;
            width: 100%;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5);
            transform: scale(0.96);
            transition: transform 0.2s ease;
        }

        .modal-overlay.open .modal-card {
            transform: scale(1);
        }

        .modal-header {
            padding: 1.15rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid var(--border-color);
            background: var(--bg-surface-elevated);
        }

        .modal-header h3 {
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--text-heading);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .modal-close-btn {
            background: transparent;
            border: none;
            color: var(--text-muted);
            font-size: 1.5rem;
            cursor: pointer;
            line-height: 1;
            padding: 0.25rem;
            transition: color 0.15s;
        }

        .modal-close-btn:hover {
            color: var(--text-heading);
        }

        .modal-body {
            padding: 1.25rem 1.5rem;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .preset-badge-btn {
            background: var(--bg-surface-elevated);
            border: 1px solid var(--border-color);
            border-radius: 6px;
            padding: 0.35rem 0.65rem;
            color: var(--text-body);
            font-size: 0.75rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.15s;
        }

        .preset-badge-btn:hover {
            border-color: var(--accent);
            color: var(--accent);
        }

        .console-form-group {
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
        }

        .console-form-group label {
            font-size: 0.775rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .console-input-row {
            display: flex;
            gap: 0.5rem;
        }

        .console-select {
            background: var(--bg-code);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--accent);
            font-weight: 700;
            font-family: var(--font-mono);
            padding: 0.5rem 0.75rem;
            font-size: 0.85rem;
            outline: none;
        }

        .console-input {
            flex: 1;
            background: var(--bg-code);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-heading);
            font-family: var(--font-mono);
            padding: 0.5rem 0.75rem;
            font-size: 0.85rem;
            outline: none;
        }

        .console-input:focus, .console-select:focus, .console-textarea:focus {
            border-color: var(--accent);
        }

        .console-textarea {
            background: var(--bg-code);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-heading);
            font-family: var(--font-mono);
            padding: 0.5rem 0.75rem;
            font-size: 0.825rem;
            outline: none;
            resize: vertical;
            min-height: 80px;
        }

        .console-response-box {
            background: var(--bg-code);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 1rem;
            font-family: var(--font-mono);
            font-size: 0.8rem;
            color: #e5e7eb;
            max-height: 240px;
            overflow-y: auto;
            white-space: pre-wrap;
            word-break: break-all;
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
                <button class="btn-claude btn-claude-secondary" onclick="openTesterModal()" style="border-color: rgba(217, 119, 87, 0.4); color: var(--accent);">
                    <span>⚡</span> API Console
                </button>
                <a href="/admin" class="btn-claude btn-claude-secondary" title="Buka Dashboard Web Admin">
                    <span>💼</span> Web Admin
                </a>
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
                <a href="#sandbox-data" class="nav-item-link"><span>🧪 Data Uji Sandbox</span></a>
                <a href="#sequence-diagrams" class="nav-item-link"><span>📊 Diagram Alur Transaksi</span></a>
                <a href="#authentication" class="nav-item-link"><span>2. Autentikasi (B2C &amp; B2B)</span></a>
                <a href="#response-envelopes" class="nav-item-link"><span>3. Standar Envelope Respon</span></a>
                <a href="#error-matrix" class="nav-item-link"><span>🎯 Matriks Error &amp; UI</span></a>
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

            <!-- SECTION: SANDBOX TEST DATA -->
            <section id="sandbox-data" class="doc-section">
                <h2>🧪 Data Pengujian Sandbox &amp; Kredensial Uji Coba</h2>
                <p>Gunakan kredensial pengujian berikut untuk melakukan simulasi transaksi di lingkungan lokal / sandbox tanpa menggunakan dana riil:</p>

                <div class="claude-callout callout-tip">
                    <div class="claude-callout-icon">💡</div>
                    <div>
                        <strong class="callout-title">Database Seeder Terintegrasi</strong>
                        Seluruh akun pengujian di bawah ini sudah tersedia secara otomatis di database melalui seeder <code>UserDemoSeeder</code> dan <code>PartnerDemoSeeder</code> dengan saldo aktif siap pakai.
                    </div>
                </div>

                <h3>A. Akun Demo Konsumen Mobile (B2C)</h3>
                <table class="params-table">
                    <thead>
                        <tr>
                            <th>Profil Akun</th>
                            <th>Nomor HP</th>
                            <th>OTP / PIN Default</th>
                            <th>Tier &amp; Saldo Demo</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>Budi Pelanggan Setia</strong></td>
                            <td><code>081400000004</code></td>
                            <td><code>123456</code></td>
                            <td>End User (Rp 500.000,00)</td>
                            <td><button class="btn-copy-code" onclick="copySnippetText('081400000004')">Salin HP</button></td>
                        </tr>
                        <tr>
                            <td><strong>Agen Mitra Retail</strong></td>
                            <td><code>081200000002</code></td>
                            <td><code>123456</code></td>
                            <td>Agent (Rp 2.500.000,00)</td>
                            <td><button class="btn-copy-code" onclick="copySnippetText('081200000002')">Salin HP</button></td>
                        </tr>
                        <tr>
                            <td><strong>Reseller Grosir Pulsa</strong></td>
                            <td><code>081300000003</code></td>
                            <td><code>123456</code></td>
                            <td>Reseller (Rp 5.000.000,00)</td>
                            <td><button class="btn-copy-code" onclick="copySnippetText('081300000003')">Salin HP</button></td>
                        </tr>
                    </tbody>
                </table>

                <h3>B. Nomor Handphone Uji Prefix Operator</h3>
                <table class="params-table">
                    <thead>
                        <tr>
                            <th>Operator Seluler</th>
                            <th>Contoh Nomor Input</th>
                            <th>Produk Terhubung</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong style="color: #ef4444;">Telkomsel</strong></td>
                            <td><code>081234567890</code></td>
                            <td>Pulsa 5K s/d 100K, Paket Data 15GB</td>
                        </tr>
                        <tr>
                            <td><strong style="color: #f59e0b;">Indosat Ooredoo</strong></td>
                            <td><code>081512345678</code></td>
                            <td>Pulsa 10K s/d 50K, Paket Freedom Data</td>
                        </tr>
                        <tr>
                            <td><strong style="color: #3b82f6;">XL / Axis</strong></td>
                            <td><code>081812345678</code> / <code>083812345678</code></td>
                            <td>Pulsa Reguler &amp; Paket Xtra Combo</td>
                        </tr>
                        <tr>
                            <td><strong style="color: #a855f7;">Tri (3)</strong></td>
                            <td><code>089612345678</code></td>
                            <td>Pulsa Tri &amp; AlwaysOn Data</td>
                        </tr>
                        <tr>
                            <td><strong style="color: #ec4899;">Smartfren</strong></td>
                            <td><code>088112345678</code></td>
                            <td>Pulsa Smartfren &amp; Paket Kuota Nonstop</td>
                        </tr>
                    </tbody>
                </table>

                <h3>C. Nomor Pelanggan Uji Pascabayar (Postpaid / Tagihan)</h3>
                <table class="params-table">
                    <thead>
                        <tr>
                            <th>Layanan Tagihan</th>
                            <th>ID Pelanggan Demo</th>
                            <th>Nominal Tagihan</th>
                            <th>Admin Fee</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>PLN Pascabayar</strong></td>
                            <td><code>512345678901</code></td>
                            <td>Rp 250.000,00</td>
                            <td>Rp 2.500,00</td>
                        </tr>
                        <tr>
                            <td><strong>BPJS Kesehatan</strong></td>
                            <td><code>8888812345678901</code></td>
                            <td>Rp 70.000,00</td>
                            <td>Rp 2.500,00</td>
                        </tr>
                        <tr>
                            <td><strong>PDAM Tirta</strong></td>
                            <td><code>1002345678</code></td>
                            <td>Rp 85.000,00</td>
                            <td>Rp 2.500,00</td>
                        </tr>
                    </tbody>
                </table>

                <h3>D. Kredensial Pengujian Mitra B2B (Open API)</h3>
                <div class="code-block">
                    <div class="code-nav">
                        <span style="font-size: 0.75rem; color: var(--text-muted); padding-left: 0.5rem;">Partner B2B Demo (PT Mitra Finansial Solusindo)</span>
                        <button class="btn-copy-code" onclick="copySnippetText('partner_live_demo123')">Salin API Key</button>
                    </div>
                    <pre class="code-content">X-Api-Key:      partner_live_demo123
API Secret:     partner_secret_demo456
Allowed IPs:    Semua IP (Wildcard Mode)
Saldo Deposit:  Rp 15.000.000,00
Custom Price:   TSEL20K = Rp 20.200 | PLN50 = Rp 50.200</pre>
                </div>
            </section>

            <!-- SECTION: SEQUENCE DIAGRAMS -->
            <section id="sequence-diagrams" class="doc-section">
                <h2>📊 Diagram Alur Transaksi (Visual Sequence)</h2>
                <p>Memahami perjalanan request dari aplikasi pengguna hingga konfirmasi terminal di server backend:</p>

                <h3>1. Alur Transaksi Prabayar (Prepaid Flow — Pulsa / Paket Data)</h3>
                <div class="endpoint-card">
                    <div class="endpoint-body">
                        <div style="display: flex; flex-direction: column; gap: 0.75rem; font-family: var(--font-mono); font-size: 0.825rem;">
                            <div style="background: var(--bg-surface-elevated); padding: 0.75rem 1rem; border-radius: 8px; border-left: 4px solid var(--accent);">
                                <strong>Step 1 (Input Nomor):</strong> Mobile App mengetik nomor &rarr; <code>GET /products/operator-prefix?phone=...</code> &rarr; Deteksi Operator &amp; Ambil Harga Tier
                            </div>
                            <div style="background: var(--bg-surface-elevated); padding: 0.75rem 1rem; border-radius: 8px; border-left: 4px solid #3b82f6;">
                                <strong>Step 2 (PIN Challenge):</strong> Mobile App memanggil <code>POST /security/pin/challenge</code> &rarr; Backend merespon <code>challenge_id</code>
                            </div>
                            <div style="background: var(--bg-surface-elevated); padding: 0.75rem 1rem; border-radius: 8px; border-left: 4px solid #f59e0b;">
                                <strong>Step 3 (Verifikasi PIN):</strong> Kirim PIN 6-digit ke <code>POST /security/pin/verify</code> &rarr; Mendapatkan <code>pin_verification_token</code> (Zero Exposure)
                            </div>
                            <div style="background: var(--bg-surface-elevated); padding: 0.75rem 1rem; border-radius: 8px; border-left: 4px solid #10b981;">
                                <strong>Step 4 (Eksekusi Transaksi):</strong> <code>POST /transactions</code> dengan header <code>X-Pin-Token</code> &amp; <code>Idempotency-Key</code> &rarr; Saldo didebit atomik &rarr; Dispatch Job ke Provider Antrean
                            </div>
                            <div style="background: var(--bg-surface-elevated); padding: 0.75rem 1rem; border-radius: 8px; border-left: 4px solid var(--accent);">
                                <strong>Step 5 (Failover Otomatis):</strong> Jika provider 1 timeout/gangguan &rarr; Circuit Breaker mengalihkan otomatis ke Provider Cadangan &rarr; Pulsa masuk ke HP pelanggan
                            </div>
                        </div>
                    </div>
                </div>

                <h3>2. Alur Transaksi Pascabayar (Postpaid Flow — Tagihan PLN / BPJS)</h3>
                <div class="endpoint-card">
                    <div class="endpoint-body">
                        <div style="display: flex; flex-direction: column; gap: 0.75rem; font-family: var(--font-mono); font-size: 0.825rem;">
                            <div style="background: var(--bg-surface-elevated); padding: 0.75rem 1rem; border-radius: 8px; border-left: 4px solid #3b82f6;">
                                <strong>Step 1 (Inquiry Tagihan):</strong> <code>POST /inquiry</code> (sku_code: PLN-POSTPAID, customer_number: 512345678901) &rarr; Sistem menanyakan ke biller &rarr; Hasil inquiry di-cache selama 10 menit
                            </div>
                            <div style="background: var(--bg-surface-elevated); padding: 0.75rem 1rem; border-radius: 8px; border-left: 4px solid #f59e0b;">
                                <strong>Step 2 (Review Rincian):</strong> Mobile App menampilkan rincian: Nama Pelanggan, Periode, Jumlah Tagihan (Cents), dan Admin Fee
                            </div>
                            <div style="background: var(--bg-surface-elevated); padding: 0.75rem 1rem; border-radius: 8px; border-left: 4px solid #10b981;">
                                <strong>Step 3 (Otorisasi &amp; Bayar):</strong> Verifikasi PIN &rarr; <code>POST /transactions</code> (product_type: postpaid, inquiry_id) &rarr; Tagihan dilunasi seketika
                            </div>
                        </div>
                    </div>
                </div>

                <h3>3. Alur B2B Async Webhook &amp; Exponential Retry Engine</h3>
                <div class="endpoint-card">
                    <div class="endpoint-body">
                        <div style="display: flex; flex-direction: column; gap: 0.75rem; font-family: var(--font-mono); font-size: 0.825rem;">
                            <div style="background: var(--bg-surface-elevated); padding: 0.75rem 1rem; border-radius: 8px; border-left: 4px solid #3b82f6;">
                                <strong>Step 1 (Order Masuk):</strong> Mitra memanggil <code>POST /partner/transactions</code> (HMAC Signed) &rarr; Backend merespon <code>202 Accepted</code> (status: pending)
                            </div>
                            <div style="background: var(--bg-surface-elevated); padding: 0.75rem 1rem; border-radius: 8px; border-left: 4px solid #10b981;">
                                <strong>Step 2 (Terminal Status):</strong> Transaksi diproses di antrean provider hingga berstatus <code>success</code> atau <code>failed</code>
                            </div>
                            <div style="background: var(--bg-surface-elevated); padding: 0.75rem 1rem; border-radius: 8px; border-left: 4px solid var(--accent);">
                                <strong>Step 3 (Webhook Callback):</strong> <code>DeliverWebhookJob</code> mengirim HTTP POST ke <code>callback_url</code> mitra dengan header <code>X-Signature</code>
                            </div>
                            <div style="background: var(--bg-surface-elevated); padding: 0.75rem 1rem; border-radius: 8px; border-left: 4px solid #ef4444;">
                                <strong>Step 4 (Retry Engine):</strong> Jika server mitra down (5xx/timeout): Sistem otomatis retry pada Attempt 2 (+1m), Attempt 3 (+5m), Attempt 4 (+30m), dan Attempt 5 (+2j)
                            </div>
                        </div>
                    </div>
                </div>
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

            <!-- SECTION: ERROR HANDLING MATRIX -->
            <section id="error-matrix" class="doc-section">
                <h2>🎯 Matriks Error Handling &amp; Panduan Mobile UI</h2>
                <p>Panduan terstandarisasi untuk tim pengembang Mobile App (Flutter / React Native / Kotlin / Swift) dalam merespon error code dari backend:</p>

                <div class="claude-callout callout-note">
                    <div class="claude-callout-icon">💡</div>
                    <div>
                        <strong class="callout-title">Desain UI Ramah Pengguna</strong>
                        Gunakan string <code>error_code</code> sebagai kunci percabangan logika UI, bukan teks pada <code>message</code>, agar aplikasi siap mendukung lokalisasi multi-bahasa.
                    </div>
                </div>

                <table class="params-table">
                    <thead>
                        <tr>
                            <th>Error Code API</th>
                            <th>HTTP Status</th>
                            <th>Pemicu &amp; Kondisi</th>
                            <th>Rekomendasi Tindakan UI Mobile</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>UNAUTHENTICATED</code></td>
                            <td><span style="color: #ef4444; font-weight: 700;">401</span></td>
                            <td>Sesi login kedaluwarsa atau token Bearer Sanctum tidak valid/revoked.</td>
                            <td>Hapus token lokal, alihkan pengguna ke layar Login/OTP dengan notifikasi toast <em>"Sesi berakhir, silakan masuk kembali"</em>.</td>
                        </tr>
                        <tr>
                            <td><code>INSUFFICIENT_BALANCE</code></td>
                            <td><span style="color: #f59e0b; font-weight: 700;">422</span></td>
                            <td>Saldo dompet pengguna atau deposit mitra kurang dari total tagihan + admin.</td>
                            <td>Munculkan BottomSheet <em>"Saldo Tidak Cukup"</em> lengkap dengan info sisa saldo dan tombol CTA <strong>"Top Up Sekarang"</strong>.</td>
                        </tr>
                        <tr>
                            <td><code>INVALID_PIN</code></td>
                            <td><span style="color: #f59e0b; font-weight: 700;">422</span></td>
                            <td>PIN 6-digit salah dimasukkan pada saat verifikasi challenge.</td>
                            <td>Kosongkan kotak PIN, trigger haptic feedback getar, dan tampilkan indikator sisa percobaan (maks 3x sebelum terkunci).</td>
                        </tr>
                        <tr>
                            <td><code>PIN_BLOCKED</code></td>
                            <td><span style="color: #ef4444; font-weight: 700;">423</span></td>
                            <td>Akun diblokir sementara karena salah memasukkan PIN 3 kali berturut-turut.</td>
                            <td>Kunci form PIN, tampilkan modal <em>"Akun Diamankan Sementara"</em> dengan tombol bantuan alur Reset PIN via WhatsApp OTP.</td>
                        </tr>
                        <tr>
                            <td><code>PRODUCT_INACTIVE</code><br><code>OUT_OF_STOCK</code></td>
                            <td><span style="color: #f59e0b; font-weight: 700;">422</span></td>
                            <td>Produk sedang mengalami pemeliharaan operator (cut-off) atau kehabisan stok.</td>
                            <td>Beri label badge <em>"Gangguan"</em> abu-abu pada kartu produk, disable tombol checkout, dan rekomendasikan nominal terdekat.</td>
                        </tr>
                        <tr>
                            <td><code>BILL_ALREADY_PAID</code></td>
                            <td><span style="color: #3b82f6; font-weight: 700;">422</span></td>
                            <td>Tagihan pascabayar (PLN/BPJS/PDAM) sudah lunas dibayar di tempat/kanal lain.</td>
                            <td>Tampilkan dialog informasi <em>"Tagihan Sudah Lunas"</em> agar pelanggan tidak panik terjadi penagihan berulang.</td>
                        </tr>
                        <tr>
                            <td><code>INVALID_CUSTOMER_NUMBER</code></td>
                            <td><span style="color: #f59e0b; font-weight: 700;">422</span></td>
                            <td>ID pelanggan atau nomor meter tidak ditemukan di biller resmi.</td>
                            <td>Sorot kotak input dengan border merah dan tampilkan hint <em>"Periksa kembali nomor meter/pelanggan Anda"</em>.</td>
                        </tr>
                        <tr>
                            <td><code>RATE_LIMIT_EXCEEDED</code></td>
                            <td><span style="color: #ef4444; font-weight: 700;">429</span></td>
                            <td>Permintaan melebihi kuota proteksi anti-spam (brute-force throttle).</td>
                            <td>Tampilkan banner countdown waktu mundur (cth: <em>"Coba lagi dalam 60 detik"</em>) dan nonaktifkan tombol submit.</td>
                        </tr>
                        <tr>
                            <td><code>TRANSACTION_PENDING</code></td>
                            <td><span style="color: #10b981; font-weight: 700;">202</span></td>
                            <td>Transaksi sedang dalam proses antrean di gateway provider switching.</td>
                            <td>Buka halaman <strong>Status Transaksi (Menunggu)</strong>, pasang polling status berkala (tiap 5 detik maks 1 menit) atau tunggu push notifikasi FCM.</td>
                        </tr>
                        <tr>
                            <td><code>PROVIDER_TIMEOUT</code></td>
                            <td><span style="color: #6366f1; font-weight: 700;">504</span></td>
                            <td>Provider hulu belum memberikan respon akhir dalam batas SLA transaksi.</td>
                            <td>Jamin bahwa saldo tidak terpotong ganda, berikan status <em>"Diproses Latar Belakang"</em> dan tawarkan riwayat mutasi.</td>
                        </tr>
                    </tbody>
                </table>
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

            <!-- SECTION: CORE SECURITY & ENGINE -->
            <section id="security-pin" class="doc-section">
                <h2>Keamanan PIN 2-Step (Zero Exposure Flow)</h2>
                <p>Untuk melindungi akun pengguna dari penyadapan atau *man-in-the-middle attack*, aplikasi mobile <strong>tidak pernah mengirimkan PIN 6-digit secara mentah</strong> pada saat eksekusi pembelian transaksi.</p>

                <div class="claude-callout callout-tip">
                    <div class="claude-callout-icon">🔐</div>
                    <div>
                        <strong class="callout-title">Prinsip Zero Exposure</strong>
                        PIN diverifikasi di endpoint khusus untuk menukar challenge ID menjadi <code>pin_verification_token</code> (valid 5 menit). Saat eksekusi pembelian produk di <code>POST /transactions</code>, aplikasi hanya mengirimkan header <code>X-Pin-Token: {token}</code> yang langsung dikonsumsi sekali pakai.
                    </div>
                </div>

                <div class="endpoint-card">
                    <div class="endpoint-header">
                        <div class="endpoint-path-wrap">
                            <span class="method-badge method-post">POST</span>
                            <span>/api/v1/security/pin/challenge</span>
                        </div>
                        <span style="font-size: 0.775rem; color: var(--text-dim);">Step 1: Minta Challenge</span>
                    </div>
                    <div class="endpoint-body">
                        <p style="font-size: 0.875rem;">Mendapatkan <code>challenge_id</code> unik untuk memulai verifikasi PIN pengguna.</p>
                    </div>
                </div>

                <div class="endpoint-card">
                    <div class="endpoint-header">
                        <div class="endpoint-path-wrap">
                            <span class="method-badge method-post">POST</span>
                            <span>/api/v1/security/pin/verify</span>
                        </div>
                        <span style="font-size: 0.775rem; color: var(--text-dim);">Step 2: Verifikasi &amp; Dapatkan Token</span>
                    </div>
                    <div class="endpoint-body">
                        <p style="font-size: 0.875rem;">Mengirimkan <code>challenge_id</code> dan <code>pin</code> 6-digit. Jika valid, sistem mengembalikan <code>pin_verification_token</code>. Dilengkapi proteksi brute force (lockout 15 menit setelah 5x salah berturut-turut).</p>
                    </div>
                </div>
            </section>

            <section id="multi-supplier" class="doc-section">
                <h2>Multi-Supplier Failover &amp; Circuit Breaker (ADR-004 &amp; ADR-005)</h2>
                <p>Engine PPOB dilengkapi sistem perutean multi-supplier otomatis (Digiflazz, VIP Payment, dll) untuk menjamin tingkat keberhasilan transaksi maksimal:</p>
                
                <div class="claude-callout callout-note">
                    <div class="claude-callout-icon">⚡</div>
                    <div>
                        <strong class="callout-title">Circuit Breaker Otomatis</strong>
                        Jika supplier utama mengalami lonjakan error di atas threshold, Circuit Breaker otomatis membuka (trip) dan mengalihkan antrean transaksi ke supplier cadangan tanpa downtime.
                    </div>
                </div>

                <ul style="margin-left: 1.5rem; margin-bottom: 1.25rem;">
                    <li><strong>Hirarki Routing:</strong> Aturan spesifik kategori &rarr; fallback ke aturan umum mitra &rarr; fallback default aman.</li>
                    <li><strong>Preferensi Mitra B2B:</strong> Mitra dapat mengaktifkan <code>allow_failover: true</code> dan memilih kebijakan <code>failover_policy</code> (none / same_category / any).</li>
                </ul>
            </section>

            <section id="webhooks" class="doc-section">
                <h2>Async Webhook Delivery Engine (ADR-007 &amp; ADR-008)</h2>
                <p>Untuk transaksi B2B yang diproses secara asinkron (respon awal <code>202 Accepted</code>), sistem mengirimkan callback HTTP POST ke <code>callback_url</code> mitra ketika transaksi mencapai status terminal (sukses/gagal).</p>

                <div class="claude-callout callout-warning">
                    <div class="claude-callout-icon">🛡️</div>
                    <div>
                        <strong class="callout-title">Keamanan Header Webhook</strong>
                        Setiap payload webhook ditandatangani menggunakan API Secret mitra: header <code>X-Signature</code> (HMAC-SHA256), <code>X-Timestamp</code>, dan <code>X-Event-Id</code> unik.
                    </div>
                </div>

                <p><strong>Jadwal Percobaan Ulang Otomatis (Exponential Backoff):</strong></p>
                <table class="params-table">
                    <thead>
                        <tr><th>Percobaan (Attempt)</th><th>Penundaan (Delay)</th><th>Status &amp; Penanganan</th></tr>
                    </thead>
                    <tbody>
                        <tr><td>Attempt 1</td><td>0 Detik (Seketika)</td><td>Kirim segera saat status transaksi terminal.</td></tr>
                        <tr><td>Attempt 2</td><td>+1 Menit</td><td>Percobaan ulang pertama jika server mitra timeout/error.</td></tr>
                        <tr><td>Attempt 3</td><td>+5 Menit</td><td>Percobaan ulang kedua.</td></tr>
                        <tr><td>Attempt 4</td><td>+30 Menit</td><td>Percobaan ulang ketiga.</td></tr>
                        <tr><td>Attempt 5</td><td>+2 Jam</td><td>Percobaan final. Jika gagal, ditandai <code>failed_permanent</code> &amp; memicu alert devops.</td></tr>
                    </tbody>
                </table>
            </section>

            <!-- SECTION: MOBILE CONVENIENCE APIS -->
            <section id="ep-home" class="doc-section">
                <h2>Mobile App APIs (B2C)</h2>

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

                <!-- Endpoint: GET /products -->
                <div id="ep-products" class="endpoint-card" style="margin-top: 2rem;">
                    <div class="endpoint-header">
                        <div class="endpoint-path-wrap">
                            <span class="method-badge method-get">GET</span>
                            <span>/api/v1/products</span>
                        </div>
                        <span style="font-size: 0.775rem; color: var(--text-dim);">Bearer Auth</span>
                    </div>
                    <div class="endpoint-body">
                        <p style="font-size: 0.875rem; margin-bottom: 0.75rem;">Katalog lengkap produk PPOB dengan filter kategori (<code>category=pulsa</code>) atau provider (<code>provider=telkomsel</code>), lengkap dengan kalkulasi harga tier pengguna yang login.</p>
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

                <!-- Endpoint: GET /wallet/mutations -->
                <div id="ep-wallet-mutations" class="endpoint-card" style="margin-top: 2rem;">
                    <div class="endpoint-header">
                        <div class="endpoint-path-wrap">
                            <span class="method-badge method-get">GET</span>
                            <span>/api/v1/wallet/mutations</span>
                        </div>
                        <span style="font-size: 0.775rem; color: var(--text-dim);">Bearer Auth</span>
                    </div>
                    <div class="endpoint-body">
                        <p style="font-size: 0.875rem; margin-bottom: 0.75rem;">Riwayat mutasi saldo dompet pengguna (kredit topup &amp; debit pembelian produk) dengan paginasi dan filter jenis mutasi.</p>
                    </div>
                </div>

                <!-- Endpoint: PUT /auth/profile -->
                <div id="ep-auth-profile" class="endpoint-card" style="margin-top: 2rem;">
                    <div class="endpoint-header">
                        <div class="endpoint-path-wrap">
                            <span class="method-badge method-put">PUT</span>
                            <span>/api/v1/auth/profile</span>
                        </div>
                        <span style="font-size: 0.775rem; color: var(--text-dim);">Bearer Auth</span>
                    </div>
                    <div class="endpoint-body">
                        <p style="font-size: 0.875rem; margin-bottom: 0.75rem;">Memperbarui profil pengguna (nama dan/atau alamat email) yang sedang login.</p>
                    </div>
                </div>

                <!-- Endpoint: POST /auth/fcm-token -->
                <div id="ep-auth-fcm" class="endpoint-card" style="margin-top: 2rem;">
                    <div class="endpoint-header">
                        <div class="endpoint-path-wrap">
                            <span class="method-badge method-post">POST</span>
                            <span>/api/v1/auth/fcm-token</span>
                        </div>
                        <span style="font-size: 0.775rem; color: var(--text-dim);">Bearer Auth</span>
                    </div>
                    <div class="endpoint-body">
                        <p style="font-size: 0.875rem; margin-bottom: 0.75rem;">Mendaftarkan atau memperbarui token Firebase Cloud Messaging (FCM) perangkat untuk push notification.</p>
                    </div>
                </div>

            </section>

            <!-- SECTION: PARTNER OPEN API -->
            <section id="partner-apis" class="doc-section">
                <h2>Partner Open API (B2B)</h2>
                <p>Endpoint khusus untuk mitra bisnis B2B dengan autentikasi API Key, HMAC-SHA256 signature, dan IP Whitelist.</p>

                <!-- Endpoint: GET /partner/balance -->
                <div id="ep-partner-balance" class="endpoint-card">
                    <div class="endpoint-header">
                        <div class="endpoint-path-wrap">
                            <span class="method-badge method-get">GET</span>
                            <span>/api/partner/balance</span>
                        </div>
                        <span style="font-size: 0.775rem; color: var(--text-dim);">HMAC Auth</span>
                    </div>
                    <div class="endpoint-body">
                        <p style="font-size: 0.875rem; margin-bottom: 0.75rem;">Pengecekan sisa saldo deposit mitra bisnis dalam satuan sen (cents) beserta saldo tampilan format Rupiah.</p>
                    </div>
                </div>

                <!-- Endpoint: POST /partner/transactions -->
                <div id="ep-partner-tx" class="endpoint-card" style="margin-top: 2rem;">
                    <div class="endpoint-header">
                        <div class="endpoint-path-wrap">
                            <span class="method-badge method-post">POST</span>
                            <span>/api/partner/transactions</span>
                        </div>
                        <span style="font-size: 0.775rem; color: var(--text-dim);">HMAC Auth</span>
                    </div>
                    <div class="endpoint-body">
                        <p style="font-size: 0.875rem; margin-bottom: 0.75rem;">Pembuatan transaksi pembelian produk PPOB oleh mitra. Mendukung mode <code>sync</code> (respon 201 Created) dan mode <code>async</code> (respon 202 Accepted dengan webhook callback).</p>
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
                <li><a href="#sandbox-data" class="toc-link">🧪 Data Uji Sandbox</a></li>
                <li><a href="#sequence-diagrams" class="toc-link">📊 Diagram Alur Transaksi</a></li>
                <li><a href="#authentication" class="toc-link">2. Autentikasi (B2C &amp; B2B)</a></li>
                <li><a href="#response-envelopes" class="toc-link">3. Standar Envelope Respon</a></li>
                <li><a href="#error-matrix" class="toc-link">🎯 Matriks Error &amp; UI</a></li>
                <li><a href="#money-standard" class="toc-link">4. Standar Nominal Uang</a></li>
                <li><a href="#security-pin" class="toc-link">Keamanan PIN 2-Step</a></li>
                <li><a href="#multi-supplier" class="toc-link">Multi-Supplier Failover</a></li>
                <li><a href="#webhooks" class="toc-link">Async Webhook Delivery</a></li>
                <li><a href="#ep-home" class="toc-link">Mobile App APIs</a></li>
                <li><a href="#ep-partner-balance" class="toc-link">Partner Open API</a></li>
                <li><a href="#export-section" class="toc-link">Pusat Unduhan Tooling</a></li>
            </ul>

            <div class="toc-tools-box">
                <div class="toc-title">Aksi Cepat</div>
                <button class="tool-link-btn" onclick="openTesterModal()" style="width: 100%; border-color: rgba(217, 119, 87, 0.4); color: var(--accent); cursor: pointer;">
                    <span>⚡</span> Buka API Console
                </button>
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

    <!-- Interactive API Console Modal -->
    <div id="modal-tester" class="modal-overlay" onclick="if(event.target === this) closeTesterModal()">
        <div class="modal-card">
            <div class="modal-header">
                <h3><span>⚡</span> Interactive API Console &amp; Tester</h3>
                <button class="modal-close-btn" onclick="closeTesterModal()" title="Tutup Modal">&times;</button>
            </div>
            <div class="modal-body">
                <!-- Preset Buttons -->
                <div>
                    <label style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; margin-bottom: 0.35rem; display: block;">Quick Presets &amp; Akun Demo</label>
                    <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                        <button class="preset-badge-btn" onclick="loginDemoBudi()" style="border-color: rgba(16, 185, 129, 0.4); color: #34d399;">
                            🔑 1-Click Login Budi (081400000004)
                        </button>
                        <button class="preset-badge-btn" onclick="applyPreset('home')">
                            GET /api/v1/home
                        </button>
                        <button class="preset-badge-btn" onclick="applyPreset('prefix')">
                            GET /api/v1/products/operator-prefix
                        </button>
                        <button class="preset-badge-btn" onclick="applyPreset('channels')">
                            GET /api/v1/wallet/payment-channels
                        </button>
                        <button class="preset-badge-btn" onclick="applyPreset('partner_balance')">
                            GET /api/partner/balance (B2B)
                        </button>
                    </div>
                </div>

                <!-- Request Form -->
                <div class="console-form-group">
                    <label>HTTP Method &amp; Endpoint URL</label>
                    <div class="console-input-row">
                        <select id="console-method" class="console-select" onchange="toggleBodyInput()">
                            <option value="GET">GET</option>
                            <option value="POST">POST</option>
                            <option value="PUT">PUT</option>
                        </select>
                        <input type="text" id="console-url" class="console-input" value="/api/v1/home" placeholder="/api/v1/...">
                    </div>
                </div>

                <div class="console-form-group">
                    <label>Bearer Token / API Key Header (Otomatis Terisi setelah Login)</label>
                    <input type="text" id="console-auth" class="console-input" placeholder="Bearer 1|... atau partner_live_demo123">
                </div>

                <div class="console-form-group" id="console-body-wrap" style="display: none;">
                    <label>Request Body (JSON)</label>
                    <textarea id="console-body" class="console-textarea" placeholder='{"phone": "081400000004"}'></textarea>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 0.25rem;">
                    <span id="console-status-badge" style="font-size: 0.8rem; font-family: var(--font-mono); color: var(--text-muted);">
                        Status: Siap diuji
                    </span>
                    <button class="btn-claude btn-claude-primary" onclick="sendTestRequest()" id="btn-send-request">
                        <span>🚀</span> Kirim Request
                    </button>
                </div>

                <!-- Response Viewer -->
                <div class="console-form-group">
                    <label>HTTP Response</label>
                    <pre id="console-response" class="console-response-box">// Hasil respon API akan ditampilkan di sini...</pre>
                </div>
            </div>
        </div>
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

        // Scrollspy for TOC and Sidebar
        window.addEventListener('scroll', () => {
            const targets = document.querySelectorAll('section.doc-section, .endpoint-card[id]');
            let current = '';
            targets.forEach(el => {
                const top = el.offsetTop - 140;
                if (window.pageYOffset >= top) {
                    current = el.getAttribute('id');
                }
            });

            if (current) {
                document.querySelectorAll('.toc-link').forEach(a => {
                    a.classList.toggle('active', a.getAttribute('href') === '#' + current);
                });
                document.querySelectorAll('#sidebar-nav .nav-item-link').forEach(a => {
                    a.classList.toggle('active', a.getAttribute('href') === '#' + current);
                });
            }
        });

        // Shortcut ⌘K / Ctrl+K
        window.addEventListener('keydown', (e) => {
            if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
                e.preventDefault();
                document.getElementById('claude-search').focus();
            }
        });

        // Interactive API Tester Console
        function openTesterModal() {
            document.getElementById('modal-tester').classList.add('open');
        }

        function closeTesterModal() {
            document.getElementById('modal-tester').classList.remove('open');
        }

        function toggleBodyInput() {
            const method = document.getElementById('console-method').value;
            const bodyWrap = document.getElementById('console-body-wrap');
            bodyWrap.style.display = (method === 'POST' || method === 'PUT') ? 'flex' : 'none';
        }

        function applyPreset(type) {
            const methodSelect = document.getElementById('console-method');
            const urlInput = document.getElementById('console-url');
            const bodyInput = document.getElementById('console-body');

            if (type === 'home') {
                methodSelect.value = 'GET';
                urlInput.value = '/api/v1/home';
            } else if (type === 'prefix') {
                methodSelect.value = 'GET';
                urlInput.value = '/api/v1/products/operator-prefix?phone=081234567890';
            } else if (type === 'channels') {
                methodSelect.value = 'GET';
                urlInput.value = '/api/v1/wallet/payment-channels';
            } else if (type === 'partner_balance') {
                methodSelect.value = 'GET';
                urlInput.value = '/api/partner/balance';
            }
            toggleBodyInput();
        }

        async function loginDemoBudi() {
            const statusBadge = document.getElementById('console-status-badge');
            const responseBox = document.getElementById('console-response');
            const authInput = document.getElementById('console-auth');

            statusBadge.innerText = 'Mengirim OTP untuk Budi (081400000004)...';
            try {
                // Step 1: Send OTP
                const resOtp = await fetch('/api/v1/auth/otp/send', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ phone: '081400000004' })
                });
                const otpJson = await resOtp.json();

                // Step 2: Verify OTP (default seed OTP is 123456)
                statusBadge.innerText = 'Memverifikasi OTP 123456...';
                const resVerify = await fetch('/api/v1/auth/otp/verify', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ phone: '081400000004', otp: '123456' })
                });
                const verifyJson = await resVerify.json();

                if (verifyJson.success && verifyJson.data && verifyJson.data.token) {
                    authInput.value = 'Bearer ' + verifyJson.data.token;
                    statusBadge.innerHTML = '<span style="color: #10b981; font-weight: 700;">✓ Login Berhasil (Token Aktif)</span>';
                    responseBox.innerText = JSON.stringify(verifyJson, null, 2);
                    showToast('Berhasil login sebagai Budi! Token siap digunakan.');
                } else {
                    statusBadge.innerHTML = '<span style="color: #ef4444; font-weight: 700;">Login Gagal</span>';
                    responseBox.innerText = JSON.stringify(verifyJson, null, 2);
                }
            } catch (err) {
                statusBadge.innerHTML = '<span style="color: #ef4444; font-weight: 700;">Gagal menghubungi server</span>';
                responseBox.innerText = String(err);
            }
        }

        async function sendTestRequest() {
            const method = document.getElementById('console-method').value;
            const url = document.getElementById('console-url').value.trim();
            const auth = document.getElementById('console-auth').value.trim();
            const bodyStr = document.getElementById('console-body').value.trim();
            const statusBadge = document.getElementById('console-status-badge');
            const responseBox = document.getElementById('console-response');
            const sendBtn = document.getElementById('btn-send-request');

            sendBtn.disabled = true;
            statusBadge.innerText = 'Mengirim request...';
            const startTime = performance.now();

            const headers = {
                'Accept': 'application/json'
            };
            if (auth) {
                if (auth.startsWith('Bearer ')) {
                    headers['Authorization'] = auth;
                } else {
                    headers['X-Api-Key'] = auth;
                }
            }

            const fetchOptions = {
                method: method,
                headers: headers
            };

            if ((method === 'POST' || method === 'PUT') && bodyStr) {
                headers['Content-Type'] = 'application/json';
                fetchOptions.body = bodyStr;
            }

            try {
                const res = await fetch(url, fetchOptions);
                const duration = Math.round(performance.now() - startTime);
                const contentType = res.headers.get('content-type') || '';
                
                let responseData;
                if (contentType.includes('application/json')) {
                    responseData = await res.json();
                    responseBox.innerText = JSON.stringify(responseData, null, 2);
                } else {
                    responseData = await res.text();
                    responseBox.innerText = responseData;
                }

                const statusColor = res.ok ? '#10b981' : (res.status >= 500 ? '#ef4444' : '#f59e0b');
                statusBadge.innerHTML = `<span style="color: ${statusColor}; font-weight: 700;">HTTP ${res.status} ${res.statusText}</span> (${duration}ms)`;
            } catch (err) {
                const duration = Math.round(performance.now() - startTime);
                statusBadge.innerHTML = `<span style="color: #ef4444; font-weight: 700;">Error: Network/CORS</span> (${duration}ms)`;
                responseBox.innerText = String(err);
            } finally {
                sendBtn.disabled = false;
            }
        }
    </script>
</body>
</html>
