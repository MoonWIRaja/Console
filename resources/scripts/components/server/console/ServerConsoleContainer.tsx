import React, { memo, useEffect, useMemo, useRef, useState } from 'react';
import { ServerContext } from '@/state/server';
import isEqual from 'react-fast-compare';
import Spinner from '@/components/elements/Spinner';
import Features from '@feature/Features';
import Console from '@/components/server/console/Console';
import PowerButtons from '@/components/server/console/PowerButtons';
import { Alert } from '@/components/elements/alert';
import { useStoreState } from 'easy-peasy';
import { ApplicationStore } from '@/state';
import { bytesToString, ip, mbToBytes } from '@/lib/formatters';
import useWebsocketEvent from '@/plugins/useWebsocketEvent';
import { SocketEvent, SocketRequest } from '@/components/server/events';
import classNames from 'classnames';

export type PowerAction = 'start' | 'stop' | 'restart' | 'kill';

type LiveStats = {
    memory: number;
    cpu: number;
    disk: number;
    rx: number;
    tx: number;
};

const clampPercent = (value: number): number => Math.max(0, Math.min(100, value));

const ServerConsoleContainer = () => {
    const name = ServerContext.useStoreState((state) => state.server.data!.name);
    const status = ServerContext.useStoreState((state) => state.status.value);
    const limits = ServerContext.useStoreState((state) => state.server.data!.limits);
    const node = ServerContext.useStoreState((state) => state.server.data!.node);
    const internalId = ServerContext.useStoreState((state) => state.server.data!.internalId);
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const isInstalling = ServerContext.useStoreState((state) => state.server.isInstalling);
    const isTransferring = ServerContext.useStoreState((state) => state.server.data!.isTransferring);
    const eggFeatures = ServerContext.useStoreState((state) => state.server.data!.eggFeatures, isEqual);
    const isNodeUnderMaintenance = ServerContext.useStoreState((state) => state.server.data!.isNodeUnderMaintenance);
    const connected = ServerContext.useStoreState((state) => state.socket.connected);
    const instance = ServerContext.useStoreState((state) => state.socket.instance);
    const username = useStoreState((state: ApplicationStore) => state.user.data!.username);
    const email = useStoreState((state: ApplicationStore) => state.user.data!.email);

    const allocation = ServerContext.useStoreState((state) => {
        const match = state.server.data!.allocations.find((item) => item.isDefault);
        return !match ? 'n/a' : `${match.alias || ip(match.ip)}:${match.port}`;
    });

    const [stats, setStats] = useState<LiveStats>({ memory: 0, cpu: 0, disk: 0, rx: 0, tx: 0 });
    const [networkRate, setNetworkRate] = useState<{ rx: number; tx: number }>({ rx: 0, tx: 0 });
    const previousNetwork = useRef<{ rx: number; tx: number }>({ rx: -1, tx: -1 });

    useEffect(() => {
        document.title = `${name} | Console`;
    }, [name]);

    useEffect(() => {
        if (connected && instance) {
            instance.send(SocketRequest.SEND_STATS);
        }
    }, [connected, instance]);

    useWebsocketEvent(SocketEvent.STATS, (data) => {
        let parsed: any = {};
        try {
            parsed = JSON.parse(data);
        } catch (error) {
            return;
        }

        const currentRx = parsed.network?.rx_bytes || 0;
        const currentTx = parsed.network?.tx_bytes || 0;
        const rxPerSecond = previousNetwork.current.rx < 0 ? 0 : Math.max(0, currentRx - previousNetwork.current.rx);
        const txPerSecond = previousNetwork.current.tx < 0 ? 0 : Math.max(0, currentTx - previousNetwork.current.tx);

        previousNetwork.current = { rx: currentRx, tx: currentTx };
        setNetworkRate({ rx: rxPerSecond, tx: txPerSecond });
        setStats({
            memory: parsed.memory_bytes || 0,
            cpu: parsed.cpu_absolute || 0,
            disk: parsed.disk_bytes || 0,
            rx: currentRx,
            tx: currentTx,
        });
    });

    const memoryLimitBytes = useMemo(() => (limits.memory > 0 ? mbToBytes(limits.memory) : 0), [limits.memory]);
    const diskLimitBytes = useMemo(() => (limits.disk > 0 ? mbToBytes(limits.disk) : 0), [limits.disk]);

    const cpuPercent = limits.cpu > 0 ? clampPercent((stats.cpu / limits.cpu) * 100) : 0;
    const memoryPercent = memoryLimitBytes > 0 ? clampPercent((stats.memory / memoryLimitBytes) * 100) : 0;
    const diskPercent = diskLimitBytes > 0 ? clampPercent((stats.disk / diskLimitBytes) * 100) : 0;

    const statusBadgeClass = classNames(
        'rounded border px-2 py-0.5 text-xs font-bold',
        status === 'running'
            ? 'border-green-200 bg-green-100 text-green-700 dark:border-green-800 dark:bg-green-900/30 dark:text-green-400'
            : status === 'offline' || status === null
                ? 'border-red-200 bg-red-100 text-red-700 dark:border-red-800 dark:bg-red-900/30 dark:text-red-400'
                : 'border-amber-200 bg-amber-100 text-amber-700 dark:border-amber-800 dark:bg-amber-900/30 dark:text-amber-400'
    );

    return (
        <div className={'relative flex h-full w-full overflow-hidden'} style={{ fontFamily: "'Inter', sans-serif" }}>
            <div
                className={
                    'pointer-events-none absolute -left-[10%] -top-[10%] h-[40%] w-[40%] rounded-full bg-blue-400/10 blur-[100px]'
                }
            />
            <div
                className={
                    'pointer-events-none absolute -bottom-[10%] -right-[10%] h-[40%] w-[40%] rounded-full bg-purple-400/10 blur-[100px]'
                }
            />
            <div className={'relative z-10 flex h-full w-full flex-col overflow-hidden md:flex-row'}>
                <div className={'flex min-h-0 flex-1 flex-col gap-6 overflow-y-auto p-6'}>
                    {(isNodeUnderMaintenance || isInstalling || isTransferring) && (
                        <Alert type={'warning'} className={'mb-0'}>
                            {isNodeUnderMaintenance
                                ? 'The node of this server is currently under maintenance and all actions are unavailable.'
                                : isInstalling
                                    ? 'This server is currently running its installation process and most actions are unavailable.'
                                    : 'This server is currently being transferred to another node and all actions are unavailable.'}
                        </Alert>
                    )}
                    <div
                        className={
                            'flex min-h-[500px] flex-1 flex-col rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800'
                        }
                    >
                        <div
                            className={
                                'flex items-center justify-between rounded-t-xl border-b border-gray-200 bg-gray-50/50 px-4 py-3 dark:border-gray-700 dark:bg-gray-800/50'
                            }
                        >
                            <h2 className={'flex items-center text-sm font-bold text-gray-700 dark:text-gray-200'}>
                                <span
                                    className={classNames('mr-2 h-2 w-2 rounded-full', {
                                        'animate-pulse bg-green-500': status === 'running',
                                        'bg-red-500': status === 'offline' || status === null,
                                        'animate-pulse bg-yellow-500':
                                            status !== 'running' && status !== 'offline' && status !== null,
                                    })}
                                />
                                Live Console
                            </h2>
                        </div>
                        <div className={'flex-1 overflow-hidden p-4'}>
                            <Spinner.Suspense>
                                <Console />
                            </Spinner.Suspense>
                        </div>
                    </div>

                    <div
                        className={
                            'rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800'
                        }
                    >
                        <h3 className={'mb-6 text-lg font-bold text-gray-900 dark:text-white'}>Server Statistics</h3>
                        <div className={'grid grid-cols-1 gap-8 md:grid-cols-2 lg:grid-cols-4'}>
                            <div className={'space-y-2'}>
                                <div className={'flex items-end justify-between'}>
                                    <span className={'text-sm font-medium text-gray-500 dark:text-gray-400'}>
                                        CPU Usage
                                    </span>
                                </div>
                                <div className={'text-3xl font-black text-gray-900 dark:text-white'}>
                                    {stats.cpu.toFixed(1)}%
                                </div>
                                <div className={'h-2 w-full rounded-full bg-gray-200 dark:bg-gray-700'}>
                                    <div
                                        className={'h-2 rounded-full bg-blue-600 transition-all duration-500'}
                                        style={{ width: `${cpuPercent}%` }}
                                    />
                                </div>
                            </div>

                            <div className={'space-y-2'}>
                                <div className={'flex items-end justify-between'}>
                                    <span className={'text-sm font-medium text-gray-500 dark:text-gray-400'}>
                                        Memory Usage
                                    </span>
                                </div>
                                <div className={'text-2xl font-black text-gray-900 dark:text-white'}>
                                    {bytesToString(stats.memory)}
                                    <span className={'text-lg font-normal text-gray-400'}>
                                        {' '}
                                        / {memoryLimitBytes > 0 ? bytesToString(memoryLimitBytes) : '\u221E'}
                                    </span>
                                </div>
                                <div className={'h-2 w-full rounded-full bg-gray-200 dark:bg-gray-700'}>
                                    <div
                                        className={'h-2 rounded-full bg-purple-600 transition-all duration-500'}
                                        style={{ width: `${memoryPercent}%` }}
                                    />
                                </div>
                            </div>

                            <div className={'space-y-2'}>
                                <div className={'flex items-end justify-between'}>
                                    <span className={'text-sm font-medium text-gray-500 dark:text-gray-400'}>
                                        Disk Usage
                                    </span>
                                </div>
                                <div className={'text-2xl font-black text-gray-900 dark:text-white'}>
                                    {bytesToString(stats.disk)}
                                    <span className={'text-lg font-normal text-gray-400'}>
                                        {' '}
                                        / {diskLimitBytes > 0 ? bytesToString(diskLimitBytes) : '\u221E'}
                                    </span>
                                </div>
                                <div className={'h-2 w-full rounded-full bg-gray-200 dark:bg-gray-700'}>
                                    <div
                                        className={'h-2 rounded-full bg-pink-600 transition-all duration-500'}
                                        style={{ width: `${diskPercent}%` }}
                                    />
                                </div>
                            </div>

                            <div className={'space-y-2'}>
                                <div className={'flex items-end justify-between'}>
                                    <span className={'text-sm font-medium text-gray-500 dark:text-gray-400'}>
                                        Network
                                    </span>
                                </div>
                                <div className={'flex flex-col space-y-1'}>
                                    <div
                                        className={
                                            'flex items-center text-sm font-bold text-gray-700 dark:text-gray-300'
                                        }
                                    >
                                        <span className={'material-icons-round mr-1 text-base text-green-500'}>
                                            arrow_downward
                                        </span>
                                        {bytesToString(networkRate.rx)}/s
                                    </div>
                                    <div
                                        className={
                                            'flex items-center text-sm font-bold text-gray-700 dark:text-gray-300'
                                        }
                                    >
                                        <span className={'material-icons-round mr-1 text-base text-blue-500'}>
                                            arrow_upward
                                        </span>
                                        {bytesToString(networkRate.tx)}/s
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <Features enabled={eggFeatures} />
                </div>

                <aside className={'flex min-h-0 w-full flex-col gap-6 overflow-y-auto p-6 md:w-80 md:pl-0'}>
                    <div
                        className={
                            'flex items-center rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800'
                        }
                    >
                        <div
                            className={
                                'mr-3 flex h-10 w-10 items-center justify-center rounded-lg bg-blue-600 text-lg font-bold text-white shadow-md shadow-blue-500/30'
                            }
                        >
                            {username.charAt(0).toUpperCase()}
                        </div>
                        <div className={'min-w-0'}>
                            <h3 className={'truncate font-bold text-gray-900 dark:text-white'}>{username}</h3>
                            <p className={'truncate text-xs text-gray-500 dark:text-gray-400'}>{email}</p>
                        </div>
                    </div>

                    <div
                        className={
                            'rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800'
                        }
                    >
                        <h3 className={'mb-4 text-lg font-bold text-gray-900 dark:text-white'}>Server Control</h3>
                        <div className={'mb-6 space-y-3 text-sm'}>
                            <div className={'flex items-center justify-between'}>
                                <span className={'text-gray-500 dark:text-gray-400'}>IP:</span>
                                <span
                                    className={
                                        'rounded bg-gray-100 px-2 py-0.5 font-mono text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-200'
                                    }
                                >
                                    {allocation}
                                </span>
                            </div>
                            <div className={'flex items-center justify-between'}>
                                <span className={'text-gray-500 dark:text-gray-400'}>Status:</span>
                                <span className={statusBadgeClass}>{(status || 'offline').toUpperCase()}</span>
                            </div>
                            <div className={'flex items-center justify-between'}>
                                <span className={'text-gray-500 dark:text-gray-400'}>Node:</span>
                                <code
                                    className={
                                        'rounded bg-neutral-200 px-2 py-1 font-mono text-xs text-neutral-800 dark:bg-neutral-900 dark:text-white'
                                    }
                                >
                                    {node}
                                </code>
                            </div>
                            <div className={'flex items-center justify-between'}>
                                <span className={'text-gray-500 dark:text-gray-400'}>Server ID:</span>
                                <code
                                    className={
                                        'rounded bg-neutral-200 px-2 py-1 font-mono text-xs text-neutral-800 dark:bg-neutral-900 dark:text-white'
                                    }
                                >
                                    {uuid}
                                </code>
                            </div>
                        </div>
                        <PowerButtons className={'space-y-3'} variant={'glass'} />
                    </div>

                    <div
                        className={
                            'flex min-h-[300px] flex-1 flex-col rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800'
                        }
                    >
                        <div className={'mb-4 flex items-center justify-between'}>
                            <h3 className={'text-lg font-bold text-gray-900 dark:text-white'}>Players</h3>
                            <span
                                className={
                                    'rounded-md bg-gray-100 px-2 py-1 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-400'
                                }
                            >
                                1 Online
                            </span>
                        </div>
                        <div
                            className={
                                'rounded-lg border border-gray-100 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-900/40'
                            }
                        >
                            <div className={'flex items-center gap-3'}>
                                <div
                                    className={
                                        'flex h-9 w-9 items-center justify-center rounded bg-blue-600 text-sm font-bold text-white shadow-sm'
                                    }
                                >
                                    A
                                </div>
                                <div>
                                    <p className={'text-sm font-bold text-gray-800 dark:text-gray-200'}>admin</p>
                                    <p className={'text-[10px] text-gray-500'}>Role: Administrator</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    );
};

export default memo(ServerConsoleContainer, isEqual);
