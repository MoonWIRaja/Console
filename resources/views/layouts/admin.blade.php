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
            {!! Theme::css('css/admin-sidebar.css?v=20260406-0208') !!}
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
                    <button type="button" class="admin-topbar-icon" id="adminSidebarToggle" aria-label="Toggle sidebar">
                        <svg
                            aria-hidden="true"
                            focusable="false"
                            width="16"
                            height="16"
                            viewBox="0 0 320 512"
                            fill="currentColor"
                            id="adminSidebarToggleArrow"
                        >
                            <path d="M34.52 239.03L228.87 44.69c9.37-9.37 24.57-9.37 33.94 0l22.67 22.67c9.36 9.36 9.37 24.52.04 33.9L131.49 256l154.02 154.75c9.34 9.38 9.32 24.54-.04 33.9l-22.67 22.67c-9.37 9.37-24.57 9.37-33.94 0L34.52 272.97c-9.37-9.37-9.37-24.57 0-33.94z" />
                        </svg>
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
                <a href="{{ route('account') }}" class="admin-topbar-user">
                    <img src="{{ Auth::user()->getImageUrl() }}" alt="User avatar" class="admin-topbar-avatar">
                    <span class="admin-topbar-user-copy">
                        <span class="admin-topbar-user-name">{{ Auth::user()->name_first }} {{ Auth::user()->name_last }}</span>
                        <span class="admin-topbar-user-role">{{ Auth::user()->root_admin ? 'Root Admin' : 'Staff Account' }}</span>
                    </span>
                </a>
            </div>
        </header>
        <div id="adminSidebarOverlay" class="admin-sidebar-overlay"></div>

        {{-- ===== Custom Sidebar (matches React sidebar) ===== --}}
        <div class="sidebar-desktop-shell" id="adminSidebar">
            {{-- Nav --}}
            <nav style="flex: 1; overflow-y: auto; padding: 14px 12px 0;">
                <div class="admin-sidebar-section" data-admin-section="basic">
                    <button
                        type="button"
                        class="sidebar-text admin-sidebar-section-toggle"
                        data-admin-section-toggle="basic"
                        aria-expanded="true"
                        style="width: 100%; display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 8px; padding: 8px 10px 6px; border: none; background: transparent; color: rgba(248, 246, 239, 0.62); cursor: pointer; text-align: left;"
                    >
                        <span class="admin-sidebar-section-title">Basic Administration</span>
                        <span class="material-icons-round admin-sidebar-section-arrow" data-admin-section-arrow="basic" style="font-size: 18px; color: rgba(248, 246, 239, 0.54); transform: rotate(0deg); transition: transform 0.2s; flex-shrink: 0;">expand_less</span>
                    </button>
                    <div class="admin-sidebar-section-content" data-admin-section-content="basic">
                        <a href="{{ route('admin.index') }}" style="text-decoration: none;">
                            <div class="sidebar-link {{ Route::currentRouteName() === 'admin.index' ? 'active' : '' }}">
                                <div style="flex-shrink: 0; width: 20px; display: flex; justify-content: center;">
                                    <span class="material-icons-round" style="font-size: 20px;">dashboard</span>
                                </div>
                                <span class="sidebar-text" style="font-size: 14px; font-weight: 500; white-space: nowrap;">Overview</span>
                            </div>
                        </a>

                        <a href="{{ route('admin.settings') }}" style="text-decoration: none;">
                            <div class="sidebar-link {{ starts_with(Route::currentRouteName(), 'admin.settings') ? 'active' : '' }}">
                                <div style="flex-shrink: 0; width: 20px; display: flex; justify-content: center;">
                                    <span class="material-icons-round" style="font-size: 20px;">settings</span>
                                </div>
                                <span class="sidebar-text" style="font-size: 14px; font-weight: 500; white-space: nowrap;">Settings</span>
                            </div>
                        </a>

                        <a href="{{ route('admin.oauth') }}" style="text-decoration: none;">
                            <div class="sidebar-link {{ starts_with(Route::currentRouteName(), 'admin.oauth') ? 'active' : '' }}">
                                <div style="flex-shrink: 0; width: 20px; display: flex; justify-content: center;">
                                    <span class="material-icons-round" style="font-size: 20px;">account_tree</span>
                                </div>
                                <span class="sidebar-text" style="font-size: 14px; font-weight: 500; white-space: nowrap;">OAuth</span>
                            </div>
                        </a>

                        <a href="{{ route('admin.discord') }}" style="text-decoration: none;">
                            <div class="sidebar-link {{ starts_with(Route::currentRouteName(), 'admin.discord') ? 'active' : '' }}">
                                <div style="flex-shrink: 0; width: 20px; display: flex; justify-content: center;">
                                    <span class="material-icons-round" style="font-size: 20px;">forum</span>
                                </div>
                                <span class="sidebar-text" style="font-size: 14px; font-weight: 500; white-space: nowrap;">Discord</span>
                            </div>
                        </a>

                        <a href="{{ route('admin.always-motd') }}" style="text-decoration: none;">
                            <div class="sidebar-link {{ starts_with(Route::currentRouteName(), 'admin.always-motd') ? 'active' : '' }}">
                                <div style="flex-shrink: 0; width: 20px; display: flex; justify-content: center;">
                                    <span class="material-icons-round" style="font-size: 20px;">stream</span>
                                </div>
                                <span class="sidebar-text" style="font-size: 14px; font-weight: 500; white-space: nowrap;">Minecraft MOTD</span>
                            </div>
                        </a>

                        <a href="{{ route('admin.down-detector') }}" style="text-decoration: none;">
                            <div class="sidebar-link {{ starts_with(Route::currentRouteName(), 'admin.down-detector') ? 'active' : '' }}">
                                <div style="flex-shrink: 0; width: 20px; display: flex; justify-content: center;">
                                    <span class="material-icons-round" style="font-size: 20px;">radar</span>
                                </div>
                                <span class="sidebar-text" style="font-size: 14px; font-weight: 500; white-space: nowrap;">Down Detector</span>
                            </div>
                        </a>

                        <a href="{{ route('admin.security') }}" style="text-decoration: none;">
                            <div class="sidebar-link {{ starts_with(Route::currentRouteName(), 'admin.security') ? 'active' : '' }}">
                                <div style="flex-shrink: 0; width: 20px; display: flex; justify-content: center;">
                                    <span class="material-icons-round" style="font-size: 20px;">shield</span>
                                </div>
                                <span class="sidebar-text" style="font-size: 14px; font-weight: 500; white-space: nowrap;">Security</span>
                            </div>
                        </a>

                        <a href="{{ route('admin.logs') }}" style="text-decoration: none;">
                            <div class="sidebar-link {{ starts_with(Route::currentRouteName(), 'admin.logs') ? 'active' : '' }}">
                                <div style="flex-shrink: 0; width: 20px; display: flex; justify-content: center;">
                                    <span class="material-icons-round" style="font-size: 20px;">receipt_long</span>
                                </div>
                                <span class="sidebar-text" style="font-size: 14px; font-weight: 500; white-space: nowrap;">System Logs</span>
                            </div>
                        </a>

                        <a href="{{ route('admin.api.index') }}" style="text-decoration: none;">
                            <div class="sidebar-link {{ starts_with(Route::currentRouteName(), 'admin.api') ? 'active' : '' }}">
                                <div style="flex-shrink: 0; width: 20px; display: flex; justify-content: center;">
                                    <span class="material-icons-round" style="font-size: 20px;">api</span>
                                </div>
                                <span class="sidebar-text" style="font-size: 14px; font-weight: 500; white-space: nowrap;">Application API</span>
                            </div>
                        </a>
                    </div>
                </div>

                <div class="admin-sidebar-section" data-admin-section="management">
                    <button
                        type="button"
                        class="sidebar-text admin-sidebar-section-toggle"
                        data-admin-section-toggle="management"
                        aria-expanded="true"
                        style="width: 100%; display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 8px; padding: 8px 10px 6px; border: none; background: transparent; color: rgba(248, 246, 239, 0.62); cursor: pointer; text-align: left;"
                    >
                        <span class="admin-sidebar-section-title">Management</span>
                        <span class="material-icons-round admin-sidebar-section-arrow" data-admin-section-arrow="management" style="font-size: 18px; color: rgba(248, 246, 239, 0.54); transform: rotate(0deg); transition: transform 0.2s; flex-shrink: 0;">expand_less</span>
                    </button>
                    <div class="admin-sidebar-section-content" data-admin-section-content="management">
                        <a href="{{ route('admin.databases') }}" style="text-decoration: none;">
                            <div class="sidebar-link {{ starts_with(Route::currentRouteName(), 'admin.databases') ? 'active' : '' }}">
                                <div style="flex-shrink: 0; width: 20px; display: flex; justify-content: center;">
                                    <span class="material-icons-round" style="font-size: 20px;">dns</span>
                                </div>
                                <span class="sidebar-text" style="font-size: 14px; font-weight: 500; white-space: nowrap;">Databases</span>
                            </div>
                        </a>

                        <a href="{{ route('admin.subdomains.index') }}" style="text-decoration: none;">
                            <div class="sidebar-link {{ starts_with(Route::currentRouteName(), 'admin.subdomains') ? 'active' : '' }}">
                                <div style="flex-shrink: 0; width: 20px; display: flex; justify-content: center;">
                                    <span class="material-icons-round" style="font-size: 20px;">alternate_email</span>
                                </div>
                                <span class="sidebar-text" style="font-size: 14px; font-weight: 500; white-space: nowrap;">Subdomains</span>
                            </div>
                        </a>

                        <a href="{{ route('admin.locations') }}" style="text-decoration: none;">
                            <div class="sidebar-link {{ starts_with(Route::currentRouteName(), 'admin.locations') ? 'active' : '' }}">
                                <div style="flex-shrink: 0; width: 20px; display: flex; justify-content: center;">
                                    <span class="material-icons-round" style="font-size: 20px;">public</span>
                                </div>
                                <span class="sidebar-text" style="font-size: 14px; font-weight: 500; white-space: nowrap;">Locations</span>
                            </div>
                        </a>

                        <a href="{{ route('admin.nodes') }}" style="text-decoration: none;">
                            <div class="sidebar-link {{ starts_with(Route::currentRouteName(), 'admin.nodes') ? 'active' : '' }}">
                                <div style="flex-shrink: 0; width: 20px; display: flex; justify-content: center;">
                                    <span class="material-icons-round" style="font-size: 20px;">hub</span>
                                </div>
                                <span class="sidebar-text" style="font-size: 14px; font-weight: 500; white-space: nowrap;">Nodes</span>
                            </div>
                        </a>

                        <a href="{{ route('admin.servers') }}" style="text-decoration: none;">
                            <div class="sidebar-link {{ starts_with(Route::currentRouteName(), 'admin.servers') ? 'active' : '' }}">
                                <div style="flex-shrink: 0; width: 20px; display: flex; justify-content: center;">
                                    <span class="material-icons-round" style="font-size: 20px;">storage</span>
                                </div>
                                <span class="sidebar-text" style="font-size: 14px; font-weight: 500; white-space: nowrap;">Servers</span>
                            </div>
                        </a>

                        <a href="{{ route('admin.users') }}" style="text-decoration: none;">
                            <div class="sidebar-link {{ starts_with(Route::currentRouteName(), 'admin.users') ? 'active' : '' }}">
                                <div style="flex-shrink: 0; width: 20px; display: flex; justify-content: center;">
                                    <span class="material-icons-round" style="font-size: 20px;">group</span>
                                </div>
                                <span class="sidebar-text" style="font-size: 14px; font-weight: 500; white-space: nowrap;">Users</span>
                            </div>
                        </a>

                        @if(Auth::user()->root_admin)
                            <a href="{{ route('admin.billing') }}" style="text-decoration: none;">
                                <div class="sidebar-link {{ starts_with(Route::currentRouteName(), 'admin.billing') ? 'active' : '' }}">
                                    <div style="flex-shrink: 0; width: 20px; display: flex; justify-content: center;">
                                        <span class="material-icons-round" style="font-size: 20px;">payments</span>
                                    </div>
                                    <span class="sidebar-text" style="font-size: 14px; font-weight: 500; white-space: nowrap;">Billing</span>
                                </div>
                            </a>

                            <a href="{{ route('admin.tickets') }}" style="text-decoration: none;">
                                <div class="sidebar-link {{ starts_with(Route::currentRouteName(), 'admin.tickets') ? 'active' : '' }}">
                                    <div style="flex-shrink: 0; width: 20px; display: flex; justify-content: center;">
                                        <span class="material-icons-round" style="font-size: 20px;">support_agent</span>
                                    </div>
                                    <span class="sidebar-text" style="font-size: 14px; font-weight: 500; white-space: nowrap;">Support</span>
                                </div>
                            </a>
                        @endif
                    </div>
                </div>

                <div class="admin-sidebar-section" data-admin-section="service-management">
                    <button
                        type="button"
                        class="sidebar-text admin-sidebar-section-toggle"
                        data-admin-section-toggle="service-management"
                        aria-expanded="true"
                        style="width: 100%; display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 8px; padding: 8px 10px 6px; border: none; background: transparent; color: rgba(248, 246, 239, 0.62); cursor: pointer; text-align: left;"
                    >
                        <span class="admin-sidebar-section-title">Service Management</span>
                        <span class="material-icons-round admin-sidebar-section-arrow" data-admin-section-arrow="service-management" style="font-size: 18px; color: rgba(248, 246, 239, 0.54); transform: rotate(0deg); transition: transform 0.2s; flex-shrink: 0;">expand_less</span>
                    </button>
                    <div class="admin-sidebar-section-content" data-admin-section-content="service-management">
                        <a href="{{ route('admin.mounts') }}" style="text-decoration: none;">
                            <div class="sidebar-link {{ starts_with(Route::currentRouteName(), 'admin.mounts') ? 'active' : '' }}">
                                <div style="flex-shrink: 0; width: 20px; display: flex; justify-content: center;">
                                    <span class="material-icons-round" style="font-size: 20px;">inventory_2</span>
                                </div>
                                <span class="sidebar-text" style="font-size: 14px; font-weight: 500; white-space: nowrap;">Mounts</span>
                            </div>
                        </a>

                        <a href="{{ route('admin.nests') }}" style="text-decoration: none;">
                            <div class="sidebar-link {{ starts_with(Route::currentRouteName(), 'admin.nests') ? 'active' : '' }}">
                                <div style="flex-shrink: 0; width: 20px; display: flex; justify-content: center;">
                                    <span class="material-icons-round" style="font-size: 20px;">grid_view</span>
                                </div>
                                <span class="sidebar-text" style="font-size: 14px; font-weight: 500; white-space: nowrap;">Nests</span>
                            </div>
                        </a>
                    </div>
                </div>
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
            <div id="userFooter" style="border-top: 1px solid rgba(var(--admin-primary-rgb), 0.12); padding: 14px 12px 12px; background: transparent; position: relative;">
                {{-- Avatar Button --}}
                <button type="button" id="userFooterBtn" class="sidebar-user-btn" style="width: 100%; display: flex; align-items: center; gap: 12px; margin-bottom: 0; cursor: pointer; padding: 4px; border-radius: 12px; border: none; background: transparent;">
                    <div style="width: 40px; height: 40px; border-radius: 999px; overflow: hidden; flex-shrink: 0; background: transparent; display: flex; align-items: center; justify-content: center; box-shadow: none;">
                        <img src="{{ Auth::user()->getImageUrl() }}" alt="User avatar" style="width: 40px; height: 40px; border-radius: 9999px; object-fit: cover;">
                    </div>
                    <div class="sidebar-text" style="font-size: 14px; font-weight: 700; color: var(--admin-foreground); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; display: flex; align-items: center; justify-content: space-between; width: 100%;">
                        <span>{{ Auth::user()->name_first }} {{ Auth::user()->name_last }}</span>
                        <span class="material-icons-round" id="userFooterArrow" style="font-size: 18px; color: rgba(248, 246, 239, 0.56);">expand_more</span>
                    </div>
                </button>

                {{-- Popup Menu --}}
                <div id="userFooterMenu" style="display: none; position: absolute; left: 12px; right: 12px; bottom: calc(100% + 8px); border: 1px solid rgba(var(--admin-primary-rgb), 0.12); background: var(--admin-surface-elevated); border-radius: 16px; padding: 10px; flex-direction: column; gap: 10px; box-shadow: 0 22px 46px rgba(0, 0, 0, 0.32), inset 0 1px 0 rgba(255, 255, 255, 0.04); z-index: 50;">

                    {{-- Profile --}}
                    <a href="{{ route('account') }}" style="text-decoration: none;">
                        <div class="footer-menu-item" style="width: 100%; color: var(--admin-foreground); display: flex; cursor: pointer; align-items: center; justify-content: space-between; gap: 8px; border-radius: 14px; padding: 12px; transition: background-color 0.15s; background: transparent;">
                            <div style="display: flex; min-width: 0; align-items: center; gap: 10px;">
                                <div style="display: flex; height: 34px; width: 34px; align-items: center; justify-content: center; border-radius: 999px; border: 1px solid rgba(var(--admin-primary-rgb), 0.12); background: rgba(var(--admin-primary-rgb), 0.06); color: var(--admin-primary);">
                                    <span class="material-icons-round" style="font-size: 16px;">person</span>
                                </div>
                                <span style="font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; white-space: nowrap;">Profile</span>
                            </div>
                        </div>
                    </a>

                    {{-- Log out --}}
                    <button type="button" id="logoutButton" class="footer-menu-item" style="width: 100%; color: var(--admin-muted-foreground); cursor: pointer; border: none; background: transparent; display: flex; align-items: center; justify-content: space-between; gap: 8px; border-radius: 14px; padding: 12px; transition: background-color 0.15s;">
                        <div style="display: flex; min-width: 0; align-items: center; gap: 10px;">
                            <div style="display: flex; height: 34px; width: 34px; align-items: center; justify-content: center; border-radius: 999px; border: 1px solid rgba(var(--admin-primary-rgb), 0.12); background: rgba(var(--admin-primary-rgb), 0.06); color: var(--admin-primary);">
                                <span class="material-icons-round" style="font-size: 16px;">logout</span>
                            </div>
                            <span style="font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; white-space: nowrap;">Log out</span>
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
            <section class="content" style="padding: 12px 0 30px; width: 100%; margin: 0;">
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
                    var sidebarToggle = document.getElementById('adminSidebarToggle');
                    var sidebarToggleArrow = document.getElementById('adminSidebarToggleArrow');
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
                            if (sidebarToggleMobileIcon) {
                                sidebarToggleMobileIcon.textContent = body.classList.contains('admin-sidebar-mobile-open') ? 'close' : 'menu';
                            }
                            return;
                        }

                        if (sidebarToggleArrow) {
                            sidebarToggleArrow.style.transform = isExpanded ? 'none' : 'rotate(180deg)';
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
                            if (isMobileViewport()) {
                                setExpanded(!body.classList.contains('admin-sidebar-mobile-open'));
                                return;
                            }

                            applyMode(mode === 'locked-open' ? 'locked-closed' : 'locked-open');
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
                            background: 'rgb(34, 34, 34)',
                            foreground: 'rgb(245, 231, 198)',
                            card: 'rgb(34, 34, 34)',
                            primary: 'rgb(245, 231, 198)',
                            'muted-foreground': 'rgba(245, 231, 198, 0.74)',
                            border: 'rgba(245, 231, 198, 0.18)',
                            accent: 'rgba(245, 231, 198, 0.1)',
                            destructive: 'rgb(245, 231, 198)'
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
                        var pRgb = extractRgb(t.primary) || '245, 231, 198';
                        var bgRgb = extractRgb(t.background) || '34, 34, 34';
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
