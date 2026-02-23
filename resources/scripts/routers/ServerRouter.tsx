import TransferListener from '@/components/server/TransferListener';
import React, { useEffect, useState } from 'react';
import { NavLink, Route, Switch, useRouteMatch } from 'react-router-dom';
import NavigationBar, { ServerNavigationBar } from '@/components/NavigationBar';
import TransitionRouter from '@/TransitionRouter';
import WebsocketHandler from '@/components/server/WebsocketHandler';
import { ServerContext } from '@/state/server';
import { CSSTransition } from 'react-transition-group';
import Can from '@/components/elements/Can';
import Spinner from '@/components/elements/Spinner';
import { NotFound, ServerError } from '@/components/elements/ScreenBlock';
import { httpErrorToHuman } from '@/api/http';
import { useStoreState } from 'easy-peasy';
import SubNavigation from '@/components/elements/SubNavigation';
import InstallListener from '@/components/server/InstallListener';
import ErrorBoundary from '@/components/elements/ErrorBoundary';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faExternalLinkAlt } from '@fortawesome/free-solid-svg-icons';
import { useLocation } from 'react-router';
import ConflictStateRenderer from '@/components/server/ConflictStateRenderer';
import PermissionRoute from '@/components/elements/PermissionRoute';
import routes from '@/routers/routes';

export default () => {
    const match = useRouteMatch<{ id: string }>();
    const location = useLocation();

    const rootAdmin = useStoreState((state) => state.user.data!.rootAdmin);
    const [error, setError] = useState('');

    const id = ServerContext.useStoreState((state) => state.server.data?.id);
    const uuid = ServerContext.useStoreState((state) => state.server.data?.uuid);
    const inConflictState = ServerContext.useStoreState((state) => state.server.inConflictState);
    const serverId = ServerContext.useStoreState((state) => state.server.data?.internalId);
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

    return (
        <React.Fragment key={'server-router'}>
            <div className="bg-background-light dark:bg-background-dark text-gray-900 dark:text-gray-100 font-sans h-[100vh] overflow-hidden flex w-full relative">
                <style>{`
                    @import url('https://fonts.googleapis.com/css2?family=Space+Mono:ital,wght@0,400;0,700;1,400&display=swap');
                    @import url('https://fonts.googleapis.com/icon?family=Material+Icons+Round');
                    .glass-panel {
                        background: rgba(255, 255, 255, 0.7);
                        backdrop-filter: blur(12px);
                        -webkit-backdrop-filter: blur(12px);
                        border: 1px solid rgba(255, 255, 255, 0.3);
                    }
                    .dark .glass-panel {
                        background: rgba(31, 41, 55, 0.7);
                        border: 1px solid rgba(255, 255, 255, 0.05);
                    }
                    .glass-sidebar {
                        background: rgba(0, 0, 0, 0.9);
                        backdrop-filter: blur(16px);
                        -webkit-backdrop-filter: blur(16px);
                    }
                    .server-main-content ::-webkit-scrollbar {
                        width: 8px;
                        height: 8px;
                    }
                    .server-main-content ::-webkit-scrollbar-track {
                        background: transparent; 
                    }
                    .server-main-content ::-webkit-scrollbar-thumb {
                        background: #cbd5e1; 
                        border-radius: 4px;
                    }
                    .dark .server-main-content ::-webkit-scrollbar-thumb {
                        background: #4b5563; 
                    }
                    .server-main-content ::-webkit-scrollbar-thumb:hover {
                        background: #94a3b8; 
                    }
                    .console-line {
                        line-height: 1.6;
                    }
                    
                    /* Utility classes matching user provided tailwind config */
                    .bg-background-light { background-color: #f3f4f6; }
                    .bg-background-dark { background-color: #111827; }
                    .bg-card-light { background-color: #ffffff; }
                    .bg-card-dark { background-color: #1f2937; }
                `}</style>
                <ServerNavigationBar
                    sidebarOpen={isMobileViewport ? mobileSidebarOpen : undefined}
                    setSidebarOpen={isMobileViewport ? setMobileSidebarOpen : undefined}
                    showMobileHeader={false}
                    routes={routes.server}
                    serverId={match.params.id}
                />

                <main className="server-main-content flex-1 flex flex-col overflow-hidden relative" style={{ minWidth: 0, fontFamily: "'Space Mono', monospace" }}>
                    {isMobileViewport && (
                        <>
                            <div
                                style={{
                                    height: '48px',
                                    position: 'sticky',
                                    top: 0,
                                    zIndex: 1000,
                                    backgroundColor: '#000000',
                                    borderBottom: '1px solid #1a1a1a',
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'space-between',
                                    padding: '0 16px',
                                }}
                            >
                                <div style={{ color: '#ffffff', fontSize: '14px', fontWeight: 'bold' }}>BurHan CONSOLE</div>
                                <button
                                    type='button'
                                    onClick={() => setMobileSidebarOpen(true)}
                                    style={{
                                        background: 'none',
                                        border: 'none',
                                        color: '#ffffff',
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
                            <div className="p-6"><ServerError message={error} /></div>
                        ) : (
                            <div className="p-6"><Spinner size={'large'} centered /></div>
                        )
                    ) : (
                        <>
                                <InstallListener />
                                <TransferListener />
                                <WebsocketHandler />
                                {inConflictState && (!rootAdmin || (rootAdmin && !location.pathname.endsWith(`/server/${id}`))) ? (
                                    <div className="p-6"><ConflictStateRenderer /></div>
                                ) : (
                                    <ErrorBoundary>
                                        <TransitionRouter>
                                <Switch location={location}>
                                    {routes.server.map(({ path, permission, component: Component }) => (
                                        <PermissionRoute key={path} permission={permission} path={to(path)} exact>
                                            <Spinner.Suspense>
                                                <Component />
                                            </Spinner.Suspense>
                                        </PermissionRoute>
                                    ))}
                                    <Route path={'*'} component={NotFound} />
                                </Switch>
                            </TransitionRouter>
                        </ErrorBoundary>
                    )}
                </>
            )}
                </main>
            </div>
        </React.Fragment>
    );
};
