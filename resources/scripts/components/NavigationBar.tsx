import * as React from 'react';
import { useEffect, useMemo, useState } from 'react';
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
import Select, { TSelectData } from '@/components/ui/select';
import { applyThemePreset, DEFAULT_THEME_ID, THEME_PRESETS } from '@/components/ui/theme-presets';

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
                            color: 'var(--foreground)',
                            lineHeight: 1,
                            letterSpacing: '-0.02em',
                            textShadow: '0 0 10px rgba(var(--primary-rgb), 0.18)',
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
    const [menuOpen, setMenuOpen] = useState(false);
    const [themeId, setThemeId] = useState(DEFAULT_THEME_ID);
    const footerRef = React.useRef<HTMLDivElement>(null);
    const closeSidebarOnMobile = () => {
        if (typeof window !== 'undefined' && window.matchMedia('(max-width: 1023px)').matches) {
            setOpen(false);
        }
    };

    useEffect(() => {
        if (typeof window === 'undefined') return;
        setThemeId(window.localStorage.getItem('panel.theme.id') || DEFAULT_THEME_ID);
    }, []);

    useEffect(() => {
        if (!expanded) setMenuOpen(false);
    }, [expanded]);

    useEffect(() => {
        if (!menuOpen) return;

        const onOutside = (event: MouseEvent) => {
            if (!footerRef.current) return;
            if (event.target instanceof Node && !footerRef.current.contains(event.target)) {
                setMenuOpen(false);
            }
        };

        document.addEventListener('mousedown', onOutside);
        return () => document.removeEventListener('mousedown', onOutside);
    }, [menuOpen]);

    const themeOptions = useMemo<TSelectData[]>(
        () =>
            THEME_PRESETS.map((theme) => ({
                id: theme.id,
                label: theme.label,
                value: theme.id,
                description: 'Dark Mode',
                icon: <span className='material-icons-round text-base'>palette</span>,
            })),
        []
    );

    const setTheme = (nextThemeId: string) => {
        setThemeId(nextThemeId);
        applyThemePreset(nextThemeId, 'dark');
        if (typeof window !== 'undefined') {
            window.localStorage.setItem('panel.theme.id', nextThemeId);
            window.localStorage.setItem('panel.theme.mode', 'dark');
        }
    };

    return (
        <div
            ref={footerRef}
            style={{
                borderTop: '1px solid var(--border)',
                padding: '16px',
                backgroundColor: 'rgba(var(--card-rgb), 0.45)',
                position: 'relative',
            }}
        >
            <button
                type='button'
                onClick={() => {
                    if (!expanded) {
                        setOpen(true);
                        return;
                    }
                    setMenuOpen((value) => !value);
                }}
                style={{
                    width: '100%',
                    display: 'flex',
                    alignItems: 'center',
                    gap: '12px',
                    marginBottom: '0',
                    justifyContent: expanded ? 'flex-start' : 'center',
                    cursor: 'pointer',
                    padding: '4px',
                    borderRadius: '8px',
                    border: 'none',
                    background: 'transparent',
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
                        backgroundColor: 'var(--background)',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        border: '1px solid rgba(var(--primary-rgb), 0.35)',
                        boxShadow:
                            '0 0 0 1px rgba(var(--primary-rgb), 0.12), 0 6px 14px -6px rgba(var(--primary-rgb), 0.45)',
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
                            color: 'var(--foreground)',
                            overflow: 'hidden',
                            textOverflow: 'ellipsis',
                            whiteSpace: 'nowrap',
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'space-between',
                            width: '100%',
                        }}
                    >
                        <span>{userName}</span>
                        <span className='material-icons-round' style={{ fontSize: '18px', color: 'var(--muted-foreground)' }}>
                            {menuOpen ? 'expand_less' : 'expand_more'}
                        </span>
                    </motion.div>
                )}
            </button>

            {expanded && menuOpen && (
                <motion.div
                    initial={{ opacity: 0, y: 8 }}
                    animate={{ opacity: 1, y: 0 }}
                    transition={{ duration: 0.2 }}
                    style={{
                        position: 'absolute',
                        left: '12px',
                        right: '12px',
                        bottom: 'calc(100% + 8px)',
                        border: '1px solid var(--border)',
                        backgroundColor: 'var(--card)',
                        borderRadius: '12px',
                        padding: '10px',
                        display: 'flex',
                        flexDirection: 'column',
                        gap: '10px',
                        boxShadow: '0 14px 34px rgba(0, 0, 0, 0.45)',
                        zIndex: 50,
                    }}
                >
                    <Link
                        to='/account'
                        style={{ textDecoration: 'none' }}
                        onClick={() => {
                            setMenuOpen(false);
                            closeSidebarOnMobile();
                        }}
                    >
                        <div
                            className='group flex cursor-pointer items-center justify-between gap-2 rounded-[14px] p-3 transition-colors hover:bg-[color:var(--accent)]'
                            style={{
                                width: '100%',
                                color: 'var(--foreground)',
                            }}
                        >
                            <div style={{ display: 'flex', minWidth: 0, alignItems: 'center', gap: '10px' }}>
                                <div
                                    style={{
                                        display: 'flex',
                                        height: '34px',
                                        width: '34px',
                                        alignItems: 'center',
                                        justifyContent: 'center',
                                        borderRadius: '999px',
                                        border: '1px solid var(--border)',
                                        background: 'var(--background)',
                                        color: 'var(--primary)',
                                    }}
                                >
                                    <span className='material-icons-round' style={{ fontSize: '16px' }}>
                                        person
                                    </span>
                                </div>
                                <span
                                    style={{
                                        fontSize: '12px',
                                        fontWeight: 700,
                                        textTransform: 'uppercase',
                                        letterSpacing: '0.05em',
                                        whiteSpace: 'nowrap',
                                    }}
                                >
                                    Profile
                                </span>
                            </div>
                        </div>
                    </Link>

                    <Select title='Theme' data={themeOptions} defaultValue={themeId} onChange={setTheme} />

                    <button
                        onClick={onLogout}
                        className='group flex cursor-pointer items-center justify-between gap-2 rounded-[14px] p-3 transition-colors hover:bg-[color:var(--accent)]'
                        style={{
                            width: '100%',
                            color: 'var(--muted-foreground)',
                            cursor: 'pointer',
                            border: 'none',
                            background: 'transparent',
                        }}
                        type='button'
                    >
                        <div style={{ display: 'flex', minWidth: 0, alignItems: 'center', gap: '10px' }}>
                            <div
                                style={{
                                    display: 'flex',
                                    height: '34px',
                                    width: '34px',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    borderRadius: '999px',
                                    border: '1px solid var(--border)',
                                    background: 'var(--background)',
                                    color: 'var(--primary)',
                                }}
                            >
                                <span className='material-icons-round' style={{ fontSize: '16px' }}>
                                    logout
                                </span>
                            </div>
                            <span
                                style={{
                                    fontSize: '12px',
                                    fontWeight: 700,
                                    textTransform: 'uppercase',
                                    letterSpacing: '0.05em',
                                    whiteSpace: 'nowrap',
                                }}
                            >
                                Log out
                            </span>
                        </div>
                    </button>
                </motion.div>
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

    const onTriggerLogout = async () => {
        if (isLoggingOut) return;

        setIsLoggingOut(true);

        try {
            await http.get('/sanctum/csrf-cookie');

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

            await http.post(
                '/auth/logout',
                {},
                csrfToken
                    ? {
                          headers: {
                              'X-CSRF-TOKEN': csrfToken,
                          },
                      }
                    : undefined
            );
        } catch (error) {
            console.error('Failed to log out cleanly.', error);
        } finally {
            window.location.assign('/auth/login');
        }
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
                    background: var(--border);
                    box-shadow: 0 0 0 rgba(12, 12, 12, 0);
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
                        rgba(var(--primary-rgb), 0.98) 0%,
                        rgba(var(--primary-rgb), 0.9) 35%,
                        rgba(var(--primary-rgb), 0.32) 60%,
                        rgba(var(--primary-rgb), 0) 100%
                    );
                    animation: sidebar-neon-flow 2.6s linear infinite;
                    filter: drop-shadow(0 0 10px rgba(var(--primary-rgb), 0.85));
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
                    color: var(--primary) !important;
                    background-color: rgba(var(--primary-rgb), 0.08) !important;
                    border-color: rgba(var(--primary-rgb), 0.25) !important;
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

    const onTriggerLogout = async () => {
        if (isLoggingOut) return;

        setIsLoggingOut(true);

        try {
            await http.get('/sanctum/csrf-cookie');

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

            await http.post(
                '/auth/logout',
                {},
                csrfToken
                    ? {
                          headers: {
                              'X-CSRF-TOKEN': csrfToken,
                          },
                      }
                    : undefined
            );
        } catch (error) {
            console.error('Failed to log out cleanly.', error);
        } finally {
            window.location.assign('/auth/login');
        }
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
                    background: var(--border);
                    box-shadow: 0 0 0 rgba(12, 12, 12, 0);
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
                        rgba(var(--primary-rgb), 0.98) 0%,
                        rgba(var(--primary-rgb), 0.9) 35%,
                        rgba(var(--primary-rgb), 0.32) 60%,
                        rgba(var(--primary-rgb), 0) 100%
                    );
                    animation: sidebar-neon-flow 2.6s linear infinite;
                    filter: drop-shadow(0 0 10px rgba(var(--primary-rgb), 0.85));
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
                    color: var(--primary) !important;
                    background-color: rgba(var(--primary-rgb), 0.08) !important;
                    border-color: rgba(var(--primary-rgb), 0.25) !important;
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
