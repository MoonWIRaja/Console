<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>{{ config('app.name', 'Pterodactyl') }} - @yield('title')</title>
        <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
        <meta name="_token" content="{{ csrf_token() }}">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <link rel="apple-touch-icon" sizes="180x180" href="/favicons/apple-touch-icon.png">
        <link rel="icon" type="image/png" href="/favicons/favicon-32x32.png" sizes="32x32">
        <link rel="icon" type="image/png" href="/favicons/favicon-16x16.png" sizes="16x16">
        <link rel="manifest" href="/favicons/manifest.json">
        <link rel="mask-icon" href="/favicons/safari-pinned-tab.svg" color="#bc6e3c">
        <link rel="shortcut icon" href="/favicons/favicon.ico">
        <meta name="msapplication-config" content="/favicons/browserconfig.xml">
        <meta name="theme-color" content="#0e4688">

        @include('layouts.scripts')

        @section('scripts')
            {!! Theme::css('vendor/select2/select2.min.css?t={cache-version}') !!}
            {!! Theme::css('vendor/bootstrap/bootstrap.min.css?t={cache-version}') !!}
            {!! Theme::css('vendor/adminlte/admin.min.css?t={cache-version}') !!}
            {!! Theme::css('vendor/adminlte/colors/skin-blue.min.css?t={cache-version}') !!}
            {!! Theme::css('vendor/sweetalert/sweetalert.min.css?t={cache-version}') !!}
            {!! Theme::css('vendor/animate/animate.min.css?t={cache-version}') !!}
            {!! Theme::css('css/pterodactyl.css?t={cache-version}') !!}
            {!! Theme::css('css/admin-sidebar.css?t={cache-version}') !!}
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/ionicons/2.0.1/css/ionicons.min.css">
            <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap">
            <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons+Round">

            <!--[if lt IE 9]>
            <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
            <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
            <![endif]-->
        @show
    </head>
    <body class="hold-transition skin-blue fixed sidebar-mini">
        {{-- ===== Custom Sidebar (matches React sidebar) ===== --}}
        <div class="sidebar-desktop-shell" id="adminSidebar">
            {{-- Logo --}}
            <div style="padding: 24px 24px 16px; overflow: hidden; white-space: nowrap;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <img src="/assets/svgs/pterodactyl.svg" alt="System Logo" class="sidebar-logo-img" style="object-fit: contain; filter: brightness(1.15); flex-shrink: 0;">
                    <div class="sidebar-text" style="font-size: 18px; font-weight: 900; color: var(--admin-foreground); line-height: 1; letter-spacing: -0.02em; text-shadow: 0 0 10px rgba(var(--admin-primary-rgb), 0.18); white-space: nowrap;">
                        BurHan Console
                    </div>
                </div>
            </div>

            {{-- Nav --}}
            <nav style="flex: 1; overflow-y: auto; padding: 0 12px;">
                <div class="sidebar-label sidebar-text">BASIC ADMINISTRATION</div>

                <a href="{{ route('admin.index') }}" style="text-decoration: none;">
                    <div class="sidebar-link {{ Route::currentRouteName() !== 'admin.index' ?: 'active' }}">
                        <div style="flex-shrink: 0; width: 20px; display: flex; justify-content: center;">
                            <span class="material-icons-round" style="font-size: 20px;">dashboard</span>
                        </div>
                        <span class="sidebar-text" style="font-size: 14px; font-weight: 500; white-space: nowrap;">Overview</span>
                    </div>
                </a>

                <a href="{{ route('admin.settings') }}" style="text-decoration: none;">
                    <div class="sidebar-link {{ ! starts_with(Route::currentRouteName(), 'admin.settings') ?: 'active' }}">
                        <div style="flex-shrink: 0; width: 20px; display: flex; justify-content: center;">
                            <span class="material-icons-round" style="font-size: 20px;">settings</span>
                        </div>
                        <span class="sidebar-text" style="font-size: 14px; font-weight: 500; white-space: nowrap;">Settings</span>
                    </div>
                </a>

                <a href="{{ route('admin.api.index') }}" style="text-decoration: none;">
                    <div class="sidebar-link {{ ! starts_with(Route::currentRouteName(), 'admin.api') ?: 'active' }}">
                        <div style="flex-shrink: 0; width: 20px; display: flex; justify-content: center;">
                            <span class="material-icons-round" style="font-size: 20px;">api</span>
                        </div>
                        <span class="sidebar-text" style="font-size: 14px; font-weight: 500; white-space: nowrap;">Application API</span>
                    </div>
                </a>

                <div class="sidebar-label sidebar-text">MANAGEMENT</div>

                <a href="{{ route('admin.databases') }}" style="text-decoration: none;">
                    <div class="sidebar-link {{ ! starts_with(Route::currentRouteName(), 'admin.databases') ?: 'active' }}">
                        <div style="flex-shrink: 0; width: 20px; display: flex; justify-content: center;">
                            <span class="material-icons-round" style="font-size: 20px;">dns</span>
                        </div>
                        <span class="sidebar-text" style="font-size: 14px; font-weight: 500; white-space: nowrap;">Databases</span>
                    </div>
                </a>

                <a href="{{ route('admin.locations') }}" style="text-decoration: none;">
                    <div class="sidebar-link {{ ! starts_with(Route::currentRouteName(), 'admin.locations') ?: 'active' }}">
                        <div style="flex-shrink: 0; width: 20px; display: flex; justify-content: center;">
                            <span class="material-icons-round" style="font-size: 20px;">public</span>
                        </div>
                        <span class="sidebar-text" style="font-size: 14px; font-weight: 500; white-space: nowrap;">Locations</span>
                    </div>
                </a>

                <a href="{{ route('admin.nodes') }}" style="text-decoration: none;">
                    <div class="sidebar-link {{ ! starts_with(Route::currentRouteName(), 'admin.nodes') ?: 'active' }}">
                        <div style="flex-shrink: 0; width: 20px; display: flex; justify-content: center;">
                            <span class="material-icons-round" style="font-size: 20px;">hub</span>
                        </div>
                        <span class="sidebar-text" style="font-size: 14px; font-weight: 500; white-space: nowrap;">Nodes</span>
                    </div>
                </a>

                <a href="{{ route('admin.servers') }}" style="text-decoration: none;">
                    <div class="sidebar-link {{ ! starts_with(Route::currentRouteName(), 'admin.servers') ?: 'active' }}">
                        <div style="flex-shrink: 0; width: 20px; display: flex; justify-content: center;">
                            <span class="material-icons-round" style="font-size: 20px;">storage</span>
                        </div>
                        <span class="sidebar-text" style="font-size: 14px; font-weight: 500; white-space: nowrap;">Servers</span>
                    </div>
                </a>

                <a href="{{ route('admin.users') }}" style="text-decoration: none;">
                    <div class="sidebar-link {{ ! starts_with(Route::currentRouteName(), 'admin.users') ?: 'active' }}">
                        <div style="flex-shrink: 0; width: 20px; display: flex; justify-content: center;">
                            <span class="material-icons-round" style="font-size: 20px;">group</span>
                        </div>
                        <span class="sidebar-text" style="font-size: 14px; font-weight: 500; white-space: nowrap;">Users</span>
                    </div>
                </a>

                <div class="sidebar-label sidebar-text">SERVICE MANAGEMENT</div>

                <a href="{{ route('admin.mounts') }}" style="text-decoration: none;">
                    <div class="sidebar-link {{ ! starts_with(Route::currentRouteName(), 'admin.mounts') ?: 'active' }}">
                        <div style="flex-shrink: 0; width: 20px; display: flex; justify-content: center;">
                            <span class="material-icons-round" style="font-size: 20px;">inventory_2</span>
                        </div>
                        <span class="sidebar-text" style="font-size: 14px; font-weight: 500; white-space: nowrap;">Mounts</span>
                    </div>
                </a>

                <a href="{{ route('admin.nests') }}" style="text-decoration: none;">
                    <div class="sidebar-link {{ ! starts_with(Route::currentRouteName(), 'admin.nests') ?: 'active' }}">
                        <div style="flex-shrink: 0; width: 20px; display: flex; justify-content: center;">
                            <span class="material-icons-round" style="font-size: 20px;">grid_view</span>
                        </div>
                        <span class="sidebar-text" style="font-size: 14px; font-weight: 500; white-space: nowrap;">Nests</span>
                    </div>
                </a>
            </nav>

            {{-- Back to Dashboard --}}
            <div style="padding: 0px 12px 8px;">
                <a href="/" style="text-decoration: none;">
                    <div class="sidebar-link">
                        <div style="flex-shrink: 0; width: 20px; display: flex; justify-content: center;">
                            <span class="material-icons-round" style="font-size: 20px;">dashboard</span>
                        </div>
                        <span class="sidebar-text" style="font-size: 14px; font-weight: 500; white-space: nowrap;">Back to Dashboard</span>
                    </div>
                </a>
            </div>

            {{-- User Footer --}}
            <div id="userFooter" style="border-top: 1px solid var(--admin-border); padding: 16px; background-color: rgba(var(--admin-card-rgb), 0.45); position: relative;">
                {{-- Avatar Button --}}
                <button type="button" id="userFooterBtn" class="sidebar-user-btn" style="width: 100%; display: flex; align-items: center; gap: 12px; margin-bottom: 0; cursor: pointer; padding: 4px; border-radius: 8px; border: none; background: transparent;">
                    <div style="width: 36px; height: 36px; border-radius: 8px; overflow: hidden; flex-shrink: 0; background-color: var(--admin-background); display: flex; align-items: center; justify-content: center; border: 1px solid rgba(var(--admin-primary-rgb), 0.35); box-shadow: 0 0 0 1px rgba(var(--admin-primary-rgb), 0.12), 0 6px 14px -6px rgba(var(--admin-primary-rgb), 0.45);">
                        <img src="{{ Auth::user()->getImageUrl() }}" alt="User avatar" style="width: 36px; height: 36px; border-radius: 9999px; object-fit: cover;">
                    </div>
                    <div class="sidebar-text" style="font-size: 14px; font-weight: 700; color: var(--admin-foreground); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; display: flex; align-items: center; justify-content: space-between; width: 100%;">
                        <span>{{ Auth::user()->name_first }} {{ Auth::user()->name_last }}</span>
                        <span class="material-icons-round" id="userFooterArrow" style="font-size: 18px; color: var(--admin-muted-foreground);">expand_more</span>
                    </div>
                </button>

                {{-- Popup Menu --}}
                <div id="userFooterMenu" style="display: none; position: absolute; left: 12px; right: 12px; bottom: calc(100% + 8px); border: 1px solid var(--admin-border); background-color: var(--admin-card); border-radius: 12px; padding: 10px; flex-direction: column; gap: 10px; box-shadow: 0 14px 34px rgba(0, 0, 0, 0.45); z-index: 50;">

                    {{-- Profile --}}
                    <a href="{{ route('account') }}" style="text-decoration: none;">
                        <div class="footer-menu-item" style="width: 100%; color: var(--admin-foreground); display: flex; cursor: pointer; align-items: center; justify-content: space-between; gap: 8px; border-radius: 14px; padding: 12px; transition: background-color 0.15s;">
                            <div style="display: flex; min-width: 0; align-items: center; gap: 10px;">
                                <div style="display: flex; height: 34px; width: 34px; align-items: center; justify-content: center; border-radius: 999px; border: 1px solid var(--admin-border); background: var(--admin-background); color: var(--admin-primary);">
                                    <span class="material-icons-round" style="font-size: 16px;">person</span>
                                </div>
                                <span style="font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; white-space: nowrap;">Profile</span>
                            </div>
                        </div>
                    </a>

                    {{-- Theme Selector (matches React Select exactly) --}}
                    <div id="themeSelectWrapper" style="position: relative; padding: 4px 12px;">
                        {{-- Trigger (matches SelectItem noDescription=true mode) --}}
                        <div id="themeSelectTrigger" style="width: 100%; overflow: hidden; border: 1px solid var(--admin-border); background-color: var(--admin-card); border-radius: 30px; cursor: pointer; box-shadow: 0 1px 2px rgba(0,0,0,0.08); transition: border-color 0.15s;">
                            <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 8px;">
                                <div style="display: flex; min-width: 0; align-items: center; gap: 12px;">
                                    <div class="theme-icon-circle" style="flex-shrink: 0;">
                                        <span class="material-icons-round" style="font-size: 16px;">palette</span>
                                    </div>
                                    <strong id="themeSelectLabel" class="theme-label-text">Cyberpunk</strong>
                                </div>
                                <div style="display: flex; align-items: center; padding-right: 12px;">
                                    <span class="material-icons-round" style="font-size: 20px; color: var(--admin-primary);">expand_more</span>
                                </div>
                            </div>
                        </div>

                        {{-- Dropdown Panel --}}
                        <div id="themeSelectDropdown" class="theme-select-dropdown">
                            {{-- Header --}}
                            <div style="display: flex; align-items: center; justify-content: space-between; padding: 12px 16px;">
                                <strong style="font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--admin-foreground);">Theme</strong>
                                <button type="button" id="themeSelectClose" class="theme-close-btn">
                                    <span class="material-icons-round" style="font-size: 12px;">close</span>
                                </button>
                            </div>
                            {{-- Items --}}
                            <div class="theme-select-scroll">
                                @foreach([
                                    ['cyberpunk', 'Cyberpunk'],
                                    ['earthy', 'Earthy'],
                                    ['amber-mono', 'Amber Mono'],
                                    ['limes', 'Limes'],
                                    ['domia', 'Domia'],
                                    ['flat-pink', 'Flat Pink'],
                                    ['terminal-muted', 'Terminal Muted'],
                                    ['light-green', 'Light Green']
                                ] as $theme)
                                <div class="theme-select-item" data-theme-id="{{ $theme[0] }}">
                                    <div style="display: flex; min-width: 0; align-items: center; gap: 12px;">
                                        <div class="theme-icon-circle" style="flex-shrink: 0;">
                                            <span class="material-icons-round" style="font-size: 16px;">palette</span>
                                        </div>
                                        <div style="display: flex; flex-direction: column; min-width: 0; width: 224px;">
                                            <strong class="theme-label-text">{{ $theme[1] }}</strong>
                                            <span style="font-size: 11px; color: var(--admin-muted-foreground); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Dark Mode</span>
                                        </div>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    {{-- Log out --}}
                    <button type="button" id="logoutButton" class="footer-menu-item" style="width: 100%; color: var(--admin-muted-foreground); cursor: pointer; border: none; background: transparent; display: flex; align-items: center; justify-content: space-between; gap: 8px; border-radius: 14px; padding: 12px; transition: background-color 0.15s;">
                        <div style="display: flex; min-width: 0; align-items: center; gap: 10px;">
                            <div style="display: flex; height: 34px; width: 34px; align-items: center; justify-content: center; border-radius: 999px; border: 1px solid var(--admin-border); background: var(--admin-background); color: var(--admin-primary);">
                                <span class="material-icons-round" style="font-size: 16px;">logout</span>
                            </div>
                            <span style="font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; white-space: nowrap;">Log out</span>
                        </div>
                    </button>
                </div>
            </div>
        </div>

        {{-- ===== Main Content ===== --}}
        <div class="admin-content-wrapper" style="display: flex; flex-direction: column;">
            <div style="flex: 1 1 auto;">
            <section class="content-header" style="padding: 24px 30px 12px;">
                @yield('content-header')
            </section>
            <section class="content" style="padding: 12px 30px 30px;">
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
            <footer style="background: var(--admin-card); color: var(--admin-muted-foreground); border-top: 1px solid rgba(var(--admin-primary-rgb), 0.22); padding: 15px 30px; font-size: 13px; font-family: 'Inter', sans-serif; margin-top: auto;">
                <div class="pull-right small" style="color: var(--admin-muted-foreground); margin-right:10px;margin-top:-7px;">
                    <strong><i class="fa fa-fw {{ $appIsGit ? 'fa-git-square' : 'fa-code-fork' }}"></i></strong> {{ $appVersion }}<br />
                    <strong><i class="fa fa-fw fa-clock-o"></i></strong> {{ round(microtime(true) - LARAVEL_START, 3) }}s
                </div>
                Copyright &copy; 2015 - {{ date('Y') }} <a href="https://pterodactyl.io/" style="color: var(--admin-primary) !important;">Pterodactyl Software</a>.
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
                    var content = document.querySelector('.admin-content-wrapper');
                    var footerBtn = document.getElementById('userFooterBtn');
                    var footerMenu = document.getElementById('userFooterMenu');
                    var footerArrow = document.getElementById('userFooterArrow');
                    var footerRef = document.getElementById('userFooter');
                    var isExpanded = false;
                    var menuOpen = false;

                    function setExpanded(val) {
                        isExpanded = val;
                        if (sidebar) sidebar.style.width = val ? '256px' : '72px';
                        if (content) content.style.marginLeft = val ? '256px' : '72px';
                        // Close menu when sidebar collapses
                        if (!val) setMenuOpen(false);
                    }

                    function setMenuOpen(val) {
                        menuOpen = val;
                        if (footerMenu) footerMenu.style.display = val ? 'flex' : 'none';
                        if (footerArrow) footerArrow.textContent = val ? 'expand_less' : 'expand_more';
                    }

                    if (sidebar) {
                        sidebar.addEventListener('mouseenter', function() { setExpanded(true); });
                        sidebar.addEventListener('mouseleave', function() { setExpanded(false); });
                    }

                    // Avatar button click — expand sidebar first if collapsed, then toggle menu
                    if (footerBtn) {
                        footerBtn.addEventListener('click', function(e) {
                            e.stopPropagation();
                            if (!isExpanded) {
                                setExpanded(true);
                                return;
                            }
                            setMenuOpen(!menuOpen);
                        });
                    }

                    // Click outside to close menu
                    document.addEventListener('mousedown', function(e) {
                        if (!menuOpen) return;
                        if (footerRef && !footerRef.contains(e.target)) {
                            setMenuOpen(false);
                        }
                    });
                })();
            </script>

            {{-- Theme presets — syncs with React sidebar via localStorage --}}
            <script>
                (function() {
                    var THEMES = {
                        'cyberpunk':       { background:'rgb(0,0,0)', foreground:'rgb(0,255,65)', card:'rgb(5,5,5)', primary:'rgb(0,255,65)', 'muted-foreground':'rgb(0,143,17)', border:'rgb(0,59,0)', accent:'rgb(0,255,65)', destructive:'rgb(255,0,0)' },
                        'earthy':          { background:'rgb(38,38,36)', foreground:'rgb(241,241,239)', card:'rgb(44,44,43)', primary:'rgb(217,119,87)', 'muted-foreground':'rgb(183,181,169)', border:'rgb(62,62,56)', accent:'rgb(26,25,21)', destructive:'rgb(239,68,68)' },
                        'amber-mono':      { background:'rgb(10,10,10)', foreground:'rgb(245,245,245)', card:'rgb(23,23,23)', primary:'rgb(225,113,0)', 'muted-foreground':'rgb(166,160,155)', border:'rgb(68,64,59)', accent:'rgb(68,64,59)', destructive:'rgb(193,0,7)' },
                        'limes':           { background:'rgb(0,0,0)', foreground:'rgb(255,255,255)', card:'rgb(0,0,0)', primary:'rgb(94,165,0)', 'muted-foreground':'rgb(164,164,164)', border:'rgb(36,36,36)', accent:'rgb(51,51,51)', destructive:'rgb(255,91,91)' },
                        'domia':           { background:'rgb(0,0,0)', foreground:'rgb(231,233,234)', card:'rgb(23,24,28)', primary:'rgb(186,0,189)', 'muted-foreground':'rgb(114,118,122)', border:'rgb(36,38,40)', accent:'rgb(6,22,34)', destructive:'rgb(244,33,46)' },
                        'flat-pink':       { background:'rgb(0,0,0)', foreground:'rgb(255,255,255)', card:'rgb(0,0,0)', primary:'rgb(124,0,81)', 'muted-foreground':'rgb(164,164,164)', border:'rgb(36,36,36)', accent:'rgb(51,51,51)', destructive:'rgb(91,91,255)' },
                        'terminal-muted':  { background:'rgb(9,12,10)', foreground:'rgb(169,214,185)', card:'rgb(13,18,15)', primary:'rgb(69,161,103)', 'muted-foreground':'rgb(117,163,134)', border:'rgb(33,44,37)', accent:'rgb(34,42,37)', destructive:'rgb(172,57,57)' },
                        'light-green':     { background:'rgb(2,6,23)', foreground:'rgb(248,250,252)', card:'rgb(15,23,42)', primary:'rgb(175,243,62)', 'muted-foreground':'rgb(148,163,184)', border:'rgb(30,41,59)', accent:'rgb(20,83,45)', destructive:'rgb(153,27,27)' }
                    };

                    var LABELS = {
                        'cyberpunk':'Cyberpunk', 'earthy':'Earthy', 'amber-mono':'Amber Mono', 'limes':'Limes',
                        'domia':'Domia', 'flat-pink':'Flat Pink', 'terminal-muted':'Terminal Muted', 'light-green':'Light Green'
                    };

                    function extractRgb(val) {
                        if (!val) return null;
                        var m = val.match(/rgb[a]?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)/);
                        return m ? m[1]+', '+m[2]+', '+m[3] : null;
                    }

                    function applyTheme(themeId) {
                        var t = THEMES[themeId];
                        if (!t) return;
                        var root = document.documentElement;
                        var pRgb = extractRgb(t.primary) || '0,255,65';
                        var bgRgb = extractRgb(t.background) || '0,0,0';
                        var cardRgb = extractRgb(t.card) || bgRgb;

                        root.style.setProperty('--admin-background', t.background);
                        root.style.setProperty('--admin-foreground', t.foreground);
                        root.style.setProperty('--admin-card', t.card);
                        root.style.setProperty('--admin-card-foreground', t.foreground);
                        root.style.setProperty('--admin-primary', t.primary);
                        root.style.setProperty('--admin-muted-foreground', t['muted-foreground']);
                        root.style.setProperty('--admin-border', t.border);
                        root.style.setProperty('--admin-accent', t.accent);
                        root.style.setProperty('--admin-destructive', t.destructive);
                        root.style.setProperty('--admin-primary-rgb', pRgb);
                        root.style.setProperty('--admin-background-rgb', bgRgb);
                        root.style.setProperty('--admin-card-rgb', cardRgb);
                        root.style.setProperty('--admin-primary-glow-soft', 'rgba('+pRgb+', 0.22)');
                        root.style.setProperty('--admin-primary-glow-medium', 'rgba('+pRgb+', 0.35)');
                        root.style.setProperty('--admin-primary-glow-strong', 'rgba('+pRgb+', 0.55)');

                        localStorage.setItem('panel.theme.id', themeId);
                        localStorage.setItem('panel.theme.mode', 'dark');

                        // Update trigger label
                        var label = document.getElementById('themeSelectLabel');
                        if (label) label.textContent = LABELS[themeId] || themeId;
                    }

                    // Custom dropdown toggle
                    var trigger = document.getElementById('themeSelectTrigger');
                    var dropdown = document.getElementById('themeSelectDropdown');
                    var closeBtn = document.getElementById('themeSelectClose');
                    var wrapper = document.getElementById('themeSelectWrapper');
                    var themeDropOpen = false;

                    function setThemeDropOpen(val) {
                        themeDropOpen = val;
                        if (dropdown) dropdown.style.display = val ? 'block' : 'none';
                    }

                    if (trigger) {
                        trigger.addEventListener('click', function(e) {
                            e.stopPropagation();
                            setThemeDropOpen(!themeDropOpen);
                        });
                    }
                    if (closeBtn) {
                        closeBtn.addEventListener('click', function(e) {
                            e.stopPropagation();
                            setThemeDropOpen(false);
                        });
                    }

                    // Click outside to close theme dropdown
                    document.addEventListener('mousedown', function(e) {
                        if (!themeDropOpen) return;
                        if (wrapper && !wrapper.contains(e.target)) {
                            setThemeDropOpen(false);
                        }
                    });

                    // Theme item clicks
                    var items = document.querySelectorAll('.theme-select-item');
                    for (var i = 0; i < items.length; i++) {
                        (function(item) {
                            item.addEventListener('click', function() {
                                var id = item.getAttribute('data-theme-id');
                                applyTheme(id);
                                setThemeDropOpen(false);
                            });
                        })(items[i]);
                    }

                    // Load saved theme on page load
                    var savedTheme = localStorage.getItem('panel.theme.id') || 'cyberpunk';
                    applyTheme(savedTheme);
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
