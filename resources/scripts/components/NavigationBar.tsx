import * as React from 'react';
import { useState } from 'react';
import { useLocation, Link } from 'react-router-dom';
import { useStoreState } from 'easy-peasy';
import { ApplicationStore } from '@/state';
import http from '@/api/http';
import Avatar from '@/components/Avatar';
import SpinnerOverlay from '@/components/elements/SpinnerOverlay';
import { ServerContext } from '@/state/server';
import {
    Sidebar,
    SidebarBody,
    SidebarLink,
    SidebarLabel,
    useSidebar,
} from '@/components/elements/sidebar/AceternitySidebar';
import { motion } from 'framer-motion';

interface NavigationBarProps {
    sidebarOpen?: boolean;
    setSidebarOpen?: React.Dispatch<React.SetStateAction<boolean>>;
    showMobileHeader?: boolean;
}

// ---------- Logo ----------
const SidebarLogo = () => {
    const { open, animate } = useSidebar();
    const expanded = animate ? open : true;
    return (
        <div
            style={{
                padding: '24px 24px 16px',
                overflow: 'hidden',
                whiteSpace: 'nowrap',
            }}
        >
            <motion.div
                animate={{ opacity: 1 }}
                transition={{ duration: 0.2 }}
                style={{
                    display: 'flex',
                    alignItems: 'center',
                    gap: '10px',
                }}
            >
                <img
                    src={'/assets/svgs/pterodactyl.svg'}
                    alt={'System Logo'}
                    style={{
                        width: expanded ? '28px' : '22px',
                        height: expanded ? '28px' : '22px',
                        objectFit: 'contain',
                        filter: 'brightness(1.15)',
                        flexShrink: 0,
                    }}
                />
                {expanded && (
                    <div
                        style={{
                            fontSize: '18px',
                            fontWeight: 900,
                            color: '#ffffff',
                            lineHeight: 1,
                            letterSpacing: '-0.02em',
                            textShadow: '0 0 10px rgba(255,255,255,0.12)',
                            whiteSpace: 'nowrap',
                        }}
                    >
                        BurHan Console
                    </div>
                )}
            </motion.div>
        </div>
    );
};

// ---------- UserFooter ----------
const UserFooter = ({ userName, onLogout }: { userName: string; onLogout: () => void }) => {
    const { open, setOpen, animate } = useSidebar();
    const expanded = animate ? open : true;
    const closeSidebarOnMobile = () => {
        if (typeof window !== 'undefined' && window.matchMedia('(max-width: 1023px)').matches) {
            setOpen(false);
        }
    };

    return (
        <div
            style={{
                borderTop: '1px solid rgba(255,255,255,0.1)',
                padding: '16px',
                backgroundColor: 'rgba(0,0,0,0.2)',
            }}
        >
            <Link to='/account' style={{ textDecoration: 'none' }} onClick={closeSidebarOnMobile}>
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: '12px',
                        marginBottom: expanded ? '16px' : '0',
                        justifyContent: expanded ? 'flex-start' : 'center',
                        cursor: 'pointer',
                        padding: '4px',
                        borderRadius: '8px',
                    }}
                    className='hover:bg-white/5 transition-colors'
                >
                    <div
                        style={{
                            width: '36px',
                            height: '36px',
                            borderRadius: '8px',
                            overflow: 'hidden',
                            flexShrink: 0,
                            backgroundColor: '#050505',
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                            border: '1px solid rgba(163, 255, 18, 0.35)',
                            boxShadow: '0 0 0 1px rgba(163, 255, 18, 0.08), 0 6px 14px -6px rgba(163, 255, 18, 0.45)',
                        }}
                    >
                        <Avatar.User size={36} variant={'beam'} />
                    </div>
                    {expanded && (
                        <motion.div
                            initial={{ opacity: 0 }}
                            animate={{ opacity: 1 }}
                            transition={{ duration: 0.2 }}
                            style={{
                                fontSize: '14px',
                                fontWeight: 700,
                                color: '#ffffff',
                                overflow: 'hidden',
                                textOverflow: 'ellipsis',
                                whiteSpace: 'nowrap',
                            }}
                        >
                            {userName}
                        </motion.div>
                    )}
                </div>
            </Link>
            {expanded && (
                <motion.button
                    initial={{ opacity: 0 }}
                    animate={{ opacity: 1 }}
                    transition={{ duration: 0.2 }}
                    onClick={onLogout}
                    className='sidebar-link'
                    style={{
                        width: '100%',
                        display: 'flex',
                        alignItems: 'center',
                        gap: '12px',
                        padding: '8px',
                        fontSize: '14px',
                        fontWeight: 500,
                        color: '#9ca3af',
                        backgroundColor: 'transparent',
                        border: 'none',
                        cursor: 'pointer',
                        transition: 'all 0.15s',
                    }}
                    type='button'
                >
                    <span className='material-icons-round' style={{ fontSize: '20px' }}>
                        logout
                    </span>
                    <span>LOG OUT</span>
                </motion.button>
            )}
        </div>
    );
};

// ---------- NavigationBar (exported) ----------
export default ({ sidebarOpen, setSidebarOpen, showMobileHeader = true }: NavigationBarProps) => {
    const rootAdmin = useStoreState((state: ApplicationStore) => !!state.user.data?.rootAdmin);
    const userName = useStoreState((state: ApplicationStore) => state.user.data?.username || 'User');
    const [isLoggingOut, setIsLoggingOut] = useState(false);
    const location = useLocation();

    const onTriggerLogout = () => {
        setIsLoggingOut(true);
        http.post('/auth/logout').finally(() => {
            // @ts-expect-error this is valid
            window.location = '/';
        });
    };

    return (
        <>
            <style>{`
                @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap');
                @import url('https://fonts.googleapis.com/icon?family=Material+Icons+Round');
                .sidebar-desktop-shell {
                    position: relative;
                }
                .sidebar-desktop-shell::after {
                    content: '';
                    position: absolute;
                    top: 10px;
                    bottom: 10px;
                    right: -1px;
                    width: 2px;
                    border-radius: 999px;
                    background: #1f2937;
                    box-shadow: 0 0 0 rgba(0, 0, 0, 0);
                    pointer-events: none;
                }
                .sidebar-desktop-shell::before {
                    content: '';
                    position: absolute;
                    top: 10px;
                    right: -4px;
                    width: 8px;
                    height: 42px;
                    border-radius: 999px;
                    background: radial-gradient(
                        ellipse at center,
                        rgba(220, 255, 162, 0.98) 0%,
                        rgba(163, 255, 18, 0.92) 35%,
                        rgba(163, 255, 18, 0.28) 60%,
                        rgba(163, 255, 18, 0) 100%
                    );
                    animation: sidebar-neon-flow 2.6s linear infinite;
                    filter: drop-shadow(0 0 10px rgba(163, 255, 18, 0.85));
                    pointer-events: none;
                }
                @keyframes sidebar-neon-flow {
                    from {
                        transform: translateY(0);
                    }
                    to {
                        transform: translateY(calc(100vh - 62px));
                    }
                }
                .sidebar-link:hover {
                    color: #a3ff12 !important;
                    background-color: rgba(163, 255, 18, 0.08) !important;
                    border-color: rgba(163, 255, 18, 0.25) !important;
                }
            `}</style>
            <SpinnerOverlay visible={isLoggingOut} />
            <Sidebar open={sidebarOpen} setOpen={setSidebarOpen}>
                <SidebarBody showMobileHeader={showMobileHeader}>
                    <SidebarLogo />

                    {/* Nav */}
                    <nav style={{ flex: 1, overflowY: 'auto', padding: '0 12px' }}>
                        <SidebarLink
                            link={{
                                label: 'Dashboard',
                                href: '/',
                                icon: (
                                    <span className='material-icons-round' style={{ fontSize: '20px' }}>
                                        dashboard
                                    </span>
                                ),
                            }}
                            active={location.pathname === '/'}
                        />

                        {rootAdmin && (
                            <>
                                <SidebarLabel label='ADMIN' />
                                <SidebarLink
                                    link={{
                                        label: 'Admin Panel',
                                        href: '/admin',
                                        icon: (
                                            <span className='material-icons-round' style={{ fontSize: '20px' }}>
                                                admin_panel_settings
                                            </span>
                                        ),
                                        external: true,
                                    }}
                                />
                            </>
                        )}
                    </nav>

                    <UserFooter userName={userName} onLogout={onTriggerLogout} />
                </SidebarBody>
            </Sidebar>
        </>
    );
};

export const ServerNavigationBar = ({
    sidebarOpen,
    setSidebarOpen,
    showMobileHeader = true,
    routes,
    serverId,
}: NavigationBarProps & { routes: any[]; serverId: string }) => {
    const userName = useStoreState((state: ApplicationStore) => state.user.data?.username || 'User');
    const rootAdmin = useStoreState((state: ApplicationStore) => !!state.user.data?.rootAdmin);
    const adminServerId = ServerContext.useStoreState((state) => state.server.data?.internalId);
    const [isLoggingOut, setIsLoggingOut] = useState(false);
    const location = useLocation();

    const onTriggerLogout = () => {
        setIsLoggingOut(true);
        http.post('/auth/logout').finally(() => {
            // @ts-expect-error this is valid
            window.location = '/';
        });
    };

    // Helper to map route paths to icons (using Material Icons Round)
    const getIconForRoute = (name: string) => {
        const lower = name.toLowerCase();
        if (lower.includes('console')) return 'terminal';
        if (lower.includes('file')) return 'folder_open';
        if (lower.includes('database')) return 'dns';
        if (lower.includes('schedule')) return 'calendar_today';
        if (lower.includes('user')) return 'group';
        if (lower.includes('backup')) return 'backup';
        if (lower.includes('network')) return 'hub';
        if (lower.includes('startup')) return 'bolt';
        if (lower.includes('setting')) return 'settings';
        if (lower.includes('activity')) return 'show_chart';
        return 'circle';
    };

    // Helper to match the URL exactly
    const matchUrl = (path: string) => {
        const currentPath = location.pathname;
        const targetPath = `/server/${serverId}${path.replace('/*', '')}`;
        if (path === '/') {
            return currentPath === `/server/${serverId}`;
        }
        return currentPath.startsWith(targetPath);
    };

    const visibleRoutes = routes.filter((r) => !!r.name);

    return (
        <>
            <style>{`
                @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap');
                @import url('https://fonts.googleapis.com/icon?family=Material+Icons+Round');
                .sidebar-desktop-shell {
                    position: relative;
                }
                .sidebar-desktop-shell::after {
                    content: '';
                    position: absolute;
                    top: 10px;
                    bottom: 10px;
                    right: -1px;
                    width: 2px;
                    border-radius: 999px;
                    background: #1f2937;
                    box-shadow: 0 0 0 rgba(0, 0, 0, 0);
                    pointer-events: none;
                }
                .sidebar-desktop-shell::before {
                    content: '';
                    position: absolute;
                    top: 10px;
                    right: -4px;
                    width: 8px;
                    height: 42px;
                    border-radius: 999px;
                    background: radial-gradient(
                        ellipse at center,
                        rgba(220, 255, 162, 0.98) 0%,
                        rgba(163, 255, 18, 0.92) 35%,
                        rgba(163, 255, 18, 0.28) 60%,
                        rgba(163, 255, 18, 0) 100%
                    );
                    animation: sidebar-neon-flow 2.6s linear infinite;
                    filter: drop-shadow(0 0 10px rgba(163, 255, 18, 0.85));
                    pointer-events: none;
                }
                @keyframes sidebar-neon-flow {
                    from {
                        transform: translateY(0);
                    }
                    to {
                        transform: translateY(calc(100vh - 62px));
                    }
                }
                .sidebar-link:hover {
                    color: #a3ff12 !important;
                    background-color: rgba(163, 255, 18, 0.08) !important;
                    border-color: rgba(163, 255, 18, 0.25) !important;
                }
            `}</style>
            <SpinnerOverlay visible={isLoggingOut} />
            <Sidebar open={sidebarOpen} setOpen={setSidebarOpen}>
                <SidebarBody showMobileHeader={showMobileHeader}>
                    <SidebarLogo />

                    {/* Nav */}
                    <nav style={{ flex: 1, overflowY: 'auto', padding: '0 12px' }}>
                        <SidebarLabel label='SERVER' />
                        {visibleRoutes.map((route) => (
                            <SidebarLink
                                key={route.path}
                                link={{
                                    label: route.name,
                                    href: `/server/${serverId}${route.path.replace('/*', '')}`,
                                    icon: (
                                        <span className='material-icons-round' style={{ fontSize: '20px' }}>
                                            {getIconForRoute(route.name)}
                                        </span>
                                    ),
                                }}
                                active={matchUrl(route.path)}
                            />
                        ))}
                        {rootAdmin && !!adminServerId && (
                            <>
                                <SidebarLabel label='ADMIN' />
                                <SidebarLink
                                    link={{
                                        label: 'Admin Server',
                                        href: `/admin/servers/view/${adminServerId}`,
                                        icon: (
                                            <span className='material-icons-round' style={{ fontSize: '20px' }}>
                                                admin_panel_settings
                                            </span>
                                        ),
                                        external: true,
                                    }}
                                />
                            </>
                        )}
                    </nav>
                    <div style={{ padding: '0 12px 8px' }}>
                        <SidebarLink
                            link={{
                                label: 'Back to Dashboard',
                                href: '/',
                                icon: (
                                    <span className='material-icons-round' style={{ fontSize: '20px' }}>
                                        dashboard
                                    </span>
                                ),
                            }}
                            active={location.pathname === '/'}
                        />
                    </div>

                    <UserFooter userName={userName} onLogout={onTriggerLogout} />
                </SidebarBody>
            </Sidebar>
        </>
    );
};
