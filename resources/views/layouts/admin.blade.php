@php($panelLogo = config('app.logo') ? asset(config('app.logo')) : asset('assets/svgs/pterodactyl.svg'))
<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>{{ config('app.name', 'Pterodactyl') }} - @yield('title')</title>
        <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
        <meta name="_token" content="{{ csrf_token() }}">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @include('partials.branding.favicon')

        @include('layouts.scripts')

        @section('scripts')
            {!! Theme::css('vendor/select2/select2.min.css?t={cache-version}') !!}
            {!! Theme::css('vendor/bootstrap/bootstrap.min.css?t={cache-version}') !!}
            {!! Theme::css('vendor/adminlte/admin.min.css?t={cache-version}') !!}
            {!! Theme::css('vendor/adminlte/colors/skin-blue.min.css?t={cache-version}') !!}
            {!! Theme::css('vendor/sweetalert/sweetalert.min.css?t={cache-version}') !!}
            {!! Theme::css('vendor/animate/animate.min.css?t={cache-version}') !!}
            {!! Theme::css('css/pterodactyl.css?t={cache-version}') !!}
            {!! Theme::css('css/admin-sidebar.css?v=20260601-0213') !!}
            {!! Theme::css('css/admin-server-theme.css?v=20260604-0042') !!}
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/ionicons/2.0.1/css/ionicons.min.css">
            <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Geist+Mono:wght@400;500;600;700;800;900&display=swap">
            <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons+Round">

            <!--[if lt IE 9]>
            <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
            <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
            <![endif]-->
        @show
    </head>
    <body class="hold-transition skin-blue fixed sidebar-mini">
        <header class="admin-topbar">
            <div class="admin-topbar-left">
                <a href="/" class="admin-topbar-brand">
                    <img src="{{ $panelLogo }}" alt="{{ config('app.name', 'Pterodactyl') }} logo" class="admin-topbar-logo" draggable="false">
                    <div class="admin-topbar-brand-copy">
                        <div class="admin-topbar-brand-title">{{ config('app.name', 'Pterodactyl') }}</div>
                    </div>
                </a>
                <div class="admin-topbar-primary-actions">
                    <a href="/" class="admin-topbar-link" title="Open Dashboard" aria-label="Open Dashboard">
                        <svg aria-hidden="true" focusable="false" width="18" height="18" viewBox="0 0 512 512" fill="currentColor">
                            <path d="M0 168v-16c0-13.255 10.745-24 24-24h360V80c0-21.367 25.899-32.042 40.971-16.971l80 80c9.372 9.373 9.372 24.569 0 33.941l-80 80C409.956 271.982 384 261.456 384 240v-48H24c-13.255 0-24-10.745-24-24zm488 152H128v-48c0-21.314-25.862-32.08-40.971-16.971l-80 80c-9.372 9.373-9.372 24.569 0 33.941l80 80C102.057 463.997 128 453.437 128 432v-48h360c13.255 0 24-10.745 24-24v-16c0-13.255-10.745-24-24-24z" />
                        </svg>
                    </a>
                    <button
                        type="button"
                        class="topbar-icon-btn"
                        id="adminSidebarToggleDesktop"
                        aria-label="Expand sidebar"
                        title="Expand sidebar"
                        style="display: inline-flex; align-items: center; justify-content: center; width: 46px; height: 46px; border-radius: 14px; border: 1px solid rgba(255, 255, 255, 0.08); background: rgba(255, 255, 255, 0.06); backdrop-filter: blur(8px); color: rgb(254, 249, 225); cursor: pointer; flex-shrink: 0; box-shadow: none;"
                    >
                        <svg
                            aria-hidden="true"
                            focusable="false"
                            width="16"
                            height="16"
                            viewBox="0 0 320 512"
                            fill="currentColor"
                            style="transform: none;"
                        >
                            <path d="M34.52 239.03L228.87 44.69c9.37-9.37 24.57-9.37 33.94 0l22.67 22.67c9.36 9.36 9.37 24.52.04 33.9L131.49 256l154.02 154.75c9.34 9.38 9.32 24.54-.04 33.9l-22.67 22.67c-9.37 9.37-24.57 9.37-33.94 0L34.52 272.97c-9.37-9.37-9.37-24.57 0-33.94z" />
                        </svg>
                    </button>
                    <button type="button" class="admin-topbar-icon" id="adminSidebarToggleMobile" aria-label="Open sidebar" title="Open sidebar" style="display: none;">
                        <span class="material-icons-round admin-topbar-toggle-mobile-icon" id="adminSidebarToggleMobileIcon">menu</span>
                    </button>
                </div>
            </div>
            <div class="admin-topbar-center">
                <div class="admin-topbar-title-card">
                    <span class="admin-topbar-eyebrow">Administration</span>
                    <span class="admin-topbar-title" id="adminTopbarTitle">@yield('title')</span>
                    <span class="admin-topbar-subtitle" id="adminTopbarSubtitle">Manage panel services, billing, nodes, and customer infrastructure.</span>
                </div>
            </div>
            <div class="admin-topbar-right">
                <a href="{{ route('account') }}" class="admin-topbar-user admin-topbar-account-link" title="Open account" aria-label="Open account">
                    <img src="{{ Auth::user()->getImageUrl() }}" alt="User avatar" class="admin-topbar-avatar">
                    <span class="admin-topbar-user-copy">
                        <span class="admin-topbar-user-name">{{ Auth::user()->name_first }} {{ Auth::user()->name_last }}</span>
                        <div style="display: flex; align-items: center; justify-content: flex-end; gap: 10px; flex-shrink: 0; min-width: fit-content;"><span style="color: rgb(254, 249, 225); font-size: 0.76rem; font-weight: 800; letter-spacing: 0.18em; text-transform: uppercase; white-space: nowrap;">V3.0.0</span><span style="display: inline-flex; align-items: center; justify-content: center; padding: 0.35rem 0.5rem; border-radius: 999px; background: rgba(255, 255, 255, 0.06); color: rgb(254, 249, 225); font-size: 9px; font-weight: 800; letter-spacing: 0.16em; text-transform: uppercase; border: 1px solid rgba(255, 255, 255, 0.08); backdrop-filter: blur(8px); box-shadow: none; white-space: nowrap;">BETA</span></div>
                    </span>
                </a>
            </div>
        </header>
        <div id="adminSidebarOverlay" class="admin-sidebar-overlay"></div>

        {{-- ===== Custom Sidebar (matches React sidebar) ===== --}}
        <div class="sidebar-desktop-shell" id="adminSidebar">
            {{-- Nav --}}
            <nav class="admin-sidebar-nav">
                @foreach(($adminSidebarSections ?? []) as $section)
                    <div class="admin-sidebar-section" data-admin-section="{{ $section['key'] }}">
                        <button
                            type="button"
                            class="sidebar-text admin-sidebar-section-toggle"
                            data-admin-section-toggle="{{ $section['key'] }}"
                            aria-expanded="true"
                        >
                            <span class="admin-sidebar-section-title">{{ $section['title'] }}</span>
                            <span class="material-icons-round admin-sidebar-section-arrow" data-admin-section-arrow="{{ $section['key'] }}">expand_less</span>
                        </button>
                        <div class="admin-sidebar-section-content" data-admin-section-content="{{ $section['key'] }}">
                            @foreach($section['items'] as $item)
                                @php($isActive = ($item['exact'] ?? false) ? Route::currentRouteName() === $item['match'] : \Illuminate\Support\Str::startsWith(Route::currentRouteName() ?? '', $item['match']))
                                <a href="{{ $item['route'] }}" class="admin-sidebar-link-anchor">
                                    <div class="sidebar-link {{ $isActive ? 'active' : '' }}">
                                        <div class="admin-sidebar-link-icon-wrap">
                                            <span class="material-icons-round admin-sidebar-link-icon">{{ $item['icon'] }}</span>
                                        </div>
                                        <span class="sidebar-text admin-sidebar-link-label">{{ $item['label'] }}</span>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </nav>

            {{-- Back to Dashboard --}}
            <div class="admin-sidebar-back-link-wrap">
                <a href="/" class="admin-sidebar-link-anchor">
                    <div class="sidebar-link">
                        <div class="admin-sidebar-link-icon-wrap">
                            <span class="material-icons-round admin-sidebar-link-icon">dashboard</span>
                        </div>
                        <span class="sidebar-text admin-sidebar-link-label">Back to Dashboard</span>
                    </div>
                </a>
            </div>

            {{-- User Footer --}}
            <div id="userFooter" class="admin-user-footer">
                {{-- Avatar Button --}}
                <button type="button" id="userFooterBtn" class="sidebar-user-btn admin-user-footer-button">
                    <div class="admin-user-footer-avatar-wrap">
                        <img src="{{ Auth::user()->getImageUrl() }}" alt="User avatar" class="admin-user-footer-avatar">
                    </div>
                    <div class="sidebar-text admin-user-footer-copy">
                        <span class="admin-user-footer-name">{{ Auth::user()->name_first }} {{ Auth::user()->name_last }}</span>
                        <span class="material-icons-round admin-user-footer-arrow" id="userFooterArrow">expand_more</span>
                    </div>
                </button>

                {{-- Popup Menu --}}
                <div id="userFooterMenu" class="admin-user-footer-menu">

                    {{-- Profile --}}
                    <a href="{{ route('account') }}" class="admin-user-footer-menu-link">
                        <div class="footer-menu-item admin-user-footer-menu-item">
                            <div class="admin-user-footer-menu-item-start">
                                <div class="admin-user-footer-menu-icon-wrap">
                                    <span class="material-icons-round admin-user-footer-menu-icon">person</span>
                                </div>
                                <span class="admin-user-footer-menu-label">Profile</span>
                            </div>
                        </div>
                    </a>

                    {{-- Log out --}}
                    <button type="button" id="logoutButton" class="footer-menu-item admin-user-footer-menu-item admin-user-footer-logout">
                        <div class="admin-user-footer-menu-item-start">
                            <div class="admin-user-footer-menu-icon-wrap">
                                <span class="material-icons-round admin-user-footer-menu-icon">logout</span>
                            </div>
                            <span class="admin-user-footer-menu-label">Log out</span>
                        </div>
                    </button>
                </div>
            </div>
        </div>

        {{-- ===== Main Content ===== --}}
        <div class="admin-content-wrapper">
            <div class="admin-main-shell">
            <section class="content-header">
                @yield('content-header')
            </section>
            <section class="content">
                <div class="row">
                    <div class="col-xs-12">
                        @if (count($errors) > 0)
                            <div class="alert alert-danger">
                                There was an error validating the data provided.<br><br>
                                <ul>
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                        @foreach (Alert::getMessages() as $type => $messages)
                            @foreach ($messages as $message)
                                <div class="alert alert-{{ $type }} alert-dismissable" role="alert">
                                    {{ $message }}
                                </div>
                            @endforeach
                        @endforeach
                    </div>
                </div>
                @yield('content')
            </section>
            </div>
            <footer class="admin-sticky-footer">
                <div class="pull-right small">
                    <strong><i class="fa fa-fw {{ $appIsGit ? 'fa-git-square' : 'fa-code-fork' }}"></i></strong> {{ $appVersion }}<br />
                    <strong><i class="fa fa-fw fa-clock-o"></i></strong> {{ round(microtime(true) - LARAVEL_START, 3) }}s
                </div>
                Copyright &copy; 2015 - {{ date('Y') }} <a href="https://pterodactyl.io/">Pterodactyl Software</a>.
            </footer>
        </div>

        @section('footer-scripts')
            <script src="/js/keyboard.polyfill.js" type="application/javascript"></script>
            <script>keyboardeventKeyPolyfill.polyfill();</script>

            {!! Theme::js('vendor/jquery/jquery.min.js?t={cache-version}') !!}
            {!! Theme::js('vendor/sweetalert/sweetalert.min.js?t={cache-version}') !!}
            {!! Theme::js('vendor/bootstrap/bootstrap.min.js?t={cache-version}') !!}
            {!! Theme::js('vendor/slimscroll/jquery.slimscroll.min.js?t={cache-version}') !!}
            {!! Theme::js('vendor/adminlte/app.min.js?t={cache-version}') !!}
            {!! Theme::js('vendor/bootstrap-notify/bootstrap-notify.min.js?t={cache-version}') !!}
            {!! Theme::js('vendor/select2/select2.full.min.js?t={cache-version}') !!}
            {!! Theme::js('js/admin/functions.js?t={cache-version}') !!}
            <script src="/js/autocomplete.js" type="application/javascript"></script>

            {{-- Sidebar hover expand/collapse + User footer popup --}}
            <script>
                (function() {
                    var sidebar = document.getElementById('adminSidebar');
                    var root = document.documentElement;
                    var body = document.body;
                    var footerBtn = document.getElementById('userFooterBtn');
                    var footerMenu = document.getElementById('userFooterMenu');
                    var footerArrow = document.getElementById('userFooterArrow');
                    var footerRef = document.getElementById('userFooter');
                    var sidebarToggle = document.getElementById('adminSidebarToggleDesktop');
                    var sidebarToggleArrow = sidebarToggle ? sidebarToggle.querySelector('svg') : null;
                    var sidebarToggleMobile = document.getElementById('adminSidebarToggleMobile');
                    var sidebarToggleMobileIcon = document.getElementById('adminSidebarToggleMobileIcon');
                    var sidebarOverlay = document.getElementById('adminSidebarOverlay');
                    var topbarTitle = document.getElementById('adminTopbarTitle');
                    var topbarSubtitle = document.getElementById('adminTopbarSubtitle');
                    var modeButtons = document.querySelectorAll('[data-sidebar-mode]');
                    var sectionButtons = document.querySelectorAll('[data-admin-section-toggle]');
                    var expandedWidth = '288px';
                    var collapsedWidth = '72px';
                    var storageKey = 'ui.sidebar.mode';
                    var sectionStorageKey = 'ui.admin.sidebar.sections';
                    var mobileBreakpoint = 767;
                    var isExpanded = false;
                    var menuOpen = false;
                    var mode = 'auto';
                    var sectionState = {};
                    var defaultTopbarTitle = topbarTitle ? topbarTitle.textContent.trim() : 'Administration';
                    var defaultTopbarSubtitle = topbarSubtitle ? topbarSubtitle.textContent.trim() : '';

                    function isMobileViewport() {
                        return window.innerWidth <= mobileBreakpoint;
                    }

                    function setSidebarWidth(width) {
                        root.style.setProperty('--admin-sidebar-width', width);
                    }

                    function updateToggleIcon() {
                        if (isMobileViewport()) {
                            if (sidebarToggleMobile) {
                                var mobileOpen = body.classList.contains('admin-sidebar-mobile-open');
                                sidebarToggleMobile.setAttribute('aria-label', mobileOpen ? 'Close sidebar' : 'Open sidebar');
                                sidebarToggleMobile.setAttribute('title', mobileOpen ? 'Close sidebar' : 'Open sidebar');
                            }
                            if (sidebarToggleMobileIcon) {
                                sidebarToggleMobileIcon.textContent = body.classList.contains('admin-sidebar-mobile-open') ? 'close' : 'menu';
                            }
                            return;
                        }

                        if (sidebarToggleArrow) {
                            sidebarToggleArrow.style.transform = isExpanded ? 'none' : 'rotate(180deg)';
                        }
                        if (sidebarToggle) {
                            sidebarToggle.setAttribute('aria-label', isExpanded ? 'Collapse sidebar' : 'Expand sidebar');
                            sidebarToggle.setAttribute('title', isExpanded ? 'Collapse sidebar' : 'Expand sidebar');
                        }
                    }

                    function syncTopbarCopy() {
                        if (!topbarTitle || !topbarSubtitle) return;

                        var heading = document.querySelector('.content-header h1');
                        if (!heading) {
                            topbarTitle.textContent = defaultTopbarTitle;
                            topbarSubtitle.textContent = defaultTopbarSubtitle;
                            return;
                        }

                        var headingClone = heading.cloneNode(true);
                        var smallNodes = headingClone.querySelectorAll('small');
                        for (var i = 0; i < smallNodes.length; i++) {
                            smallNodes[i].remove();
                        }

                        var titleText = headingClone.textContent.replace(/\s+/g, ' ').trim();
                        var subtitleNode = heading.querySelector('small');
                        var subtitleText = subtitleNode ? subtitleNode.textContent.replace(/\s+/g, ' ').trim() : '';

                        topbarTitle.textContent = titleText || defaultTopbarTitle;
                        topbarSubtitle.textContent = subtitleText || defaultTopbarSubtitle;
                    }

                    function normalizeBreadcrumbs() {
                        var breadcrumbs = document.querySelectorAll('ol.breadcrumb');

                        for (var b = 0; b < breadcrumbs.length; b++) {
                            var breadcrumb = breadcrumbs[b];
                            if (breadcrumb.classList.contains('admin-breadcrumb-normalized')) continue;

                            var originalItems = Array.prototype.slice.call(breadcrumb.children).filter(function(item) {
                                return item.tagName && item.tagName.toLowerCase() === 'li' && !item.classList.contains('admin-breadcrumb-separator');
                            });

                            if (!originalItems.length) continue;

                            while (breadcrumb.firstChild) {
                                breadcrumb.removeChild(breadcrumb.firstChild);
                            }

                            breadcrumb.classList.add('admin-breadcrumb-clean', 'admin-breadcrumb-normalized');

                            for (var i = 0; i < originalItems.length; i++) {
                                var item = originalItems[i];
                                item.classList.add('admin-breadcrumb-item');

                                if (item.classList.contains('active') && !item.querySelector('a') && !item.querySelector('span')) {
                                    var span = document.createElement('span');
                                    while (item.firstChild) {
                                        span.appendChild(item.firstChild);
                                    }
                                    item.appendChild(span);
                                }

                                if (i > 0) {
                                    var separator = document.createElement('li');
                                    var separatorText = document.createElement('span');
                                    separator.className = 'admin-breadcrumb-separator';
                                    separator.setAttribute('aria-hidden', 'true');
                                    separatorText.textContent = '>';
                                    separator.appendChild(separatorText);
                                    breadcrumb.appendChild(separator);
                                }

                                breadcrumb.appendChild(item);
                            }
                        }
                    }

                    function readSectionState() {
                        try {
                            var stored = localStorage.getItem(sectionStorageKey);
                            var parsed = stored ? JSON.parse(stored) : {};
                            return parsed && typeof parsed === 'object' ? parsed : {};
                        } catch (error) {
                            return {};
                        }
                    }

                    function persistSectionState() {
                        try {
                            localStorage.setItem(sectionStorageKey, JSON.stringify(sectionState));
                        } catch (error) {
                            // Ignore storage write errors.
                        }
                    }

                    function applySectionState(sectionName) {
                        var button = document.querySelector('[data-admin-section-toggle="' + sectionName + '"]');
                        var arrow = document.querySelector('[data-admin-section-arrow="' + sectionName + '"]');
                        var content = document.querySelector('[data-admin-section-content="' + sectionName + '"]');
                        var isOpen = sectionState[sectionName] !== false;

                        if (button) {
                            button.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                        }

                        if (arrow) {
                            arrow.textContent = isOpen ? 'expand_less' : 'expand_more';
                            arrow.style.transform = isOpen ? 'rotate(0deg)' : 'rotate(180deg)';
                        }

                        if (content) {
                            content.classList.toggle('is-collapsed', !isOpen);
                        }
                    }

                    function persistMode(nextMode) {
                        mode = nextMode;
                        localStorage.setItem(storageKey, nextMode);
                        localStorage.setItem('ui.sidebar.locked', nextMode === 'locked-open' ? 'true' : 'false');
                        syncModeButtons();
                    }

                    function readMode() {
                        var storedMode = localStorage.getItem(storageKey);
                        if (storedMode === 'locked-open' || storedMode === 'locked-closed' || storedMode === 'auto') {
                            return storedMode;
                        }

                        return localStorage.getItem('ui.sidebar.locked') === 'true' ? 'locked-open' : 'auto';
                    }

                    function syncModeButtons() {
                        for (var i = 0; i < modeButtons.length; i++) {
                            var button = modeButtons[i];
                            var active = button.getAttribute('data-sidebar-mode') === mode;
                            button.classList.toggle('active', active);
                        }
                    }

                    function syncSidebarMode() {
                        if (!sidebar) return;
                        sidebar.setAttribute('data-sidebar-mode', mode);
                    }

                    function syncFooterMenuLayout() {
                        if (!footerMenu) return;

                        var compactMenu = menuOpen && mode === 'locked-closed' && !isExpanded;
                        footerMenu.classList.toggle('sidebar-footer-menu-compact', compactMenu);
                    }

                    function setMenuOpen(val) {
                        menuOpen = val;
                        if (footerMenu) footerMenu.style.display = val ? 'flex' : 'none';
                        if (footerArrow) footerArrow.textContent = val ? 'expand_less' : 'expand_more';
                        syncFooterMenuLayout();
                    }

                    function setExpanded(val) {
                        var mobile = isMobileViewport();
                        isExpanded = val;

                        if (sidebar) {
                            sidebar.style.width = mobile ? expandedWidth : (val ? expandedWidth : collapsedWidth);
                            sidebar.classList.toggle('is-expanded', mobile ? val : val);
                        }

                        if (mobile) {
                            body.classList.toggle('admin-sidebar-mobile-open', val);
                            setSidebarWidth('0px');
                        } else {
                            body.classList.remove('admin-sidebar-mobile-open');
                            setSidebarWidth(val ? expandedWidth : collapsedWidth);
                        }

                        updateToggleIcon();
                        syncFooterMenuLayout();
                        if (!val) setMenuOpen(false);
                    }

                    mode = readMode();
                    sectionState = readSectionState();
                    syncModeButtons();
                    syncSidebarMode();

                    function applyMode(nextMode) {
                        persistMode(nextMode);
                        syncSidebarMode();

                        if (isMobileViewport()) {
                            setExpanded(false);
                            return;
                        }

                        if (nextMode === 'locked-open') {
                            setExpanded(true);
                            return;
                        }

                        setExpanded(false);
                    }

                    applyMode(mode);

                    if (sidebar) {
                        sidebar.addEventListener('mouseenter', function() {
                            if (!isMobileViewport() && mode === 'auto') setExpanded(true);
                        });
                        sidebar.addEventListener('mouseleave', function() {
                            if (!isMobileViewport() && mode === 'auto') setExpanded(false);
                        });
                    }

                    if (footerBtn) {
                        footerBtn.addEventListener('click', function(e) {
                            e.stopPropagation();

                            if (!isExpanded && !isMobileViewport() && mode === 'auto') {
                                setExpanded(true);
                                return;
                            }

                            setMenuOpen(!menuOpen);
                        });
                    }

                    if (sidebarToggle) {
                        sidebarToggle.addEventListener('click', function() {
                            applyMode(mode === 'locked-open' ? 'locked-closed' : 'locked-open');
                        });
                    }

                    if (sidebarToggleMobile) {
                        sidebarToggleMobile.addEventListener('click', function() {
                            setExpanded(!body.classList.contains('admin-sidebar-mobile-open'));
                        });
                    }

                    for (var i = 0; i < modeButtons.length; i++) {
                        (function(button) {
                            button.addEventListener('click', function(event) {
                                event.stopPropagation();
                                applyMode(button.getAttribute('data-sidebar-mode') || 'auto');
                            });
                        })(modeButtons[i]);
                    }

                    for (var j = 0; j < sectionButtons.length; j++) {
                        (function(button) {
                            var sectionName = button.getAttribute('data-admin-section-toggle');
                            if (!sectionName) return;

                            applySectionState(sectionName);

                            button.addEventListener('click', function() {
                                var isOpen = sectionState[sectionName] !== false;
                                sectionState[sectionName] = !isOpen;
                                persistSectionState();
                                applySectionState(sectionName);
                            });
                        })(sectionButtons[j]);
                    }

                    // Click outside to close menu
                    document.addEventListener('mousedown', function(e) {
                        if (!menuOpen) return;
                        if (footerRef && !footerRef.contains(e.target)) {
                            setMenuOpen(false);
                        }
                    });

                    if (sidebarOverlay) {
                        sidebarOverlay.addEventListener('click', function() {
                            setExpanded(false);
                        });
                    }

                    document.addEventListener('keydown', function(event) {
                        if (event.key === 'Escape' && isMobileViewport()) {
                            setExpanded(false);
                        }
                    });

                    window.addEventListener('resize', function() {
                        if (isMobileViewport()) {
                            body.classList.remove('admin-sidebar-mobile-open');
                            setSidebarWidth('0px');
                            updateToggleIcon();
                            return;
                        }

                        body.classList.remove('admin-sidebar-mobile-open');
                        if (mode === 'locked-open') {
                            setExpanded(true);
                            return;
                        }

                        setExpanded(false);
                    });

                    normalizeBreadcrumbs();
                    syncTopbarCopy();
                    updateToggleIcon();
                })();
            </script>

            {{-- Theme mode — syncs with React panel via localStorage --}}
            <script>
                (function() {
                    var THEME_ID = 'burhan-core';
                    var THEME_MODES = {
                        dark: {
                            background: 'rgb(214, 210, 199)',
                            foreground: 'rgb(116, 34, 32)',
                            card: 'rgb(254, 249, 225)',
                            primary: 'rgb(116, 34, 32)',
                            'muted-foreground': 'rgba(116, 34, 32, 0.62)',
                            'text-subtle': 'rgba(116, 34, 32, 0.58)',
                            border: 'rgba(45, 74, 62, 0.28)',
                            accent: 'rgb(45, 74, 62)',
                            destructive: 'rgb(116, 34, 32)',
                            'surface-elevated': 'rgb(254, 249, 225)'
                        }
                    };

                    function extractRgb(val) {
                        if (!val) return null;
                        var m = val.match(/rgb[a]?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)/);
                        return m ? m[1]+', '+m[2]+', '+m[3] : null;
                    }

                    function normalizeMode() { return 'dark'; }

                    function applyThemeMode(mode) {
                        var normalizedMode = normalizeMode(mode);
                        var t = THEME_MODES[normalizedMode];
                        if (!t) return;
                        var root = document.documentElement;
                        var pRgb = extractRgb(t.primary) || '116, 34, 32';
                        var bgRgb = extractRgb(t.background) || '214, 210, 199';
                        var cardRgb = extractRgb(t.card) || bgRgb;

                        root.style.setProperty('--admin-background', t.background);
                        root.style.setProperty('--admin-foreground', t.foreground);
                        root.style.setProperty('--admin-card', t.card);
                        root.style.setProperty('--admin-card-foreground', t.foreground);
                        root.style.setProperty('--admin-primary', t.primary);
                        root.style.setProperty('--admin-muted-foreground', t['muted-foreground']);
                        root.style.setProperty('--admin-text-subtle', t['text-subtle'] || t['muted-foreground']);
                        root.style.setProperty('--admin-border', t.border);
                        root.style.setProperty('--admin-accent', t.accent);
                        root.style.setProperty('--admin-destructive', t.destructive);
                        root.style.setProperty('--admin-primary-rgb', pRgb);
                        root.style.setProperty('--admin-background-rgb', bgRgb);
                        root.style.setProperty('--admin-card-rgb', cardRgb);
                        root.style.setProperty('--admin-surface-elevated', t['surface-elevated'] || t.card);
                        root.style.setProperty('--admin-surface-elevated-rgb', extractRgb(t['surface-elevated'] || t.card) || cardRgb);
                        root.style.setProperty('--admin-primary-glow-soft', 'rgba('+pRgb+', 0.18)');
                        root.style.setProperty('--admin-primary-glow-medium', 'rgba('+pRgb+', 0.28)');
                        root.style.setProperty('--admin-primary-glow-strong', 'rgba('+pRgb+', 0.4)');

                        localStorage.setItem('panel.theme.id', THEME_ID);
                        localStorage.setItem('panel.theme.mode', normalizedMode);

                    }
                    applyThemeMode('dark');
                })();
            </script>

            @if(Auth::user()->root_admin)
                <script>
                    $('#logoutButton').on('click', function (event) {
                        event.preventDefault();

                        var that = this;
                        swal({
                            title: 'Do you want to log out?',
                            type: 'warning',
                            showCancelButton: true,
                            confirmButtonColor: '#d9534f',
                            cancelButtonColor: '#d33',
                            confirmButtonText: 'Log out'
                        }, function () {
                             $.ajax({
                                type: 'POST',
                                url: '{{ route('auth.logout') }}',
                                data: {
                                    _token: '{{ csrf_token() }}'
                                },complete: function () {
                                    window.location.href = '{{route('auth.login')}}';
                                }
                        });
                    });
                });
                </script>
            @endif

            <script>
                $(function () {
                    $('[data-toggle="tooltip"]').tooltip();
                })
            </script>
        @show
    </body>
</html>
