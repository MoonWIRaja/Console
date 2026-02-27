import React from 'react';

interface InteractiveHoverButtonProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
    text?: string;
    variant?: 'neutral' | 'success' | 'warning' | 'danger';
}

const InteractiveHoverButton = React.forwardRef<HTMLButtonElement, InteractiveHoverButtonProps>(
    ({ text = 'Button', className = '', variant = 'neutral', disabled, ...props }, ref) => {
        const variantClass =
            variant === 'success'
                ? 'border-[#22c55e] bg-[#05210f] text-[#d1fae5] hover:border-[#4ade80] hover:text-[#ecfdf5] focus-visible:ring-[#22c55e]/60'
                : variant === 'warning'
                ? 'border-[#f59e0b] bg-[#241402] text-[#fef3c7] hover:border-[#fbbf24] hover:text-[#fffbeb] focus-visible:ring-[#f59e0b]/60'
                : variant === 'danger'
                ? 'border-[#ef4444] bg-[#2a0707] text-[#fee2e2] hover:border-[#f87171] hover:text-[#fff1f2] focus-visible:ring-[#ef4444]/60'
                : 'border-[#1f2a14] bg-[#000000] text-[#f8f6ef] hover:border-[#a3ff12] hover:text-[#d9ff93] focus-visible:ring-[#a3ff12]/60';

        const glowClass =
            variant === 'success'
                ? 'bg-[radial-gradient(circle_at_20%_50%,rgba(34,197,94,0.28),transparent_60%)]'
                : variant === 'warning'
                ? 'bg-[radial-gradient(circle_at_20%_50%,rgba(245,158,11,0.30),transparent_60%)]'
                : variant === 'danger'
                ? 'bg-[radial-gradient(circle_at_20%_50%,rgba(239,68,68,0.30),transparent_60%)]'
                : 'bg-[radial-gradient(circle_at_20%_50%,rgba(163,255,18,0.28),transparent_60%)]';

        return (
            <button
                ref={ref}
                className={[
                    'group relative inline-flex h-11 min-w-[10.75rem] cursor-pointer items-center justify-center overflow-hidden rounded-full',
                    'px-6 text-sm font-semibold uppercase tracking-wide transition-all duration-300 focus:outline-none focus-visible:ring-2',
                    'disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:brightness-100',
                    variantClass,
                    className,
                ].join(' ')}
                disabled={disabled}
                {...props}
            >
                <span className='relative z-20 inline-flex items-center gap-2'>
                    <span className='transition-transform duration-300 group-hover:-translate-x-0.5'>{text}</span>
                    <span
                        aria-hidden='true'
                        className='-translate-x-1 opacity-0 transition-all duration-300 group-hover:translate-x-0 group-hover:opacity-100'
                    >
                        &rarr;
                    </span>
                </span>
                <span
                    className={`pointer-events-none absolute inset-0 z-10 rounded-full opacity-0 transition-opacity duration-300 group-hover:opacity-100 ${glowClass}`}
                />
            </button>
        );
    }
);

InteractiveHoverButton.displayName = 'InteractiveHoverButton';

export { InteractiveHoverButton };
