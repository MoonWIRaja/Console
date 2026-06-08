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
            return { border: '#dc2626', accent: '#dc2626' };
        case 'success':
            return { border: '#16a34a', accent: '#16a34a' };
        case 'warning':
            return { border: '#ca8a04', accent: '#ca8a04' };
        case 'info':
            return { border: '#2D4A3E', accent: '#2D4A3E' };
        default:
            return { border: '#2D4A3E', accent: '#742220' };
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
                backgroundColor: '#FEF9E1',
                backgroundImage: [
                    'repeating-linear-gradient(0deg, transparent, transparent 4.5px, rgba(116, 34, 32, 0.04) 4.5px, rgba(116, 34, 32, 0.04) 5px)',
                    'repeating-linear-gradient(60deg, transparent, transparent 4.5px, rgba(116, 34, 32, 0.04) 4.5px, rgba(116, 34, 32, 0.04) 5px)',
                    'repeating-linear-gradient(120deg, transparent, transparent 4.5px, rgba(116, 34, 32, 0.04) 4.5px, rgba(116, 34, 32, 0.04) 5px)',
                ].join(', '),
                border: `2px solid #2D4A3E`,
                borderLeft: `4px solid ${colors.border}`,
                color: '#742220',
                padding: '12px 16px',
                marginTop: '8px',
                maxWidth: '340px',
                minWidth: '280px',
                fontFamily:
                    "var(--font-mono, 'Geist Mono', ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace)",
                fontSize: '11px',
                lineHeight: '1.5',
                display: 'flex',
                alignItems: 'flex-start',
                gap: '10px',
                pointerEvents: 'auto',
                transform: visible && !exiting ? 'translateX(0)' : 'translateX(120%)',
                opacity: visible && !exiting ? 1 : 0,
                transition: 'transform 0.3s ease, opacity 0.3s ease',
                boxShadow: '4px 4px 0px 0px #2D4A3E',
                borderRadius: '12px',
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
                <div style={{ color: 'rgba(116, 34, 32, 0.75)', wordBreak: 'break-word' }}>{message}</div>
            </div>
            <button
                onClick={handleClose}
                style={{
                    background: 'none',
                    border: 'none',
                    color: 'rgba(116, 34, 32, 0.45)',
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
