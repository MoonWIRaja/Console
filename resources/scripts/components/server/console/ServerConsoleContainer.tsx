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
import Avatar from '@/components/Avatar';

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
        'rounded-lg border px-2 py-0.5 text-xs font-bold',
        status === 'running'
            ? 'border-[color:var(--primary)] bg-[color:var(--primary)]/10 text-[color:var(--primary)]'
            : status === 'offline' || status === null
            ? 'border-red-500/40 bg-red-500/10 text-red-400'
            : 'border-amber-500/40 bg-amber-500/10 text-amber-300'
    );

    return (
        <div
            className={
                'relative flex min-h-screen w-full overflow-x-hidden bg-[color:var(--card)] text-gray-100 lg:h-screen lg:overflow-hidden'
            }
            style={{
                fontFamily:
                    "'Space Mono', 'IBM Plex Mono', ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace",
            }}
        >
            <div className={'pointer-events-none absolute inset-0 z-0 hidden lg:flex'}>
                <div className={'h-full w-[77%] bg-[color:var(--card)]'} />
                <div className={'h-full w-[23%] bg-[color:var(--card)]'} />
            </div>
            <div
                className={
                    'pointer-events-none absolute -left-[10%] -top-[10%] hidden h-[40%] w-[40%] rounded-full bg-blue-400/10 blur-[100px]'
                }
            />
            <div
                className={
                    'pointer-events-none absolute -bottom-[10%] -right-[10%] hidden h-[40%] w-[40%] rounded-full bg-purple-400/10 blur-[100px]'
                }
            />
            <div
                className={
                    'relative z-10 flex min-h-screen w-full min-w-0 flex-col overflow-x-hidden overflow-y-visible lg:h-full lg:overflow-hidden xl:flex-row'
                }
            >
                <div
                    className={
                        'flex min-h-0 w-full min-w-0 flex-col gap-6 overflow-x-hidden overflow-y-visible p-4 md:p-6 lg:overflow-y-auto xl:w-[77%] xl:flex-none'
                    }
                >
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
                            'flex min-h-[420px] min-w-0 flex-1 flex-col rounded-xl border border-[color:var(--border)] bg-[color:var(--card)] shadow-none md:min-h-[500px]'
                        }
                    >
                        <div
                            className={
                                'flex items-center justify-between border-b border-[color:var(--border)] bg-[color:var(--card)] px-4 py-3'
                            }
                        >
                            <h2
                                className={'flex items-center text-sm font-bold uppercase tracking-wide text-[#f8f6ef]'}
                            >
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
                        <div className={'min-w-0 flex-1 overflow-hidden p-4'}>
                            <Spinner.Suspense>
                                <Console />
                            </Spinner.Suspense>
                        </div>
                    </div>

                    <div
                        className={
                            'rounded-xl border border-[color:var(--border)] bg-[color:var(--card)] p-6 shadow-[0_0_0_1px_rgba(var(--primary-rgb), 0.06)]'
                        }
                    >
                        <h3 className={'mb-6 text-lg font-bold uppercase tracking-wide text-[#f8f6ef]'}>
                            Server Statistics
                        </h3>
                        <div className={'grid grid-cols-1 gap-8 md:grid-cols-2 lg:grid-cols-4'}>
                            <div className={'space-y-2'}>
                                <div className={'flex items-end justify-between'}>
                                    <span className={'text-sm font-medium text-gray-400'}>CPU Usage</span>
                                </div>
                                <div className={'text-3xl font-black text-[#f8f6ef]'}>{stats.cpu.toFixed(1)}%</div>
                                <div className={'h-2 w-full rounded-full bg-white/10'}>
                                    <div
                                        className={'h-2 rounded-none bg-blue-600 transition-all duration-500'}
                                        style={{ width: `${cpuPercent}%` }}
                                    />
                                </div>
                            </div>

                            <div className={'space-y-2'}>
                                <div className={'flex items-end justify-between'}>
                                    <span className={'text-sm font-medium text-gray-400'}>Memory Usage</span>
                                </div>
                                <div className={'text-2xl font-black text-[#f8f6ef]'}>
                                    {bytesToString(stats.memory)}
                                    <span className={'text-lg font-normal text-gray-500'}>
                                        {' '}
                                        / {memoryLimitBytes > 0 ? bytesToString(memoryLimitBytes) : '\u221E'}
                                    </span>
                                </div>
                                <div className={'h-2 w-full rounded-full bg-white/10'}>
                                    <div
                                        className={'h-2 rounded-none bg-purple-600 transition-all duration-500'}
                                        style={{ width: `${memoryPercent}%` }}
                                    />
                                </div>
                            </div>

                            <div className={'space-y-2'}>
                                <div className={'flex items-end justify-between'}>
                                    <span className={'text-sm font-medium text-gray-400'}>Disk Usage</span>
                                </div>
                                <div className={'text-2xl font-black text-[#f8f6ef]'}>
                                    {bytesToString(stats.disk)}
                                    <span className={'text-lg font-normal text-gray-500'}>
                                        {' '}
                                        / {diskLimitBytes > 0 ? bytesToString(diskLimitBytes) : '\u221E'}
                                    </span>
                                </div>
                                <div className={'h-2 w-full rounded-full bg-white/10'}>
                                    <div
                                        className={'h-2 rounded-none bg-pink-600 transition-all duration-500'}
                                        style={{ width: `${diskPercent}%` }}
                                    />
                                </div>
                            </div>

                            <div className={'space-y-2'}>
                                <div className={'flex items-end justify-between'}>
                                    <span className={'text-sm font-medium text-gray-400'}>Network</span>
                                </div>
                                <div className={'flex flex-col space-y-1'}>
                                    <div className={'flex items-center text-sm font-bold text-gray-200'}>
                                        <span className={'material-icons-round mr-1 text-base text-green-500'}>
                                            arrow_downward
                                        </span>
                                        {bytesToString(networkRate.rx)}/s
                                    </div>
                                    <div className={'flex items-center text-sm font-bold text-gray-200'}>
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

                <aside
                    className={
                        'flex min-h-0 w-full min-w-0 flex-col gap-6 overflow-x-hidden overflow-y-visible p-4 md:p-6 lg:overflow-y-auto xl:w-[23%] xl:flex-none xl:pl-0'
                    }
                >
                    <div
                        className={
                            'flex items-center rounded-xl border border-[color:var(--border)] bg-[color:var(--card)] p-4 shadow-[0_0_0_1px_rgba(var(--primary-rgb), 0.05)]'
                        }
                    >
                        <div
                            className={
                                'mr-3 flex h-10 w-10 items-center justify-center overflow-hidden rounded-lg border border-[color:var(--primary)] bg-[color:var(--card)] shadow-[0_0_12px_rgba(var(--primary-rgb), 0.25)]'
                            }
                        >
                            <Avatar.User size={40} />
                        </div>
                        <div className={'min-w-0'}>
                            <h3 className={'truncate font-bold text-[#f8f6ef]'}>{username}</h3>
                            <p className={'truncate text-xs text-gray-400'}>{email}</p>
                        </div>
                    </div>

                    <div
                        className={
                            'rounded-xl border border-[color:var(--border)] bg-[color:var(--card)] p-5 shadow-[0_0_0_1px_rgba(var(--primary-rgb), 0.05)]'
                        }
                    >
                        <h3 className={'mb-4 text-lg font-bold uppercase tracking-wide text-[#f8f6ef]'}>
                            Server Control
                        </h3>
                        <div className={'mb-6 space-y-3 text-sm'}>
                            <div className={'flex items-start justify-between gap-3'}>
                                <span className={'text-gray-400'}>IP:</span>
                                <span
                                    className={
                                        'max-w-[70%] break-all rounded-md border border-[color:var(--border)] bg-[color:var(--card)] px-2 py-0.5 text-right font-mono text-xs font-medium text-gray-200'
                                    }
                                >
                                    {allocation}
                                </span>
                            </div>
                            <div className={'flex items-start justify-between gap-3'}>
                                <span className={'text-gray-400'}>Status:</span>
                                <span className={statusBadgeClass}>{(status || 'offline').toUpperCase()}</span>
                            </div>
                            <div className={'flex items-start justify-between gap-3'}>
                                <span className={'text-gray-400'}>Node:</span>
                                <code
                                    className={
                                        'max-w-[70%] break-all rounded-md border border-[color:var(--border)] bg-[color:var(--card)] px-2 py-1 text-right font-mono text-xs text-gray-200'
                                    }
                                >
                                    {node}
                                </code>
                            </div>
                            <div className={'flex items-start justify-between gap-3'}>
                                <span className={'text-gray-400'}>Server ID:</span>
                                <code
                                    title={uuid}
                                    className={
                                        'max-w-[70%] break-all rounded-md border border-[color:var(--border)] bg-[color:var(--card)] px-2 py-1 text-right font-mono text-xs text-gray-200'
                                    }
                                >
                                    {uuid.slice(0, 8)}
                                </code>
                            </div>
                        </div>
                        <PowerButtons className={'space-y-3'} variant={'glass'} />
                    </div>

                    <div
                        className={
                            'flex min-h-[300px] flex-1 flex-col rounded-xl border border-[color:var(--border)] bg-[color:var(--card)] p-5 shadow-[0_0_0_1px_rgba(var(--primary-rgb), 0.05)]'
                        }
                    >
                        <div className={'mb-4 flex items-center justify-between'}>
                            <h3 className={'text-lg font-bold text-[#f8f6ef]'}>Players</h3>
                            <span
                                className={
                                    'rounded-md border border-[color:var(--border)] bg-[color:var(--card)] px-2 py-1 text-xs font-medium text-gray-300'
                                }
                            >
                                1 Online
                            </span>
                        </div>
                        <div className={'relative mb-4'}>
                            <input
                                className={
                                    'w-full rounded-lg border border-gray-800 bg-[color:var(--card)] py-2 pl-3 pr-8 text-xs text-white outline-none focus:border-[color:var(--primary)] focus:ring-1 focus:ring-[color:var(--primary)]'
                                }
                                placeholder={'Filter by Name or ID...'}
                                type={'text'}
                            />
                            <span
                                className={
                                    'material-icons-round pointer-events-none absolute right-2 top-2 text-sm text-gray-500'
                                }
                            >
                                search
                            </span>
                        </div>
                        <div className={'space-y-3 overflow-y-auto pr-1'}>
                            {[
                                { name: 'Ekarl', ping: '32ms', color: 'bg-blue-500', tag: 'E' },
                                { name: 'Coral', ping: '45ms', color: 'bg-indigo-500', tag: 'C' },
                                { name: 'Izzxt', ping: '28ms', color: 'bg-cyan-500', tag: 'I' },
                                { name: 'Quantizen', ping: '37ms', color: 'bg-sky-500', tag: 'Q' },
                                { name: 'Nxim', ping: '41ms', color: 'bg-blue-400', tag: 'N' },
                            ].map((player) => (
                                <div
                                    key={player.name}
                                    className={
                                        'flex items-center justify-between rounded-lg border border-transparent p-2 transition-colors hover:border-[color:var(--border)] hover:bg-[color:var(--card)]/40'
                                    }
                                >
                                    <div className={'flex items-center gap-3'}>
                                        <div
                                            className={classNames(
                                                'flex h-9 w-9 items-center justify-center rounded text-sm font-bold text-white shadow-sm',
                                                player.color
                                            )}
                                        >
                                            {player.tag}
                                        </div>
                                        <div>
                                            <p className={'text-sm font-bold text-gray-100'}>{player.name}</p>
                                            <p className={'text-[10px] text-gray-500'}>Ping: {player.ping}</p>
                                        </div>
                                    </div>
                                    <div className={'flex gap-1'}>
                                        <button
                                            className={
                                                'rounded p-1 text-gray-500 hover:bg-[color:var(--primary)]/10 hover:text-[color:var(--primary)]'
                                            }
                                            type={'button'}
                                        >
                                            <span className={'material-icons-round text-sm'}>edit_note</span>
                                        </button>
                                        <button
                                            className={
                                                'rounded p-1 text-gray-500 hover:bg-[color:var(--primary)]/10 hover:text-[color:var(--primary)]'
                                            }
                                            type={'button'}
                                        >
                                            <span className={'material-icons-round text-sm'}>settings</span>
                                        </button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    );
};

export default memo(ServerConsoleContainer, isEqual);
