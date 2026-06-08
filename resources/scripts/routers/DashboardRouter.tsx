import React, { useEffect, useState } from 'react';
import { Redirect, Route, Switch } from 'react-router-dom';
import NavigationBar from '@/components/NavigationBar';
import DashboardContainer from '@/components/dashboard/DashboardContainer';
import DashboardTopbar, { DASHBOARD_TOPBAR_HEIGHT } from '@/components/dashboard/DashboardTopbar';
import AccountProfileContainer from '@/components/dashboard/AccountProfileContainer';
import BillingContainer from '@/components/billing/BillingContainer';
import BillingTicketsContainer from '@/components/billing/BillingTicketsContainer';
import { NotFound } from '@/components/elements/ScreenBlock';
import TransitionRouter from '@/TransitionRouter';
import { useLocation } from 'react-router';
import PageLoadingSkeleton from '@/components/elements/PageLoadingSkeleton';

const BILLING_TOPBAR_TITLES = {
    plan: 'Plan',
    subscriptions: 'My Subscriptions',
    invoices: 'Invoices',
    receipts: 'Receipts',
    orders: 'My Billing Orders',
} as const;

const dashboardPaperBackground = '#D6D2C7';

export default () => {
    const location = useLocation();
    const [mobileSidebarOpen, setMobileSidebarOpen] = useState(false);
    const [searchQuery, setSearchQuery] = useState('');
    const [isMobileViewport, setIsMobileViewport] = useState(
        typeof window !== 'undefined' ? window.innerWidth < 1024 : false
    );
    const isDashboardHome = location.pathname === '/';
    const isBillingPage = location.pathname === '/billing';
    const isAccountPage = location.pathname === '/account';
    const billingSectionParam = new URLSearchParams(location.search).get('section');
    const billingTopbarTitle =
        BILLING_TOPBAR_TITLES[billingSectionParam as keyof typeof BILLING_TOPBAR_TITLES] || BILLING_TOPBAR_TITLES.plan;

    useEffect(() => {
        const onResize = () => setIsMobileViewport(window.innerWidth < 1024);
        onResize();
        window.addEventListener('resize', onResize);

        return () => window.removeEventListener('resize', onResize);
    }, []);

    useEffect(() => {
        setMobileSidebarOpen(false);
    }, [location.pathname, isMobileViewport]);

    useEffect(() => {
        if (!isDashboardHome && searchQuery) {
            setSearchQuery('');
        }
    }, [isDashboardHome, searchQuery]);

    useEffect(() => {
        const html = document.documentElement;
        const body = document.body;
        const app = document.getElementById('app');
        const previousHtmlOverflow = html.style.overflow;
        const previousBodyOverflow = body.style.overflow;
        const previousHtmlHeight = html.style.height;
        const previousBodyHeight = body.style.height;
        const previousHtmlWidth = html.style.width;
        const previousBodyWidth = body.style.width;
        const previousBodyPosition = body.style.position;
        const previousBodyInset = body.style.inset;
        const previousBodyMargin = body.style.margin;
        const previousAppHeight = app?.style.height;
        const previousAppWidth = app?.style.width;
        const previousAppOverflow = app?.style.overflow;

        html.style.overflow = 'hidden';
        body.style.overflow = 'hidden';
        html.style.height = '100%';
        body.style.height = '100%';
        html.style.width = '100%';
        body.style.width = '100%';
        body.style.position = 'fixed';
        body.style.inset = '0';
        body.style.margin = '0';

        if (app) {
            app.style.height = '100%';
            app.style.width = '100%';
            app.style.overflow = 'hidden';
        }

        return () => {
            html.style.overflow = previousHtmlOverflow;
            body.style.overflow = previousBodyOverflow;
            html.style.height = previousHtmlHeight;
            body.style.height = previousBodyHeight;
            html.style.width = previousHtmlWidth;
            body.style.width = previousBodyWidth;
            body.style.position = previousBodyPosition;
            body.style.inset = previousBodyInset;
            body.style.margin = previousBodyMargin;

            if (app) {
                app.style.height = previousAppHeight || '';
                app.style.width = previousAppWidth || '';
                app.style.overflow = previousAppOverflow || '';
            }
        };
    }, []);

    return (
        <div
            className='fixed inset-0 z-0 overflow-hidden'
            style={{
                height: '100dvh',
                background: isAccountPage ? dashboardPaperBackground : undefined,
                backgroundColor: isAccountPage ? undefined : 'var(--background)',
            }}
        >
            <DashboardTopbar
                isMobileViewport={isMobileViewport}
                onOpenSidebar={isMobileViewport ? () => setMobileSidebarOpen(true) : undefined}
                onCloseSidebar={isMobileViewport ? () => setMobileSidebarOpen(false) : undefined}
                isSidebarOpen={isMobileViewport ? mobileSidebarOpen : false}
                searchQuery={searchQuery}
                onSearchChange={setSearchQuery}
                showSearch={isDashboardHome}
                centerTitle={isBillingPage ? billingTopbarTitle : undefined}
            />
            <div
                style={{
                    position: 'absolute',
                    top: `${DASHBOARD_TOPBAR_HEIGHT}px`,
                    right: 0,
                    bottom: 0,
                    left: 0,
                    display: 'flex',
                    minHeight: 0,
                    overflow: 'hidden',
                }}
            >
                <NavigationBar
                    sidebarOpen={isMobileViewport ? mobileSidebarOpen : undefined}
                    setSidebarOpen={isMobileViewport ? setMobileSidebarOpen : undefined}
                    showMobileHeader={false}
                    showSidebarLogo={false}
                    topOffset={DASHBOARD_TOPBAR_HEIGHT}
                />
                <div
                    style={{
                        flex: 1,
                        display: 'flex',
                        flexDirection: 'column',
                        background: isAccountPage ? dashboardPaperBackground : undefined,
                        backgroundColor: isAccountPage ? undefined : 'var(--background)',
                        color: 'var(--foreground)',
                        minHeight: 0,
                        minWidth: 0,
                        overflow: isMobileViewport ? 'auto' : 'hidden',
                        fontFamily:
                            "var(--font-mono, 'Geist Mono', ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace)",
                    }}
                >
                    <TransitionRouter>
                        <React.Suspense
                            fallback={<PageLoadingSkeleton rows={7} showChrome={false} className='min-h-[65vh]' />}
                        >
                            <Switch location={location}>
                                <Route path={'/'} exact>
                                    <DashboardContainer searchQuery={searchQuery} />
                                </Route>
                                <Route path={'/account'} exact>
                                    <AccountProfileContainer />
                                </Route>
                                <Route path={'/billing'} exact>
                                    <BillingContainer />
                                </Route>
                                <Route path={'/tickets'} exact>
                                    <BillingTicketsContainer />
                                </Route>
                                <Route path={'/tickets/:ticketId'} exact>
                                    <BillingTicketsContainer />
                                </Route>
                                <Route path={'/billing/tickets'} exact>
                                    <BillingTicketsContainer />
                                </Route>
                                <Route path={'/billing/tickets/:ticketId'} exact>
                                    <BillingTicketsContainer />
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
        </div>
    );
};
