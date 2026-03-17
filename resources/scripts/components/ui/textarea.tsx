import * as React from 'react';
import { cn } from '@/lib/utils';

export type TextareaProps = React.TextareaHTMLAttributes<HTMLTextAreaElement>;

const Textarea = React.forwardRef<HTMLTextAreaElement, TextareaProps>(({ className, ...props }, ref) => (
    <textarea
        ref={ref}
        className={cn(
            'flex min-h-[96px] w-full rounded-[20px] border border-[color:var(--border)] bg-[rgba(3,8,14,0.84)]',
            'px-4 py-3 text-sm text-[#f8f6ef] shadow-none transition-all duration-150 outline-none',
            'placeholder:text-[color:var(--muted-foreground)] focus:border-[rgba(var(--primary-rgb),0.4)]',
            'focus:bg-[rgba(5,11,18,0.94)] focus:ring-2 focus:ring-[rgba(var(--primary-rgb),0.16)]',
            'disabled:cursor-not-allowed disabled:opacity-60',
            className
        )}
        {...props}
    />
));

Textarea.displayName = 'Textarea';

export { Textarea };
