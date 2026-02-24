import React from 'react';
import { GlowCard } from '@/components/ui/spotlight-card';

export function Default() {
    return (
        <div className='flex h-screen w-screen flex-row items-center justify-center gap-10 bg-black'>
            <GlowCard glowColor='green'>
                <div className='h-full w-full rounded-[13px] bg-black' />
            </GlowCard>
            <GlowCard glowColor='green'>
                <div className='h-full w-full rounded-[13px] bg-black' />
            </GlowCard>
            <GlowCard glowColor='green'>
                <div className='h-full w-full rounded-[13px] bg-black' />
            </GlowCard>
        </div>
    );
}
