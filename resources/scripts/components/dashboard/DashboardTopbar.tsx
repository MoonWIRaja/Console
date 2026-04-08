import React, { useEffect, useRef, useState } from 'react';
import { useStoreState } from 'easy-peasy';
import { Link, useHistory, useLocation } from 'react-router-dom';
import { ApplicationStore } from '@/state';
import getServers from '@/api/getServers';
import { Server } from '@/api/server/getServer';
import useSiteBranding from '@/hooks/useSiteBranding';
import {
    getStoredSidebarMode,
    SIDEBAR_MODE_SYNC_EVENT,
    SidebarMode,
    syncSidebarMode,
} from '@/components/elements/sidebar/AceternitySidebar';

export const DASHBOARD_TOPBAR_HEIGHT = 88;
const DASHBOARD_VERSION = 'V2.5.0';
const DASHBOARD_CHANNEL = 'BETA';

interface Props {
    isMobileViewport?: boolean;
    onOpenSidebar?: () => void;
    onCloseSidebar?: () => void;
    isSidebarOpen?: boolean;
    searchQuery?: string;
    onSearchChange?: (value: string) => void;
    showSearch?: boolean;
    centerTitle?: string;
    centerSubtitle?: string;
}

const getServerAddress = (server: Server) => {
    const allocation = server.allocations.find((item) => item.isDefault);

    if (allocation) {
        return `${allocation.alias || allocation.ip}:${allocation.port}`;
    }

    return `${server.sftpDetails.ip}:${server.sftpDetails.port}`;
};

const DashboardTopbar = ({
    isMobileViewport = false,
    onOpenSidebar,
    onCloseSidebar,
    isSidebarOpen = false,
    searchQuery = '',
    onSearchChange,
    showSearch = false,
    centerTitle,
    centerSubtitle,
}: Props) => {
    const { name, logo } = useSiteBranding();
    const history = useHistory();
    const location = useLocation();
    const rootAdmin = useStoreState((state: ApplicationStore) => !!state.user.data?.rootAdmin);
    const userUuid = useStoreState((state: ApplicationStore) => state.user.data?.uuid || '');

    const [sidebarMode, setSidebarMode] = useState<SidebarMode>(() => getStoredSidebarMode());
    const [showAllServers, setShowAllServers] = useState(false);
    const [serverSwitcherOpen, setServerSwitcherOpen] = useState(false);
    const [serverSwitcherQuery, setServerSwitcherQuery] = useState('');
    const [serverSwitcherLoading, setServerSwitcherLoading] = useState(false);
    const [serverSwitcherError, setServerSwitcherError] = useState('');
    const [serverResults, setServerResults] = useState<Server[]>([]);

    const switcherButtonRef = useRef<HTMLButtonElement | null>(null);
    const switcherPopoverRef = useRef<HTMLDivElement | null>(null);
    const switcherInputRef = useRef<HTMLInputElement | null>(null);

    useEffect(() => {
        const handleSidebarModeSync = (event: Event) => {
            const nextMode = (event as CustomEvent<SidebarMode>).detail;
            setSidebarMode(
                nextMode === 'locked-open' || nextMode === 'locked-closed' || nextMode === 'auto'
                    ? nextMode
                    : getStoredSidebarMode()
            );
        };

        window.addEventListener(SIDEBAR_MODE_SYNC_EVENT, handleSidebarModeSync as EventListener);

        return () => {
            window.removeEventListener(SIDEBAR_MODE_SYNC_EVENT, handleSidebarModeSync as EventListener);
        };
    }, []);

    useEffect(() => {
        if (typeof window === 'undefined' || !userUuid) {
            setShowAllServers(false);
            return;
        }

        setShowAllServers(window.localStorage.getItem(`${userUuid}:show_all_servers`) === 'true');
    }, [location.pathname, userUuid]);

    useEffect(() => {
        if (!serverSwitcherOpen) {
            return;
        }

        const handleOutsideClick = (event: MouseEvent) => {
            if (
                switcherButtonRef.current?.contains(event.target as Node) ||
                switcherPopoverRef.current?.contains(event.target as Node)
            ) {
                return;
            }

            setServerSwitcherOpen(false);
        };

        const handleEscape = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                setServerSwitcherOpen(false);
            }
        };

        document.addEventListener('mousedown', handleOutsideClick);
        document.addEventListener('keydown', handleEscape);

        return () => {
            document.removeEventListener('mousedown', handleOutsideClick);
            document.removeEventListener('keydown', handleEscape);
        };
    }, [serverSwitcherOpen]);

    useEffect(() => {
        if (!serverSwitcherOpen) {
            return;
        }

        window.requestAnimationFrame(() => switcherInputRef.current?.focus());
    }, [serverSwitcherOpen]);

    useEffect(() => {
        setServerSwitcherOpen(false);
        setServerSwitcherQuery('');
    }, [location.pathname]);

    useEffect(() => {
        if (!serverSwitcherOpen) {
            return;
        }

        let active = true;
        const term = serverSwitcherQuery.trim();
        const shouldUseAdminServers = rootAdmin && showAllServers;
        const requestDelay = term.length > 0 ? 220 : 0;
        const timer = window.setTimeout(() => {
            setServerSwitcherLoading(true);
            setServerSwitcherError('');

            getServers({
                query: term || undefined,
                type: shouldUseAdminServers ? (term ? 'admin-all' : 'admin') : undefined,
            })
                .then(({ items }) => {
                    if (!active) {
                        return;
                    }

                    setServerResults(items.slice(0, 8));
                })
                .catch(() => {
                    if (!active) {
                        return;
                    }

                    setServerSwitcherError('Unable to load servers right now.');
                    setServerResults([]);
                })
                .then(() => {
                    if (active) {
                        setServerSwitcherLoading(false);
                    }
                });
        }, requestDelay);

        return () => {
            active = false;
            window.clearTimeout(timer);
        };
    }, [rootAdmin, serverSwitcherOpen, serverSwitcherQuery, showAllServers]);

    const isSidebarCollapsed = sidebarMode === 'locked-closed';

    const handleServerSwitcherToggle = () => {
        setServerSwitcherOpen((value) => !value);
    };

    const handleDesktopSidebarToggle = () => {
        syncSidebarMode(isSidebarCollapsed ? 'locked-open' : 'locked-closed');
    };

    const handleServerSelect = (serverId: string) => {
        setServerSwitcherOpen(false);
        setServerSwitcherQuery('');
        history.push(`/server/${serverId}`);
    };

    const sidebarControl = !isMobileViewport ? (
        <button
            type='button'
            onClick={handleDesktopSidebarToggle}
            aria-label={isSidebarCollapsed ? 'Expand sidebar' : 'Collapse sidebar'}
            title={isSidebarCollapsed ? 'Expand sidebar' : 'Collapse sidebar'}
            style={{
                display: 'inline-flex',
                alignItems: 'center',
                justifyContent: 'center',
                width: '46px',
                height: '46px',
                borderRadius: '14px',
                border: '1px solid rgba(245, 231, 198, 0.12)',
                background: 'rgba(245, 231, 198, 0.06)',
                color: 'rgba(245, 231, 198, 0.76)',
                cursor: 'pointer',
                flexShrink: 0,
            }}
        >
            <svg
                aria-hidden='true'
                focusable='false'
                width='16'
                height='16'
                viewBox='0 0 320 512'
                fill='currentColor'
                style={{ transform: isSidebarCollapsed ? 'rotate(180deg)' : 'none' }}
            >
                <path d='M34.52 239.03L228.87 44.69c9.37-9.37 24.57-9.37 33.94 0l22.67 22.67c9.36 9.36 9.37 24.52.04 33.9L131.49 256l154.02 154.75c9.34 9.38 9.32 24.54-.04 33.9l-22.67 22.67c-9.37 9.37-24.57 9.37-33.94 0L34.52 272.97c-9.37-9.37-9.37-24.57 0-33.94z' />
            </svg>
        </button>
    ) : isSidebarOpen && onCloseSidebar ? (
        <button
            type='button'
            onClick={onCloseSidebar}
            aria-label='Close sidebar'
            title='Close sidebar'
            style={{
                display: 'inline-flex',
                alignItems: 'center',
                justifyContent: 'center',
                width: '46px',
                height: '46px',
                borderRadius: '14px',
                border: '1px solid rgba(245, 231, 198, 0.12)',
                background: 'rgba(245, 231, 198, 0.06)',
                color: 'rgba(245, 231, 198, 0.76)',
                cursor: 'pointer',
                flexShrink: 0,
            }}
        >
            <svg aria-hidden='true' focusable='false' width='16' height='16' viewBox='0 0 352 512' fill='currentColor'>
                <path d='M242.72 256l100.07-100.07c12.28-12.28 12.28-32.19 0-44.48l-22.24-22.24c-12.28-12.28-32.19-12.28-44.48 0L176 189.28 75.93 89.21c-12.28-12.28-32.19-12.28-44.48 0L9.21 111.45c-12.28 12.28-12.28 32.19 0 44.48L109.28 256 9.21 356.07c-12.28 12.28-12.28 32.19 0 44.48l22.24 22.24c12.28 12.28 32.2 12.28 44.48 0L176 322.72l100.07 100.07c12.28 12.28 32.2 12.28 44.48 0l22.24-22.24c12.28-12.28 12.28-32.19 0-44.48L242.72 256z' />
            </svg>
        </button>
    ) : null;

    return (
        <header
            style={{
                position: 'fixed',
                top: 0,
                left: 0,
                right: 0,
                height: `${DASHBOARD_TOPBAR_HEIGHT}px`,
                zIndex: 49,
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'space-between',
                gap: isMobileViewport ? '10px' : '16px',
                padding: isMobileViewport ? '0 12px' : '0 16px',
                borderBottom: '1px solid rgba(245, 231, 198, 0.12)',
                background: '#2D2D2D',
                backdropFilter: 'blur(12px)',
                boxShadow: '0 18px 40px rgba(0, 0, 0, 0.16)',
                fontFamily:
                    "var(--font-sans, 'Geist Mono', ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace)",
            }}
        >
            <div
                style={{
                    position: 'relative',
                    display: 'flex',
                    alignItems: 'center',
                    gap: isMobileViewport ? '10px' : '14px',
                    minWidth: 0,
                    flex: 1,
                }}
            >
                {isMobileViewport && onOpenSidebar && !isSidebarOpen && (
                    <button
                        type='button'
                        onClick={onOpenSidebar}
                        aria-label='Open sidebar menu'
                        style={{
                            display: 'inline-flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                            width: '44px',
                            height: '44px',
                            borderRadius: '14px',
                            border: '1px solid rgba(245, 231, 198, 0.12)',
                            background: 'rgba(245, 231, 198, 0.06)',
                            color: 'rgba(245, 231, 198, 0.76)',
                            cursor: 'pointer',
                            flexShrink: 0,
                        }}
                    >
                        <span className='material-icons-round' style={{ fontSize: '22px' }}>
                            menu
                        </span>
                    </button>
                )}

                <Link
                    to='/'
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: '12px',
                        minWidth: 0,
                        textDecoration: 'none',
                        color: 'inherit',
                    }}
                >
                    <img
                        draggable={false}
                        src={logo}
                        alt={`${name} logo`}
                        style={{
                            width: isMobileViewport ? '3.2rem' : '4rem',
                            height: isMobileViewport ? '3.2rem' : '4rem',
                            borderRadius: '0.75rem',
                            objectFit: 'contain',
                            objectPosition: 'center',
                            flexShrink: 0,
                        }}
                    />
                    <div style={{ minWidth: 0 }}>
                        <div
                            style={{
                                color: '#F5E7C6',
                                fontSize: isMobileViewport ? '1rem' : '1.1rem',
                                fontWeight: 700,
                                whiteSpace: 'nowrap',
                                overflow: 'hidden',
                                textOverflow: 'ellipsis',
                                maxWidth: isMobileViewport ? '8.75rem' : 'none',
                            }}
                        >
                            {name}
                        </div>
                    </div>
                </Link>

                <div
                    style={{ position: 'relative', display: 'flex', alignItems: 'center', gap: '10px', flexShrink: 0 }}
                >
                    <button
                        ref={switcherButtonRef}
                        type='button'
                        onClick={handleServerSwitcherToggle}
                        aria-label='Switch server'
                        title='Switch server'
                        style={{
                            display: 'inline-flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                            width: '46px',
                            height: '46px',
                            borderRadius: '14px',
                            border: '1px solid rgba(245, 231, 198, 0.12)',
                            background: 'rgba(245, 231, 198, 0.06)',
                            color: '#F5E7C6',
                            cursor: 'pointer',
                            flexShrink: 0,
                            boxShadow: 'inset 0 1px 0 rgba(255, 255, 255, 0.04)',
                        }}
                    >
                        <svg
                            aria-hidden='true'
                            focusable='false'
                            width='18'
                            height='18'
                            viewBox='0 0 512 512'
                            fill='currentColor'
                        >
                            <path d='M0 168v-16c0-13.255 10.745-24 24-24h360V80c0-21.367 25.899-32.042 40.971-16.971l80 80c9.372 9.373 9.372 24.569 0 33.941l-80 80C409.956 271.982 384 261.456 384 240v-48H24c-13.255 0-24-10.745-24-24zm488 152H128v-48c0-21.314-25.862-32.08-40.971-16.971l-80 80c-9.372 9.373-9.372 24.569 0 33.941l80 80C102.057 463.997 128 453.437 128 432v-48h360c13.255 0 24-10.745 24-24v-16c0-13.255-10.745-24-24-24z' />
                        </svg>
                    </button>

                    {sidebarControl}

                    {serverSwitcherOpen && (
                        <div
                            ref={switcherPopoverRef}
                            style={{
                                position: isMobileViewport ? 'fixed' : 'absolute',
                                top: isMobileViewport ? `${DASHBOARD_TOPBAR_HEIGHT + 12}px` : 'calc(100% + 12px)',
                                left: isMobileViewport ? '16px' : 0,
                                right: isMobileViewport ? '16px' : 'auto',
                                width: isMobileViewport ? 'auto' : '380px',
                                maxHeight: 'min(72vh, 520px)',
                                overflow: 'hidden',
                                borderRadius: '18px',
                                border: '1px solid rgba(245, 231, 198, 0.12)',
                                background: '#2D2D2D',
                                boxShadow: '0 24px 56px rgba(0, 0, 0, 0.38)',
                                backdropFilter: 'blur(16px)',
                                zIndex: 60,
                            }}
                        >
                            <div style={{ padding: '14px', borderBottom: '1px solid rgba(245, 231, 198, 0.08)' }}>
                                <div
                                    style={{
                                        display: 'flex',
                                        alignItems: 'center',
                                        gap: '10px',
                                        height: '48px',
                                        padding: '0 14px',
                                        borderRadius: '14px',
                                        border: '1px solid rgba(245, 231, 198, 0.08)',
                                        background: 'rgba(255, 255, 255, 0.02)',
                                    }}
                                >
                                    <svg
                                        aria-hidden='true'
                                        focusable='false'
                                        width='14'
                                        height='14'
                                        viewBox='0 0 512 512'
                                        fill='currentColor'
                                        style={{ color: 'rgba(248, 246, 239, 0.54)', flexShrink: 0 }}
                                    >
                                        <path d='M505 442.7L405.3 343c-4.5-4.5-10.6-7-17-7H372c27.6-35.3 44-79.7 44-128C416 93.1 322.9 0 208 0S0 93.1 0 208s93.1 208 208 208c48.3 0 92.7-16.4 128-44v16.3c0 6.4 2.5 12.5 7 17l99.7 99.7c9.4 9.4 24.6 9.4 33.9 0l28.3-28.3c9.4-9.4 9.4-24.6.1-34zM208 336c-70.7 0-128-57.2-128-128 0-70.7 57.2-128 128-128 70.7 0 128 57.2 128 128 0 70.7-57.2 128-128 128z' />
                                    </svg>
                                    <input
                                        ref={switcherInputRef}
                                        type='text'
                                        value={serverSwitcherQuery}
                                        onChange={(event) => setServerSwitcherQuery(event.currentTarget.value)}
                                        placeholder='Search servers...'
                                        style={{
                                            flex: 1,
                                            minWidth: 0,
                                            border: 'none',
                                            outline: 'none',
                                            background: 'transparent',
                                            color: 'var(--foreground)',
                                            fontSize: '0.92rem',
                                        }}
                                    />
                                </div>
                            </div>

                            <div style={{ maxHeight: '360px', overflowY: 'auto', padding: '10px' }}>
                                {serverSwitcherLoading && (
                                    <div
                                        style={{
                                            padding: '18px 14px',
                                            color: 'rgba(248, 246, 239, 0.68)',
                                            fontSize: '0.82rem',
                                        }}
                                    >
                                        Loading servers...
                                    </div>
                                )}

                                {!serverSwitcherLoading && serverSwitcherError && (
                                    <div
                                        style={{
                                            padding: '18px 14px',
                                            color: '#fca5a5',
                                            fontSize: '0.82rem',
                                        }}
                                    >
                                        {serverSwitcherError}
                                    </div>
                                )}

                                {!serverSwitcherLoading && !serverSwitcherError && !serverResults.length && (
                                    <div
                                        style={{
                                            padding: '18px 14px',
                                            color: 'rgba(248, 246, 239, 0.68)',
                                            fontSize: '0.82rem',
                                        }}
                                    >
                                        No servers matched your search.
                                    </div>
                                )}

                                {!serverSwitcherLoading &&
                                    !serverSwitcherError &&
                                    serverResults.map((server) => (
                                        <button
                                            key={server.uuid}
                                            type='button'
                                            onClick={() => handleServerSelect(server.id.toString())}
                                            style={{
                                                display: 'flex',
                                                alignItems: 'center',
                                                gap: '12px',
                                                width: '100%',
                                                padding: '12px 14px',
                                                borderRadius: '14px',
                                                border: '1px solid rgba(255, 255, 255, 0.06)',
                                                background: 'rgba(255, 255, 255, 0.02)',
                                                color: 'var(--foreground)',
                                                cursor: 'pointer',
                                                textAlign: 'left',
                                                transition: 'all 0.18s ease',
                                                marginBottom: '8px',
                                            }}
                                        >
                                            <div
                                                style={{
                                                    display: 'flex',
                                                    alignItems: 'center',
                                                    justifyContent: 'center',
                                                    width: '38px',
                                                    height: '38px',
                                                    borderRadius: '12px',
                                                    border: '1px solid rgba(255, 255, 255, 0.08)',
                                                    background: 'rgba(255, 255, 255, 0.03)',
                                                    color: 'rgba(248, 246, 239, 0.82)',
                                                    flexShrink: 0,
                                                }}
                                            >
                                                <svg
                                                    aria-hidden='true'
                                                    focusable='false'
                                                    width='16'
                                                    height='16'
                                                    viewBox='0 0 512 512'
                                                    fill='currentColor'
                                                >
                                                    <path d='M480 160H32c-17.673 0-32-14.327-32-32V64c0-17.673 14.327-32 32-32h448c17.673 0 32 14.327 32 32v64c0 17.673-14.327 32-32 32zm-48-88c-13.255 0-24 10.745-24 24s10.745 24 24 24 24-10.745 24-24-10.745-24-24-24zm-64 0c-13.255 0-24 10.745-24 24s10.745 24 24 24 24-10.745 24-24-10.745-24-24-24zm112 248H32c-17.673 0-32-14.327-32-32v-64c0-17.673 14.327-32 32-32h448c17.673 0 32 14.327 32 32v64c0 17.673-14.327 32-32 32zm-48-88c-13.255 0-24 10.745-24 24s10.745 24 24 24 24-10.745 24-24-10.745-24-24-24zm-64 0c-13.255 0-24 10.745-24 24s10.745 24 24 24 24-10.745 24-24-10.745-24-24-24zm112 248H32c-17.673 0-32-14.327-32-32v-64c0-17.673 14.327-32 32-32h448c17.673 0 32 14.327 32 32v64c0 17.673-14.327 32-32 32zm-48-88c-13.255 0-24 10.745-24 24s10.745 24 24 24 24-10.745 24-24-10.745-24-24-24zm-64 0c-13.255 0-24 10.745-24 24s10.745 24 24 24 24-10.745 24-24-10.745-24-24-24z' />
                                                </svg>
                                            </div>
                                            <div style={{ minWidth: 0, flex: 1 }}>
                                                <p
                                                    style={{
                                                        margin: 0,
                                                        color: 'rgba(248, 246, 239, 0.96)',
                                                        fontSize: '0.92rem',
                                                        fontWeight: 700,
                                                        whiteSpace: 'nowrap',
                                                        overflow: 'hidden',
                                                        textOverflow: 'ellipsis',
                                                    }}
                                                >
                                                    {server.name}
                                                </p>
                                                <p
                                                    style={{
                                                        margin: '4px 0 0',
                                                        color: 'rgba(174, 183, 194, 0.72)',
                                                        fontSize: '0.75rem',
                                                        whiteSpace: 'nowrap',
                                                        overflow: 'hidden',
                                                        textOverflow: 'ellipsis',
                                                    }}
                                                >
                                                    {getServerAddress(server)}
                                                </p>
                                            </div>
                                        </button>
                                    ))}
                            </div>
                        </div>
                    )}
                </div>
            </div>

            {!isMobileViewport && (
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'flex-end',
                        gap: '10px',
                        flexShrink: 0,
                        minWidth: 'fit-content',
                    }}
                >
                    <span
                        style={{
                            color: 'rgba(248, 246, 239, 0.82)',
                            fontSize: '0.76rem',
                            fontWeight: 800,
                            letterSpacing: '0.18em',
                            textTransform: 'uppercase',
                            whiteSpace: 'nowrap',
                        }}
                    >
                        {DASHBOARD_VERSION}
                    </span>
                    <span
                        style={{
                            display: 'inline-flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                            padding: '0.35rem 0.5rem',
                            borderRadius: '999px',
                            background: 'rgba(245, 231, 198, 0.16)',
                            color: '#F5E7C6',
                            fontSize: '9px',
                            fontWeight: 800,
                            letterSpacing: '0.16em',
                            textTransform: 'uppercase',
                            border: '1px solid rgba(245, 231, 198, 0.14)',
                            boxShadow: 'inset 0 1px 0 rgba(255, 255, 255, 0.06)',
                            whiteSpace: 'nowrap',
                        }}
                    >
                        {DASHBOARD_CHANNEL}
                    </span>
                </div>
            )}

            {showSearch && !isMobileViewport && (
                <div
                    style={{
                        position: 'absolute',
                        left: '50%',
                        top: '50%',
                        transform: 'translate(-50%, -50%)',
                        width: 'min(20rem, 36vw)',
                    }}
                >
                    <label
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: '10px',
                            width: '100%',
                            padding: '0 14px',
                            height: '48px',
                            borderRadius: '16px',
                            border: '1px solid rgba(255, 255, 255, 0.08)',
                            background: 'rgba(255, 255, 255, 0.04)',
                            boxShadow: 'inset 0 1px 0 rgba(255, 255, 255, 0.04)',
                        }}
                    >
                        <span
                            style={{
                                color: 'rgba(248, 246, 239, 0.5)',
                                fontSize: '0.72rem',
                                fontWeight: 800,
                                letterSpacing: '0.18em',
                                textTransform: 'uppercase',
                                flexShrink: 0,
                            }}
                        >
                            Search
                        </span>
                        <input
                            type='text'
                            value={searchQuery}
                            onChange={(event) => onSearchChange?.(event.currentTarget.value)}
                            placeholder='Filter servers on this page'
                            style={{
                                flex: 1,
                                minWidth: 0,
                                border: 'none',
                                outline: 'none',
                                background: 'transparent',
                                color: 'var(--foreground)',
                                fontSize: '0.92rem',
                            }}
                        />
                        <span
                            className='material-icons-round'
                            style={{ fontSize: '20px', color: 'rgba(248, 246, 239, 0.58)' }}
                        >
                            search
                        </span>
                    </label>
                </div>
            )}

            {!showSearch && !!centerTitle && !isMobileViewport && (
                <div
                    style={{
                        position: 'absolute',
                        left: '50%',
                        top: '50%',
                        transform: 'translate(-50%, -50%)',
                        width: 'min(24rem, 40vw)',
                        pointerEvents: 'none',
                    }}
                >
                    <div
                        style={{
                            display: 'flex',
                            flexDirection: 'column',
                            gap: '4px',
                            width: '100%',
                            minWidth: 0,
                            padding: '10px 16px',
                            borderRadius: '16px',
                            border: '1px solid rgba(255, 255, 255, 0.08)',
                            background:
                                'linear-gradient(180deg, rgba(255, 255, 255, 0.045), rgba(255, 255, 255, 0.02)), rgba(255, 255, 255, 0.025)',
                            boxShadow: 'inset 0 1px 0 rgba(255, 255, 255, 0.04)',
                            textAlign: 'center',
                        }}
                    >
                        <span
                            style={{
                                color: 'rgba(248, 246, 239, 0.96)',
                                fontSize: '0.92rem',
                                fontWeight: 800,
                                letterSpacing: '0.04em',
                                whiteSpace: 'nowrap',
                                overflow: 'hidden',
                                textOverflow: 'ellipsis',
                            }}
                        >
                            {centerTitle}
                        </span>
                        {centerSubtitle ? (
                            <span
                                style={{
                                    color: 'rgba(248, 246, 239, 0.52)',
                                    fontSize: '0.68rem',
                                    fontWeight: 800,
                                    letterSpacing: '0.18em',
                                    textTransform: 'uppercase',
                                    whiteSpace: 'nowrap',
                                    overflow: 'hidden',
                                    textOverflow: 'ellipsis',
                                }}
                            >
                                {centerSubtitle}
                            </span>
                        ) : null}
                    </div>
                </div>
            )}
        </header>
    );
};

export default DashboardTopbar;
