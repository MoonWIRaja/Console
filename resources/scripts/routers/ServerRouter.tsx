import TransferListener from '@/components/server/TransferListener';
import React, { useEffect, useState } from 'react';
import { Route, Switch, useRouteMatch } from 'react-router-dom';
import { ServerNavigationBar } from '@/components/NavigationBar';
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
import useSiteBranding from '@/hooks/useSiteBranding';

export default () => {
    const match = useRouteMatch<{ id: string }>();
    const location = useLocation();
    const { name } = useSiteBranding();

    const rootAdmin = useStoreState((state) => state.user.data!.rootAdmin);
    const [error, setError] = useState('');

    const id = ServerContext.useStoreState((state) => state.server.data?.id);
    const uuid = ServerContext.useStoreState((state) => state.server.data?.uuid);
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
                className='font-sans fixed inset-0 z-0 flex h-screen min-h-0 w-full overflow-hidden'
                style={{
                    height: '100dvh',
                    background: 'linear-gradient(180deg, rgba(4, 7, 12, 0.98), rgba(1, 2, 5, 1))',
                    color: 'var(--foreground)',
                }}
            >
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
                            radial-gradient(circle at 10% 2%, rgba(var(--primary-rgb), 0.18), transparent 34%),
                            radial-gradient(circle at 90% 100%, rgba(84, 140, 255, 0.2), transparent 38%),
                            linear-gradient(180deg, rgba(4, 7, 12, 0.98), rgba(1, 2, 5, 1));
                    }

                    .server-theme-shell::before {
                        content: '';
                        position: absolute;
                        inset: 0;
                        pointer-events: none;
                        background:
                            repeating-linear-gradient(
                                90deg,
                                rgba(255, 255, 255, 0.014) 0,
                                rgba(255, 255, 255, 0.014) 1px,
                                transparent 1px,
                                transparent 40px
                            );
                        opacity: 0.2;
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
                            rgba(112, 168, 255, 0.08) 0%,
                            rgba(112, 168, 255, 0.03) 42%,
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
                        .server-theme-scroll {
                            padding-bottom: 0;
                        }
                    }
                `}</style>
                <ServerNavigationBar
                    sidebarOpen={isMobileViewport ? mobileSidebarOpen : undefined}
                    setSidebarOpen={isMobileViewport ? setMobileSidebarOpen : undefined}
                    showMobileHeader={false}
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
                    {isMobileViewport && (
                        <>
                            <div
                                style={{
                                    height: '48px',
                                    position: 'sticky',
                                    top: 0,
                                    zIndex: 1000,
                                    backgroundColor: 'var(--card)',
                                    backdropFilter: 'blur(16px)',
                                    WebkitBackdropFilter: 'blur(16px)',
                                    borderBottom: '1px solid var(--border)',
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'space-between',
                                    padding: '0 16px',
                                }}
                            >
                                <div style={{ color: 'var(--foreground)', fontSize: '14px', fontWeight: 900 }}>
                                    {name}
                                </div>
                                <button
                                    type='button'
                                    onClick={() => setMobileSidebarOpen(true)}
                                    style={{
                                        background: 'none',
                                        border: 'none',
                                        color: 'var(--primary)',
                                        fontSize: '20px',
                                        cursor: 'pointer',
                                        padding: '4px',
                                        lineHeight: 1,
                                    }}
                                >
                                    ☰
                                </button>
                            </div>
                        </>
                    )}

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
                                                                ({ path, permission, component: Component }) => (
                                                                    <PermissionRoute
                                                                        key={path}
                                                                        permission={permission}
                                                                        path={to(path)}
                                                                        exact
                                                                    >
                                                                        <Spinner.Suspense
                                                                            fallback={
                                                                                <div className='p-6'>
                                                                                    <PageLoadingSkeleton rows={9} />
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
                                </div>
                            </div>
                        </>
                    )}
                </main>
            </div>
        </React.Fragment>
    );
};
