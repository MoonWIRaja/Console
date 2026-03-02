import React, { createContext, useContext, useState, useCallback, useEffect } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { Link } from 'react-router-dom';

// ============================================================
// Aceternity-style Sidebar (ported for React 16 + framer-motion v6)
// Collapsible on hover, mobile responsive, dark neon theme
// ============================================================

interface SidebarContextType {
    open: boolean;
    setOpen: React.Dispatch<React.SetStateAction<boolean>>;
    animate: boolean;
}

const SidebarContext = createContext<SidebarContextType>({
    open: false,
    setOpen: () => undefined,
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
    const open = openProp !== undefined ? openProp : openState;
    const setOpen = setOpenProp !== undefined ? setOpenProp : setOpenState;

    return <SidebarContext.Provider value={{ open, setOpen, animate }}>{children}</SidebarContext.Provider>;
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
    const { open, setOpen, animate: shouldAnimate } = useSidebar();

    return (
        <>
            <motion.div
                onMouseEnter={() => setOpen(true)}
                onMouseLeave={() => setOpen(false)}
                animate={{ width: shouldAnimate ? (open ? '256px' : '72px') : '256px' }}
                transition={{ duration: 0.3, ease: 'easeInOut' }}
                style={{
                    height: '100vh',
                    position: 'fixed',
                    top: 0,
                    left: 0,
                    zIndex: 20,
                    background: 'var(--card)',
                    display: 'flex',
                    flexDirection: 'column',
                    borderRight: '1px solid rgba(var(--primary-rgb), 0.22)',
                    fontFamily: "'Inter', sans-serif",
                    overflow: 'hidden',
                    flexShrink: 0,
                }}
                className={`sidebar-desktop-shell ${className || ''}`}
            >
                {children}
            </motion.div>
            {/* Spacer to push content right */}
            <motion.div
                animate={{ width: shouldAnimate ? (open ? '256px' : '72px') : '256px' }}
                transition={{ duration: 0.3, ease: 'easeInOut' }}
                className='flex-shrink-0'
            />
        </>
    );
}

// ---------- MobileSidebar ----------
function MobileSidebar({ children, className, showMobileHeader = true }: SidebarBodyProps) {
    const { open, setOpen } = useSidebar();

    return (
        <div className={className || ''}>
            {showMobileHeader && (
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'space-between',
                        background: 'var(--card)',
                        padding: '12px 16px',
                        fontFamily: "'Inter', sans-serif",
                        position: 'fixed',
                        top: 0,
                        left: 0,
                        right: 0,
                        zIndex: 1000,
                        borderBottom: '1px solid rgba(var(--primary-rgb), 0.22)',
                    }}
                >
                    <div style={{ color: 'var(--foreground)', fontSize: '14px', fontWeight: 900 }}>BurHan Console</div>
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
                                width: '256px',
                                background: 'var(--card)',
                                zIndex: 1002,
                                display: 'flex',
                                flexDirection: 'column',
                                fontFamily: "'Inter', sans-serif",
                                borderRight: '1px solid rgba(var(--primary-rgb), 0.22)',
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
                gap: expanded ? '12px' : '0px',
                padding: '10px 12px',
                textDecoration: 'none',
                color: active ? 'var(--primary)' : 'var(--foreground)',
                backgroundColor: active ? 'var(--primary-glow-soft)' : 'transparent',
                border: `1px solid ${active ? 'rgba(var(--primary-rgb), 0.35)' : 'transparent'}`,
                boxShadow: active ? '0 0 12px rgba(var(--primary-rgb), 0.2)' : 'none',
                borderRadius: '8px',
                margin: '2px 0',
                transition: 'all 0.15s',
                cursor: 'pointer',
                overflow: 'hidden',
                whiteSpace: 'nowrap',
            }}
            className={`sidebar-link ${className || ''}`}
        >
            <div style={{ flexShrink: 0, width: '20px', display: 'flex', justifyContent: 'center' }}>{link.icon}</div>
            <motion.span
                animate={{
                    display: shouldAnimate ? (open ? 'inline-block' : 'none') : 'inline-block',
                    opacity: shouldAnimate ? (open ? 1 : 0) : 1,
                }}
                transition={{ duration: 0.2 }}
                style={{
                    fontSize: '14px',
                    fontWeight: 500,
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
                padding: '16px 12px 8px',
                fontSize: '12px',
                fontWeight: 500,
                letterSpacing: '0.05em',
                color: 'var(--muted-foreground)',
                textTransform: 'uppercase',
            }}
        >
            {label}
        </motion.div>
    );
};
