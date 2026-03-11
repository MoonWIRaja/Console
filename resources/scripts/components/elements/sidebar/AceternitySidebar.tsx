import React, { createContext, useContext, useState, useCallback, useEffect } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { Link } from 'react-router-dom';
import useSiteBranding from '@/hooks/useSiteBranding';

// ============================================================
// Aceternity-style Sidebar (ported for React 16 + framer-motion v6)
// Collapsible on hover, mobile responsive, dark neon theme
// ============================================================

export type SidebarMode = 'auto' | 'locked-open' | 'locked-closed';

interface SidebarContextType {
    open: boolean;
    setOpen: React.Dispatch<React.SetStateAction<boolean>>;
    mode: SidebarMode;
    setMode: React.Dispatch<React.SetStateAction<SidebarMode>>;
    animate: boolean;
}

const SidebarContext = createContext<SidebarContextType>({
    open: false,
    setOpen: () => undefined,
    mode: 'auto',
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
    const [openState, setOpenState] = useState(false);
    const [mode, setMode] = useState<SidebarMode>('auto');
    const open = openProp !== undefined ? openProp : openState;
    const setOpen = setOpenProp !== undefined ? setOpenProp : setOpenState;

    useEffect(() => {
        if (typeof window === 'undefined') {
            return;
        }

        const storedMode = window.localStorage.getItem('ui.sidebar.mode');
        if (storedMode === 'locked-open' || storedMode === 'locked-closed' || storedMode === 'auto') {
            setMode(storedMode);
            return;
        }

        const legacyLocked = window.localStorage.getItem('ui.sidebar.locked') === 'true';
        setMode(legacyLocked ? 'locked-open' : 'auto');
    }, []);

    useEffect(() => {
        if (typeof window === 'undefined') {
            return;
        }

        window.localStorage.setItem('ui.sidebar.mode', mode);
        window.localStorage.setItem('ui.sidebar.locked', mode === 'locked-open' ? 'true' : 'false');
    }, [mode]);

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
}

// ---------- DesktopSidebar ----------
function DesktopSidebar({ children, className }: SidebarBodyProps) {
    const { open, setOpen, mode, animate: shouldAnimate } = useSidebar();
    const isAuto = mode === 'auto';

    useEffect(() => {
        if (mode === 'locked-open' && !open) {
            setOpen(true);
        }
        if (mode === 'locked-closed' && open) {
            setOpen(false);
        }
    }, [mode, open, setOpen]);

    return (
        <>
            <motion.div
                onMouseEnter={() => isAuto && setOpen(true)}
                onMouseLeave={() => isAuto && setOpen(false)}
                animate={{ width: shouldAnimate ? (open ? '288px' : '72px') : '288px' }}
                transition={{ duration: 0.3, ease: 'easeInOut' }}
                style={{
                    height: '100vh',
                    position: 'fixed',
                    top: 0,
                    left: 0,
                    zIndex: 20,
                    background:
                        'radial-gradient(circle at 16% -2%, rgba(var(--primary-rgb), 0.18), transparent 36%), radial-gradient(circle at 106% 92%, rgba(84, 140, 255, 0.17), transparent 40%), linear-gradient(180deg, rgba(4, 7, 12, 0.98), rgba(1, 2, 5, 1))',
                    display: 'flex',
                    flexDirection: 'column',
                    borderRight: '1px solid rgba(255, 255, 255, 0.09)',
                    fontFamily: "var(--font-sans, 'Inter', sans-serif)",
                    overflow: 'visible',
                    flexShrink: 0,
                    boxShadow:
                        'inset 1px 0 0 rgba(255, 255, 255, 0.04), inset -1px 0 0 rgba(255, 255, 255, 0.03), 0 20px 48px rgba(0, 0, 0, 0.42)',
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
function MobileSidebar({ children, className, showMobileHeader = true }: SidebarBodyProps) {
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
                        background: 'linear-gradient(180deg, rgba(9, 12, 18, 0.97), rgba(4, 6, 10, 0.99))',
                        padding: '12px 16px',
                        fontFamily: "var(--font-sans, 'Inter', sans-serif)",
                        position: 'fixed',
                        top: 0,
                        left: 0,
                        right: 0,
                        zIndex: 1000,
                        borderBottom: '1px solid rgba(255, 255, 255, 0.09)',
                        boxShadow: '0 8px 20px rgba(0, 0, 0, 0.3)',
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
                                inset: 0,
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
                                top: 0,
                                left: 0,
                                height: '100vh',
                                width: '288px',
                                background:
                                    'radial-gradient(circle at 16% -2%, rgba(var(--primary-rgb), 0.18), transparent 36%), radial-gradient(circle at 106% 92%, rgba(84, 140, 255, 0.17), transparent 40%), linear-gradient(180deg, rgba(4, 7, 12, 0.98), rgba(1, 2, 5, 1))',
                                zIndex: 1002,
                                display: 'flex',
                                flexDirection: 'column',
                                fontFamily: "var(--font-sans, 'Inter', sans-serif)",
                                borderRight: '1px solid rgba(255, 255, 255, 0.09)',
                                boxShadow: '0 24px 52px rgba(0, 0, 0, 0.45)',
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

export const SidebarBody = ({ children, className, showMobileHeader = true }: SidebarBodyProps) => {
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
        <MobileSidebar className={className} showMobileHeader={showMobileHeader}>
            {children}
        </MobileSidebar>
    ) : (
        <DesktopSidebar className={className}>{children}</DesktopSidebar>
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

    const content = (
        <div
            style={{
                display: 'flex',
                alignItems: 'center',
                justifyContent: expanded ? 'flex-start' : 'center',
                gap: expanded ? '12px' : '0px',
                padding: expanded ? '11px 12px' : '10px',
                textDecoration: 'none',
                color: active ? '#eff7dc' : 'rgba(248, 246, 239, 0.78)',
                background: active
                    ? 'linear-gradient(100deg, rgba(var(--primary-rgb), 0.32), rgba(var(--primary-rgb), 0.12))'
                    : 'linear-gradient(180deg, rgba(255, 255, 255, 0.034), rgba(255, 255, 255, 0.015))',
                border: `1px solid ${active ? 'rgba(var(--primary-rgb), 0.34)' : 'rgba(255, 255, 255, 0.07)'}`,
                boxShadow: active
                    ? 'inset 0 1px 0 rgba(255, 255, 255, 0.12), 0 16px 24px rgba(var(--primary-rgb), 0.12), 0 0 20px rgba(var(--primary-rgb), 0.14)'
                    : 'inset 0 1px 0 rgba(255, 255, 255, 0.04)',
                borderRadius: '14px',
                margin: '4px 0',
                transition: 'all 0.2s ease',
                cursor: 'pointer',
                overflow: 'hidden',
                whiteSpace: 'nowrap',
            }}
            className={`sidebar-link ${className || ''}`}
        >
            <div
                style={{
                    flexShrink: 0,
                    width: expanded ? '28px' : '34px',
                    height: expanded ? '28px' : '34px',
                    borderRadius: expanded ? '10px' : '12px',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    background: active ? 'rgba(var(--primary-rgb), 0.2)' : 'rgba(255, 255, 255, 0.04)',
                    color: active ? '#eff7dc' : 'rgba(248, 246, 239, 0.78)',
                    boxShadow: active ? '0 0 16px rgba(var(--primary-rgb), 0.2)' : 'none',
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

    if (link.onClick) {
        return <div onClick={handleActivate}>{content}</div>;
    }

    if (link.external) {
        return (
            <a href={link.href} rel='noreferrer' style={{ textDecoration: 'none' }} onClick={handleActivate}>
                {content}
            </a>
        );
    }

    return (
        <Link to={link.href} style={{ textDecoration: 'none' }} onClick={handleActivate}>
            {content}
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
