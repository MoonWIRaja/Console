import React, { useEffect, useRef } from 'react';

type TurnstileInstance = {
    render: (container: HTMLElement, options: Record<string, unknown>) => string;
    reset: (widgetId: string) => void;
    remove: (widgetId: string) => void;
};

declare global {
    interface Window {
        turnstile?: TurnstileInstance;
    }
}

interface Props {
    siteKey: string;
    onVerify: (token: string) => void;
    onExpire: () => void;
    onError: () => void;
    className?: string;
}

const SCRIPT_ID = 'cloudflare-turnstile-script';
const SCRIPT_SRC = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';

const ensureScript = (): Promise<void> =>
    new Promise((resolve, reject) => {
        const existing = document.getElementById(SCRIPT_ID) as HTMLScriptElement | null;
        if (existing) {
            if (window.turnstile) {
                resolve();
                return;
            }

            existing.addEventListener('load', () => resolve(), { once: true });
            existing.addEventListener('error', () => reject(new Error('Unable to load Turnstile script.')), {
                once: true,
            });
            return;
        }

        const script = document.createElement('script');
        script.id = SCRIPT_ID;
        script.src = SCRIPT_SRC;
        script.async = true;
        script.defer = true;
        script.onload = () => resolve();
        script.onerror = () => reject(new Error('Unable to load Turnstile script.'));
        document.head.appendChild(script);
    });

const TurnstileWidget = ({ siteKey, onVerify, onExpire, onError, className }: Props) => {
    const containerRef = useRef<HTMLDivElement | null>(null);
    const widgetIdRef = useRef<string | null>(null);
    const onVerifyRef = useRef(onVerify);
    const onExpireRef = useRef(onExpire);
    const onErrorRef = useRef(onError);

    useEffect(() => {
        onVerifyRef.current = onVerify;
        onExpireRef.current = onExpire;
        onErrorRef.current = onError;
    }, [onVerify, onExpire, onError]);

    useEffect(() => {
        let mounted = true;

        const mountWidget = async () => {
            try {
                await ensureScript();
            } catch (_error) {
                if (mounted) {
                    onErrorRef.current();
                }

                return;
            }

            if (!mounted || !window.turnstile || !containerRef.current || widgetIdRef.current) {
                return;
            }

            widgetIdRef.current = window.turnstile.render(containerRef.current, {
                sitekey: siteKey,
                theme: 'dark',
                callback: (token: string) => onVerifyRef.current(token),
                'expired-callback': () => onExpireRef.current(),
                'error-callback': () => onErrorRef.current(),
            });
        };

        mountWidget();

        return () => {
            mounted = false;
            if (widgetIdRef.current && window.turnstile) {
                window.turnstile.remove(widgetIdRef.current);
            }
            widgetIdRef.current = null;
        };
    }, [siteKey]);

    return <div ref={containerRef} className={className} />;
};

export default TurnstileWidget;
