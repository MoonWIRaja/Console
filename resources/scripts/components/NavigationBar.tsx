import * as React from 'react';
import { useEffect, useMemo, useState } from 'react';
import { useLocation, Link } from 'react-router-dom';
import { useStoreState } from 'easy-peasy';
import { ApplicationStore } from '@/state';
import http from '@/api/http';
import Avatar from '@/components/Avatar';
import SpinnerOverlay from '@/components/elements/SpinnerOverlay';
import { ServerContext } from '@/state/server';
import { Sidebar, SidebarBody, SidebarLink, useSidebar } from '@/components/elements/sidebar/AceternitySidebar';
import { motion } from 'framer-motion';
import useSiteBranding from '@/hooks/useSiteBranding';

interface NavigationBarProps {
    sidebarOpen?: boolean;
    setSidebarOpen?: React.Dispatch<React.SetStateAction<boolean>>;
    showMobileHeader?: boolean;
    showSidebarLogo?: boolean;
    topOffset?: number;
}

interface SidebarSectionLinkItem {
    label: string;
    href: string;
    icon: React.ReactNode;
    active?: boolean;
    external?: boolean;
    onClick?: () => void;
}

interface SidebarSectionProps {
    label: string;
    items: SidebarSectionLinkItem[];
    defaultExpanded?: boolean;
}

// ---------- Logo ----------
const SidebarLogo = () => {
    const { open, animate } = useSidebar();
    const { logo, name } = useSiteBranding();
    const expanded = animate ? open : true;
    return (
        <div
            style={{
                padding: '16px 14px 12px',
                overflow: 'visible',
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
                    width: '100%',
                    maxWidth: '100%',
                    minWidth: 0,
                    borderRadius: '18px',
                    overflow: 'visible',
                    justifyContent: expanded ? 'flex-start' : 'center',
                    padding: expanded ? '10px 14px' : '10px',
                }}
            >
                <img
                    src={logo}
                    alt={'System Logo'}
                    style={{
                        width: expanded ? '54px' : '45px',
                        height: expanded ? '54px' : '45px',
                        objectFit: 'contain',
                        filter: 'brightness(1.12)',
                        flexShrink: 0,
                        display: 'block',
                        transform: expanded ? 'none' : 'scale(1.6)',
                        transformOrigin: 'center',
                    }}
                />
                {expanded && (
                    <div
                        style={{
                            flex: 1,
                            minWidth: 0,
                            maxWidth: '100%',
                            whiteSpace: 'nowrap',
                        }}
                    >
                        <span
                            style={{
                                fontSize: '14px',
                                fontWeight: 900,
                                color: 'var(--foreground)',
                                lineHeight: 1,
                                letterSpacing: '0.05em',
                                textTransform: 'uppercase',
                                textShadow: '0 0 10px rgba(var(--primary-rgb), 0.18)',
                                overflow: 'hidden',
                                textOverflow: 'ellipsis',
                            }}
                        >
                            {name}
                        </span>
                    </div>
                )}
            </motion.div>
        </div>
    );
};

const SidebarSection = ({ label, items, defaultExpanded = false }: SidebarSectionProps) => {
    const { open, animate } = useSidebar();
    const expanded = animate ? open : true;
    const hasActiveItem = items.some((item) => item.active);
    const [isExpanded, setIsExpanded] = useState(defaultExpanded || hasActiveItem);

    useEffect(() => {
        if (hasActiveItem) {
            setIsExpanded(true);
        }
    }, [hasActiveItem]);

    if (!items.length) {
        return null;
    }

    return (
        <div
            style={{
                marginBottom: expanded ? '14px' : '10px',
            }}
        >
            {expanded ? (
                <button
                    type='button'
                    onClick={() => setIsExpanded((value) => !value)}
                    style={{
                        width: '100%',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'space-between',
                        gap: '10px',
                        marginBottom: '8px',
                        padding: '8px 10px 6px',
                        border: 'none',
                        background: 'transparent',
                        color: 'rgba(248, 246, 239, 0.62)',
                        cursor: 'pointer',
                        textAlign: 'left',
                    }}
                >
                    <span
                        style={{
                            fontSize: '0.67rem',
                            fontWeight: 900,
                            letterSpacing: '0.18em',
                            textTransform: 'uppercase',
                            whiteSpace: 'nowrap',
                            overflow: 'hidden',
                            textOverflow: 'ellipsis',
                        }}
                    >
                        {label}
                    </span>
                    <span
                        className='material-icons-round'
                        style={{
                            fontSize: '18px',
                            color: 'rgba(248, 246, 239, 0.54)',
                            transform: isExpanded ? 'rotate(0deg)' : 'rotate(180deg)',
                            transition: 'transform 0.2s ease',
                            flexShrink: 0,
                        }}
                    >
                        expand_less
                    </span>
                </button>
            ) : (
                <div
                    aria-hidden='true'
                    style={{
                        width: '100%',
                        height: '14px',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                    }}
                >
                    <span
                        style={{
                            width: '22px',
                            height: '1px',
                            borderRadius: '999px',
                            background: 'rgba(255, 255, 255, 0.12)',
                        }}
                    />
                </div>
            )}

            {(expanded ? isExpanded : true) && (
                <div>
                    {items.map((item) => (
                        <SidebarLink
                            key={`${label}-${item.label}-${item.href}`}
                            link={{
                                label: item.label,
                                href: item.href,
                                icon: item.icon,
                                external: item.external,
                                onClick: item.onClick,
                            }}
                            active={item.active}
                        />
                    ))}
                </div>
            )}
        </div>
    );
};

// ---------- UserFooter ----------
const UserFooter = ({ userName, onLogout }: { userName: string; onLogout: () => void }) => {
    const { open, setOpen, animate, mode } = useSidebar();
    const expanded = animate ? open : true;
    const [menuOpen, setMenuOpen] = useState(false);
    const footerRef = React.useRef<HTMLDivElement>(null);
    const closeSidebarOnMobile = () => {
        if (typeof window !== 'undefined' && window.matchMedia('(max-width: 1023px)').matches) {
            setOpen(false);
        }
    };

    useEffect(() => {
        if (!expanded && mode !== 'locked-closed') {
            setMenuOpen(false);
        }
    }, [expanded, mode]);

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

    return (
        <div
            ref={footerRef}
            style={{
                borderTop: '1px solid rgba(245, 231, 198, 0.12)',
                padding: '14px 12px 12px',
                background: 'transparent',
                position: 'relative',
            }}
        >
            <button
                type='button'
                onClick={() => {
                    if (!expanded && mode !== 'locked-closed') {
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
                    borderRadius: '12px',
                    border: 'none',
                    background: 'transparent',
                }}
                className='transition-colors'
            >
                <div
                    style={{
                        width: '40px',
                        height: '40px',
                        borderRadius: '999px',
                        overflow: 'hidden',
                        flexShrink: 0,
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        background: 'transparent',
                        boxShadow: 'none',
                    }}
                >
                    <Avatar.User size={40} variant={'beam'} />
                </div>
                {expanded && (
                    <motion.div
                        initial={{ opacity: 0 }}
                        animate={{ opacity: 1 }}
                        transition={{ duration: 0.2 }}
                        style={{
                            fontSize: '14px',
                            fontWeight: 700,
                            color: '#F5E7C6',
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
                        <span
                            className='material-icons-round'
                            style={{ fontSize: '18px', color: 'rgba(248, 246, 239, 0.56)' }}
                        >
                            {menuOpen ? 'expand_less' : 'expand_more'}
                        </span>
                    </motion.div>
                )}
            </button>

            {menuOpen && (
                <motion.div
                    initial={{ opacity: 0, y: 8 }}
                    animate={{ opacity: 1, y: 0 }}
                    transition={{ duration: 0.2 }}
                    style={{
                        position: 'absolute',
                        left: expanded ? '12px' : '8px',
                        right: expanded ? '12px' : 'auto',
                        bottom: 'calc(100% + 8px)',
                        border: '1px solid rgba(245, 231, 198, 0.12)',
                        background: '#2D2D2D',
                        borderRadius: '16px',
                        padding: '10px',
                        width: expanded ? 'auto' : '240px',
                        display: 'flex',
                        flexDirection: 'column',
                        gap: '10px',
                        boxShadow: '0 22px 46px rgba(0, 0, 0, 0.32), inset 0 1px 0 rgba(255, 255, 255, 0.04)',
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
                                color: '#F5E7C6',
                                background: 'transparent',
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
                                        border: '1px solid rgba(245, 231, 198, 0.12)',
                                        background: 'rgba(245, 231, 198, 0.06)',
                                        color: '#F5E7C6',
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

                    <button
                        onClick={onLogout}
                        className='group flex cursor-pointer items-center justify-between gap-2 rounded-[14px] p-3 transition-colors hover:bg-[color:var(--accent)]'
                        style={{
                            width: '100%',
                            color: '#A0A0A0',
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
                                    border: '1px solid rgba(245, 231, 198, 0.12)',
                                    background: 'rgba(245, 231, 198, 0.06)',
                                    color: '#F5E7C6',
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
export default ({
    sidebarOpen,
    setSidebarOpen,
    showMobileHeader = true,
    showSidebarLogo = true,
    topOffset = 0,
}: NavigationBarProps) => {
    const rootAdmin = useStoreState((state: ApplicationStore) => !!state.user.data?.rootAdmin);
    const userName = useStoreState((state: ApplicationStore) => state.user.data?.username || 'User');
    const [isLoggingOut, setIsLoggingOut] = useState(false);
    const location = useLocation();
    const searchParams = useMemo(() => new URLSearchParams(location.search), [location.search]);

    const createIcon = (icon: string) => (
        <span className='material-icons-round' style={{ fontSize: '22px' }}>
            {icon}
        </span>
    );

    const billingSection = location.pathname === '/billing' ? searchParams.get('section') || 'plan' : null;
    const isSupportRoute = location.pathname.startsWith('/tickets') || location.pathname.startsWith('/billing/tickets');
    const supportSection = isSupportRoute
        ? searchParams.get('view') ||
          (location.pathname.startsWith('/tickets/') ||
          location.pathname.startsWith('/billing/tickets/') ||
          searchParams.has('ticket')
              ? 'chat'
              : 'tickets')
        : null;

    const myServerItems: SidebarSectionLinkItem[] = [
        {
            label: 'Dashboard',
            href: '/',
            icon: createIcon('dashboard'),
            active: location.pathname === '/',
        },
    ];

    const myBillingItems: SidebarSectionLinkItem[] = [
        {
            label: 'Plan',
            href: '/billing?section=plan',
            icon: createIcon('inventory_2'),
            active: billingSection === 'plan',
        },
        {
            label: 'My Subscriptions',
            href: '/billing?section=subscriptions',
            icon: createIcon('subscriptions'),
            active: billingSection === 'subscriptions',
        },
        {
            label: 'Invoices',
            href: '/billing?section=invoices',
            icon: createIcon('receipt_long'),
            active: billingSection === 'invoices',
        },
        {
            label: 'Receipts',
            href: '/billing?section=receipts',
            icon: createIcon('fact_check'),
            active: billingSection === 'receipts',
        },
        {
            label: 'My Billing Orders',
            href: '/billing?section=orders',
            icon: createIcon('shopping_bag'),
            active: billingSection === 'orders',
        },
    ];

    const mySupportItems: SidebarSectionLinkItem[] = [
        {
            label: 'Tickets',
            href: '/tickets?view=tickets',
            icon: createIcon('confirmation_number'),
            active: supportSection === 'tickets',
        },
        {
            label: 'Chat',
            href: '/tickets?view=chat',
            icon: createIcon('forum'),
            active: supportSection === 'chat',
        },
    ];

    const adminItems: SidebarSectionLinkItem[] = rootAdmin
        ? [
              {
                  label: 'Admin Panel',
                  href: '/admin',
                  icon: createIcon('admin_panel_settings'),
                  external: true,
                  active: location.pathname.startsWith('/admin'),
              },
          ]
        : [];

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
                    background: linear-gradient(
                        180deg,
                        rgba(255, 255, 255, 0.28) 0%,
                        rgba(255, 255, 255, 0.08) 46%,
                        rgba(255, 255, 255, 0.25) 100%
                    );
                    box-shadow: 0 0 14px rgba(var(--primary-rgb), 0.11);
                    pointer-events: none;
                }
                .sidebar-desktop-shell::before {
                    content: '';
                    position: absolute;
                    top: 10px;
                    right: -5px;
                    width: 10px;
                    height: 54px;
                    border-radius: 999px;
                    background: radial-gradient(
                        ellipse at center,
                        rgba(var(--primary-rgb), 1) 0%,
                        rgba(var(--primary-rgb), 0.92) 34%,
                        rgba(var(--primary-rgb), 0.35) 60%,
                        rgba(var(--primary-rgb), 0) 100%
                    );
                    animation: sidebar-neon-flow 3.2s linear infinite;
                    filter: drop-shadow(0 0 12px rgba(var(--primary-rgb), 0.9));
                    pointer-events: none;
                }
                @keyframes sidebar-neon-flow {
                    from {
                        transform: translateY(0);
                    }
                    to {
                        transform: translateY(calc(100vh - 76px));
                    }
                }
                .sidebar-link:hover {
                    color: #eff7dc !important;
                    background: linear-gradient(
                        90deg,
                        rgba(var(--primary-rgb), 0.22),
                        rgba(var(--primary-rgb), 0.08)
                    ) !important;
                    border-color: rgba(var(--primary-rgb), 0.3) !important;
                }
                .sidebar-scroll-region {
                    scrollbar-width: none;
                    -ms-overflow-style: none;
                }
                .sidebar-scroll-region::-webkit-scrollbar {
                    display: none;
                    width: 0;
                    height: 0;
                }
            `}</style>
            <SpinnerOverlay visible={isLoggingOut} />
            <Sidebar open={sidebarOpen} setOpen={setSidebarOpen}>
                <SidebarBody showMobileHeader={showMobileHeader} topOffset={topOffset}>
                    {showSidebarLogo ? <SidebarLogo /> : null}

                    {/* Nav */}
                    <nav
                        className='sidebar-scroll-region'
                        style={{ flex: 1, overflowY: 'auto', overflowX: 'hidden', padding: '0 12px' }}
                    >
                        <SidebarSection label='My Server' items={myServerItems} defaultExpanded />
                        <SidebarSection label='My Billing' items={myBillingItems} defaultExpanded />
                        <SidebarSection label='My Support' items={mySupportItems} defaultExpanded />
                        {adminItems.length > 0 && <SidebarSection label='Admin' items={adminItems} />}
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
    showSidebarLogo = true,
    topOffset = 0,
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
    const createIcon = (icon: string) => (
        <span className='material-icons-round' style={{ fontSize: '22px' }}>
            {icon}
        </span>
    );

    const serverRouteItems: SidebarSectionLinkItem[] = visibleRoutes.map((route) => ({
        label: route.name,
        href: `/server/${serverId}${route.path.replace('/*', '')}`,
        icon: createIcon(getIconForRoute(route.name)),
        active: matchUrl(route.path),
    }));

    const getSectionItems = (labels: string[]): SidebarSectionLinkItem[] =>
        labels
            .map((label) => serverRouteItems.find((item) => item.label === label))
            .filter((item): item is SidebarSectionLinkItem => !!item);

    const overviewItems = getSectionItems(['Console']);
    const configurationItems = getSectionItems(['Schedules', 'Network', 'Startup', 'Settings']);
    const managementItems = getSectionItems(['Files', 'Databases', 'Backups']);
    const accessAndLogsItems = getSectionItems(['Users', 'Activity']);

    const adminItems: SidebarSectionLinkItem[] = rootAdmin
        ? [
              {
                  label: 'Admin Panel',
                  href: '/admin',
                  icon: createIcon('admin_panel_settings'),
                  external: true,
                  active: location.pathname.startsWith('/admin'),
              },
              ...(!adminServerId
                  ? []
                  : [
                        {
                            label: 'Admin Server',
                            href: `/admin/servers/view/${adminServerId}`,
                            icon: createIcon('tune'),
                            external: true,
                            active: false,
                        },
                    ]),
          ]
        : [];

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
                    background: linear-gradient(
                        180deg,
                        rgba(255, 255, 255, 0.28) 0%,
                        rgba(255, 255, 255, 0.08) 46%,
                        rgba(255, 255, 255, 0.25) 100%
                    );
                    box-shadow: 0 0 14px rgba(var(--primary-rgb), 0.11);
                    pointer-events: none;
                }
                .sidebar-desktop-shell::before {
                    content: '';
                    position: absolute;
                    top: 10px;
                    right: -5px;
                    width: 10px;
                    height: 54px;
                    border-radius: 999px;
                    background: radial-gradient(
                        ellipse at center,
                        rgba(var(--primary-rgb), 1) 0%,
                        rgba(var(--primary-rgb), 0.92) 34%,
                        rgba(var(--primary-rgb), 0.35) 60%,
                        rgba(var(--primary-rgb), 0) 100%
                    );
                    animation: sidebar-neon-flow 3.2s linear infinite;
                    filter: drop-shadow(0 0 12px rgba(var(--primary-rgb), 0.9));
                    pointer-events: none;
                }
                @keyframes sidebar-neon-flow {
                    from {
                        transform: translateY(0);
                    }
                    to {
                        transform: translateY(calc(100vh - 76px));
                    }
                }
                .sidebar-link:hover {
                    color: #eff7dc !important;
                    background: linear-gradient(
                        90deg,
                        rgba(var(--primary-rgb), 0.22),
                        rgba(var(--primary-rgb), 0.08)
                    ) !important;
                    border-color: rgba(var(--primary-rgb), 0.3) !important;
                }
                .sidebar-scroll-region {
                    scrollbar-width: none;
                    -ms-overflow-style: none;
                }
                .sidebar-scroll-region::-webkit-scrollbar {
                    display: none;
                    width: 0;
                    height: 0;
                }
            `}</style>
            <SpinnerOverlay visible={isLoggingOut} />
            <Sidebar open={sidebarOpen} setOpen={setSidebarOpen}>
                <SidebarBody showMobileHeader={showMobileHeader} topOffset={topOffset}>
                    {showSidebarLogo ? <SidebarLogo /> : null}

                    {/* Nav */}
                    <nav
                        className='sidebar-scroll-region'
                        style={{ flex: 1, overflowY: 'auto', overflowX: 'hidden', padding: '0 12px' }}
                    >
                        <SidebarSection label='Overview' items={overviewItems} defaultExpanded />
                        <SidebarSection label='Configuration' items={configurationItems} defaultExpanded />
                        <SidebarSection label='Management' items={managementItems} defaultExpanded />
                        <SidebarSection label='Access & Logs' items={accessAndLogsItems} defaultExpanded />
                        <SidebarSection label='Admin' items={adminItems} />
                    </nav>
                    <div style={{ padding: '0 12px 8px' }}>
                        <SidebarLink
                            link={{
                                label: 'Back to Dashboard',
                                href: '/',
                                icon: createIcon('dashboard'),
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
