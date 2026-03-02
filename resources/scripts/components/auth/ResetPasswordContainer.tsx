import React from 'react';
import { Link } from 'react-router-dom';

export default () => {
    return (
        <div className='fixed inset-0 z-50 flex h-screen w-full overflow-hidden bg-[color:var(--card)] text-gray-100'>
            <div className='hidden h-full w-[70%] bg-[color:var(--card)] lg:block' />
            <div className='w-full overflow-y-auto bg-[color:var(--card)] px-8 sm:px-12 md:px-16 lg:w-[30%] lg:px-10 xl:px-12'>
                <div className='mx-auto flex h-full w-full max-w-md flex-col justify-center py-12'>
                    <h1 className='text-4xl font-bold leading-tight tracking-tight text-[#f8f6ef] [text-shadow:0_0_14px_rgba(248,246,239,0.32)]'>
                        BurHan Console
                    </h1>
                    <div className='mt-8 rounded-xl border border-[color:var(--border)] bg-[color:var(--card)] p-6'>
                        <h2 className='text-sm font-bold uppercase tracking-wider text-gray-200'>Link Not Supported</h2>
                        <p className='mt-3 text-sm text-neutral-300'>
                            Password reset now uses a 6-digit PIN sent to email. Please request a new PIN below.
                        </p>
                        <Link
                            to={'/auth/password'}
                            className='mt-6 inline-flex items-center justify-center rounded-lg bg-[color:var(--primary)] px-4 py-3 text-sm font-bold uppercase tracking-wide text-[color:var(--primary-foreground)] transition-all duration-300 hover:-translate-y-0.5 hover:shadow-[0_0_20px_rgba(var(--primary-rgb), 0.55)]'
                        >
                            Go to Reset PIN
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    );
};
