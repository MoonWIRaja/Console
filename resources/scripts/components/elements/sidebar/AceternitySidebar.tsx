import React, { createContext, useContext, useState, useCallback } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { Link } from 'react-router-dom';

// ============================================================
// Aceternity-style Sidebar (ported for React 16 + framer-motion v6)
// Collapsible on hover, mobile responsive, dark theme
// ============================================================

interface SidebarContextType {
    open: boolean;
    setOpen: React.Dispatch<React.SetStateAction<boolean>>;
    animate: boolean;
}

const SidebarContext = createContext<SidebarContextType>({
    open: false,
    setOpen: () => {},
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

export const SidebarProvider = ({ children, open: openProp, setOpen: setOpenProp, animate = true }: SidebarProviderProps) => {
    const [openState, setOpenState] = useState(false);
    const open = openProp !== undefined ? openProp : openState;
    const setOpen = setOpenProp !== undefined ? setOpenProp : setOpenState;

    return (
        <SidebarContext.Provider value={{ open, setOpen, animate }}>
            {children}
        </SidebarContext.Provider>
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
}

export const SidebarBody = ({ children, className }: SidebarBodyProps) => {
    return (
        <>
            <DesktopSidebar className={className}>{children}</DesktopSidebar>
            <MobileSidebar className={className}>{children}</MobileSidebar>
        </>
    );
};

// ---------- DesktopSidebar ----------
const DesktopSidebar = ({ children, className }: SidebarBodyProps) => {
    const { open, setOpen, animate: shouldAnimate } = useSidebar();

    return (
        <>
            <motion.div
                onMouseEnter={() => setOpen(true)}
                onMouseLeave={() => setOpen(false)}
                animate={{ width: shouldAnimate ? (open ? '250px' : '60px') : '250px' }}
                transition={{ duration: 0.3, ease: 'easeInOut' }}
                style={{
                    height: '100vh',
                    position: 'fixed',
                    top: 0,
                    left: 0,
                    zIndex: 1000,
                    backgroundColor: '#000000',
                    display: 'flex',
                    flexDirection: 'column',
                    borderRight: '1px solid #1a1a1a',
                    fontFamily: "'Space Mono', monospace",
                    overflow: 'hidden',
                }}
                className={`hidden lg:flex ${className || ''}`}
            >
                {children}
            </motion.div>
            {/* Spacer to push content right */}
            <motion.div
                animate={{ width: shouldAnimate ? (open ? '250px' : '60px') : '250px' }}
                transition={{ duration: 0.3, ease: 'easeInOut' }}
                className="hidden lg:block flex-shrink-0"
            />
        </>
    );
};

// ---------- MobileSidebar ----------
const MobileSidebar = ({ children, className }: SidebarBodyProps) => {
    const { open, setOpen } = useSidebar();

    return (
        <div className={`lg:hidden ${className || ''}`}>
            {/* Mobile Header Bar */}
            <div
                style={{
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'space-between',
                    backgroundColor: '#000000',
                    padding: '10px 16px',
                    fontFamily: "'Space Mono', monospace",
                    position: 'fixed',
                    top: 0,
                    left: 0,
                    right: 0,
                    zIndex: 1000,
                    borderBottom: '1px solid #1a1a1a',
                }}
            >
                <div style={{ color: '#ffffff', fontSize: '14px', fontWeight: 'bold' }}>
                    BurHan CONSOLE
                </div>
                <button
                    onClick={() => setOpen(!open)}
                    style={{
                        background: 'none',
                        border: 'none',
                        color: '#ffffff',
                        fontSize: '20px',
                        cursor: 'pointer',
                        padding: '4px',
                        lineHeight: 1,
                    }}
                >
                    {open ? '✕' : '☰'}
                </button>
            </div>

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
                                backgroundColor: '#000000',
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
                                width: '260px',
                                backgroundColor: '#000000',
                                zIndex: 1002,
                                display: 'flex',
                                flexDirection: 'column',
                                fontFamily: "'Space Mono', monospace",
                                borderRight: '1px solid #1a1a1a',
                            }}
                        >
                            <div style={{ display: 'flex', justifyContent: 'flex-end', padding: '12px 16px' }}>
                                <button
                                    onClick={() => setOpen(false)}
                                    style={{
                                        background: 'none',
                                        border: 'none',
                                        color: '#ffffff',
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
    const { open, animate: shouldAnimate } = useSidebar();

    const content = (
        <div
            style={{
                display: 'flex',
                alignItems: 'center',
                gap: open ? '10px' : '0px',
                padding: '10px 18px',
                textDecoration: 'none',
                color: active ? '#ffffff' : '#6b7280',
                backgroundColor: active ? '#111111' : 'transparent',
                borderLeft: active ? '2px solid #ffffff' : '2px solid transparent',
                transition: 'all 0.15s',
                cursor: 'pointer',
                overflow: 'hidden',
                whiteSpace: 'nowrap',
            }}
            className={`sidebar-link ${className || ''}`}
        >
            <div style={{ flexShrink: 0, width: '20px', display: 'flex', justifyContent: 'center' }}>
                {link.icon}
            </div>
            <motion.span
                animate={{
                    display: shouldAnimate ? (open ? 'inline-block' : 'none') : 'inline-block',
                    opacity: shouldAnimate ? (open ? 1 : 0) : 1,
                }}
                transition={{ duration: 0.2 }}
                style={{
                    fontSize: '11px',
                    fontWeight: 'bold',
                    letterSpacing: '0.05em',
                    textTransform: 'uppercase',
                    whiteSpace: 'nowrap',
                }}
            >
                {link.label}
            </motion.span>
        </div>
    );

    if (link.onClick) {
        return <div onClick={link.onClick}>{content}</div>;
    }

    if (link.external) {
        return <a href={link.href} rel="noreferrer" style={{ textDecoration: 'none' }}>{content}</a>;
    }

    return <Link to={link.href} style={{ textDecoration: 'none' }}>{content}</Link>;
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
                padding: '12px 18px 6px',
                fontSize: '9px',
                fontWeight: 'bold',
                letterSpacing: '0.1em',
                color: '#4b5563',
                textTransform: 'uppercase',
            }}
        >
            {label}
        </motion.div>
    );
};
