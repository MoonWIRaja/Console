import React from 'react';
import { Link } from 'react-router-dom';
import AuthTopbar from '@/components/auth/AuthTopbar';
import AuthBackdropGame from '@/components/auth/AuthBackdropGame';
import AuthBrandPanel from '@/components/auth/AuthBrandPanel';
import { GlowCard } from '@/components/ui/spotlight-card';
import { burhanAuthThemeStyles } from '@/components/auth/authTheme';

export default () => {
    return (
        <div className='burhan-auth-stage burhan-auth-stage-game fixed inset-0 z-50 flex h-[100dvh] w-full overflow-hidden text-[color:var(--foreground)]'>
            <style>{burhanAuthThemeStyles}</style>
            <AuthTopbar />
            <AuthBackdropGame className='burhan-auth-backdrop-full' />
            <div className='burhan-auth-rail burhan-auth-rail-floating h-full w-full overflow-y-auto px-6 pb-5 pt-24 sm:px-10 sm:pb-6 sm:pt-24 md:px-14 lg:w-full lg:overflow-y-auto lg:px-8 lg:pb-4 lg:pt-24 xl:px-10'>
                <div className='burhan-auth-shell burhan-auth-shell-floating mx-auto flex h-full min-h-0 w-full max-w-[32rem] flex-col justify-center py-0'>
                    <GlowCard
                        glowColor='green'
                        customSize
                        orbit
                        orbitDurationMs={2800}
                        className='burhan-auth-glow w-full max-h-full'
                    >
                        <div className='burhan-auth-card'>
                            <AuthBrandPanel />
                            <div className='rounded-[1.35rem] border border-[rgba(255,255,255,0.06)] bg-[rgba(255,255,255,0.02)] px-4 py-4'>
                                <h2 className='text-[0.72rem] font-extrabold uppercase tracking-[0.18em] text-[rgba(248,246,239,0.72)]'>
                                    Link Not Supported
                                </h2>
                                <p className='mt-2 text-sm leading-7 text-[rgba(151,160,171,0.94)]'>
                                    Password reset now uses a 6-digit PIN sent to email. Request a new PIN below.
                                </p>
                                <Link
                                    to={'/auth/password'}
                                    className='burhan-auth-submit mt-4 flex min-h-[4.35rem] w-full items-center justify-center rounded-[1.25rem] border border-[rgba(var(--primary-rgb),0.34)] px-4 text-[0.82rem] font-black uppercase tracking-[0.16em] text-[#0a0d10]'
                                >
                                    Go to Reset PIN
                                </Link>
                            </div>
                        </div>
                    </GlowCard>
                    <div className='mt-5 pb-6 text-center'>
                        <Link className='burhan-auth-meta-link' to={'/auth/login'}>
                            Return to Login
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    );
};
