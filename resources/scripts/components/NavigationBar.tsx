import * as React from 'react';
import { useState } from 'react';
import { useLocation, Link } from 'react-router-dom';
import { useStoreState } from 'easy-peasy';
import { ApplicationStore } from '@/state';
import http from '@/api/http';
import SpinnerOverlay from '@/components/elements/SpinnerOverlay';
import Avatar from '@/components/Avatar';
import {
    Sidebar,
    SidebarBody,
    SidebarLink,
    SidebarLabel,
    useSidebar,
} from '@/components/elements/sidebar/AceternitySidebar';
import { motion } from 'framer-motion';

// ---------- Logo ----------
const SidebarLogo = () => {
    const { open, animate } = useSidebar();
    return (
        <div style={{
            padding: '20px 18px 16px',
            borderBottom: '1px solid #1a1a1a',
            overflow: 'hidden',
            whiteSpace: 'nowrap',
        }}>
            <motion.div
                animate={{
                    fontSize: animate ? (open ? '16px' : '13px') : '16px',
                }}
                transition={{ duration: 0.2 }}
                style={{
                    fontWeight: 'bold',
                    color: '#ffffff',
                    lineHeight: 1.2,
                    letterSpacing: '-0.02em',
                }}
            >
                {open ? (
                    <>BurHan<br />CONSOLE</>
                ) : (
                    <>B<br />C</>
                )}
            </motion.div>
        </div>
    );
};

// ---------- UserFooter ----------
const UserFooter = ({ userName, onLogout }: { userName: string; onLogout: () => void }) => {
    const { open } = useSidebar();
    return (
        <div style={{ borderTop: '1px solid #1a1a1a', padding: '12px 14px' }}>
            <Link to="/account" style={{ textDecoration: 'none' }}>
                <div style={{
                    display: 'flex',
                    alignItems: 'center',
                    gap: '8px',
                    marginBottom: open ? '10px' : '0',
                    justifyContent: open ? 'flex-start' : 'center',
                    cursor: 'pointer',
                    padding: '4px',
                    borderRadius: '4px',
                }}
                    className="hover:bg-neutral-900 transition-colors"
                >
                    <div style={{
                        width: '24px',
                        height: '24px',
                        borderRadius: '0',
                        overflow: 'hidden',
                        flexShrink: 0,
                    }}>
                        <Avatar.User />
                    </div>
                    {open && (
                        <motion.div
                            initial={{ opacity: 0 }}
                            animate={{ opacity: 1 }}
                            transition={{ duration: 0.2 }}
                            style={{
                                fontSize: '10px',
                                fontWeight: 'bold',
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
            {open && (
                <motion.button
                    initial={{ opacity: 0 }}
                    animate={{ opacity: 1 }}
                    transition={{ duration: 0.2 }}
                    onClick={onLogout}
                    className="sidebar-link"
                    style={{
                        width: '100%',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        gap: '6px',
                        padding: '7px',
                        fontSize: '9px',
                        fontWeight: 'bold',
                        letterSpacing: '0.05em',
                        textTransform: 'uppercase',
                        color: '#6b7280',
                        backgroundColor: 'transparent',
                        border: '1px solid #272727',
                        cursor: 'pointer',
                        transition: 'all 0.15s',
                        fontFamily: "'Space Mono', monospace",
                    }}
                >
                    <span>↪</span>
                    <span>LOG OUT</span>
                </motion.button>
            )}
        </div>
    );
};

// ---------- NavigationBar (exported) ----------
export default () => {
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
                @import url('https://fonts.googleapis.com/css2?family=Space+Mono:ital,wght@0,400;0,700;1,400&display=swap');
                .sidebar-link:hover {
                    color: #ffffff !important;
                    background-color: #111111 !important;
                }
            `}</style>
            <SpinnerOverlay visible={isLoggingOut} />
            <Sidebar>
                <SidebarBody>
                    <SidebarLogo />

                    {/* Nav */}
                    <nav style={{ flex: 1, overflowY: 'auto', padding: '8px 0' }}>
                        <SidebarLabel label="MAIN" />
                        <SidebarLink
                            link={{
                                label: 'Dashboard',
                                href: '/',
                                icon: <span style={{ fontSize: '14px', lineHeight: 1 }}>⊞</span>,
                            }}
                            active={location.pathname === '/'}
                        />

                        {rootAdmin && (
                            <>
                                <SidebarLabel label="ADMIN" />
                                <SidebarLink
                                    link={{
                                        label: 'Admin Panel',
                                        href: '/admin',
                                        icon: <span style={{ fontSize: '14px', lineHeight: 1 }}>⚙</span>,
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
