import * as React from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

type ChatBubbleVariant = 'sent' | 'received';

interface ChatBubbleProps {
    variant?: ChatBubbleVariant;
    className?: string;
    children: React.ReactNode;
}

interface ChatBubbleMessageProps {
    variant?: ChatBubbleVariant;
    className?: string;
    children?: React.ReactNode;
}

interface ChatBubbleAvatarProps {
    src?: string | null;
    fallback?: string;
    className?: string;
}

interface ChatBubbleActionProps {
    icon?: React.ReactNode;
    onClick?: () => void;
    className?: string;
}

const getInitials = (value: string): string => {
    const normalized = value.trim();
    if (normalized === '') {
        return '?';
    }

    const tokens = normalized.split(/\s+/).filter(Boolean);
    if (tokens.length === 1) {
        return tokens[0].slice(0, 2).toUpperCase();
    }

    return `${tokens[0][0] || ''}${tokens[1][0] || ''}`.toUpperCase();
};

export function ChatBubble({ variant = 'received', className, children }: ChatBubbleProps) {
    return (
        <div className={cn('flex items-end gap-3', variant === 'sent' ? 'flex-row-reverse' : 'flex-row', className)}>
            {children}
        </div>
    );
}

export function ChatBubbleMessage({ variant = 'received', className, children }: ChatBubbleMessageProps) {
    return (
        <div
            className={cn(
                'max-w-[min(82%,44rem)] rounded-[22px] border px-4 py-3 shadow-[0_18px_30px_rgba(0,0,0,0.16)]',
                variant === 'sent'
                    ? 'rounded-br-[10px] border-[rgba(var(--primary-rgb),0.26)] bg-[linear-gradient(135deg,rgba(var(--primary-rgb),0.32),rgba(var(--primary-rgb),0.08))] text-[#f8f6ef]'
                    : 'rounded-bl-[10px] border-white/10 bg-[rgba(255,255,255,0.035)] text-[color:var(--muted-foreground)]',
                className
            )}
        >
            {children}
        </div>
    );
}

export function ChatBubbleAvatar({ src, fallback = '?', className }: ChatBubbleAvatarProps) {
    return (
        <div
            className={cn(
                'relative flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-white/10 bg-[rgba(255,255,255,0.04)] text-[11px] font-black uppercase tracking-[0.16em] text-[#f8f6ef]',
                className
            )}
        >
            {src ? (
                <img src={src} alt={'Message avatar'} className={'h-full w-full object-cover'} />
            ) : (
                <span>{getInitials(fallback)}</span>
            )}
        </div>
    );
}

export function ChatBubbleAction({ icon, onClick, className }: ChatBubbleActionProps) {
    return (
        <Button
            type={'button'}
            variant={'ghost'}
            size={'icon'}
            className={cn('h-7 w-7 rounded-full text-[color:var(--muted-foreground)]', className)}
            onClick={onClick}
        >
            {icon}
        </Button>
    );
}

export function ChatBubbleActionWrapper({ className, children }: { className?: string; children: React.ReactNode }) {
    return <div className={cn('mt-2 flex items-center gap-1', className)}>{children}</div>;
}
