import TransferListener from '@/components/server/TransferListener';
import React, { useEffect, useState } from 'react';
import { Route, Switch, useRouteMatch } from 'react-router-dom';
import { ServerNavigationBar } from '@/components/NavigationBar';
import DashboardTopbar, { DASHBOARD_TOPBAR_HEIGHT } from '@/components/dashboard/DashboardTopbar';
import TransitionRouter from '@/TransitionRouter';
import WebsocketHandler from '@/components/server/WebsocketHandler';
import { ServerContext } from '@/state/server';
import Spinner from '@/components/elements/Spinner';
import { NotFound, ServerError } from '@/components/elements/ScreenBlock';
import { httpErrorToHuman } from '@/api/http';
import { useStoreState } from 'easy-peasy';
import InstallListener from '@/components/server/InstallListener';
import ErrorBoundary from '@/components/elements/ErrorBoundary';
import { useLocation } from 'react-router';
import ConflictStateRenderer from '@/components/server/ConflictStateRenderer';
import PermissionRoute from '@/components/elements/PermissionRoute';
import routes from '@/routers/routes';
import PageLoadingSkeleton from '@/components/elements/PageLoadingSkeleton';
import ConsoleSidebar from '@/components/server/console/ConsoleSidebar';

export default () => {
    const match = useRouteMatch<{ id: string }>();
    const location = useLocation();

    const rootAdmin = useStoreState((state) => state.user.data!.rootAdmin);
    const [error, setError] = useState('');

    const id = ServerContext.useStoreState((state) => state.server.data?.id);
    const uuid = ServerContext.useStoreState((state) => state.server.data?.uuid);
    const serverName = ServerContext.useStoreState((state) => state.server.data?.name || 'Server');
    const serverStatus = ServerContext.useStoreState((state) => state.server.data?.status ?? null);
    const inConflictState = ServerContext.useStoreState((state) => state.server.inConflictState);
    const getServer = ServerContext.useStoreActions((actions) => actions.server.getServer);
    const clearServerState = ServerContext.useStoreActions((actions) => actions.clearServerState);

    const to = (value: string, url = false) => {
        if (value === '/') {
            return url ? match.url : match.path;
        }
        return `${(url ? match.url : match.path).replace(/\/*$/, '')}/${value.replace(/^\/+/, '')}`;
    };

    useEffect(
        () => () => {
            clearServerState();
        },
        []
    );

    useEffect(() => {
        setError('');

        getServer(match.params.id).catch((error) => {
            console.error(error);
            setError(httpErrorToHuman(error));
        });

        return () => {
            clearServerState();
        };
    }, [match.params.id]);

    const [mobileSidebarOpen, setMobileSidebarOpen] = useState(false);
    const [isMobileViewport, setIsMobileViewport] = useState(
        typeof window !== 'undefined' ? window.innerWidth < 1024 : false
    );

    useEffect(() => {
        const onResize = () => setIsMobileViewport(window.innerWidth < 1024);
        onResize();
        window.addEventListener('resize', onResize);

        return () => window.removeEventListener('resize', onResize);
    }, []);

    useEffect(() => {
        setMobileSidebarOpen(false);
    }, [location.pathname, isMobileViewport]);

    useEffect(() => {
        const html = document.documentElement;
        const body = document.body;
        const app = document.getElementById('app');
        const previousHtmlOverflow = html.style.overflow;
        const previousBodyOverflow = body.style.overflow;
        const previousHtmlHeight = html.style.height;
        const previousBodyHeight = body.style.height;
        const previousHtmlWidth = html.style.width;
        const previousBodyWidth = body.style.width;
        const previousBodyPosition = body.style.position;
        const previousBodyInset = body.style.inset;
        const previousBodyMargin = body.style.margin;
        const previousAppHeight = app?.style.height;
        const previousAppWidth = app?.style.width;
        const previousAppOverflow = app?.style.overflow;

        html.style.overflow = 'hidden';
        body.style.overflow = 'hidden';
        html.style.height = '100%';
        body.style.height = '100%';
        html.style.width = '100%';
        body.style.width = '100%';
        body.style.position = 'fixed';
        body.style.inset = '0';
        body.style.margin = '0';

        if (app) {
            app.style.height = '100%';
            app.style.width = '100%';
            app.style.overflow = 'hidden';
        }

        return () => {
            html.style.overflow = previousHtmlOverflow;
            body.style.overflow = previousBodyOverflow;
            html.style.height = previousHtmlHeight;
            body.style.height = previousBodyHeight;
            html.style.width = previousHtmlWidth;
            body.style.width = previousBodyWidth;
            body.style.position = previousBodyPosition;
            body.style.inset = previousBodyInset;
            body.style.margin = previousBodyMargin;

            if (app) {
                app.style.height = previousAppHeight || '';
                app.style.width = previousAppWidth || '';
                app.style.overflow = previousAppOverflow || '';
            }
        };
    }, []);

    const consoleRoute = `/server/${match.params.id}`;
    const isConsoleRoute = location.pathname === consoleRoute || location.pathname === `${consoleRoute}/`;
    const isInstallConflict =
        serverStatus === 'installing' || serverStatus === 'install_failed' || serverStatus === 'reinstall_failed';
    // Allow Console route during install/reinstall so users can still monitor progress.
    const canBypassConflictState = isConsoleRoute && (rootAdmin || isInstallConflict);
    return (
        <React.Fragment key={'server-router'}>
            <div
                className='font-sans fixed inset-0 z-0 h-screen min-h-0 w-full overflow-hidden'
                style={{
                    height: '100dvh',
                    background: 'var(--background)',
                    color: 'var(--foreground)',
                }}
            >
                <DashboardTopbar
                    isMobileViewport={isMobileViewport}
                    onOpenSidebar={isMobileViewport ? () => setMobileSidebarOpen(true) : undefined}
                    onCloseSidebar={isMobileViewport ? () => setMobileSidebarOpen(false) : undefined}
                    isSidebarOpen={isMobileViewport ? mobileSidebarOpen : false}
                    centerTitle={serverName}
                />
                <style>{`
                    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500&display=swap');
                    @import url('https://fonts.googleapis.com/icon?family=Material+Icons+Round');

                    .server-theme-shell {
                        position: relative;
                        display: flex;
                        flex: 1;
                        height: 100%;
                        min-height: 0;
                        flex-direction: column;
                        overflow: hidden;
                        background:
                            radial-gradient(circle at 10% 2%, rgba(var(--primary-rgb), 0.08), transparent 34%),
                            linear-gradient(180deg, rgba(var(--background-rgb), 1), rgba(var(--background-rgb), 0.985));
                    }

                    .server-theme-shell::before {
                        content: '';
                        position: absolute;
                        inset: 0;
                        pointer-events: none;
                        background:
                            repeating-linear-gradient(
                                90deg,
                                rgba(var(--primary-rgb), 0.02) 0,
                                rgba(var(--primary-rgb), 0.02) 1px,
                                transparent 1px,
                                transparent 40px
                            );
                        opacity: 0.12;
                    }

                    .server-theme-shell::after {
                        content: '';
                        position: absolute;
                        left: 50%;
                        top: -24%;
                        width: min(1100px, 96vw);
                        height: 120%;
                        transform: translateX(-50%);
                        border-radius: 999px;
                        pointer-events: none;
                        background: radial-gradient(
                            ellipse at center,
                            rgba(var(--primary-rgb), 0.06) 0%,
                            rgba(var(--primary-rgb), 0.02) 42%,
                            transparent 72%
                        );
                    }

                    .server-theme-scroll {
                        position: relative;
                        z-index: 1;
                        display: flex;
                        flex: 1;
                        min-height: 0;
                        height: 100%;
                        flex-direction: column;
                        overflow: hidden;
                        padding-bottom: 0;
                        overscroll-behavior: none;
                    }

                    .server-main-content,
                    .server-theme-shell,
                    .server-theme-scroll,
                    .server-route-fill {
                        width: 100%;
                        max-width: 100%;
                    }

                    .server-route-fill {
                        display: flex;
                        flex: 1;
                        min-height: 0;
                        height: 100%;
                        flex-direction: column;
                        overflow: hidden;
                    }

                    .server-route-layout {
                        position: relative;
                        display: flex;
                        flex: 1;
                        min-height: 0;
                        height: 100%;
                        width: 100%;
                        min-width: 0;
                        flex-direction: column;
                        overflow: hidden;
                    }

                    @media (min-width: 1280px) {
                        .server-route-layout {
                            flex-direction: row;
                        }
                    }

                    .server-route-fill > * {
                        display: flex;
                        flex: 1;
                        min-height: 0;
                        flex-direction: column;
                    }

                    .server-route-fill section,
                    .server-route-fill .fade-enter,
                    .server-route-fill .fade-enter-active,
                    .server-route-fill .fade-enter-done,
                    .server-route-fill .fade-exit,
                    .server-route-fill .fade-exit-active,
                    .server-route-fill [class*='Fade__Container'] {
                        display: flex;
                        flex: 1;
                        min-height: 0;
                        width: 100%;
                        max-width: 100%;
                        flex-direction: column;
                    }

                    .server-main-content .content-container-full {
                        display: flex;
                        flex: 1;
                        min-height: 0;
                        width: 100%;
                        max-width: 100%;
                        flex-direction: column;
                        overflow: hidden;
                    }

                    .server-main-content .server-content-shell {
                        flex: 1;
                        min-height: 0;
                    }

                    .server-main-content .server-content-body {
                        display: flex;
                        min-height: 0;
                        flex: 1;
                        flex-direction: column;
                        overflow: hidden;
                    }

                    .server-main-content ::-webkit-scrollbar {
                        width: 9px;
                        height: 9px;
                    }

                    .server-main-content ::-webkit-scrollbar-track {
                        background: transparent;
                    }

                    .server-main-content ::-webkit-scrollbar-thumb {
                        background: rgba(118, 130, 148, 0.56);
                        border-radius: 999px;
                        border: 2px solid transparent;
                        background-clip: content-box;
                    }

                    .server-main-content ::-webkit-scrollbar-thumb:hover {
                        background: rgba(var(--primary-rgb), 0.62);
                        border: 2px solid transparent;
                        background-clip: content-box;
                    }

                    @media (max-width: 1023px) {
                        .server-route-layout {
                            height: auto;
                            min-height: auto;
                            overflow: visible;
                        }

                        .server-theme-scroll {
                            padding-bottom: 0;
                            overflow-y: auto;
                            overflow-x: hidden;
                        }

                        .server-route-fill,
                        .server-route-fill > *,
                        .server-route-fill section,
                        .server-route-fill .fade-enter,
                        .server-route-fill .fade-enter-active,
                        .server-route-fill .fade-enter-done,
                        .server-route-fill .fade-exit,
                        .server-route-fill .fade-exit-active,
                        .server-route-fill [class*='Fade__Container'] {
                            height: auto;
                            min-height: auto;
                            overflow: visible;
                        }

                        .server-main-content .content-container-full {
                            flex: 0 0 auto;
                            overflow: visible;
                        }

                        .server-main-content .server-content-shell {
                            min-height: auto;
                            border-radius: 1.1rem;
                        }

                        .server-main-content .server-content-body {
                            overflow: visible;
                            padding: 0.85rem;
                        }
                    }
                `}</style>
                <div
                    style={{
                        position: 'absolute',
                        top: `${DASHBOARD_TOPBAR_HEIGHT}px`,
                        right: 0,
                        bottom: 0,
                        left: 0,
                        display: 'flex',
                        minHeight: 0,
                        overflow: 'hidden',
                    }}
                >
                    <ServerNavigationBar
                        sidebarOpen={isMobileViewport ? mobileSidebarOpen : undefined}
                        setSidebarOpen={isMobileViewport ? setMobileSidebarOpen : undefined}
                        showMobileHeader={false}
                        showSidebarLogo={false}
                        topOffset={DASHBOARD_TOPBAR_HEIGHT}
                        routes={routes.server}
                        serverId={match.params.id}
                    />

                    <main
                        className='server-main-content flex-1 min-h-0 flex flex-col overflow-hidden relative'
                        style={{
                            minWidth: 0,
                            fontFamily: "var(--font-sans, 'Inter', sans-serif)",
                            backgroundColor: 'transparent',
                        }}
                    >
                        {!uuid || !id ? (
                            error ? (
                                <div className='p-6'>
                                    <ServerError message={error} />
                                </div>
                            ) : (
                                <div className='p-6'>
                                    <PageLoadingSkeleton rows={9} />
                                </div>
                            )
                        ) : (
                            <>
                                <InstallListener />
                                <TransferListener />
                                <WebsocketHandler />
                                <div className='server-theme-shell'>
                                    <div className='server-theme-scroll'>
                                        <div className='server-route-layout'>
                                            {inConflictState && !canBypassConflictState ? (
                                                <div className='server-route-fill px-4 pt-4 xl:px-6'>
                                                    <ConflictStateRenderer />
                                                </div>
                                            ) : (
                                                <div className='server-route-fill overflow-x-hidden'>
                                                    <div className='flex min-h-0 flex-1 flex-col'>
                                                        <ErrorBoundary>
                                                            <TransitionRouter>
                                                                <Switch location={location}>
                                                                    {routes.server.map(
                                                                        ({
                                                                            path,
                                                                            permission,
                                                                            component: Component,
                                                                        }) => (
                                                                            <PermissionRoute
                                                                                key={path}
                                                                                permission={permission}
                                                                                path={to(path)}
                                                                                exact
                                                                            >
                                                                                <Spinner.Suspense
                                                                                    fallback={
                                                                                        <div className='p-6'>
                                                                                            <PageLoadingSkeleton
                                                                                                rows={9}
                                                                                            />
                                                                                        </div>
                                                                                    }
                                                                                >
                                                                                    <Component />
                                                                                </Spinner.Suspense>
                                                                            </PermissionRoute>
                                                                        )
                                                                    )}
                                                                    <Route path={'*'} component={NotFound} />
                                                                </Switch>
                                                            </TransitionRouter>
                                                        </ErrorBoundary>
                                                    </div>
                                                </div>
                                            )}
                                            {!isConsoleRoute && (
                                                <ConsoleSidebar syncMode={'full'} contributeToSharedTranscript={true} />
                                            )}
                                        </div>
                                    </div>
                                </div>
                            </>
                        )}
                    </main>
                </div>
            </div>
        </React.Fragment>
    );
};
