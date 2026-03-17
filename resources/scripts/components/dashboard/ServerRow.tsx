import React, { useEffect, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import { Server } from '@/api/server/getServer';
import getServerResourceUsage, { ServerPowerState, ServerStats } from '@/api/server/getServerResourceUsage';
import { bytesToString, ip, mbToBytes } from '@/lib/formatters';
import getWebsocketToken from '@/api/server/getWebsocketToken';
import { Websocket } from '@/plugins/Websocket';
import { SocketEvent, SocketRequest } from '@/components/server/events';

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
        <Link
            to={`/server/${server.id}`}
            className={`dashboard-server-row group block no-underline ${className || ''}`}
        >
            <div className='dashboard-server-grid'>
                <div className='dashboard-server-summary'>
                    <div className='relative'>
                        <div className='dashboard-server-iconbox'>
                            <span className='material-icons-round dashboard-server-icon text-[1.35rem]'>dns</span>
                        </div>
                        <div className='dashboard-server-status-dot-wrap'>
                            <span
                                className='dashboard-server-status-dot-glow'
                                style={{ backgroundColor: statusColor }}
                            />
                            <span className='dashboard-server-status-dot' style={{ backgroundColor: statusColor }} />
                        </div>
                    </div>
                    <div className='min-w-0'>
                        <h3 className='dashboard-server-name truncate'>{server.name}</h3>
                        <div className='dashboard-server-meta-row'>
                            <span
                                className='dashboard-neon-glow-text dashboard-server-status'
                                style={{ color: statusColor }}
                            >
                                {statusLabel}
                            </span>
                            <span className='dashboard-server-separator'>•</span>
                            <span className='dashboard-server-address truncate'>{allocationLabel}</span>
                        </div>
                    </div>
                </div>

                <div className='dashboard-server-metrics'>
                    <div className='dashboard-server-metric'>
                        <div className='dashboard-server-metric-label'>CPU LOAD</div>
                        <div className='dashboard-server-metric-value-row'>
                            <span>{stats ? `${cpuValue.toFixed(1)}%` : '--'}</span>
                            <span>{stats ? `${cpuPercent.toFixed(0)}%` : '--'}</span>
                        </div>
                        <div className='dashboard-progress-track'>
                            <div
                                className={`dashboard-progress-fill ${
                                    alarms.cpu ? 'dashboard-progress-alert' : 'dashboard-progress-cyan'
                                }`}
                                style={{ width: `${stats ? cpuPercent : 0}%` }}
                            />
                        </div>
                        <p className='dashboard-server-metric-hint'>Limit: {cpuLimit}</p>
                    </div>

                    <div className='dashboard-server-metric'>
                        <div className='dashboard-server-metric-label'>MEMORY</div>
                        <div className='dashboard-server-metric-value-row'>
                            <span>{stats ? bytesToString(memoryValue) : '--'}</span>
                            <span>{stats ? `${memoryPercent.toFixed(0)}%` : '--'}</span>
                        </div>
                        <div className='dashboard-progress-track'>
                            <div
                                className={`dashboard-progress-fill ${
                                    alarms.memory ? 'dashboard-progress-alert' : 'dashboard-progress-amber'
                                }`}
                                style={{ width: `${stats ? memoryPercent : 0}%` }}
                            />
                        </div>
                        <p className='dashboard-server-metric-hint'>Allocation: {memoryLimit}</p>
                    </div>

                    <div className='dashboard-server-metric'>
                        <div className='dashboard-server-metric-label'>STORAGE</div>
                        <div className='dashboard-server-metric-value-row'>
                            <span>{stats ? bytesToString(diskValue) : '--'}</span>
                            <span>{stats ? `${diskPercent.toFixed(0)}%` : '--'}</span>
                        </div>
                        <div className='dashboard-progress-track'>
                            <div
                                className={`dashboard-progress-fill ${
                                    alarms.disk ? 'dashboard-progress-alert' : 'dashboard-progress-lime'
                                }`}
                                style={{ width: `${stats ? diskPercent : 0}%` }}
                            />
                        </div>
                        <p className='dashboard-server-metric-hint'>Capacity: {diskLimit}</p>
                    </div>
                </div>
            </div>
        </Link>
    );
};
