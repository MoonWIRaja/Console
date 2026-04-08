import React, { useEffect, useState } from 'react';
import { Link, useRouteMatch } from 'react-router-dom';
import { httpErrorToHuman } from '@/api/http';
import { ServerContext } from '@/state/server';
import Console from '@/components/server/console/Console';
import InstallListener from '@/components/server/InstallListener';
import TransferListener from '@/components/server/TransferListener';
import WebsocketHandler from '@/components/server/WebsocketHandler';
import PageLoadingSkeleton from '@/components/elements/PageLoadingSkeleton';
import { ServerError } from '@/components/elements/ScreenBlock';
import { SocketRequest } from '@/components/server/events';
import { InteractiveHoverButton } from '@/components/ui/interactive-hover-button';

export default () => {
    const match = useRouteMatch<{ id: string }>();
    const [error, setError] = useState('');

    const serverId = ServerContext.useStoreState((state) => state.server.data?.id);
    const serverName = ServerContext.useStoreState((state) => state.server.data?.name || 'Server');
    const status = ServerContext.useStoreState((state) => state.status.value);
    const instance = ServerContext.useStoreState((state) => state.socket.instance);
    const connected = ServerContext.useStoreState((state) => state.socket.connected);
    const getServer = ServerContext.useStoreActions((actions) => actions.server.getServer);
    const clearServerState = ServerContext.useStoreActions((actions) => actions.clearServerState);

    const sendPowerCommand = (command: 'start' | 'restart' | 'stop') => {
        if (instance && connected) {
            instance.send(SocketRequest.SET_STATE, command);
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

    return (
        <>
            <div
                className='fixed inset-0 z-0 flex min-h-0 w-full flex-col overflow-hidden'
                style={{
                    height: '100dvh',
                    background:
                        'radial-gradient(circle at 10% 2%, rgba(var(--primary-rgb), 0.08), transparent 34%), linear-gradient(180deg, rgba(var(--background-rgb), 1), rgba(var(--background-rgb), 0.985))',
                    color: 'var(--foreground)',
                    fontFamily:
                        "var(--font-sans, 'Geist Mono', ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace)",
                }}
            >
                <header
                    style={{
                        position: 'relative',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'space-between',
                        gap: '16px',
                        minHeight: '72px',
                        padding: '0 16px',
                        borderBottom: '1px solid var(--surface-border)',
                        background: '#2D2D2D',
                        backdropFilter: 'blur(12px)',
                        boxShadow: '0 10px 24px rgba(0, 0, 0, 0.32)',
                    }}
                >
                    <div style={{ minWidth: 0, flex: 1 }}>
                        <div
                            style={{
                                color: 'rgba(248, 246, 239, 0.96)',
                                fontSize: '1rem',
                                fontWeight: 800,
                                whiteSpace: 'nowrap',
                                overflow: 'hidden',
                                textOverflow: 'ellipsis',
                            }}
                        >
                            {serverName}
                        </div>
                        <div
                            style={{
                                marginTop: '4px',
                                color: 'rgba(174, 183, 194, 0.74)',
                                fontSize: '0.72rem',
                                fontWeight: 700,
                                letterSpacing: '0.14em',
                                textTransform: 'uppercase',
                            }}
                        >
                            Standalone Console
                        </div>
                    </div>
                    <div
                        style={{
                            position: 'absolute',
                            left: '50%',
                            transform: 'translateX(-50%)',
                            display: 'flex',
                            alignItems: 'center',
                            gap: '12px',
                        }}
                    >
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
                    <div style={{ flex: '0 0 auto' }}>
                        <Link
                            to={serverId ? `/server/${serverId}` : '/'}
                            style={{
                                display: 'inline-flex',
                                alignItems: 'center',
                                justifyContent: 'center',
                                minWidth: '9.5rem',
                                height: '42px',
                                padding: '0 16px',
                                borderRadius: '999px',
                                border: '1px solid var(--surface-border)',
                                background: 'var(--surface-elevated)',
                                color: 'var(--foreground)',
                                textDecoration: 'none',
                                fontSize: '0.74rem',
                                fontWeight: 800,
                                letterSpacing: '0.12em',
                                textTransform: 'uppercase',
                                whiteSpace: 'nowrap',
                            }}
                        >
                            Back to Server
                        </Link>
                    </div>
                </header>

                <main className='flex min-h-0 flex-1 flex-col overflow-hidden p-4 md:p-6'>
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
                            <div className='flex min-h-0 flex-1 flex-col overflow-hidden rounded-2xl border border-[color:var(--border)] bg-[color:var(--card)] shadow-[0_0_0_1px_rgba(var(--primary-rgb),0.06),0_20px_35px_rgba(12,12,12,0.45)]'>
                                <Console />
                            </div>
                        </>
                    )}
                </main>
            </div>
        </>
    );
};
