import React from 'react';
import FlashMessageRender from '@/components/FlashMessageRender';

export default () => (
    <div className={'min-h-screen bg-[color:var(--background)] px-6 py-8 font-mono text-[color:var(--foreground)] md:px-10'}>
        <FlashMessageRender byKey={'billing'} />

        <div className={'mx-auto max-w-4xl'}>
            <div className={'mb-8 max-w-2xl'}>
                <h1 className={'text-3xl font-black tracking-tight text-[#f8f6ef]'}>Billing</h1>
                <p className={'mt-3 text-sm text-[color:var(--muted-foreground)]'}>
                    This section is temporarily unavailable while the billing system is being reworked.
                </p>
            </div>

            <section className={'rounded-2xl border border-[color:var(--border)] bg-[color:var(--card)] p-8 shadow-[0_0_0_1px_rgba(var(--primary-rgb),0.04)]'}>
                <div className={'mx-auto flex max-w-2xl flex-col items-center text-center'}>
                    <div className={'flex h-20 w-20 items-center justify-center rounded-full border border-[color:var(--border)] bg-[color:var(--accent)] text-[color:var(--primary)] shadow-[0_0_24px_rgba(var(--primary-rgb),0.18)]'}>
                        <span className={'material-icons-round text-4xl'}>schedule</span>
                    </div>
                    <h2 className={'mt-6 text-3xl font-black tracking-tight text-[#f8f6ef]'}>Coming Soon</h2>
                    <p className={'mt-4 max-w-xl text-sm leading-7 text-[color:var(--muted-foreground)]'}>
                        Billing has been paused for now. Ordering servers, pricing, and node configuration will return after the new flow is ready.
                    </p>
                </div>
            </section>
        </div>
    </div>
);
