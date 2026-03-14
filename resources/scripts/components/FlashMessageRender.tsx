import React, { useEffect, useMemo, useState } from 'react';
import { Actions, useStoreActions, useStoreState } from 'easy-peasy';
import { ApplicationStore } from '@/state';
import { FlashMessage } from '@/state/flashes';
import Portal from '@/components/elements/Portal';

type Props = Readonly<{
    byKey?: string;
    className?: string;
}>;

interface ToastItemProps {
    id: string;
    type: string;
    title?: string;
    message: string;
    onDismiss: (id: string) => void;
}

const getToastColors = (type: string) => {
    switch (type) {
        case 'error':
            return { bg: '#1a1a1a', border: '#ef4444', accent: '#ef4444' };
        case 'success':
            return { bg: '#1a1a1a', border: '#22c55e', accent: '#22c55e' };
        case 'warning':
            return { bg: '#1a1a1a', border: '#eab308', accent: '#eab308' };
        case 'info':
            return { bg: '#1a1a1a', border: '#3b82f6', accent: '#3b82f6' };
        default:
            return { bg: '#1a1a1a', border: '#6b7280', accent: '#6b7280' };
    }
};

const getIcon = (type: string) => {
    switch (type) {
        case 'error':
            return '✕';
        case 'success':
            return '✓';
        case 'warning':
            return '⚠';
        case 'info':
            return 'ℹ';
        default:
            return '•';
    }
};

const getFlashSignature = ({ key, type, title, message }: Pick<FlashMessage, 'key' | 'type' | 'title' | 'message'>) =>
    `${key || 'global'}:${type}:${title || ''}:${message}`;

const ToastItem = ({ id, type, title, message, onDismiss }: ToastItemProps) => {
    const [visible, setVisible] = useState(false);
    const [exiting, setExiting] = useState(false);
    const colors = getToastColors(type);

    useEffect(() => {
        // Slide in
        const showTimer = setTimeout(() => setVisible(true), 50);

        // Auto dismiss after 5s
        const hideTimer = setTimeout(() => {
            setExiting(true);
            setTimeout(() => onDismiss(id), 300);
        }, 5000);

        return () => {
            clearTimeout(showTimer);
            clearTimeout(hideTimer);
        };
    }, []);

    const handleClose = () => {
        setExiting(true);
        setTimeout(() => onDismiss(id), 300);
    };

    return (
        <div
            style={{
                backgroundColor: colors.bg,
                borderLeft: `3px solid ${colors.border}`,
                color: '#ffffff',
                padding: '12px 16px',
                marginTop: '8px',
                maxWidth: '340px',
                minWidth: '280px',
                fontFamily: "'Space Mono', monospace",
                fontSize: '11px',
                lineHeight: '1.5',
                display: 'flex',
                alignItems: 'flex-start',
                gap: '10px',
                pointerEvents: 'auto',
                transform: visible && !exiting ? 'translateX(0)' : 'translateX(-120%)',
                opacity: visible && !exiting ? 1 : 0,
                transition: 'transform 0.3s ease, opacity 0.3s ease',
                boxShadow: '0 4px 12px rgba(12, 12, 12, 0.4)',
                borderRadius: '0',
            }}
        >
            <span
                style={{
                    color: colors.accent,
                    fontWeight: 'bold',
                    fontSize: '14px',
                    lineHeight: '1',
                    marginTop: '1px',
                    flexShrink: 0,
                }}
            >
                {getIcon(type)}
            </span>
            <div style={{ flex: 1, minWidth: 0 }}>
                {title && (
                    <div
                        style={{
                            fontWeight: 'bold',
                            textTransform: 'uppercase',
                            letterSpacing: '0.05em',
                            marginBottom: '2px',
                            color: colors.accent,
                            fontSize: '10px',
                        }}
                    >
                        {title}
                    </div>
                )}
                <div style={{ color: '#d1d5db', wordBreak: 'break-word' }}>{message}</div>
            </div>
            <button
                onClick={handleClose}
                style={{
                    background: 'none',
                    border: 'none',
                    color: '#6b7280',
                    cursor: 'pointer',
                    fontSize: '14px',
                    lineHeight: '1',
                    padding: '0',
                    flexShrink: 0,
                    marginTop: '1px',
                }}
            >
                ×
            </button>
        </div>
    );
};

const FlashMessageRender = ({ byKey, className }: Props) => {
    const flashes = useStoreState((state: ApplicationStore) =>
        state.flashes.items.filter((flash) => (byKey ? flash.key === byKey : true))
    );
    const removeFlash = useStoreActions((actions: Actions<ApplicationStore>) => actions.flashes.removeFlash);

    const visibleFlashes = useMemo(() => {
        const seen = new Set<string>();

        return flashes
            .map((flash, index) => ({
                ...flash,
                id: flash.id ?? `flash-${flash.type}-${index}`,
            }))
            .reduceRight<Array<FlashMessage & { id: string }>>((items, flash) => {
                const signature = getFlashSignature(flash);

                if (seen.has(signature)) {
                    return items;
                }

                seen.add(signature);
                items.unshift(flash);

                return items;
            }, []);
    }, [flashes]);

    if (!visibleFlashes.length) return null;

    return (
        <Portal>
            <div
                className={className}
                style={{
                    position: 'fixed',
                    right: '20px',
                    bottom: '20px',
                    left: 'auto',
                    zIndex: 2147483647,
                    display: 'flex',
                    flexDirection: 'column-reverse',
                    alignItems: 'flex-end',
                    pointerEvents: 'none',
                    maxWidth: 'calc(100vw - 32px)',
                }}
            >
                {visibleFlashes.map((flash) => (
                    <ToastItem
                        key={flash.id}
                        id={flash.id}
                        type={flash.type}
                        title={flash.title}
                        message={flash.message}
                        onDismiss={removeFlash}
                    />
                ))}
            </div>
        </Portal>
    );
};

export default FlashMessageRender;
