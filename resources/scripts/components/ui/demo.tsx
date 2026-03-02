import React from 'react';
import { GlowCard } from '@/components/ui/spotlight-card';

export function Default() {
    return (
        <div className='flex h-screen w-screen flex-row items-center justify-center gap-10 bg-[color:var(--card)]'>
            <GlowCard glowColor='green'>
                <div className='h-full w-full rounded-[13px] bg-[color:var(--card)]' />
            </GlowCard>
            <GlowCard glowColor='green'>
                <div className='h-full w-full rounded-[13px] bg-[color:var(--card)]' />
            </GlowCard>
            <GlowCard glowColor='green'>
                <div className='h-full w-full rounded-[13px] bg-[color:var(--card)]' />
            </GlowCard>
        </div>
    );
}
