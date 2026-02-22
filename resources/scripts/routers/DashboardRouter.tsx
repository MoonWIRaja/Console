import React from 'react';
import { Route, Switch } from 'react-router-dom';
import NavigationBar from '@/components/NavigationBar';
import DashboardContainer from '@/components/dashboard/DashboardContainer';
import AccountProfileContainer from '@/components/dashboard/AccountProfileContainer';
import { NotFound } from '@/components/elements/ScreenBlock';
import TransitionRouter from '@/TransitionRouter';
import { useLocation } from 'react-router';
import Spinner from '@/components/elements/Spinner';

export default () => {
    const location = useLocation();

    return (
        <div style={{ display: 'flex', minHeight: '100vh', backgroundColor: '#ffffff' }}>
            <style>{`
                @import url('https://fonts.googleapis.com/css2?family=Space+Mono:ital,wght@0,400;0,700;1,400&display=swap');
            `}</style>
            <NavigationBar />
            <div style={{
                flex: 1,
                backgroundColor: '#ffffff',
                minHeight: '100vh',
                fontFamily: "'Space Mono', monospace",
            }}>
                {/* Mobile top spacing */}
                <div className="lg:hidden" style={{ height: '48px' }} />
                <TransitionRouter>
                    <React.Suspense fallback={<Spinner centered />}>
                        <Switch location={location}>
                            <Route path={'/'} exact>
                                <DashboardContainer />
                            </Route>
                            <Route path={'/account'} exact>
                                <AccountProfileContainer />
                            </Route>
                            {/* Redirect old routes to /account to prevent broken links in case users saved them */}
                            <Route path={'/account/api'} exact>
                                <div style={{ padding: '40px', textAlign: 'center' }}>Redirecting to Account... <script>window.location.href = '/account';</script></div>
                            </Route>
                            <Route path={'/account/ssh'} exact>
                                <div style={{ padding: '40px', textAlign: 'center' }}>Redirecting to Account... <script>window.location.href = '/account';</script></div>
                            </Route>
                            <Route path={'/account/activity'} exact>
                                <div style={{ padding: '40px', textAlign: 'center' }}>Redirecting to Account... <script>window.location.href = '/account';</script></div>
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
