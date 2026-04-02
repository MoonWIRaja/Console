import React, { createContext, useContext, useState, useCallback, useEffect } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { Link } from 'react-router-dom';
import useSiteBranding from '@/hooks/useSiteBranding';

// ============================================================
// Aceternity-style Sidebar (ported for React 16 + framer-motion v6)
// Desktop sidebar is controlled explicitly by the topbar toggle, mobile responsive, dark neon theme
// ============================================================

export type SidebarMode = 'auto' | 'locked-open' | 'locked-closed';
export const SIDEBAR_MODE_STORAGE_KEY = 'ui.sidebar.mode';
export const SIDEBAR_MODE_SYNC_EVENT = 'ui:sidebar-mode-sync';

const isSidebarMode = (value: string | null): value is SidebarMode =>
    value === 'locked-open' || value === 'locked-closed' || value === 'auto';

export const getStoredSidebarMode = (): SidebarMode => {
    if (typeof window === 'undefined') {
        return 'locked-open';
    }

    const storedMode = window.localStorage.getItem(SIDEBAR_MODE_STORAGE_KEY);
    if (storedMode === 'locked-open' || storedMode === 'locked-closed') {
        return storedMode;
    }

    if (storedMode === 'auto') {
        return 'locked-open';
    }

    return window.localStorage.getItem('ui.sidebar.locked') === 'true' ? 'locked-open' : 'locked-open';
};

export const syncSidebarMode = (mode: SidebarMode) => {
    if (typeof window === 'undefined') {
        return;
    }

    const normalizedMode: SidebarMode = mode === 'locked-closed' ? 'locked-closed' : 'locked-open';
    window.localStorage.setItem(SIDEBAR_MODE_STORAGE_KEY, normalizedMode);
    window.localStorage.setItem('ui.sidebar.locked', normalizedMode === 'locked-open' ? 'true' : 'false');
    window.dispatchEvent(new CustomEvent<SidebarMode>(SIDEBAR_MODE_SYNC_EVENT, { detail: normalizedMode }));
};

interface SidebarContextType {
    open: boolean;
    setOpen: React.Dispatch<React.SetStateAction<boolean>>;
    mode: SidebarMode;
    setMode: React.Dispatch<React.SetStateAction<SidebarMode>>;
    animate: boolean;
}

const SidebarContext = createContext<SidebarContextType>({
    open: true,
    setOpen: () => undefined,
    mode: 'locked-open',
    setMode: () => undefined,
    animate: true,
});

export const useSidebar = () => useContext(SidebarContext);

// ---------- SidebarProvider ----------
interface SidebarProviderProps {
    children: React.ReactNode;
    open?: boolean;
    setOpen?: React.Dispatch<React.SetStateAction<boolean>>;
    animate?: boolean;
}

export const SidebarProvider = ({
    children,
    open: openProp,
    setOpen: setOpenProp,
    animate = true,
}: SidebarProviderProps) => {
    const [mode, setMode] = useState<SidebarMode>(() => getStoredSidebarMode());
    const [openState, setOpenState] = useState(() => getStoredSidebarMode() !== 'locked-closed');
    const open = openProp !== undefined ? openProp : openState;
    const setOpen = setOpenProp !== undefined ? setOpenProp : setOpenState;

    useEffect(() => {
        if (typeof window === 'undefined') {
            return;
        }

        syncSidebarMode(mode);
    }, [mode]);

    useEffect(() => {
        if (typeof window === 'undefined') {
            return;
        }

        const handleSidebarModeSync = (event: Event) => {
            const nextMode = (event as CustomEvent<SidebarMode>).detail;
            if (isSidebarMode(nextMode)) {
                setMode(nextMode);
                return;
            }

            setMode(getStoredSidebarMode());
        };

        window.addEventListener(SIDEBAR_MODE_SYNC_EVENT, handleSidebarModeSync as EventListener);

        return () => {
            window.removeEventListener(SIDEBAR_MODE_SYNC_EVENT, handleSidebarModeSync as EventListener);
        };
    }, []);

    return (
        <SidebarContext.Provider value={{ open, setOpen, mode, setMode, animate }}>{children}</SidebarContext.Provider>
    );
};

// ---------- Sidebar ----------
interface SidebarProps {
    children: React.ReactNode;
    open?: boolean;
    setOpen?: React.Dispatch<React.SetStateAction<boolean>>;
    animate?: boolean;
}

export const Sidebar = ({ children, open, setOpen, animate = true }: SidebarProps) => {
    return (
        <SidebarProvider open={open} setOpen={setOpen} animate={animate}>
            {children}
        </SidebarProvider>
    );
};

// ---------- SidebarBody ----------
interface SidebarBodyProps {
    children: React.ReactNode;
    className?: string;
    showMobileHeader?: boolean;
    topOffset?: number;
}

// ---------- DesktopSidebar ----------
function DesktopSidebar({ children, className, topOffset = 0 }: SidebarBodyProps) {
    const { open, setOpen, mode, animate: shouldAnimate } = useSidebar();

    useEffect(() => {
        if (mode !== 'locked-closed' && !open) {
            setOpen(true);
        }
        if (mode === 'locked-closed' && open) {
            setOpen(false);
        }
    }, [mode, open, setOpen]);

    return (
        <>
            <motion.div
                animate={{ width: shouldAnimate ? (open ? '288px' : '72px') : '288px' }}
                transition={{ duration: 0.3, ease: 'easeInOut' }}
                style={{
                    height: `calc(100vh - ${topOffset}px)`,
                    position: 'fixed',
                    top: `${topOffset}px`,
                    left: 0,
                    zIndex: 20,
                    background: '#2D2D2D',
                    display: 'flex',
                    flexDirection: 'column',
                    borderRight: '1px solid rgba(245, 231, 198, 0.12)',
                    fontFamily: "var(--font-sans, 'Inter', sans-serif)",
                    overflow: 'visible',
                    flexShrink: 0,
                    boxShadow:
                        'inset 1px 0 0 rgba(255, 255, 255, 0.03), inset -1px 0 0 rgba(255, 255, 255, 0.02), 0 20px 48px rgba(0, 0, 0, 0.24)',
                }}
                className={`sidebar-desktop-shell ${className || ''}`}
            >
                {children}
            </motion.div>
            {/* Spacer to push content right */}
            <motion.div
                animate={{ width: shouldAnimate ? (open ? '288px' : '72px') : '288px' }}
                transition={{ duration: 0.3, ease: 'easeInOut' }}
                className='flex-shrink-0'
            />
        </>
    );
}

// ---------- MobileSidebar ----------
function MobileSidebar({ children, className, showMobileHeader = true, topOffset = 0 }: SidebarBodyProps) {
    const { open, setOpen } = useSidebar();
    const { name } = useSiteBranding();

    return (
        <div className={className || ''}>
            {showMobileHeader && (
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'space-between',
                        background: '#2D2D2D',
                        padding: '12px 16px',
                        fontFamily: "var(--font-sans, 'Inter', sans-serif)",
                        position: 'fixed',
                        top: 0,
                        left: 0,
                        right: 0,
                        zIndex: 1000,
                        borderBottom: '1px solid rgba(245, 231, 198, 0.12)',
                        boxShadow: '0 18px 40px rgba(0, 0, 0, 0.16)',
                    }}
                >
                    <div style={{ color: 'var(--foreground)', fontSize: '14px', fontWeight: 900 }}>{name}</div>
                    <button
                        onClick={() => setOpen(!open)}
                        style={{
                            background: 'none',
                            border: 'none',
                            color: 'var(--primary)',
                            fontSize: '20px',
                            cursor: 'pointer',
                            padding: '4px',
                            lineHeight: 1,
                        }}
                    >
                        {open ? '✕' : '☰'}
                    </button>
                </div>
            )}

            {/* Mobile Overlay + Drawer */}
            <AnimatePresence>
                {open && (
                    <>
                        <motion.div
                            initial={{ opacity: 0 }}
                            animate={{ opacity: 0.5 }}
                            exit={{ opacity: 0 }}
                            onClick={() => setOpen(false)}
                            style={{
                                position: 'fixed',
                                top: `${topOffset}px`,
                                right: 0,
                                bottom: 0,
                                left: 0,
                                backgroundColor: 'rgba(var(--background-rgb), 0.55)',
                                zIndex: 1001,
                            }}
                        />
                        <motion.div
                            initial={{ x: '-100%' }}
                            animate={{ x: 0 }}
                            exit={{ x: '-100%' }}
                            transition={{ duration: 0.3, ease: 'easeInOut' }}
                            style={{
                                position: 'fixed',
                                top: `${topOffset}px`,
                                left: 0,
                                height: `calc(100vh - ${topOffset}px)`,
                                width: '288px',
                                background: '#2D2D2D',
                                zIndex: 1002,
                                display: 'flex',
                                flexDirection: 'column',
                                fontFamily: "var(--font-sans, 'Inter', sans-serif)",
                                borderRight: '1px solid rgba(245, 231, 198, 0.12)',
                                boxShadow: '0 24px 52px rgba(0, 0, 0, 0.28)',
                            }}
                            className='sidebar-mobile-shell'
                        >
                            <div style={{ display: 'flex', justifyContent: 'flex-end', padding: '12px 16px' }}>
                                <button
                                    onClick={() => setOpen(false)}
                                    style={{
                                        background: 'none',
                                        border: 'none',
                                        color: 'var(--primary)',
                                        fontSize: '18px',
                                        cursor: 'pointer',
                                    }}
                                >
                                    ✕
                                </button>
                            </div>
                            {children}
                        </motion.div>
                    </>
                )}
            </AnimatePresence>
        </div>
    );
}

export const SidebarBody = ({ children, className, showMobileHeader = true, topOffset = 0 }: SidebarBodyProps) => {
    const [isMobile, setIsMobile] = useState(
        typeof window !== 'undefined' ? window.matchMedia('(max-width: 1023px)').matches : false
    );

    useEffect(() => {
        const mediaQuery = window.matchMedia('(max-width: 1023px)');
        const handleChange = (event: MediaQueryListEvent) => setIsMobile(event.matches);

        setIsMobile(mediaQuery.matches);
        mediaQuery.addEventListener('change', handleChange);

        return () => mediaQuery.removeEventListener('change', handleChange);
    }, []);

    return isMobile ? (
        <MobileSidebar className={className} showMobileHeader={showMobileHeader} topOffset={topOffset}>
            {children}
        </MobileSidebar>
    ) : (
        <DesktopSidebar className={className} topOffset={topOffset}>
            {children}
        </DesktopSidebar>
    );
};

// ---------- SidebarLink ----------
export interface LinkItem {
    label: string;
    href: string;
    icon: React.ReactNode;
    external?: boolean;
    onClick?: () => void;
}

interface SidebarLinkProps {
    link: LinkItem;
    active?: boolean;
    className?: string;
}

export const SidebarLink = ({ link, active, className }: SidebarLinkProps) => {
    const { open, setOpen, animate: shouldAnimate } = useSidebar();
    const expanded = shouldAnimate ? open : true;
    const [tooltipPosition, setTooltipPosition] = useState<{ top: number; left: number } | null>(null);

    const closeOnMobile = useCallback(() => {
        if (typeof window !== 'undefined' && window.matchMedia('(max-width: 1023px)').matches) {
            setOpen(false);
        }
    }, [setOpen]);

    const handleActivate = useCallback(() => {
        if (link.onClick) {
            link.onClick();
        }

        closeOnMobile();
    }, [closeOnMobile, link]);

    const showCollapsedTooltip = !expanded && !!tooltipPosition;
    const wrapperStyle: React.CSSProperties = {
        position: 'relative',
        display: 'block',
        width: '100%',
        margin: '0',
    };

    const handleMouseEnter = (event: React.MouseEvent<HTMLElement>) => {
        if (expanded) {
            return;
        }

        const rect = event.currentTarget.getBoundingClientRect();
        setTooltipPosition({
            top: rect.top + rect.height / 2,
            left: rect.right + 12,
        });
    };

    const handleMouseLeave = () => {
        setTooltipPosition(null);
    };

    const content = (
        <div
            style={{
                display: 'flex',
                alignItems: 'center',
                justifyContent: expanded ? 'flex-start' : 'center',
                gap: expanded ? '12px' : '0px',
                width: expanded ? '100%' : '48px',
                minWidth: expanded ? undefined : '48px',
                height: expanded ? 'auto' : '48px',
                padding: expanded ? '11px 12px' : '0',
                textDecoration: 'none',
                color: active ? '#eff7dc' : 'rgba(248, 246, 239, 0.78)',
                background: active
                    ? 'linear-gradient(100deg, rgba(var(--primary-rgb), 0.32), rgba(var(--primary-rgb), 0.12))'
                    : 'linear-gradient(180deg, rgba(255, 255, 255, 0.034), rgba(255, 255, 255, 0.015))',
                border: `1px solid ${active ? 'rgba(var(--primary-rgb), 0.34)' : 'rgba(255, 255, 255, 0.07)'}`,
                boxShadow: active
                    ? 'inset 0 1px 0 rgba(255, 255, 255, 0.12), 0 16px 24px rgba(var(--primary-rgb), 0.12), 0 0 20px rgba(var(--primary-rgb), 0.14)'
                    : 'inset 0 1px 0 rgba(255, 255, 255, 0.04)',
                borderRadius: expanded ? '14px' : '16px',
                margin: expanded ? '4px 0' : '6px auto',
                transition: 'all 0.2s ease',
                cursor: 'pointer',
                overflow: 'visible',
                whiteSpace: 'nowrap',
            }}
            className={`sidebar-link ${className || ''}`}
        >
            <div
                style={{
                    flexShrink: 0,
                    width: expanded ? '32px' : '38px',
                    height: expanded ? '32px' : '38px',
                    borderRadius: '999px',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    background: 'transparent',
                    color: active ? '#eff7dc' : 'rgba(248, 246, 239, 0.78)',
                    boxShadow: 'none',
                }}
            >
                {link.icon}
            </div>
            <motion.span
                animate={{
                    display: shouldAnimate ? (open ? 'inline-block' : 'none') : 'inline-block',
                    opacity: shouldAnimate ? (open ? 1 : 0) : 1,
                }}
                transition={{ duration: 0.2 }}
                style={{
                    fontSize: '0.8rem',
                    fontWeight: 800,
                    letterSpacing: '0.08em',
                    textTransform: 'uppercase',
                    whiteSpace: 'nowrap',
                }}
            >
                {link.label}
            </motion.span>
        </div>
    );

    const tooltip = showCollapsedTooltip ? (
        <span
            style={{
                position: 'fixed',
                left: `${tooltipPosition?.left || 0}px`,
                top: `${tooltipPosition?.top || 0}px`,
                transform: 'translateY(-50%)',
                padding: '8px 12px',
                borderRadius: '12px',
                border: '1px solid rgba(255, 255, 255, 0.08)',
                background:
                    'linear-gradient(180deg, rgba(255, 255, 255, 0.04), rgba(255, 255, 255, 0.02)), rgba(7, 10, 15, 0.98)',
                color: '#eff7dc',
                fontSize: '0.72rem',
                fontWeight: 800,
                letterSpacing: '0.08em',
                textTransform: 'uppercase',
                boxShadow: '0 18px 34px rgba(0, 0, 0, 0.38), 0 0 18px rgba(var(--primary-rgb), 0.12)',
                pointerEvents: 'none',
                zIndex: 80,
                whiteSpace: 'nowrap',
            }}
        >
            {link.label}
        </span>
    ) : null;

    if (link.onClick) {
        return (
            <div
                style={wrapperStyle}
                onClick={handleActivate}
                onMouseEnter={handleMouseEnter}
                onMouseLeave={handleMouseLeave}
                title={!expanded ? link.label : undefined}
            >
                {content}
                {tooltip}
            </div>
        );
    }

    if (link.external) {
        return (
            <a
                href={link.href}
                rel='noreferrer'
                style={{ ...wrapperStyle, textDecoration: 'none' }}
                onClick={handleActivate}
                onMouseEnter={handleMouseEnter}
                onMouseLeave={handleMouseLeave}
                title={!expanded ? link.label : undefined}
            >
                {content}
                {tooltip}
            </a>
        );
    }

    return (
        <Link
            to={link.href}
            style={{ ...wrapperStyle, textDecoration: 'none' }}
            onClick={handleActivate}
            onMouseEnter={handleMouseEnter}
            onMouseLeave={handleMouseLeave}
            title={!expanded ? link.label : undefined}
        >
            {content}
            {tooltip}
        </Link>
    );
};

// ---------- SidebarLabel ----------
interface SidebarLabelProps {
    label: string;
}

export const SidebarLabel = ({ label }: SidebarLabelProps) => {
    const { open, animate: shouldAnimate } = useSidebar();

    return (
        <motion.div
            animate={{
                display: shouldAnimate ? (open ? 'block' : 'none') : 'block',
                opacity: shouldAnimate ? (open ? 1 : 0) : 1,
            }}
            transition={{ duration: 0.2 }}
            style={{
                padding: '16px 14px 8px',
                fontSize: '0.68rem',
                fontWeight: 800,
                letterSpacing: '0.18em',
                color: 'rgba(248, 246, 239, 0.5)',
                textTransform: 'uppercase',
            }}
        >
            {label}
        </motion.div>
    );
};
