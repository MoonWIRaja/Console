import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { ITerminalOptions, Terminal } from 'xterm';
import { FitAddon } from 'xterm-addon-fit';
import { SearchAddon } from 'xterm-addon-search';
import { SearchBarAddon } from 'xterm-addon-search-bar';
import { WebLinksAddon } from 'xterm-addon-web-links';
import { Unicode11Addon } from 'xterm-addon-unicode11';
import { ScrollDownHelperAddon } from '@/plugins/XtermScrollDownHelperAddon';
import SpinnerOverlay from '@/components/elements/SpinnerOverlay';
import { ServerContext } from '@/state/server';
import { usePermissions } from '@/plugins/usePermissions';
import { theme as th } from 'twin.macro';
import useEventListener from '@/plugins/useEventListener';
import { debounce } from 'debounce';
import { usePersistedState } from '@/plugins/usePersistedState';
import { SocketEvent, SocketRequest } from '@/components/server/events';
import classNames from 'classnames';
import useSiteBranding from '@/hooks/useSiteBranding';
import { useLocation } from 'react-router-dom';

import 'xterm/css/xterm.css';
import styles from './style.module.css';

type ConsoleVariant = 'default' | 'sidebar';
type ConsoleSyncMode = 'full' | 'live-only';

interface ConsoleProps {
    variant?: ConsoleVariant;
    onRequestClose?: () => void;
    syncMode?: ConsoleSyncMode;
    contributeToSharedTranscript?: boolean;
}

type TranscriptEntry = {
    plain: string;
    rendered: string;
};

const isDark = typeof document !== 'undefined' ? document.documentElement.classList.contains('dark') : false;
const MAX_TRANSCRIPT_LINES = 25000;
const MAX_SHARE_CHARACTERS = 9_500_000;
const sharedTranscriptByServer = new Map<string, TranscriptEntry[]>();

const clampTranscriptEntries = (entries: TranscriptEntry[]): TranscriptEntry[] =>
    entries.length > MAX_TRANSCRIPT_LINES ? entries.slice(entries.length - MAX_TRANSCRIPT_LINES) : entries;

const readSharedTranscript = (serverId: string): TranscriptEntry[] =>
    (sharedTranscriptByServer.get(serverId) || []).slice();

const appendSharedTranscript = (serverId: string, entry: TranscriptEntry): void => {
    const current = sharedTranscriptByServer.get(serverId) || [];
    sharedTranscriptByServer.set(serverId, clampTranscriptEntries([...current, entry]));
};

const clearSharedTranscript = (serverId: string): void => {
    sharedTranscriptByServer.delete(serverId);
};

const theme = isDark
    ? {
        background: '#0C0C0C',
        cursor: 'transparent',
        cursorAccent: 'transparent',
        black: th`colors.black`.toString(),
        red: '#E54B4B',
        green: '#9ECE58',
        yellow: '#FAED70',
        blue: '#396FE2',
        magenta: '#BB80B3',
        cyan: '#2DDAFD',
        white: '#d0d0d0',
        brightBlack: 'rgba(255, 255, 255, 0.2)',
        brightRed: '#FF5370',
        brightGreen: '#C3E88D',
        brightYellow: '#FFCB6B',
        brightBlue: '#82AAFF',
        brightMagenta: '#C792EA',
        brightCyan: '#89DDFF',
        brightWhite: '#ffffff',
        selection: '#FAF089',
    }
    : {
        background: '#0C0C0C',
        cursor: 'transparent',
        cursorAccent: 'transparent',
        black: '#111827',
        red: '#dc2626',
        green: '#16a34a',
        yellow: '#ca8a04',
        blue: '#2563eb',
        magenta: '#7c3aed',
        cyan: '#0891b2',
        white: '#111827',
        brightBlack: '#6b7280',
        brightRed: '#ef4444',
        brightGreen: '#22c55e',
        brightYellow: '#eab308',
        brightBlue: '#3b82f6',
        brightMagenta: '#8b5cf6',
        brightCyan: '#06b6d4',
        brightWhite: '#0f172a',
        selection: '#d1d5db',
    };

const terminalProps: ITerminalOptions = {
    disableStdin: true,
    cursorStyle: 'underline',
    // Theme uses rgba() for some terminal colors (e.g. brightBlack), so transparency must be enabled.
    allowTransparency: true,
    fontSize: 13,
    lineHeight: 1.28,
    letterSpacing: 0.2,
    // Keep terminal output readable with a true monospace stack even when app-wide font changes.
    fontFamily: `'Geist Mono', ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace`,
    rows: 30,
    rendererType: 'dom',
    theme: theme,
    // Keep compatibility with environments that block accelerated/canvas renderer optimizations.
    allowProposedApi: true,
};

export default ({
    variant = 'default',
    onRequestClose,
    syncMode = 'full',
    contributeToSharedTranscript = true,
}: ConsoleProps) => {
    const location = useLocation();
    const ref = useRef<HTMLDivElement>(null);
    const terminal = useMemo(() => new Terminal({ ...terminalProps }), []);
    const fitAddon = new FitAddon();
    const searchAddon = new SearchAddon();
    const searchBar = new SearchBarAddon({ searchAddon });
    const webLinksAddon = new WebLinksAddon();
    const unicode11Addon = new Unicode11Addon();
    const scrollDownHelperAddon = new ScrollDownHelperAddon();
    const { connected, instance } = ServerContext.useStoreState((state) => state.socket);
    const [canSendCommands] = usePermissions(['control.console']);
    const serverId = ServerContext.useStoreState((state) => state.server.data!.id);
    const isTransferring = ServerContext.useStoreState((state) => state.server.data!.isTransferring);
    const status = ServerContext.useStoreState((state) => state.status.value);
    const { name: siteName } = useSiteBranding();
    const [history, setHistory] = usePersistedState<string[]>(`${serverId}:command_history`, []);
    const [historyIndex, setHistoryIndex] = useState(-1);
    const [isPaused, setIsPaused] = useState(false);
    const [isSharing, setIsSharing] = useState(false);
    const [shareNotice, setShareNotice] = useState<string | null>(null);
    const shareNoticeTimeout = useRef<number | null>(null);
    const transcriptEntries = useRef<TranscriptEntry[]>([]);
    const bufferedEntries = useRef<TranscriptEntry[]>([]);
    const hasRenderedOutput = useRef(false);
    const isSidebar = variant === 'sidebar';
    const isStandaloneConsole = location.pathname.startsWith('/console/');
    const standaloneConsoleHref = `/console/${serverId}`;
    // SearchBarAddon has hardcoded z-index: 999 :(
    const zIndex = `
    .xterm-search-bar__addon {
        z-index: 10;
    }`;
    const fitTerminal = useCallback(() => {
        if (!terminal.element) return;
        fitAddon.fit();
    }, [terminal, fitAddon]);

    const terminalPrelude = useMemo(() => `\u001b[1m\u001b[33mcontainer@${siteName}~ \u001b[0m`, [siteName]);
    const terminalPlainPrelude = useMemo(() => `container@${siteName}~ `, [siteName]);

    const brandDaemonLine = useCallback(
        (line: string) => line.replace(/\[Pterodactyl Daemon\]:/g, `[${siteName} Daemon]:`),
        [siteName]
    );

    const setEphemeralShareNotice = useCallback((message: string) => {
        setShareNotice(message);

        if (shareNoticeTimeout.current) {
            window.clearTimeout(shareNoticeTimeout.current);
        }

        shareNoticeTimeout.current = window.setTimeout(() => {
            setShareNotice(null);
            shareNoticeTimeout.current = null;
        }, 2400);
    }, []);

    const buildTranscriptEntry = useCallback(
        (line: string, options?: { prelude?: boolean; tone?: 'normal' | 'error' }) => {
            const cleaned = brandDaemonLine(line).replace(/(?:\r\n|\r|\n)$/im, '');
            const prelude = options?.prelude ? terminalPrelude : '';
            const plainPrelude = options?.prelude ? terminalPlainPrelude : '';

            return {
                plain: `${plainPrelude}${cleaned}`,
                rendered:
                    options?.tone === 'error'
                        ? `${terminalPrelude}\u001b[1m\u001b[41m${cleaned}\u001b[0m`
                        : `${prelude}${cleaned}\u001b[0m`,
            };
        },
        [brandDaemonLine, terminalPrelude, terminalPlainPrelude]
    );

    const persistTranscriptEntry = useCallback(
        (entry: TranscriptEntry) => {
            transcriptEntries.current = clampTranscriptEntries([...transcriptEntries.current, entry]);

            if (contributeToSharedTranscript) {
                appendSharedTranscript(serverId, entry);
            }
        },
        [serverId, contributeToSharedTranscript]
    );

    const writeTranscriptEntry = useCallback(
        (entry: TranscriptEntry) => {
            terminal.write(`${hasRenderedOutput.current ? '\r\n' : ''}${entry.rendered}`);
            hasRenderedOutput.current = true;
        },
        [terminal]
    );

    const pushTranscriptEntry = useCallback(
        (entry: TranscriptEntry) => {
            persistTranscriptEntry(entry);

            if (isPaused) {
                bufferedEntries.current = clampTranscriptEntries([...bufferedEntries.current, entry]);
                return;
            }

            writeTranscriptEntry(entry);
        },
        [persistTranscriptEntry, isPaused, writeTranscriptEntry]
    );

    const clearConsoleOutput = useCallback(() => {
        terminal.clear();
        transcriptEntries.current = [];
        bufferedEntries.current = [];
        hasRenderedOutput.current = false;

        if (contributeToSharedTranscript) {
            clearSharedTranscript(serverId);
        }

        setEphemeralShareNotice('Console cleared.');
    }, [terminal, contributeToSharedTranscript, serverId, setEphemeralShareNotice]);

    const hydrateFromSharedTranscript = useCallback(() => {
        const sharedTranscript = readSharedTranscript(serverId);
        if (!sharedTranscript.length) {
            return false;
        }

        transcriptEntries.current = sharedTranscript;
        bufferedEntries.current = [];
        terminal.clear();
        hasRenderedOutput.current = false;
        sharedTranscript.forEach((entry) => writeTranscriptEntry(entry));

        return true;
    }, [serverId, terminal, writeTranscriptEntry]);

    const shareConsoleOutput = useCallback(async () => {
        if (isSharing) {
            return;
        }

        const lines = transcriptEntries.current.map((entry) => entry.plain);
        if (!lines.length) {
            setEphemeralShareNotice('Console is empty.');
            return;
        }

        setIsSharing(true);

        try {
            let sharedLines = lines.slice(-MAX_TRANSCRIPT_LINES);
            let content = sharedLines.join('\n');

            while (content.length > MAX_SHARE_CHARACTERS && sharedLines.length > 1) {
                sharedLines = sharedLines.slice(1);
                content = sharedLines.join('\n');
            }

            const response = await fetch('https://api.mclo.gs/1/log', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    content,
                    source: typeof window !== 'undefined' ? window.location.hostname : siteName,
                }),
            });

            const data = await response.json();
            if (!response.ok || !data?.url) {
                throw new Error(data?.error || data?.message || 'Unable to share logs right now.');
            }

            if (typeof navigator !== 'undefined' && navigator.clipboard?.writeText) {
                navigator.clipboard.writeText(data.url).catch(() => undefined);
            }

            window.open(data.url, '_blank', 'noopener,noreferrer');
            setEphemeralShareNotice('Shared to mclo.gs.');
        } catch (error) {
            setEphemeralShareNotice(error instanceof Error ? error.message : 'Unable to share logs right now.');
        } finally {
            setIsSharing(false);
        }
    }, [isSharing, setEphemeralShareNotice, siteName]);

    const handleConsoleOutput = useCallback(
        (line: string, prelude = false) => pushTranscriptEntry(buildTranscriptEntry(line, { prelude })),
        [pushTranscriptEntry, buildTranscriptEntry]
    );

    const handleTransferStatus = useCallback(
        (status: string) => {
            switch (status) {
                // Sent by either the source or target node if a failure occurs.
                case 'failure':
                    pushTranscriptEntry(buildTranscriptEntry('Transfer has failed.', { prelude: true }));
                    return;
            }
        },
        [pushTranscriptEntry, buildTranscriptEntry]
    );

    const handleDaemonErrorOutput = useCallback(
        (line: string) => pushTranscriptEntry(buildTranscriptEntry(line, { prelude: true, tone: 'error' })),
        [pushTranscriptEntry, buildTranscriptEntry]
    );

    const handlePowerChangeEvent = useCallback(
        (state: string) => pushTranscriptEntry(buildTranscriptEntry(`Server marked as ${state}...`, { prelude: true })),
        [pushTranscriptEntry, buildTranscriptEntry]
    );

    const handleCommandKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
        if (e.key === 'ArrowUp') {
            const newIndex = Math.min(historyIndex + 1, history!.length - 1);

            setHistoryIndex(newIndex);
            e.currentTarget.value = history![newIndex] || '';

            // By default up arrow will also bring the cursor to the start of the line,
            // so we'll preventDefault to keep it at the end.
            e.preventDefault();
        }

        if (e.key === 'ArrowDown') {
            const newIndex = Math.max(historyIndex - 1, -1);

            setHistoryIndex(newIndex);
            e.currentTarget.value = history![newIndex] || '';
        }

        const command = e.currentTarget.value;
        if (e.key === 'Enter' && command.length > 0) {
            setHistory((prevHistory) => [command, ...prevHistory!].slice(0, 32));
            setHistoryIndex(-1);

            instance && instance.send('send command', command);
            e.currentTarget.value = '';
        }
    };

    useEffect(() => {
        if (connected && ref.current && !terminal.element) {
            terminal.loadAddon(fitAddon);
            terminal.loadAddon(searchAddon);
            terminal.loadAddon(searchBar);
            terminal.loadAddon(webLinksAddon);
            terminal.loadAddon(unicode11Addon);
            terminal.loadAddon(scrollDownHelperAddon);

            terminal.open(ref.current);

            // Activate Unicode 11 for proper emoji and special character width handling
            terminal.unicode.activeVersion = '11';

            fitTerminal();
            window.setTimeout(fitTerminal, 50);
            window.setTimeout(fitTerminal, 200);
            searchBar.addNewStyle(zIndex);

            // Add support for capturing keys
            terminal.attachCustomKeyEventHandler((e: KeyboardEvent) => {
                if ((e.ctrlKey || e.metaKey) && e.key === 'c') {
                    document.execCommand('copy');
                    return false;
                } else if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
                    e.preventDefault();
                    searchBar.show();
                    return false;
                } else if (e.key === 'Escape') {
                    searchBar.hidden();
                }
                return true;
            });
        }
    }, [terminal, connected, fitTerminal]);

    useEventListener('resize', debounce(fitTerminal, 100));

    useEffect(
        () => () => {
            if (shareNoticeTimeout.current) {
                window.clearTimeout(shareNoticeTimeout.current);
            }
        },
        []
    );

    useEffect(() => {
        if (!ref.current || !terminal.element) return;

        const observer = new ResizeObserver(() => fitTerminal());
        observer.observe(ref.current);
        if (ref.current.parentElement) {
            observer.observe(ref.current.parentElement);
        }

        // Fit once on mount/layout settle to avoid visible empty space.
        requestAnimationFrame(fitTerminal);
        window.setTimeout(fitTerminal, 120);

        return () => observer.disconnect();
    }, [terminal, connected, fitTerminal]);

    useEffect(() => {
        if (!terminal.element) return;

        const onViewportChange = debounce(fitTerminal, 80);
        const visualViewport = window.visualViewport;

        window.addEventListener('orientationchange', onViewportChange);
        document.addEventListener('visibilitychange', onViewportChange);
        if (visualViewport) {
            visualViewport.addEventListener('resize', onViewportChange);
            visualViewport.addEventListener('scroll', onViewportChange);
        }

        return () => {
            window.removeEventListener('orientationchange', onViewportChange);
            document.removeEventListener('visibilitychange', onViewportChange);
            if (visualViewport) {
                visualViewport.removeEventListener('resize', onViewportChange);
                visualViewport.removeEventListener('scroll', onViewportChange);
            }
        };
    }, [terminal, fitTerminal]);

    useEffect(() => {
        if (!isPaused && bufferedEntries.current.length > 0) {
            bufferedEntries.current.forEach((entry) => writeTranscriptEntry(entry));
            bufferedEntries.current = [];
        }
    }, [isPaused, writeTranscriptEntry]);

    useEffect(() => {
        const listeners: Record<string, (s: string) => void> = {
            [SocketEvent.STATUS]: handlePowerChangeEvent,
            [SocketEvent.CONSOLE_OUTPUT]: handleConsoleOutput,
            [SocketEvent.INSTALL_OUTPUT]: handleConsoleOutput,
            [SocketEvent.TRANSFER_LOGS]: handleConsoleOutput,
            [SocketEvent.TRANSFER_STATUS]: handleTransferStatus,
            [SocketEvent.DAEMON_MESSAGE]: (line) => handleConsoleOutput(line, true),
            [SocketEvent.DAEMON_ERROR]: handleDaemonErrorOutput,
        };

        if (connected && instance) {
            const hasSharedTranscript = hydrateFromSharedTranscript();

            if (!hasSharedTranscript) {
                transcriptEntries.current = [];
                bufferedEntries.current = [];
                hasRenderedOutput.current = false;

                // Do not clear the console if the server is being transferred.
                if (!isTransferring) {
                    terminal.clear();
                }
            }

            Object.keys(listeners).forEach((key: string) => {
                instance.addListener(key, listeners[key]);
            });

            if (!hasSharedTranscript && syncMode === 'full') {
                instance.send(SocketRequest.SEND_LOGS);
            }
        }

        return () => {
            if (instance) {
                Object.keys(listeners).forEach((key: string) => {
                    instance.removeListener(key, listeners[key]);
                });
            }
        };
    }, [
        connected,
        instance,
        syncMode,
        isTransferring,
        terminal,
        hydrateFromSharedTranscript,
        handleConsoleOutput,
        handleDaemonErrorOutput,
        handlePowerChangeEvent,
        handleTransferStatus,
    ]);

    return (
        <div className={classNames(styles.terminal, styles[`terminal_${variant}`], 'relative')}>
            {!connected && <SpinnerOverlay visible size={'large'} />}
            {isSidebar && (
                <div className={styles.sidebar_header}>
                    <div className={styles.sidebar_header_left}>
                        <h3 className={styles.sidebar_title}>
                            <span className={classNames('material-icons-round text-[18px]', {
                                'text-green-400': status === 'running',
                                'text-yellow-400': status === 'starting' || status === 'stopping',
                                'text-red-400': status === 'offline',
                                'text-gray-400': status === null,
                            })}>terminal</span>
                            Console
                        </h3>
                        {shareNotice && <p className={styles.sidebar_notice}>{shareNotice}</p>}
                    </div>
                    <div className={styles.sidebar_actions}>
                        <button
                            type={'button'}
                            title={'Start Server'}
                            aria-label={'Start Server'}
                            className={styles.sidebar_action}
                            onClick={() => instance && instance.send(SocketRequest.SET_STATE, 'start')}
                        >
                            <span className={'material-icons-round text-[18px] text-green-400'}>play_arrow</span>
                        </button>
                        <button
                            type={'button'}
                            title={'Restart Server'}
                            aria-label={'Restart Server'}
                            className={styles.sidebar_action}
                            onClick={() => instance && instance.send(SocketRequest.SET_STATE, 'restart')}
                        >
                            <span className={'material-icons-round text-[18px] text-amber-400'}>restart_alt</span>
                        </button>
                        <button
                            type={'button'}
                            title={'Stop Server'}
                            aria-label={'Stop Server'}
                            className={styles.sidebar_action}
                            onClick={() => instance && instance.send(SocketRequest.SET_STATE, 'stop')}
                        >
                            <span className={'material-icons-round text-[18px] text-red-400'}>stop</span>
                        </button>
                        {onRequestClose && (
                            <button
                                type={'button'}
                                title={'Close console'}
                                aria-label={'Close console'}
                                className={styles.sidebar_action}
                                onClick={onRequestClose}
                            >
                                <span className={'material-icons-round text-[18px]'}>close</span>
                            </button>
                        )}
                    </div>
                </div>
            )}
            <div
                className={classNames(styles.container, styles.overflows_container, { 'rounded-b': !canSendCommands })}
            >
                <div className={'h-full'}>
                    <div id={styles.terminal} ref={ref} />
                </div>
            </div>
            {canSendCommands && (
                <div className={classNames(styles.command_bar)}>
                    <div className={classNames('relative flex items-center', styles.command_shell)}>
                        <div className={styles.command_input_wrap}>
                            <input
                                className={classNames(styles.command_input)}
                                type={'text'}
                                placeholder={'Type a command...'}
                                aria-label={'Console command input.'}
                                disabled={!instance || !connected}
                                onKeyDown={handleCommandKeyDown}
                                autoCorrect={'off'}
                                autoCapitalize={'none'}
                            />
                        </div>
                        {!isStandaloneConsole && !isSidebar && (
                            <a
                                className={styles.command_external}
                                target='_blank'
                                rel='noreferrer'
                                href={standaloneConsoleHref}
                                title={'Open standalone console'}
                                aria-label={'Open standalone console'}
                            >
                                <svg
                                    aria-hidden='true'
                                    focusable='false'
                                    width='16'
                                    height='16'
                                    viewBox='0 0 512 512'
                                    fill='currentColor'
                                >
                                    <path d='M432 320H400a16 16 0 0 0-16 16V448H64V128H208a16 16 0 0 0 16-16V80a16 16 0 0 0-16-16H48A48 48 0 0 0 0 112V464a48 48 0 0 0 48 48H400a48 48 0 0 0 48-48V336a16 16 0 0 0-16-16ZM488 0H360c-21.37 0-32.05 25.91-17 41l35.73 35.73L135 320.37a24 24 0 0 0 0 34L157.67 377a24 24 0 0 0 34 0L435.28 133.32 471 169c15 15 41 4.5 41-17V24a24 24 0 0 0-24-24Z' />
                                </svg>
                            </a>
                        )}
                    </div>
                </div>
            )}
        </div>
    );
};
