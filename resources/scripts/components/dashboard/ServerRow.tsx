import React, { useEffect, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import { Server } from '@/api/server/getServer';
import getServerResourceUsage, { ServerPowerState, ServerStats } from '@/api/server/getServerResourceUsage';
import { bytesToString, ip, mbToBytes } from '@/lib/formatters';
import getWebsocketToken from '@/api/server/getWebsocketToken';
import { Websocket } from '@/plugins/Websocket';
import { SocketEvent, SocketRequest } from '@/components/server/events';
import { GlowCard } from '@/components/ui/spotlight-card';

const isAlarmState = (current: number, limit: number): boolean => limit > 0 && current / (limit * 1024 * 1024) >= 0.9;

type Timer = ReturnType<typeof setInterval>;
const RESOURCE_POLL_INTERVAL = 5000;

const getStatusColor = (status: ServerPowerState | undefined): string => {
    if (!status || status === 'offline') return '#ef4444';
    if (status === 'running') return '#22c55e';
    return '#eab308';
};

const getStatusLabel = (status: ServerPowerState | undefined): string => {
    if (!status || status === 'offline') return 'OFFLINE';
    if (status === 'running') return 'RUNNING';
    if (status === 'starting') return 'STARTING';
    if (status === 'stopping') return 'STOPPING';
    return 'UNKNOWN';
};

const clampPercent = (value: number): number => Math.max(0, Math.min(100, value));

export default ({ server, className }: { server: Server; className?: string }) => {
    const pollInterval = useRef<Timer | null>(null);
    const socketRef = useRef<Websocket | null>(null);
    const [isSuspended, setIsSuspended] = useState(server.status === 'suspended');
    const [stats, setStats] = useState<ServerStats | null>(null);

    const getStats = () =>
        getServerResourceUsage(server.uuid)
            .then((data) => setStats(data))
            .catch((error) => console.error(error));

    const stopPolling = () => {
        if (pollInterval.current) {
            clearInterval(pollInterval.current);
            pollInterval.current = null;
        }
    };

    const startPolling = () => {
        if (pollInterval.current) return;

        getStats();
        pollInterval.current = setInterval(() => getStats(), RESOURCE_POLL_INTERVAL);
    };

    useEffect(() => {
        setIsSuspended(stats?.isSuspended || server.status === 'suspended');
    }, [stats?.isSuspended, server.status]);

    useEffect(() => {
        if (isSuspended) {
            stopPolling();
            socketRef.current?.close();
            socketRef.current = null;
            return;
        }

        let isMounted = true;
        let updatingToken = false;

        // Immediate value while websocket is bootstrapping.
        startPolling();

        const socket = new Websocket();
        socketRef.current = socket;

        const updateToken = () => {
            if (updatingToken || !isMounted) return;

            updatingToken = true;
            getWebsocketToken(server.uuid)
                .then((data) => socket.setToken(data.token, true))
                .catch((error) => console.error(error))
                .finally(() => {
                    updatingToken = false;
                });
        };

        socket.on('auth success', () => {
            if (!isMounted) return;

            stopPolling();
            socket.send(SocketRequest.SEND_STATS);
        });

        socket.on(SocketEvent.STATS, (data: string) => {
            if (!isMounted) return;

            try {
                const parsed = JSON.parse(data);
                setStats((current) => ({
                    status: current?.status || 'running',
                    isSuspended: current?.isSuspended || false,
                    memoryUsageInBytes: parsed.memory_bytes,
                    cpuUsagePercent: parsed.cpu_absolute,
                    diskUsageInBytes: parsed.disk_bytes,
                    networkRxInBytes: parsed.network?.rx_bytes ?? 0,
                    networkTxInBytes: parsed.network?.tx_bytes ?? 0,
                    uptime: parsed.uptime || 0,
                }));
            } catch (error) {
                console.error(error);
            }
        });

        socket.on(SocketEvent.STATUS, (status: ServerPowerState) => {
            if (!isMounted) return;

            setStats((current) =>
                current
                    ? {
                          ...current,
                          status,
                      }
                    : current
            );
        });

        socket.on('token expiring', updateToken);
        socket.on('token expired', updateToken);
        socket.on('SOCKET_CLOSE', () => {
            if (!isMounted) return;
            startPolling();
        });
        socket.on('SOCKET_CONNECT_ERROR', () => {
            if (!isMounted) return;
            startPolling();
        });
        socket.on('SOCKET_ERROR', () => {
            if (!isMounted) return;
            startPolling();
        });
        socket.on('jwt error', () => {
            updateToken();
            if (!isMounted) return;
            startPolling();
        });

        getWebsocketToken(server.uuid)
            .then((data) => {
                if (!isMounted) return;
                socket.setToken(data.token).connect(data.socket);
            })
            .catch((error) => {
                console.error(error);
                if (!isMounted) return;
                startPolling();
            });

        return () => {
            isMounted = false;
            stopPolling();
            socket.close();
            socketRef.current = null;
        };
    }, [isSuspended, server.uuid]);

    const cpuLimit = server.limits.cpu !== 0 ? `${server.limits.cpu}%` : '∞';
    const memoryLimit = server.limits.memory !== 0 ? bytesToString(mbToBytes(server.limits.memory)) : '∞';
    const diskLimit = server.limits.disk !== 0 ? bytesToString(mbToBytes(server.limits.disk)) : '∞';

    const cpuValue = stats?.cpuUsagePercent ?? 0;
    const memoryValue = stats?.memoryUsageInBytes ?? 0;
    const diskValue = stats?.diskUsageInBytes ?? 0;

    const cpuPercent =
        server.limits.cpu > 0 ? clampPercent((cpuValue / server.limits.cpu) * 100) : clampPercent(cpuValue);
    const memoryPercent =
        server.limits.memory > 0 ? clampPercent((memoryValue / mbToBytes(server.limits.memory)) * 100) : 0;
    const diskPercent = server.limits.disk > 0 ? clampPercent((diskValue / mbToBytes(server.limits.disk)) * 100) : 0;

    const alarms = {
        cpu: stats ? (server.limits.cpu === 0 ? false : stats.cpuUsagePercent >= server.limits.cpu * 0.9) : false,
        memory: stats ? isAlarmState(stats.memoryUsageInBytes, server.limits.memory) : false,
        disk: stats
            ? server.limits.disk === 0
                ? false
                : isAlarmState(stats.diskUsageInBytes, server.limits.disk)
            : false,
    };

    const fallbackStatus = server.isTransferring
        ? 'TRANSFERRING'
        : server.status === 'installing'
        ? 'INSTALLING'
        : server.status === 'restoring_backup'
        ? 'RESTORING'
        : server.status === 'suspended'
        ? 'SUSPENDED'
        : 'UNAVAILABLE';

    const statusLabel = isSuspended ? 'SUSPENDED' : stats ? getStatusLabel(stats.status) : fallbackStatus;
    const statusColor = isSuspended ? '#ef4444' : stats ? getStatusColor(stats.status) : '#eab308';

    const defaultAllocation = server.allocations.find((allocation) => allocation.isDefault);
    const allocationLabel = defaultAllocation
        ? `${defaultAllocation.alias || ip(defaultAllocation.ip)}:${defaultAllocation.port}`
        : 'No allocation';

    return (
        <GlowCard
            glowColor='green'
            customSize
            orbit
            orbitDurationMs={2800}
            hoverGlow
            className={`w-full rounded-xl [--radius:12] [--border:2] [--size:185] ${className || ''}`}
        >
            <Link
                to={`/server/${server.id}`}
                className={'shine-border group block rounded-xl border border-gray-800 bg-[#000000] p-6 no-underline'}
            >
                <div className='flex flex-col gap-8 xl:flex-row xl:items-center'>
                    <div className='flex min-w-[240px] items-center gap-5'>
                        <div className='relative'>
                            <div className='flex h-14 w-14 items-center justify-center rounded-lg border border-white/10 bg-black transition-colors duration-500 group-hover:border-[#a3ff12]'>
                                <span className='material-icons-round text-2xl text-gray-400 transition-colors duration-500 group-hover:text-[#a3ff12]'>
                                    dns
                                </span>
                            </div>
                            <div className='absolute -right-1 -top-1 flex h-4 w-4'>
                                <span
                                    className='absolute inline-flex h-full w-full rounded-full opacity-40'
                                    style={{ backgroundColor: statusColor }}
                                />
                                <span
                                    className='relative inline-flex h-4 w-4 rounded-full border-2 border-black'
                                    style={{ backgroundColor: statusColor }}
                                />
                            </div>
                        </div>
                        <div className='min-w-0'>
                            <h3 className='truncate font-mono text-lg font-bold tracking-tight text-white'>
                                {server.name}
                            </h3>
                            <div className='mt-1 flex flex-wrap items-center gap-2'>
                                <span
                                    className='neon-glow-text text-[10px] font-bold uppercase italic tracking-widest'
                                    style={{ color: statusColor }}
                                >
                                    {statusLabel}
                                </span>
                                <span className='text-[10px] text-gray-500'>•</span>
                                <span className='truncate text-[10px] text-gray-500'>{allocationLabel}</span>
                            </div>
                        </div>
                    </div>

                    <div className='flex-1 grid-cols-1 gap-6 font-mono md:grid md:grid-cols-3'>
                        <div className='space-y-2'>
                            <div className='flex items-end justify-between'>
                                <span className='text-[10px] font-bold uppercase tracking-widest text-gray-500'>
                                    CPU LOAD
                                </span>
                                <span
                                    className='text-sm font-bold'
                                    style={{ color: alarms.cpu ? '#ef4444' : '#a3ff12' }}
                                >
                                    {stats ? `${cpuValue.toFixed(1)}%` : '--'}
                                </span>
                            </div>
                            <div className='h-1 w-full overflow-hidden rounded-full bg-white/5'>
                                <div
                                    className={`h-full transition-all duration-700 ${
                                        alarms.cpu ? '' : 'progress-neon'
                                    }`}
                                    style={{
                                        width: `${stats ? cpuPercent : 0}%`,
                                        backgroundColor: alarms.cpu ? '#ef4444' : '#a3ff12',
                                    }}
                                />
                            </div>
                            <p className='text-right text-[9px] uppercase text-gray-600'>Limit: {cpuLimit}</p>
                        </div>

                        <div className='space-y-2'>
                            <div className='flex items-end justify-between'>
                                <span className='text-[10px] font-bold uppercase tracking-widest text-gray-500'>
                                    MEMORY
                                </span>
                                <span
                                    className='text-sm font-bold'
                                    style={{ color: alarms.memory ? '#ef4444' : '#a3ff12' }}
                                >
                                    {stats ? bytesToString(memoryValue) : '--'}
                                </span>
                            </div>
                            <div className='h-1 w-full overflow-hidden rounded-full bg-white/5'>
                                <div
                                    className={`h-full transition-all duration-700 ${
                                        alarms.memory ? '' : 'progress-neon'
                                    }`}
                                    style={{
                                        width: `${stats ? memoryPercent : 0}%`,
                                        backgroundColor: alarms.memory ? '#ef4444' : '#a3ff12',
                                    }}
                                />
                            </div>
                            <p className='text-right text-[9px] uppercase text-gray-600'>Allocation: {memoryLimit}</p>
                        </div>

                        <div className='space-y-2'>
                            <div className='flex items-end justify-between'>
                                <span className='text-[10px] font-bold uppercase tracking-widest text-gray-500'>
                                    STORAGE
                                </span>
                                <span
                                    className='text-sm font-bold'
                                    style={{ color: alarms.disk ? '#ef4444' : '#a3ff12' }}
                                >
                                    {stats ? bytesToString(diskValue) : '--'}
                                </span>
                            </div>
                            <div className='h-1 w-full overflow-hidden rounded-full bg-white/5'>
                                <div
                                    className={`h-full transition-all duration-700 ${
                                        alarms.disk ? '' : 'progress-neon'
                                    }`}
                                    style={{
                                        width: `${stats ? diskPercent : 0}%`,
                                        backgroundColor: alarms.disk ? '#ef4444' : '#a3ff12',
                                    }}
                                />
                            </div>
                            <p className='text-right text-[9px] uppercase text-gray-600'>Capacity: {diskLimit}</p>
                        </div>
                    </div>
                </div>
            </Link>
        </GlowCard>
    );
};
