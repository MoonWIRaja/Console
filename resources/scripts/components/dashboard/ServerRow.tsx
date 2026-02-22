import React, { memo, useEffect, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import { Server } from '@/api/server/getServer';
import getServerResourceUsage, { ServerPowerState, ServerStats } from '@/api/server/getServerResourceUsage';
import { bytesToString, ip, mbToBytes } from '@/lib/formatters';
import Spinner from '@/components/elements/Spinner';

const isAlarmState = (current: number, limit: number): boolean => limit > 0 && current / (limit * 1024 * 1024) >= 0.9;

type Timer = ReturnType<typeof setInterval>;

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

export default ({ server, className }: { server: Server; className?: string }) => {
    const interval = useRef<Timer>(null) as React.MutableRefObject<Timer>;
    const [isSuspended, setIsSuspended] = useState(server.status === 'suspended');
    const [stats, setStats] = useState<ServerStats | null>(null);

    const getStats = () =>
        getServerResourceUsage(server.uuid)
            .then((data) => setStats(data))
            .catch((error) => console.error(error));

    useEffect(() => {
        setIsSuspended(stats?.isSuspended || server.status === 'suspended');
    }, [stats?.isSuspended, server.status]);

    useEffect(() => {
        if (isSuspended) return;

        getStats().then(() => {
            interval.current = setInterval(() => getStats(), 30000);
        });

        return () => {
            interval.current && clearInterval(interval.current);
        };
    }, [isSuspended]);

    const alarms = { cpu: false, memory: false, disk: false };
    if (stats) {
        alarms.cpu = server.limits.cpu === 0 ? false : stats.cpuUsagePercent >= server.limits.cpu * 0.9;
        alarms.memory = isAlarmState(stats.memoryUsageInBytes, server.limits.memory);
        alarms.disk = server.limits.disk === 0 ? false : isAlarmState(stats.diskUsageInBytes, server.limits.disk);
    }

    const diskLimit = server.limits.disk !== 0 ? bytesToString(mbToBytes(server.limits.disk)) : '∞';
    const memoryLimit = server.limits.memory !== 0 ? bytesToString(mbToBytes(server.limits.memory)) : '∞';
    const cpuLimit = server.limits.cpu !== 0 ? server.limits.cpu + '%' : '∞';

    const statusColor = getStatusColor(stats?.status);

    return (
        <Link
            to={`/server/${server.id}`}
            className={className}
            style={{
                display: 'flex',
                alignItems: 'center',
                padding: '16px 20px',
                border: '1px solid #e5e7eb',
                backgroundColor: '#ffffff',
                textDecoration: 'none',
                transition: 'all 0.15s',
                fontFamily: "'Space Mono', monospace",
                gap: '20px',
                position: 'relative',
            }}
            onMouseEnter={(e) => {
                (e.currentTarget as HTMLElement).style.borderColor = '#000000';
                (e.currentTarget as HTMLElement).style.backgroundColor = '#fafafa';
            }}
            onMouseLeave={(e) => {
                (e.currentTarget as HTMLElement).style.borderColor = '#e5e7eb';
                (e.currentTarget as HTMLElement).style.backgroundColor = '#ffffff';
            }}
        >
            {/* Status Dot */}
            <div style={{
                width: '8px',
                height: '8px',
                borderRadius: '50%',
                backgroundColor: statusColor,
                flexShrink: 0,
            }} />

            {/* Server Info */}
            <div style={{ flex: 1, minWidth: 0 }}>
                <div style={{ fontSize: '13px', fontWeight: 'bold', color: '#000000', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                    {server.name}
                </div>
                {!!server.description && (
                    <div style={{ fontSize: '10px', color: '#9ca3af', marginTop: '2px', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                        {server.description}
                    </div>
                )}
            </div>

            {/* IP Address */}
            <div style={{ fontSize: '10px', color: '#6b7280', display: 'none', flexShrink: 0 }} className="hidden lg:block">
                {server.allocations
                    .filter((alloc) => alloc.isDefault)
                    .map((allocation) => (
                        <span key={allocation.ip + allocation.port.toString()}>
                            {allocation.alias || ip(allocation.ip)}:{allocation.port}
                        </span>
                    ))}
            </div>

            {/* Stats */}
            {!stats || isSuspended ? (
                isSuspended ? (
                    <div style={{ flexShrink: 0 }}>
                        <span style={{
                            fontSize: '9px',
                            fontWeight: 'bold',
                            padding: '3px 8px',
                            backgroundColor: '#fee2e2',
                            color: '#ef4444',
                            letterSpacing: '0.05em',
                            textTransform: 'uppercase',
                        }}>
                            {server.status === 'suspended' ? 'SUSPENDED' : 'ERROR'}
                        </span>
                    </div>
                ) : server.isTransferring || server.status ? (
                        <div style={{ flexShrink: 0 }}>
                            <span style={{
                                fontSize: '9px',
                                fontWeight: 'bold',
                                padding: '3px 8px',
                                backgroundColor: '#f3f4f6',
                                color: '#6b7280',
                                letterSpacing: '0.05em',
                                textTransform: 'uppercase',
                            }}>
                                {server.isTransferring
                                    ? 'TRANSFERRING'
                                    : server.status === 'installing'
                                        ? 'INSTALLING'
                                        : server.status === 'restoring_backup'
                                        ? 'RESTORING'
                                        : 'UNAVAILABLE'}
                        </span>
                    </div>
                ) : (
                    <Spinner size={'small'} />
                )
            ) : (
                    <div style={{ display: 'flex', gap: '20px', flexShrink: 0 }} className="hidden sm:flex">
                        {/* CPU */}
                        <div style={{ textAlign: 'center' }}>
                            <div style={{ fontSize: '11px', fontWeight: 'bold', color: alarms.cpu ? '#ef4444' : '#000000' }}>
                                {stats.cpuUsagePercent.toFixed(1)}%
                            </div>
                            <div style={{ fontSize: '9px', color: '#9ca3af', marginTop: '1px' }}>CPU / {cpuLimit}</div>
                        </div>
                        {/* Memory */}
                        <div style={{ textAlign: 'center' }}>
                            <div style={{ fontSize: '11px', fontWeight: 'bold', color: alarms.memory ? '#ef4444' : '#000000' }}>
                                {bytesToString(stats.memoryUsageInBytes)}
                            </div>
                            <div style={{ fontSize: '9px', color: '#9ca3af', marginTop: '1px' }}>RAM / {memoryLimit}</div>
                        </div>
                        {/* Disk */}
                        <div style={{ textAlign: 'center' }}>
                            <div style={{ fontSize: '11px', fontWeight: 'bold', color: alarms.disk ? '#ef4444' : '#000000' }}>
                                {bytesToString(stats.diskUsageInBytes)}
                            </div>
                            <div style={{ fontSize: '9px', color: '#9ca3af', marginTop: '1px' }}>DISK / {diskLimit}</div>
                        </div>
                        {/* Status Label */}
                        <div style={{ textAlign: 'center', minWidth: '60px' }}>
                            <div style={{ fontSize: '9px', fontWeight: 'bold', color: statusColor, letterSpacing: '0.05em' }}>
                                {getStatusLabel(stats.status)}
                            </div>
                    </div>
                </div>
            )}
        </Link>
    );
};
