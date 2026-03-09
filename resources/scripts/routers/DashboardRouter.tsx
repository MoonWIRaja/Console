import React, { useEffect, useState } from 'react';
import { Redirect, Route, Switch } from 'react-router-dom';
import NavigationBar from '@/components/NavigationBar';
import DashboardContainer from '@/components/dashboard/DashboardContainer';
import AccountProfileContainer from '@/components/dashboard/AccountProfileContainer';
import BillingContainer from '@/components/billing/BillingContainer';
import { NotFound } from '@/components/elements/ScreenBlock';
import TransitionRouter from '@/TransitionRouter';
import { useLocation } from 'react-router';
import PageLoadingSkeleton from '@/components/elements/PageLoadingSkeleton';

export default () => {
    const location = useLocation();
    const [mobileSidebarOpen, setMobileSidebarOpen] = useState(false);
    const [isMobileViewport, setIsMobileViewport] = useState(
        typeof window !== 'undefined' ? window.innerWidth < 1024 : false
    );

    useEffect(() => {
        const onResize = () => setIsMobileViewport(window.innerWidth < 1024);
        onResize();
        window.addEventListener('resize', onResize);

        return () => window.removeEventListener('resize', onResize);
    }, []);

    useEffect(() => {
        setMobileSidebarOpen(false);
    }, [location.pathname, isMobileViewport]);

    return (
        <div style={{ display: 'flex', minHeight: '100vh', backgroundColor: 'var(--background)' }}>
            <NavigationBar
                sidebarOpen={isMobileViewport ? mobileSidebarOpen : undefined}
                setSidebarOpen={isMobileViewport ? setMobileSidebarOpen : undefined}
                showMobileHeader={false}
            />
            <div
                style={{
                    flex: 1,
                    backgroundColor: 'var(--background)',
                    color: 'var(--foreground)',
                    minHeight: '100vh',
                    fontFamily: "var(--font-mono, 'Space Mono', monospace)",
                }}
            >
                {isMobileViewport && (
                    <>
                        <div
                            style={{
                                height: '48px',
                                position: 'fixed',
                                top: 0,
                                left: 0,
                                right: 0,
                                zIndex: 1000,
                                backgroundColor: 'var(--card)',
                                borderBottom: '1px solid var(--border)',
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'space-between',
                                padding: '0 16px',
                                fontFamily: "'Space Mono', monospace",
                            }}
                        >
                            <div style={{ color: 'var(--foreground)', fontSize: '14px', fontWeight: 'bold' }}>
                                BurHan CONSOLE
                            </div>
                            <button
                                type='button'
                                onClick={() => setMobileSidebarOpen(true)}
                                style={{
                                    background: 'none',
                                    border: 'none',
                                    color: 'var(--primary)',
                                    fontSize: '20px',
                                    cursor: 'pointer',
                                    padding: '4px',
                                    lineHeight: 1,
                                }}
                                aria-label='Open sidebar menu'
                            >
                                ☰
                            </button>
                        </div>
                        <div style={{ height: '48px' }} />
                    </>
                )}
                <TransitionRouter>
                    <React.Suspense fallback={<PageLoadingSkeleton rows={7} showChrome={false} className='min-h-[65vh]' />}>
                        <Switch location={location}>
                            <Route path={'/'} exact>
                                <DashboardContainer />
                            </Route>
                            <Route path={'/account'} exact>
                                <AccountProfileContainer />
                            </Route>
                            <Route path={'/billing'} exact>
                                <BillingContainer />
                            </Route>
                            {/* Redirect old routes to /account to prevent broken links in case users saved them */}
                            <Route path={'/account/api'} exact>
                                <Redirect to={'/account'} />
                            </Route>
                            <Route path={'/account/ssh'} exact>
                                <Redirect to={'/account'} />
                            </Route>
                            <Route path={'/account/activity'} exact>
                                <Redirect to={'/account'} />
                            </Route>
                            <Route path={'*'}>
                                <NotFound />
                            </Route>
                        </Switch>
                    </React.Suspense>
                </TransitionRouter>
            </div>
        </div>
    );
};
