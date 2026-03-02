import React from 'react';
import { ArrowRight } from 'lucide-react';

interface InteractiveHoverButtonProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
    text?: string;
    variant?: 'neutral' | 'success' | 'warning' | 'danger';
}

const InteractiveHoverButton = React.forwardRef<HTMLButtonElement, InteractiveHoverButtonProps>(
    ({ text = 'Button', className = '', variant = 'neutral', disabled, ...props }, ref) => {
        const frameClass =
            variant === 'success'
                ? 'border-[#22c55e] bg-[color:var(--card)] text-[#d1fae5] hover:border-[#4ade80] focus-visible:ring-[#22c55e]/65'
                : variant === 'warning'
                ? 'border-[#f59e0b] bg-[color:var(--card)] text-[#fef3c7] hover:border-[#fbbf24] focus-visible:ring-[#f59e0b]/65'
                : variant === 'danger'
                ? 'border-[#ef4444] bg-[color:var(--card)] text-[#fee2e2] hover:border-[#f87171] focus-visible:ring-[#ef4444]/65'
                : 'border-[color:var(--border)] bg-[color:var(--card)] text-[#f8f6ef] hover:border-[color:var(--primary)] focus-visible:ring-[#a3ff12]/65';

        const fillClass =
            variant === 'success'
                ? 'bg-[#22c55e] group-hover:shadow-[0_0_28px_rgba(34,197,94,0.62)]'
                : variant === 'warning'
                ? 'bg-[#f59e0b] group-hover:shadow-[0_0_28px_rgba(245,158,11,0.62)]'
                : variant === 'danger'
                ? 'bg-[#ef4444] group-hover:shadow-[0_0_28px_rgba(239,68,68,0.62)]'
                : 'bg-[color:var(--primary)] group-hover:shadow-[0_0_28px_rgba(var(--primary-rgb), 0.62)]';

        const hoverTextClass =
            variant === 'success'
                ? 'text-[#021108]'
                : variant === 'warning'
                ? 'text-[#130a00]'
                : variant === 'danger'
                ? 'text-[#190303]'
                : 'text-[#0a1202]';

        return (
            <button
                ref={ref}
                className={[
                    'group relative inline-flex h-11 min-w-[10.75rem] cursor-pointer items-center justify-center overflow-hidden rounded-full border',
                    'px-6 text-sm font-semibold uppercase tracking-wide transition-all duration-300 focus:outline-none focus-visible:ring-2',
                    'disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:border-current disabled:hover:text-inherit',
                    frameClass,
                    className,
                ].join(' ')}
                disabled={disabled}
                {...props}
            >
                <span className='relative z-20 inline-block translate-x-0 transition-all duration-300 group-hover:-translate-x-4 group-hover:opacity-0'>
                    {text}
                </span>
                <span
                    className={[
                        'pointer-events-none absolute inset-0 z-20 flex translate-x-8 items-center justify-center gap-2 opacity-0',
                        'transition-all duration-300 group-hover:translate-x-0 group-hover:opacity-100',
                        hoverTextClass,
                    ].join(' ')}
                >
                    <span>{text}</span>
                    <ArrowRight size={14} />
                </span>
                <span
                    className={[
                        'pointer-events-none absolute left-3 top-1/2 z-10 h-1.5 w-1.5 -translate-y-1/2 rounded-full opacity-95',
                        'transition-all duration-300 group-hover:left-0 group-hover:top-0 group-hover:h-full',
                        'group-hover:w-full group-hover:translate-y-0 group-hover:rounded-none group-hover:opacity-100',
                        fillClass,
                    ].join(' ')}
                />
            </button>
        );
    }
);

InteractiveHoverButton.displayName = 'InteractiveHoverButton';

export { InteractiveHoverButton };
