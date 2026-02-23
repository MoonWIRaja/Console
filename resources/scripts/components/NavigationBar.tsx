import * as React from 'react';
import { useState } from 'react';
import { useLocation, Link } from 'react-router-dom';
import { useStoreState } from 'easy-peasy';
import { ApplicationStore } from '@/state';
import http from '@/api/http';
import SpinnerOverlay from '@/components/elements/SpinnerOverlay';
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
                animate={{
                    fontSize: animate ? (open ? '24px' : '16px') : '24px',
                }}
                transition={{ duration: 0.2 }}
                style={{
                    fontWeight: 900,
                    color: '#ffffff',
                    lineHeight: 1.1,
                    letterSpacing: '-0.02em',
                }}
            >
                {expanded ? (
                    <>
                        <div style={{ fontSize: '24px', fontWeight: 900 }}>BusHen</div>
                        <div
                            style={{
                                fontSize: '10px',
                                fontWeight: 700,
                                letterSpacing: '0.1em',
                                color: '#9ca3af',
                                marginTop: '4px',
                            }}
                        >
                            CONSOLE
                        </div>
                    </>
                ) : (
                    <>
                        <div style={{ fontSize: '18px', fontWeight: 900 }}>B</div>
                        <div style={{ fontSize: '8px', fontWeight: 700, color: '#9ca3af' }}>C</div>
                    </>
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
                            backgroundColor: '#22c55e',
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                            color: '#ffffff',
                            fontWeight: 700,
                            fontSize: '16px',
                            boxShadow: '0 4px 6px -1px rgba(34, 197, 94, 0.2)',
                        }}
                    >
                        {userName.charAt(0).toUpperCase()}
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
    const rootAdmin = useStoreState((state: ApplicationStore) => state.user.data!.rootAdmin);
    const userName = useStoreState((state: ApplicationStore) => state.user.data!.username);
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
                .sidebar-link:hover {
                    color: #ffffff !important;
                    background-color: rgba(255,255,255,0.05) !important;
                }
            `}</style>
            <SpinnerOverlay visible={isLoggingOut} />
            <Sidebar open={sidebarOpen} setOpen={setSidebarOpen}>
                <SidebarBody showMobileHeader={showMobileHeader}>
                    <SidebarLogo />

                    {/* Nav */}
                    <nav style={{ flex: 1, overflowY: 'auto', padding: '0 12px' }}>
                        <SidebarLabel label='MAIN' />
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
    const userName = useStoreState((state: ApplicationStore) => state.user.data!.username);
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
                .sidebar-link:hover {
                    color: #ffffff !important;
                    background-color: rgba(255,255,255,0.05) !important;
                }
            `}</style>
            <SpinnerOverlay visible={isLoggingOut} />
            <Sidebar open={sidebarOpen} setOpen={setSidebarOpen}>
                <SidebarBody showMobileHeader={showMobileHeader}>
                    <SidebarLogo />

                    {/* Nav */}
                    <nav style={{ flex: 1, overflowY: 'auto', padding: '0 12px' }}>
                        <SidebarLabel label='MAIN' />
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
                    </nav>

                    <UserFooter userName={userName} onLogout={onTriggerLogout} />
                </SidebarBody>
            </Sidebar>
        </>
    );
};
