import { ReactNode, useCallback, useEffect, useRef, useState } from 'react';

interface ScrollState {
    isAtBottom: boolean;
    autoScrollEnabled: boolean;
}

interface UseAutoScrollOptions {
    offset?: number;
    smooth?: boolean;
    content?: ReactNode;
}

export function useAutoScroll(options: UseAutoScrollOptions = {}) {
    const { offset = 20, smooth = false, content } = options;
    const scrollRef = useRef<HTMLDivElement>(null);
    const lastContentHeight = useRef(0);

    const [scrollState, setScrollState] = useState<ScrollState>({
        isAtBottom: true,
        autoScrollEnabled: true,
    });

    const checkIsAtBottom = useCallback(
        (element: HTMLElement) => {
            const { scrollTop, scrollHeight, clientHeight } = element;
            return Math.abs(scrollHeight - scrollTop - clientHeight) <= offset;
        },
        [offset]
    );

    const scrollToBottom = useCallback(
        (instant?: boolean) => {
            if (!scrollRef.current) {
                return;
            }

            const targetScrollTop = scrollRef.current.scrollHeight - scrollRef.current.clientHeight;

            if (instant) {
                scrollRef.current.scrollTop = targetScrollTop;
            } else {
                scrollRef.current.scrollTo({
                    top: targetScrollTop,
                    behavior: smooth ? 'smooth' : 'auto',
                });
            }

            setScrollState({
                isAtBottom: true,
                autoScrollEnabled: true,
            });
        },
        [smooth]
    );

    const handleScroll = useCallback(() => {
        if (!scrollRef.current) {
            return;
        }

        const atBottom = checkIsAtBottom(scrollRef.current);

        setScrollState((current) => ({
            isAtBottom: atBottom,
            autoScrollEnabled: atBottom ? true : current.autoScrollEnabled,
        }));
    }, [checkIsAtBottom]);

    useEffect(() => {
        const element = scrollRef.current;
        if (!element) {
            return;
        }

        element.addEventListener('scroll', handleScroll, { passive: true });
        return () => element.removeEventListener('scroll', handleScroll);
    }, [handleScroll]);

    useEffect(() => {
        const element = scrollRef.current;
        if (!element) {
            return;
        }

        const currentHeight = element.scrollHeight;
        const hasNewContent = currentHeight !== lastContentHeight.current;

        if (hasNewContent) {
            if (scrollState.autoScrollEnabled) {
                requestAnimationFrame(() => scrollToBottom(lastContentHeight.current === 0));
            }

            lastContentHeight.current = currentHeight;
        }
    }, [content, scrollState.autoScrollEnabled, scrollToBottom]);

    useEffect(() => {
        const element = scrollRef.current;
        if (!element || typeof ResizeObserver === 'undefined') {
            return;
        }

        const observer = new ResizeObserver(() => {
            if (scrollState.autoScrollEnabled) {
                scrollToBottom(true);
            }
        });

        observer.observe(element);
        return () => observer.disconnect();
    }, [scrollState.autoScrollEnabled, scrollToBottom]);

    const disableAutoScroll = useCallback(() => {
        const atBottom = scrollRef.current ? checkIsAtBottom(scrollRef.current) : false;
        if (atBottom) {
            return;
        }

        setScrollState((current) => ({
            ...current,
            autoScrollEnabled: false,
        }));
    }, [checkIsAtBottom]);

    return {
        scrollRef,
        isAtBottom: scrollState.isAtBottom,
        autoScrollEnabled: scrollState.autoScrollEnabled,
        scrollToBottom: () => scrollToBottom(false),
        disableAutoScroll,
    };
}
