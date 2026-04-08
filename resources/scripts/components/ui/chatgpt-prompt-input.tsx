import * as React from 'react';
import { ArrowUp, Paperclip, Sparkles, X } from 'lucide-react';
import { cn } from '@/lib/utils';

export interface PromptBoxAttachment {
    id: string;
    name: string;
    meta?: string;
}

export interface PromptBoxProps extends React.TextareaHTMLAttributes<HTMLTextAreaElement> {
    attachments?: PromptBoxAttachment[];
    helperText?: string;
    onAttachClick?: () => void;
    onRemoveAttachment?: (id: string) => void;
    statusLabel?: string;
    submitDisabled?: boolean;
}

export const PromptBox = React.forwardRef<HTMLTextAreaElement, PromptBoxProps>(
    (
        {
            attachments = [],
            className,
            helperText = '',
            onAttachClick,
            onKeyDown,
            onRemoveAttachment,
            statusLabel = 'Discord Sync',
            submitDisabled = false,
            value,
            ...props
        },
        ref
    ) => {
        const internalTextareaRef = React.useRef<HTMLTextAreaElement>(null);
        const normalizedValue =
            typeof value === 'string' ? value : Array.isArray(value) ? value.join(' ') : value?.toString() || '';

        React.useImperativeHandle(ref, () => internalTextareaRef.current as HTMLTextAreaElement, []);

        React.useLayoutEffect(() => {
            const textarea = internalTextareaRef.current;
            if (!textarea) {
                return;
            }

            textarea.style.height = 'auto';
            textarea.style.height = `${Math.min(textarea.scrollHeight, 220)}px`;
        }, [normalizedValue]);

        const handleKeyDown = (event: React.KeyboardEvent<HTMLTextAreaElement>) => {
            onKeyDown?.(event);

            if (event.defaultPrevented) {
                return;
            }

            const isComposing = Boolean((event.nativeEvent as KeyboardEvent).isComposing);
            if (event.key !== 'Enter' || event.shiftKey || isComposing || submitDisabled) {
                return;
            }

            event.preventDefault();
            const form = internalTextareaRef.current?.form;
            if (!form) {
                return;
            }

            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
                return;
            }

            form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
        };

        return (
            <div
                className={cn(
                    'rounded-[30px] border border-white/10 bg-[linear-gradient(180deg,rgba(255,255,255,0.05),rgba(255,255,255,0.025))] p-2 shadow-[0_24px_80px_-44px_rgba(0,0,0,0.9)]',
                    className
                )}
            >
                <div
                    className={
                        'rounded-[26px] border border-white/8 bg-[rgba(3,8,14,0.86)] px-3 py-3 shadow-[inset_0_1px_0_rgba(255,255,255,0.04)]'
                    }
                >
                    {attachments.length > 0 ? (
                        <div className={'mb-3 flex flex-wrap gap-2 px-1'}>
                            {attachments.map((attachment) => (
                                <div
                                    key={attachment.id}
                                    className={
                                        'inline-flex max-w-full items-center gap-2 rounded-full border border-[rgba(var(--primary-rgb),0.24)] bg-[rgba(var(--primary-rgb),0.1)] px-3 py-1.5 text-xs font-semibold text-[#f8f6ef]'
                                    }
                                >
                                    <Paperclip size={12} />
                                    <span className={'max-w-[180px] truncate sm:max-w-[240px]'}>{attachment.name}</span>
                                    {attachment.meta ? (
                                        <span className={'text-[color:var(--muted-foreground)]'}>
                                            {attachment.meta}
                                        </span>
                                    ) : null}
                                    {onRemoveAttachment ? (
                                        <button
                                            type={'button'}
                                            className={
                                                'inline-flex h-5 w-5 items-center justify-center rounded-full text-[color:var(--muted-foreground)] transition hover:bg-white/10 hover:text-[#f8f6ef]'
                                            }
                                            onClick={() => onRemoveAttachment(attachment.id)}
                                        >
                                            <X size={12} />
                                        </button>
                                    ) : null}
                                </div>
                            ))}
                        </div>
                    ) : null}

                    <textarea
                        ref={internalTextareaRef}
                        rows={1}
                        value={value}
                        className={cn(
                            'custom-scrollbar min-h-[72px] w-full resize-none border-0 bg-transparent px-2 py-2 text-sm leading-7 text-[#f8f6ef] outline-none',
                            'placeholder:text-[color:var(--muted-foreground)] focus:ring-0 focus-visible:outline-none'
                        )}
                        onKeyDown={handleKeyDown}
                        {...props}
                    />

                    <div
                        className={
                            'mt-2 flex flex-col gap-3 border-t border-white/8 px-1 pt-3 sm:flex-row sm:items-center sm:justify-between'
                        }
                    >
                        <div className={'flex flex-wrap items-center gap-2'}>
                            <button
                                type={'button'}
                                className={
                                    'inline-flex h-9 w-9 items-center justify-center rounded-full border border-white/10 bg-[rgba(255,255,255,0.04)] text-[#f8f6ef] transition hover:border-[rgba(var(--primary-rgb),0.26)] hover:bg-[rgba(var(--primary-rgb),0.12)]'
                                }
                                onClick={onAttachClick}
                            >
                                <Paperclip size={16} />
                                <span className={'sr-only'}>Attach files</span>
                            </button>
                            <div
                                className={
                                    'inline-flex items-center gap-2 rounded-full border border-white/10 bg-[rgba(255,255,255,0.04)] px-3 py-1.5 text-[11px] font-bold uppercase tracking-[0.18em] text-[color:var(--muted-foreground)]'
                                }
                            >
                                <Sparkles size={12} className={'text-[color:var(--primary)]'} />
                                <span>{statusLabel}</span>
                            </div>
                        </div>

                        <div className={'flex items-center justify-between gap-3 sm:justify-end'}>
                            {helperText ? (
                                <p
                                    className={
                                        'text-xs leading-6 text-[color:var(--muted-foreground)] sm:max-w-[24rem] sm:text-right'
                                    }
                                >
                                    {helperText}
                                </p>
                            ) : null}
                            <button
                                type={'submit'}
                                className={
                                    'inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-[linear-gradient(135deg,rgba(var(--primary-rgb),0.88),rgba(var(--primary-rgb),0.58))] text-[#08110d] shadow-[0_0_28px_rgba(var(--primary-rgb),0.25)] transition hover:scale-[1.03] hover:shadow-[0_0_32px_rgba(var(--primary-rgb),0.32)] disabled:scale-100 disabled:cursor-not-allowed disabled:opacity-50'
                                }
                                disabled={submitDisabled}
                            >
                                <ArrowUp size={18} />
                                <span className={'sr-only'}>Send reply</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        );
    }
);

PromptBox.displayName = 'PromptBox';
