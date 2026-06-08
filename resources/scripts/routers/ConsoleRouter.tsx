import React, { useEffect, useState } from 'react';
import { Link, useRouteMatch } from 'react-router-dom';
import { httpErrorToHuman } from '@/api/http';
import { ServerContext } from '@/state/server';
import Console from '@/components/server/console/Console';
import ConsoleBackground from '@/components/server/console/ConsoleBackground';
import InstallListener from '@/components/server/InstallListener';
import TransferListener from '@/components/server/TransferListener';
import WebsocketHandler from '@/components/server/WebsocketHandler';
import PageLoadingSkeleton from '@/components/elements/PageLoadingSkeleton';
import { ServerError } from '@/components/elements/ScreenBlock';
import Spinner from '@/components/elements/Spinner';
import { InteractiveHoverButton } from '@/components/ui/interactive-hover-button';
import sendPowerCommandApi from '@/api/server/sendPowerCommand';
import DashboardTopbar, { DASHBOARD_TOPBAR_HEIGHT } from '@/components/dashboard/DashboardTopbar';
import { applyThemeVariables, SERVER_THEME_VARIABLES, serverThemeStyle } from '@/components/server/serverTheme';

export default () => {
    const match = useRouteMatch<{ id: string }>();
    const [error, setError] = useState('');

    const serverId = ServerContext.useStoreState((state) => state.server.data?.id);
    const serverUuid = ServerContext.useStoreState((state) => state.server.data?.uuid || '');
    const serverName = ServerContext.useStoreState((state) => state.server.data?.name || 'Server');
    const status = ServerContext.useStoreState((state) => state.status.value);
    const connected = ServerContext.useStoreState((state) => state.socket.connected);
    const getServer = ServerContext.useStoreActions((actions) => actions.server.getServer);
    const clearServerState = ServerContext.useStoreActions((actions) => actions.clearServerState);

    const sendPowerCommand = (command: 'start' | 'restart' | 'stop') => {
        if (serverUuid && connected) {
            void sendPowerCommandApi(serverUuid, command).catch((error) => console.error(error));
        }
    };

    useEffect(
        () => () => {
            clearServerState();
        },
        []
    );

    useEffect(() => {
        setError('');

        getServer(match.params.id).catch((err) => {
            console.error(err);
            setError(httpErrorToHuman(err));
        });

        return () => {
            clearServerState();
        };
    }, [match.params.id]);

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

    useEffect(() => {
        const modalPortal = document.getElementById('modal-portal');
        return applyThemeVariables(modalPortal, SERVER_THEME_VARIABLES);
    }, []);

    return (
        <>
            <div
                className='font-sans fixed inset-0 z-0 h-screen min-h-0 w-full overflow-hidden'
                style={{
                    height: '100dvh',
                    background: 'var(--background)',
                    color: 'var(--foreground)',
                    ...serverThemeStyle,
                }}
            >
                <DashboardTopbar
                    centerTitle={serverName}
                    centerSubtitle={'Standalone Console'}
                    showSidebarControl={false}
                    showServerSwitcher={false}
                />
                <style>{`
                    .standalone-console-shell {
                        position: absolute;
                        inset: ${DASHBOARD_TOPBAR_HEIGHT}px 0 0 0;
                        display: flex;
                        min-height: 0;
                        flex-direction: column;
                        overflow: hidden;
                        background:
                            radial-gradient(circle at 10% 2%, rgba(var(--primary-rgb), 0.08), transparent 34%),
                            linear-gradient(180deg, rgba(var(--background-rgb), 1), rgba(var(--background-rgb), 0.985));
                    }

                    .standalone-console-shell::before {
                        content: '';
                        position: absolute;
                        inset: 0;
                        z-index: 0;
                        pointer-events: none;
                        background-image:
                            repeating-linear-gradient(0deg, transparent, transparent 4.5px, rgba(116, 34, 32, 0.045) 4.5px, rgba(116, 34, 32, 0.045) 5px),
                            repeating-linear-gradient(60deg, transparent, transparent 4.5px, rgba(116, 34, 32, 0.045) 4.5px, rgba(116, 34, 32, 0.045) 5px),
                            repeating-linear-gradient(120deg, transparent, transparent 4.5px, rgba(116, 34, 32, 0.045) 4.5px, rgba(116, 34, 32, 0.045) 5px);
                    }

                    .standalone-console-shell::after {
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

                    .standalone-console-content {
                        position: relative;
                        z-index: 1;
                        display: flex;
                        min-height: 0;
                        flex: 1;
                        flex-direction: column;
                        overflow: hidden;
                    }

                    .standalone-console-actions {
                        display: flex;
                        flex-wrap: wrap;
                        align-items: center;
                        justify-content: space-between;
                        gap: 0.75rem;
                        padding: 1rem 1rem 0;
                    }

                    .standalone-console-power {
                        display: flex;
                        flex-wrap: wrap;
                        gap: 0.75rem;
                    }

                    .standalone-console-back {
                        display: inline-flex;
                        align-items: center;
                        justify-content: center;
                        min-width: 9.5rem;
                        height: 42px;
                        padding: 0 16px;
                        border-radius: 999px;
                        border: 1px solid #2D4A3E;
                        background: #FEF9E1;
                        color: #742220;
                        text-decoration: none;
                        font-size: 0.74rem;
                        font-weight: 900;
                        letter-spacing: 0.12em;
                        text-transform: uppercase;
                        white-space: nowrap;
                        transition: background-color 0.15s ease, color 0.15s ease, box-shadow 0.15s ease;
                    }

                    .standalone-console-back:hover {
                        background: #F5EFD5;
                        color: #2D4A3E;
                        box-shadow: 2px 2px 0 0 #2D4A3E;
                    }

                    .standalone-console-card {
                        position: relative;
                        display: flex;
                        min-height: 0;
                        flex: 1;
                        flex-direction: column;
                        overflow: hidden;
                        margin: 1rem;
                        border: 2px solid #2D4A3E;
                        border-radius: 22px;
                        background-color: #FEF9E1;
                        background-image:
                            repeating-linear-gradient(0deg, transparent, transparent 4.5px, rgba(116, 34, 32, 0.04) 4.5px, rgba(116, 34, 32, 0.04) 5px),
                            repeating-linear-gradient(60deg, transparent, transparent 4.5px, rgba(116, 34, 32, 0.04) 4.5px, rgba(116, 34, 32, 0.04) 5px),
                            repeating-linear-gradient(120deg, transparent, transparent 4.5px, rgba(116, 34, 32, 0.04) 4.5px, rgba(116, 34, 32, 0.04) 5px);
                        box-shadow: 4px 4px 0 0 #2D4A3E;
                    }

                    .standalone-console-card::before {
                        content: '';
                        position: absolute;
                        inset: 0;
                        z-index: 1;
                        pointer-events: none;
                        background-image:
                            repeating-linear-gradient(0deg, transparent, transparent 4.5px, rgba(116, 34, 32, 0.045) 4.5px, rgba(116, 34, 32, 0.045) 5px),
                            repeating-linear-gradient(60deg, transparent, transparent 4.5px, rgba(116, 34, 32, 0.045) 4.5px, rgba(116, 34, 32, 0.045) 5px),
                            repeating-linear-gradient(120deg, transparent, transparent 4.5px, rgba(116, 34, 32, 0.045) 4.5px, rgba(116, 34, 32, 0.045) 5px);
                    }

                    .standalone-console-card-head {
                        position: relative;
                        z-index: 2;
                        display: flex;
                        align-items: center;
                        justify-content: space-between;
                        border-bottom: 2px solid #E8E0C8;
                        background: #F5EFD5;
                        padding: 0.75rem 1rem;
                    }

                    .standalone-console-title-row {
                        display: flex;
                        align-items: center;
                        gap: 0.75rem;
                    }

                    .standalone-console-title-row h2 {
                        margin: 0;
                        color: #742220;
                        font-size: 0.875rem;
                        font-weight: 800;
                        letter-spacing: 0.14em;
                        text-transform: uppercase;
                    }

                    .standalone-console-status-dot {
                        height: 0.625rem;
                        width: 0.625rem;
                        flex-shrink: 0;
                        border-radius: 999px;
                        box-shadow: 0 0 0 2px rgba(116, 34, 32, 0.16);
                    }

                    .standalone-console-status-dot.running {
                        background: #4ade80;
                        box-shadow: 0 0 0 2px rgba(34, 197, 94, 0.28);
                        animation: standalone-console-pulse 1.5s ease-in-out infinite;
                    }

                    .standalone-console-status-dot.offline {
                        background: #ef4444;
                        box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.28);
                    }

                    .standalone-console-status-dot.busy {
                        background: #facc15;
                        box-shadow: 0 0 0 2px rgba(250, 204, 21, 0.3);
                        animation: standalone-console-pulse 1.5s ease-in-out infinite;
                    }

                    .standalone-console-panel-body {
                        position: relative;
                        z-index: 2;
                        min-width: 0;
                        flex: 1;
                        overflow: hidden;
                        border-radius: 0 0 18px 18px;
                        background: transparent;
                    }

                    @keyframes standalone-console-pulse {
                        0%, 100% { opacity: 1; }
                        50% { opacity: 0.45; }
                    }

                    @media (min-width: 768px) {
                        .standalone-console-actions {
                            padding: 1.25rem 1.5rem 0;
                        }

                        .standalone-console-card {
                            margin: 1.25rem 1.5rem 1.5rem;
                        }
                    }
                `}</style>

                <main className='standalone-console-shell'>
                    <div className='standalone-console-content'>
                    {!serverId ? (
                        error ? (
                            <div className='flex min-h-0 flex-1 items-center justify-center'>
                                <ServerError message={error} />
                            </div>
                        ) : (
                            <PageLoadingSkeleton rows={8} className='min-h-0 flex-1' />
                        )
                    ) : (
                        <>
                            <InstallListener />
                            <TransferListener />
                            <WebsocketHandler />
                            <div className='standalone-console-actions'>
                                <div className='standalone-console-power'>
                                    <InteractiveHoverButton
                                        type='button'
                                        text='Start Server'
                                        variant='success'
                                        disabled={!connected || status !== 'offline'}
                                        onClick={() => sendPowerCommand('start')}
                                        iconName='play_arrow'
                                        style={{ minWidth: '170px' }}
                                    />
                                    <InteractiveHoverButton
                                        type='button'
                                        text='Restart Server'
                                        variant='warning'
                                        disabled={!connected || !status}
                                        onClick={() => sendPowerCommand('restart')}
                                        iconName='restart_alt'
                                        style={{ minWidth: '170px' }}
                                    />
                                    <InteractiveHoverButton
                                        type='button'
                                        text='Stop Server'
                                        variant='danger'
                                        disabled={!connected || status === 'offline'}
                                        onClick={() => sendPowerCommand('stop')}
                                        iconName='stop'
                                        style={{ minWidth: '170px' }}
                                    />
                                </div>
                                <Link to={serverId ? `/server/${match.params.id}` : '/'} className='standalone-console-back'>
                                    Back to Server
                                </Link>
                            </div>
                            <div className='standalone-console-card'>
                                <div className='standalone-console-card-head'>
                                    <div className='standalone-console-title-row'>
                                        <span
                                            className={`standalone-console-status-dot ${
                                                status === 'running'
                                                    ? 'running'
                                                    : status === 'offline' || status === null
                                                        ? 'offline'
                                                        : 'busy'
                                            }`}
                                        />
                                        <h2>Live Console</h2>
                                    </div>
                                </div>
                                <div className='standalone-console-panel-body'>
                                    <ConsoleBackground />
                                    <div style={{ position: 'relative', zIndex: 4, height: '100%' }}>
                                        <Spinner.Suspense>
                                            <Console />
                                        </Spinner.Suspense>
                                    </div>
                                </div>
                            </div>
                        </>
                    )}
                    </div>
                </main>
            </div>
        </>
    );
};
