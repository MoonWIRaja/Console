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

import 'xterm/css/xterm.css';
import styles from './style.module.css';

const isDark = typeof document !== 'undefined' ? document.documentElement.classList.contains('dark') : false;

const theme = isDark
    ? {
          background: '#0C0C0C',
          cursor: 'transparent',
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
          cursor: '#111827',
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
    fontFamily: `'JetBrains Mono', 'IBM Plex Mono', ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace`,
    rows: 30,
    rendererType: 'dom',
    theme: theme,
    // Keep compatibility with environments that block accelerated/canvas renderer optimizations.
    allowProposedApi: true,
};

export default () => {
    const TERMINAL_PRELUDE = '\u001b[1m\u001b[33mcontainer@pterodactyl~ \u001b[0m';
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
    const [history, setHistory] = usePersistedState<string[]>(`${serverId}:command_history`, []);
    const [historyIndex, setHistoryIndex] = useState(-1);
    // SearchBarAddon has hardcoded z-index: 999 :(
    const zIndex = `
    .xterm-search-bar__addon {
        z-index: 10;
    }`;
    const fitTerminal = useCallback(() => {
        if (!terminal.element) return;
        fitAddon.fit();
    }, [terminal, fitAddon]);

    const handleConsoleOutput = (line: string, prelude = false) =>
        terminal.writeln((prelude ? TERMINAL_PRELUDE : '') + line.replace(/(?:\r\n|\r|\n)$/im, '') + '\u001b[0m');

    const handleTransferStatus = (status: string) => {
        switch (status) {
            // Sent by either the source or target node if a failure occurs.
            case 'failure':
                terminal.writeln(TERMINAL_PRELUDE + 'Transfer has failed.\u001b[0m');
                return;
        }
    };

    const handleDaemonErrorOutput = (line: string) =>
        terminal.writeln(
            TERMINAL_PRELUDE + '\u001b[1m\u001b[41m' + line.replace(/(?:\r\n|\r|\n)$/im, '') + '\u001b[0m'
        );

    const handlePowerChangeEvent = (state: string) =>
        terminal.writeln(TERMINAL_PRELUDE + 'Server marked as ' + state + '...\u001b[0m');

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
    }, [terminal, connected]);

    useEventListener('resize', debounce(fitTerminal, 100));

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
            // Do not clear the console if the server is being transferred.
            if (!isTransferring) {
                terminal.clear();
            }

            Object.keys(listeners).forEach((key: string) => {
                instance.addListener(key, listeners[key]);
            });
            instance.send(SocketRequest.SEND_LOGS);
        }

        return () => {
            if (instance) {
                Object.keys(listeners).forEach((key: string) => {
                    instance.removeListener(key, listeners[key]);
                });
            }
        };
    }, [connected, instance]);

    return (
        <div className={classNames(styles.terminal, 'relative')}>
            {!connected && <SpinnerOverlay visible size={'large'} />}
            <div
                className={classNames(styles.container, styles.overflows_container, { 'rounded-b': !canSendCommands })}
            >
                <div className={'h-full'}>
                    <div id={styles.terminal} ref={ref} />
                </div>
            </div>
            {canSendCommands && (
                <div className={classNames(styles.command_bar)} style={{ backgroundColor: '#0C0C0C' }}>
                    <div
                        className={classNames('relative flex items-center', styles.command_shell)}
                        style={{ backgroundColor: '#0C0C0C' }}
                    >
                        <input
                            className={classNames(styles.command_input)}
                            type={'text'}
                            placeholder={'Type a command...'}
                            aria-label={'Console command input.'}
                            style={{ backgroundColor: '#0C0C0C' }}
                            disabled={!instance || !connected}
                            onKeyDown={handleCommandKeyDown}
                            autoCorrect={'off'}
                            autoCapitalize={'none'}
                        />
                    </div>
                </div>
            )}
        </div>
    );
};
