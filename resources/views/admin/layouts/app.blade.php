<!DOCTYPE html>
<html lang="id" class="h-full bg-slate-950 text-slate-100">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Dashboard' }} — Backoffice PPOB</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" nonce="{{ $csp_nonce ?? '' }}"></script>
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js" nonce="{{ $csp_nonce ?? '' }}"></script>
</head>
<body class="h-full font-sans antialiased bg-slate-950" x-data="{ sidebarOpen: false }">
    {{-- Top Slim Progress Bar for Instant Seamless Navigation --}}
    <div id="seamless-progress-bar" class="fixed top-0 left-0 h-0.5 bg-gradient-to-r from-indigo-500 via-purple-500 to-emerald-400 z-[99999] transition-all duration-200 pointer-events-none opacity-0 w-0"></div>

    <div class="min-h-full flex flex-col lg:flex-row">
        {{-- Mobile Sidebar Backdrop --}}
        <div x-show="sidebarOpen" x-cloak class="fixed inset-0 z-40 bg-slate-950/80 backdrop-blur-sm lg:hidden" @click="sidebarOpen = false"></div>

        {{-- Sidebar --}}
        <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
               class="fixed inset-y-0 left-0 z-50 w-64 bg-slate-900 border-r border-slate-800 transition-transform duration-300 ease-in-out lg:translate-x-0 lg:static lg:inset-auto lg:flex lg:flex-col lg:w-64 flex-shrink-0">
            <div class="flex items-center justify-between h-16 px-6 border-b border-slate-800/80 bg-slate-900/50">
                <div class="flex items-center gap-3">
                    <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-indigo-600 text-white font-bold text-sm">
                        P
                    </div>
                    <span class="text-base font-bold tracking-tight text-white">Backoffice</span>
                </div>
                <button class="lg:hidden text-slate-400 hover:text-white" @click="sidebarOpen = false">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            {{-- Navigation Links --}}
            <nav class="flex-1 px-4 py-4 space-y-1 overflow-y-auto">
                <a href="{{ route('admin.dashboard') }}"
                   class="flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium {{ request()->routeIs('admin.dashboard*') ? 'bg-indigo-600/10 text-indigo-400 border border-indigo-500/20' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white' }}">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                    <span>Dashboard</span>
                </a>

                <div class="pt-4 pb-1">
                    <p class="px-3 text-xs font-semibold uppercase tracking-wider text-slate-500">Operasional</p>
                </div>

                {{-- Transaksi --}}
                <a href="{{ route('admin.transactions.index') }}"
                   class="flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium {{ request()->routeIs('admin.transactions*') ? 'bg-indigo-600/10 text-indigo-400 border border-indigo-500/20' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white' }}">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    <span>Transaksi</span>
                </a>

                {{-- Produk & SKU --}}
                <a href="{{ route('admin.products.index') }}"
                   class="flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium {{ request()->routeIs('admin.products*') || request()->routeIs('admin.categories*') ? 'bg-indigo-600/10 text-indigo-400 border border-indigo-500/20' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white' }}">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                    <span>Produk & SKU</span>
                </a>

                {{-- Operator & Routing --}}
                <a href="{{ route('admin.operators.index') }}"
                   class="flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium {{ request()->routeIs('admin.operators*') ? 'bg-indigo-600/10 text-indigo-400 border border-indigo-500/20' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white' }}">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                    <span>Operator & Prefix</span>
                </a>

                {{-- Vendor / Supplier Gateway --}}
                <a href="{{ route('admin.providers.index') }}"
                   class="flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium {{ request()->routeIs('admin.providers*') ? 'bg-indigo-600/10 text-indigo-400 border border-indigo-500/20' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white' }}">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    <span>Vendor / Supplier</span>
                </a>

                {{-- Mitra B2B --}}
                <a href="{{ route('admin.partners.index') }}"
                   class="flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium {{ request()->routeIs('admin.partners*') ? 'bg-indigo-600/10 text-indigo-400 border border-indigo-500/20' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white' }}">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    <span>Mitra B2B</span>
                </a>

                {{-- Pengguna Mobile Apps (Agen / Retail) --}}
                <a href="{{ route('admin.users.index') }}"
                   class="flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium {{ request()->routeIs('admin.users*') ? 'bg-indigo-600/10 text-indigo-400 border border-indigo-500/20' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white' }}">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                    <span>Pengguna Mobile</span>
                </a>

                {{-- Keuangan (Super Admin & Finance Only) --}}
                @if(auth('admin')->check() && (auth('admin')->user()->isSuperAdmin() || auth('admin')->user()->isFinance()))
                    <div class="pt-4 pb-1">
                        <p class="px-3 text-xs font-semibold uppercase tracking-wider text-slate-500">Keuangan & Deposit</p>
                    </div>

                    {{-- Approval Topup --}}
                    <a href="{{ route('admin.finance.topups.index') }}"
                       class="flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium {{ request()->routeIs('admin.finance.topups*') ? 'bg-indigo-600/10 text-indigo-400 border border-indigo-500/20' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white' }}">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span>Approval Top-Up</span>
                    </a>

                    {{-- Double-Entry Ledger --}}
                    <a href="{{ route('admin.finance.ledger.index') }}"
                       class="flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium {{ request()->routeIs('admin.finance.ledger*') || request()->routeIs('admin.finance.adjustments*') ? 'bg-indigo-600/10 text-indigo-400 border border-indigo-500/20' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white' }}">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span>Ledger & Mutasi Saldo</span>
                    </a>

                    {{-- Provider Reconciliation --}}
                    <a href="{{ route('admin.finance.reconciliation.index') }}"
                       class="flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium {{ request()->routeIs('admin.finance.reconciliation*') ? 'bg-indigo-600/10 text-indigo-400 border border-indigo-500/20' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white' }}">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                        <span>Rekonsiliasi Provider</span>
                    </a>
                @endif

                {{-- Sistem & Keamanan --}}
                <div class="pt-4 pb-1">
                    <p class="px-3 text-xs font-semibold uppercase tracking-wider text-slate-500">Sistem & Keamanan</p>
                </div>

                {{-- Audit Trail Logs --}}
                <a href="{{ route('admin.audit_logs.index') }}"
                   class="flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium {{ request()->routeIs('admin.audit_logs*') ? 'bg-indigo-600/10 text-indigo-400 border border-indigo-500/20' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white' }}">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <span>Audit Trail Log</span>
                </a>
            </nav>

            {{-- Sidebar Footer --}}
            <div class="p-4 border-t border-slate-800/80 bg-slate-900/40">
                <a href="{{ route('admin.profile') }}"
                   class="flex items-center gap-3 px-3 py-2 rounded-xl text-sm text-slate-300 hover:bg-slate-800/60 hover:text-white transition-colors">
                    <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    <span>Pengaturan Profil</span>
                </a>
            </div>
        </aside>

        {{-- Main Content Area --}}
        <div class="flex-1 flex flex-col min-w-0">
            {{-- Topbar --}}
            <header class="h-16 bg-slate-900/60 backdrop-blur-md border-b border-slate-800 flex items-center justify-between px-4 sm:px-6 lg:px-8">
                <button class="lg:hidden text-slate-400 hover:text-white" @click="sidebarOpen = true">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>

                <div class="flex-1"></div>

                {{-- User Info & Role Badge --}}
                <div class="flex items-center gap-4">
                    @if(auth('admin')->check())
                        @php
                            $role = auth('admin')->user()->role;
                            $roleLabel = $role instanceof \App\Domain\Admin\Enums\AdminRole ? $role->label() : 'Administrator';
                            $roleBadgeColor = match(auth('admin')->user()->role?->value ?? '') {
                                'super_admin' => 'bg-indigo-500/10 text-indigo-400 border-indigo-500/20',
                                'finance_admin' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
                                default => 'bg-amber-500/10 text-amber-400 border-amber-500/20',
                            };
                        @endphp
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border {{ $roleBadgeColor }}">
                            {{ $roleLabel }}
                        </span>

                        <div class="flex items-center gap-3">
                            <div class="text-right hidden sm:block">
                                <div class="text-sm font-medium text-white">{{ auth('admin')->user()->name }}</div>
                                <div class="text-xs text-slate-400">{{ auth('admin')->user()->email }}</div>
                            </div>

                            <form method="POST" action="{{ route('admin.logout') }}">
                                @csrf
                                <button type="submit" title="Logout"
                                        class="p-2 rounded-xl text-slate-400 hover:text-rose-400 hover:bg-rose-500/10 transition-colors">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                                </button>
                            </form>
                        </div>
                    @endif
                </div>
            </header>

            {{-- Main Body --}}
            <main id="admin-main-content" class="flex-1 p-4 sm:p-6 lg:p-8 transition-opacity duration-150">
                {{-- Flash Notifications --}}
                @if (session('success'))
                    <div class="mb-6 p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-sm flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <svg class="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                            <span>{{ session('success') }}</span>
                        </div>
                    </div>
                @endif

                @if (session('error'))
                    <div class="mb-6 p-4 rounded-2xl bg-rose-500/10 border border-rose-500/20 text-rose-400 text-sm flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <svg class="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                            <span>{{ session('error') }}</span>
                        </div>
                    </div>
                @endif

                {{ $slot ?? '' }}
                @yield('content')
            </main>
        </div>
    </div>

    {{-- Seamless Main-Content Navigation & Pre-fetch on Hover Engine --}}
    <script nonce="{{ $csp_nonce ?? '' }}">
        (function () {
            const progressBar = document.getElementById('seamless-progress-bar');
            const prefetchCache = new Map();
            let hoverTimeout = null;
            let progressTimer = null;

            function startProgress() {
                if (!progressBar) return;
                progressBar.style.opacity = '1';
                progressBar.style.width = '25%';
                clearInterval(progressTimer);
                progressTimer = setInterval(() => {
                    const currentWidth = parseFloat(progressBar.style.width) || 25;
                    if (currentWidth < 85) {
                        progressBar.style.width = (currentWidth + Math.random() * 15) + '%';
                    }
                }, 150);
            }

            function endProgress() {
                if (!progressBar) return;
                clearInterval(progressTimer);
                progressBar.style.width = '100%';
                setTimeout(() => {
                    progressBar.style.opacity = '0';
                    setTimeout(() => {
                        progressBar.style.width = '0%';
                    }, 200);
                }, 100);
            }

            function isInternalAdminLink(a) {
                if (!a || !a.href) return false;
                if (a.target && a.target !== '_self') return false;
                if (a.hasAttribute('download')) return false;
                if (a.hasAttribute('data-no-seamless')) return false;
                if (a.getAttribute('href').startsWith('#')) return false;
                if (a.closest('form')) return false;
                
                try {
                    const url = new URL(a.href, window.location.origin);
                    if (url.origin !== window.location.origin) return false;
                    const path = url.pathname;
                    const prefix = "{{ config('admin.prefix', 'office') }}";
                    return path.startsWith('/' + prefix) || path.startsWith('/admin') || path.startsWith('/office');
                } catch (e) {
                    return false;
                }
            }

            async function prefetch(url) {
                if (prefetchCache.has(url)) {
                    const item = prefetchCache.get(url);
                    if (Date.now() - item.time < 30000) return;
                }
                try {
                    const res = await fetch(url, {
                        headers: { 'X-Seamless-Prefetch': '1', 'Accept': 'text/html' }
                    });
                    if (res.ok) {
                        const html = await res.text();
                        prefetchCache.set(url, { html, time: Date.now() });
                    }
                } catch (e) {}
            }

            async function navigateTo(targetUrl, updateHistory = true) {
                const url = new URL(targetUrl, window.location.origin).href;

                window.dispatchEvent(new CustomEvent('seamless:before-swap'));

                startProgress();

                let html = null;
                if (prefetchCache.has(url)) {
                    const cached = prefetchCache.get(url);
                    if (Date.now() - cached.time < 30000) {
                        html = cached.html;
                    }
                }

                if (!html) {
                    try {
                        const res = await fetch(url, {
                            headers: { 'X-Seamless-Navigation': '1', 'Accept': 'text/html' }
                        });
                        
                        const prefix = "{{ config('admin.prefix', 'office') }}";
                        if (!res.ok || (res.redirected && !res.url.includes(prefix))) {
                            window.location.href = res.url || url;
                            return;
                        }
                        html = await res.text();
                        prefetchCache.set(url, { html, time: Date.now() });
                    } catch (e) {
                        window.location.href = url;
                        return;
                    }
                }

                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const newMain = doc.querySelector('main');
                const currentMain = document.querySelector('main');

                if (!newMain || !currentMain) {
                    window.location.href = url;
                    return;
                }

                const newTitle = doc.querySelector('title');
                if (newTitle) {
                    document.title = newTitle.innerText;
                }

                currentMain.style.opacity = '0.7';
                setTimeout(() => {
                    currentMain.innerHTML = newMain.innerHTML;
                    currentMain.className = newMain.className;
                    currentMain.style.opacity = '1';

                    // Re-run inline scripts inside main
                    const scripts = currentMain.querySelectorAll('script');
                    scripts.forEach(oldScript => {
                        const newScript = document.createElement('script');
                        Array.from(oldScript.attributes).forEach(attr => newScript.setAttribute(attr.name, attr.value));
                        newScript.appendChild(document.createTextNode(oldScript.innerHTML));
                        oldScript.parentNode.replaceChild(newScript, oldScript);
                    });

                    // Re-initialize Alpine on the new main content
                    if (window.Alpine) {
                        try {
                            window.Alpine.initTree(currentMain);
                        } catch (e) {
                            console.warn('Alpine initTree warning:', e);
                        }
                    }

                    // Update Active State on Sidebar Links
                    updateSidebarActiveLink(url);

                    // Close mobile sidebar if open
                    const bodyEl = document.querySelector('body[x-data]');
                    if (bodyEl && window.Alpine) {
                        try {
                            const data = window.Alpine.$data(bodyEl);
                            if (data && data.sidebarOpen) data.sidebarOpen = false;
                        } catch (e) {}
                    }

                    if (updateHistory) {
                        window.history.pushState({}, '', url);
                    }

                    window.scrollTo({ top: 0, behavior: 'instant' });
                    endProgress();

                    window.dispatchEvent(new CustomEvent('seamless:swapped', { detail: { url } }));
                }, 40);
            }

            function updateSidebarActiveLink(targetUrl) {
                const targetPath = new URL(targetUrl, window.location.origin).pathname;
                const links = document.querySelectorAll('aside nav a');

                links.forEach(a => {
                    const linkPath = new URL(a.href, window.location.origin).pathname;
                    const prefix = "{{ config('admin.prefix', 'office') }}";
                    
                    const isRootDashboard = linkPath === '/' + prefix || linkPath === '/admin' || linkPath === '/office';
                    const isActive = isRootDashboard 
                        ? (targetPath === linkPath)
                        : (targetPath === linkPath || (linkPath.length > 5 && targetPath.startsWith(linkPath)));

                    if (isActive) {
                        a.classList.add('bg-indigo-600/10', 'text-indigo-400', 'border', 'border-indigo-500/20');
                        a.classList.remove('text-slate-300', 'hover:bg-slate-800/60', 'hover:text-white');
                    } else {
                        a.classList.remove('bg-indigo-600/10', 'text-indigo-400', 'border', 'border-indigo-500/20');
                        a.classList.add('text-slate-300', 'hover:bg-slate-800/60', 'hover:text-white');
                    }
                });
            }

            // 1. Delegated Click Handler for all links
            document.addEventListener('click', (e) => {
                if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.defaultPrevented) return;
                if (e.button !== 0) return;

                const link = e.target.closest('a');
                if (!isInternalAdminLink(link)) return;

                if (link.href === window.location.href) {
                    e.preventDefault();
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                    return;
                }

                e.preventDefault();
                navigateTo(link.href, true);
            });

            // 2. Pre-fetching on Hover
            document.addEventListener('mouseover', (e) => {
                const link = e.target.closest('a');
                if (!isInternalAdminLink(link)) return;
                if (link.href === window.location.href) return;

                clearTimeout(hoverTimeout);
                hoverTimeout = setTimeout(() => {
                    prefetch(link.href);
                }, 60);
            });

            document.addEventListener('mouseout', (e) => {
                const link = e.target.closest('a');
                if (link) {
                    clearTimeout(hoverTimeout);
                }
            });

            // 3. Browser Back / Forward Buttons
            window.addEventListener('popstate', () => {
                navigateTo(window.location.href, false);
            });

            window.Seamless = { navigateTo, prefetch };
        })();
    </script>
</body>
</html>
