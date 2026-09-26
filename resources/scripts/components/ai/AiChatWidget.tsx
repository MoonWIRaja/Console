import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useLocation } from 'react-router-dom';
import { useStoreState } from 'easy-peasy';
import useSiteBranding from '@/hooks/useSiteBranding';
import Avatar from '@/components/Avatar';
import { ApplicationStore } from '@/state';
import { httpErrorToHuman } from '@/api/http';
import {
    AiAction,
    AiConfig,
    AiConversation,
    AiConversationSummary,
    AiMessage,
    AiMode,
    createAiConversation,
    deleteAiConversation,
    editAiMessage,
    getAiConfig,
    getAiConversation,
    getAiConversations,
    resolveAiAction,
    sendAiMessage,
} from '@/api/ai';

// Matches the light console palette used across the panel (cream, maroon, deep green, hard offset shadows).
const C = {
    bg: '#FEF9E1',
    panel: '#FEF9E1',
    raised: '#F5EFD5',
    border: '#C8BCA0',
    borderStrong: '#2D4A3E',
    text: '#4B3A2F',
    dim: 'rgba(75, 58, 47, 0.72)',
    faint: 'rgba(75, 58, 47, 0.5)',
    brand: '#742220',
    accent: '#742220',
    accentInk: '#FEF9E1',
    accentSoft: 'rgba(116, 34, 32, 0.07)',
    accentLine: 'rgba(116, 34, 32, 0.32)',
    agent: '#2D4A3E',
    agentSoft: 'rgba(45, 74, 62, 0.12)',
    danger: '#B42318',
    add: '#2D6A4F',
    remove: '#A03A2F',
};

const TOOL_LABELS: Record<string, string> = {
    list_my_servers: 'listed your servers',
    get_server_status: 'checked server status',
    list_files: 'browsed files',
    read_file: 'read a file',
    remember_about_user: 'saved a memory',
    forget_about_user: 'forgot a memory',
};

const ACTION_TITLES: Record<string, string> = {
    write_file: 'Edit file',
    create_directory: 'Create folder',
    delete_files: 'Delete files',
    rename_file: 'Rename / move',
    send_console_command: 'Run console command',
    power_action: 'Power action',
};

const storage = {
    get: (key: string): string | null => {
        try {
            return window.localStorage.getItem(key);
        } catch {
            return null;
        }
    },
    set: (key: string, value: string) => {
        try {
            window.localStorage.setItem(key, value);
        } catch {
            // ignore
        }
    },
};

const serverIdFromPath = (path: string): string | null => {
    const match = path.match(/^\/(?:server|console)\/([a-z0-9-]{8,36})/i);

    return match ? match[1].slice(0, 8) : null;
};

const timeOf = (iso: string | null) =>
    iso ? new Date(iso).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '';

const renderInline = (text: string, keyBase: string) =>
    text.split(/(`[^`\n]+`|\*\*[^*\n]+\*\*)/g).map((part, i) => {
        if (part.startsWith('`') && part.endsWith('`') && part.length > 2) {
            return (
                <code
                    key={`${keyBase}-${i}`}
                    style={{
                        background: 'rgba(75,58,47,0.08)',
                        padding: '1px 5px',
                        borderRadius: 4,
                        fontSize: '0.92em',
                    }}
                >
                    {part.slice(1, -1)}
                </code>
            );
        }
        if (part.startsWith('**') && part.endsWith('**') && part.length > 4) {
            return <strong key={`${keyBase}-${i}`}>{part.slice(2, -2)}</strong>;
        }

        return <React.Fragment key={`${keyBase}-${i}`}>{part}</React.Fragment>;
    });

const Markdown = ({ text }: { text: string }) => {
    const blocks = text.split(/```/);

    return (
        <>
            {blocks.map((block, i) =>
                i % 2 === 1 ? (
                    <pre
                        key={i}
                        style={{
                            background: C.bg,
                            border: `1px solid ${C.border}`,
                            borderRadius: 8,
                            padding: '8px 10px',
                            margin: '6px 0',
                            overflowX: 'auto',
                            fontSize: 11.5,
                            whiteSpace: 'pre',
                        }}
                    >
                        {block.replace(/^[a-z0-9_+-]*\n/i, '')}
                    </pre>
                ) : (
                    <span key={i} style={{ whiteSpace: 'pre-wrap' }}>
                        {renderInline(block, String(i))}
                    </span>
                )
            )}
        </>
    );
};

type DiffLine = { type: ' ' | '+' | '-'; text: string };

const lineDiff = (before: string, after: string): DiffLine[] | null => {
    const a = before.split('\n');
    const b = after.split('\n');
    if (a.length * b.length > 2_500_000) return null;

    const dp: number[][] = Array.from({ length: a.length + 1 }, () => new Array(b.length + 1).fill(0));
    for (let i = a.length - 1; i >= 0; i--) {
        for (let j = b.length - 1; j >= 0; j--) {
            dp[i][j] = a[i] === b[j] ? dp[i + 1][j + 1] + 1 : Math.max(dp[i + 1][j], dp[i][j + 1]);
        }
    }

    const out: DiffLine[] = [];
    let i = 0;
    let j = 0;
    while (i < a.length && j < b.length) {
        if (a[i] === b[j]) {
            out.push({ type: ' ', text: a[i] });
            i++;
            j++;
        } else if (dp[i + 1][j] >= dp[i][j + 1]) {
            out.push({ type: '-', text: a[i++] });
        } else {
            out.push({ type: '+', text: b[j++] });
        }
    }
    while (i < a.length) out.push({ type: '-', text: a[i++] });
    while (j < b.length) out.push({ type: '+', text: b[j++] });

    return out;
};

const compactDiff = (lines: DiffLine[], context = 3): (DiffLine | null)[] => {
    const keep = new Array(lines.length).fill(false);
    lines.forEach((line, idx) => {
        if (line.type !== ' ') {
            for (let k = Math.max(0, idx - context); k <= Math.min(lines.length - 1, idx + context); k++)
                keep[k] = true;
        }
    });

    const out: (DiffLine | null)[] = [];
    lines.forEach((line, idx) => {
        if (keep[idx]) out.push(line);
        else if (out[out.length - 1] !== null) out.push(null);
    });

    return out;
};

const FilePreview = ({ action }: { action: AiAction }) => {
    const content = String(action.arguments.content ?? '');
    const diff = useMemo(
        () => (action.original !== null ? lineDiff(action.original, content) : null),
        [action.original, content]
    );

    const box: React.CSSProperties = {
        background: C.bg,
        border: `1px solid ${C.border}`,
        borderRadius: 6,
        maxHeight: 260,
        overflow: 'auto',
        fontFamily: 'ui-monospace, SFMono-Regular, Menlo, Consolas, monospace',
        fontSize: 11,
        lineHeight: 1.5,
        margin: '6px 0',
    };

    if (!diff) {
        return (
            <>
                <div style={{ color: C.dim, fontSize: 11 }}>
                    {action.original === null ? 'New file:' : 'New content (file too large to diff):'}
                </div>
                <pre style={{ ...box, padding: 8, whiteSpace: 'pre' }}>{content}</pre>
            </>
        );
    }

    const lines = compactDiff(diff);
    const changed = diff.filter((l) => l.type !== ' ').length;

    return (
        <div style={box}>
            {changed === 0 && <div style={{ padding: 8, color: C.dim }}>No changes.</div>}
            {lines.map((line, idx) =>
                line === null ? (
                    <div key={idx} style={{ color: C.faint, padding: '0 8px' }}>
                        ···
                    </div>
                ) : (
                    <div
                        key={idx}
                        style={{
                            whiteSpace: 'pre',
                            padding: '0 8px',
                            background:
                                line.type === '+'
                                    ? 'rgba(159, 212, 154, 0.12)'
                                    : line.type === '-'
                                    ? 'rgba(240, 154, 140, 0.13)'
                                    : 'transparent',
                            color: line.type === '+' ? C.add : line.type === '-' ? C.remove : C.dim,
                        }}
                    >
                        {line.type} {line.text}
                    </div>
                )
            )}
        </div>
    );
};

const ActionCard = ({
    action,
    busy,
    onDecide,
}: {
    action: AiAction;
    busy: boolean;
    onDecide: (action: AiAction, decision: 'approve' | 'reject') => void;
}) => {
    const args = action.arguments;
    const statusColor =
        action.status === 'executed'
            ? C.accent
            : action.status === 'pending'
            ? C.agent
            : action.status === 'failed'
            ? C.danger
            : C.dim;

    return (
        <div
            style={{
                border: `1px solid ${action.status === 'pending' ? C.agent : C.border}`,
                background: action.status === 'pending' ? C.agentSoft : C.raised,
                borderRadius: 10,
                padding: '9px 11px',
                marginTop: 6,
                width: '100%',
            }}
        >
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 8 }}>
                <strong style={{ fontSize: 12 }}>{ACTION_TITLES[action.tool] || action.tool}</strong>
                <span style={{ fontSize: 10, color: statusColor, textTransform: 'uppercase', fontWeight: 700 }}>
                    {action.status === 'pending' ? 'needs approval' : action.status}
                </span>
            </div>
            {action.server && <div style={{ fontSize: 10.5, color: C.dim }}>on {action.server}</div>}
            {args.reason && <div style={{ fontSize: 12, marginTop: 4 }}>{String(args.reason)}</div>}

            <div style={{ fontSize: 11.5, marginTop: 6, color: C.text }}>
                {action.tool === 'write_file' && (
                    <>
                        <code>{String(args.path)}</code>
                        <FilePreview action={action} />
                    </>
                )}
                {action.tool === 'create_directory' && <code>{String(args.path)}</code>}
                {action.tool === 'delete_files' &&
                    (Array.isArray(args.paths) ? args.paths : []).map((p: string) => (
                        <div key={p}>
                            <code style={{ color: C.remove }}>{p}</code>
                        </div>
                    ))}
                {action.tool === 'rename_file' && (
                    <>
                        <code>{String(args.from)}</code> → <code>{String(args.to)}</code>
                    </>
                )}
                {action.tool === 'send_console_command' && (
                    <pre
                        style={{
                            background: C.bg,
                            borderRadius: 6,
                            padding: '6px 8px',
                            margin: '4px 0',
                            whiteSpace: 'pre-wrap',
                        }}
                    >
                        &gt; {String(args.command)}
                    </pre>
                )}
                {action.tool === 'power_action' && (
                    <strong style={{ textTransform: 'uppercase', color: C.agent }}>{String(args.action)}</strong>
                )}
            </div>

            {action.status === 'pending' ? (
                <div style={{ display: 'flex', gap: 6, marginTop: 8 }}>
                    <button
                        type='button'
                        disabled={busy}
                        onClick={() => onDecide(action, 'approve')}
                        style={{
                            flex: 1,
                            background: C.accent,
                            color: C.accentInk,
                            border: 'none',
                            borderRadius: 7,
                            padding: '6px 0',
                            fontWeight: 700,
                            fontSize: 12,
                            cursor: busy ? 'wait' : 'pointer',
                            opacity: busy ? 0.6 : 1,
                        }}
                    >
                        Approve
                    </button>
                    <button
                        type='button'
                        disabled={busy}
                        onClick={() => onDecide(action, 'reject')}
                        style={{
                            flex: 1,
                            background: 'transparent',
                            color: C.text,
                            border: `1px solid ${C.border}`,
                            borderRadius: 7,
                            padding: '6px 0',
                            fontWeight: 600,
                            fontSize: 12,
                            cursor: busy ? 'wait' : 'pointer',
                        }}
                    >
                        Reject
                    </button>
                </div>
            ) : (
                action.result && (
                    <div style={{ fontSize: 10.5, color: C.dim, marginTop: 6, whiteSpace: 'pre-wrap' }}>
                        {action.result}
                    </div>
                )
            )}
        </div>
    );
};

const AnneyMark = ({ size = 28 }: { size?: number }) => {
    const { name, logo } = useSiteBranding();

    return (
        <img
            draggable={false}
            src={logo}
            alt={`${name} logo`}
            style={{
                width: size,
                height: size,
                borderRadius: '0.75rem',
                objectFit: 'contain',
                objectPosition: 'center',
                flexShrink: 0,
            }}
        />
    );
};

const smallIcon = (d: string) => (
    <svg
        width={13}
        height={13}
        viewBox='0 0 24 24'
        fill='none'
        stroke='currentColor'
        strokeWidth='2'
        strokeLinecap='round'
        strokeLinejoin='round'
        aria-hidden
    >
        <path d={d} />
    </svg>
);

const COPY_ICON = 'M9 9h10v11H9z M5 15V4h10';
const CHECK_ICON = 'M5 12.5l4.5 4.5L19 7.5';
const EDIT_ICON = 'M4 20h4L19 9l-4-4L4 16z M13.5 6.5l4 4';

const MessageTool = ({
    title,
    onClick,
    children,
}: {
    title: string;
    onClick: () => void;
    children: React.ReactNode;
}) => (
    <button
        type='button'
        title={title}
        aria-label={title}
        className='anney-msg-tool'
        onClick={onClick}
        style={{
            background: 'transparent',
            border: 'none',
            color: C.faint,
            cursor: 'pointer',
            padding: 4,
            borderRadius: 6,
            display: 'inline-flex',
        }}
    >
        {children}
    </button>
);

const MessageBubble = ({
    message,
    busy,
    onDecide,
    onEdit,
}: {
    message: AiMessage;
    busy: boolean;
    onDecide: (action: AiAction, decision: 'approve' | 'reject') => void;
    onEdit: (message: AiMessage, content: string) => void;
}) => {
    const mine = message.role === 'user';
    const [copied, setCopied] = useState(false);
    const [editing, setEditing] = useState(false);
    const [draft, setDraft] = useState(message.content);

    const copy = () => {
        const done = () => {
            setCopied(true);
            setTimeout(() => setCopied(false), 1500);
        };
        if (navigator.clipboard?.writeText) {
            navigator.clipboard
                .writeText(message.content)
                .then(done)
                .catch(() => undefined);
        } else {
            const area = document.createElement('textarea');
            area.value = message.content;
            document.body.appendChild(area);
            area.select();
            document.execCommand('copy');
            area.remove();
            done();
        }
    };

    const saveEdit = () => {
        const text = draft.trim();
        if (!text || busy) return;
        setEditing(false);
        if (text !== message.content.trim()) onEdit(message, text);
    };

    const bubble: React.CSSProperties = {
        maxWidth: '100%',
        padding: '9px 13px',
        background: mine ? C.accentSoft : C.raised,
        border: `1px solid ${mine ? C.accentLine : C.border}`,
        borderRadius: mine ? '14px 14px 4px 14px' : '14px 14px 14px 4px',
        color: C.text,
        fontSize: 12.5,
        lineHeight: 1.6,
        wordBreak: 'break-word',
    };

    const meta = (
        <div
            className='anney-msg-meta'
            style={{
                display: 'flex',
                alignItems: 'center',
                gap: 2,
                marginTop: 3,
                flexDirection: mine ? 'row-reverse' : 'row',
            }}
        >
            <span style={{ color: C.faint, fontSize: 10, padding: '0 4px' }}>
                {timeOf(message.created_at)}
                {!mine && message.model ? ` · ${message.model}` : ''}
            </span>
            {message.content !== '' && !editing && (
                <MessageTool title={copied ? 'Copied' : 'Copy'} onClick={copy}>
                    {smallIcon(copied ? CHECK_ICON : COPY_ICON)}
                </MessageTool>
            )}
            {mine && !editing && !busy && message.id > 0 && (
                <MessageTool
                    title='Edit'
                    onClick={() => {
                        setDraft(message.content);
                        setEditing(true);
                    }}
                >
                    {smallIcon(EDIT_ICON)}
                </MessageTool>
            )}
        </div>
    );

    if (mine) {
        return (
            <div
                className='anney-msg'
                style={{ display: 'flex', gap: 10, alignItems: 'flex-start', justifyContent: 'flex-end' }}
            >
                <div
                    style={{
                        display: 'flex',
                        flexDirection: 'column',
                        alignItems: 'flex-end',
                        minWidth: 0,
                        maxWidth: '80%',
                    }}
                >
                    {editing ? (
                        <div style={{ ...bubble, width: 360, maxWidth: '100%', background: C.bg }}>
                            <textarea
                                value={draft}
                                autoFocus
                                rows={3}
                                onChange={(e) => setDraft(e.target.value)}
                                onKeyDown={(e) => {
                                    if (e.key === 'Enter' && !e.shiftKey) {
                                        e.preventDefault();
                                        saveEdit();
                                    }
                                    if (e.key === 'Escape') setEditing(false);
                                }}
                                style={{
                                    width: '100%',
                                    resize: 'vertical',
                                    background: 'transparent',
                                    border: 'none',
                                    outline: 'none',
                                    color: C.text,
                                    fontFamily: 'inherit',
                                    fontSize: 12.5,
                                    lineHeight: 1.6,
                                }}
                            />
                            <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 6, marginTop: 6 }}>
                                <button
                                    type='button'
                                    onClick={() => setEditing(false)}
                                    style={{
                                        background: 'transparent',
                                        border: `1px solid ${C.border}`,
                                        color: C.dim,
                                        borderRadius: 7,
                                        padding: '4px 10px',
                                        fontSize: 11,
                                        cursor: 'pointer',
                                        fontFamily: 'inherit',
                                    }}
                                >
                                    Cancel
                                </button>
                                <button
                                    type='button'
                                    onClick={saveEdit}
                                    disabled={!draft.trim() || busy}
                                    style={{
                                        background: C.accent,
                                        border: 'none',
                                        color: C.accentInk,
                                        borderRadius: 7,
                                        padding: '4px 12px',
                                        fontSize: 11,
                                        fontWeight: 700,
                                        cursor: 'pointer',
                                        fontFamily: 'inherit',
                                    }}
                                >
                                    Send
                                </button>
                            </div>
                        </div>
                    ) : (
                        <div style={bubble}>
                            <Markdown text={message.content} />
                        </div>
                    )}
                    {meta}
                </div>
                <span
                    style={{
                        flexShrink: 0,
                        borderRadius: '0.75rem',
                        overflow: 'hidden',
                        display: 'inline-flex',
                        width: 28,
                        height: 28,
                    }}
                >
                    <Avatar.User size={28} />
                </span>
            </div>
        );
    }

    return (
        <div className='anney-msg' style={{ display: 'flex', gap: 10, alignItems: 'flex-start' }}>
            <AnneyMark size={28} />
            <div
                style={{
                    display: 'flex',
                    flexDirection: 'column',
                    alignItems: 'flex-start',
                    minWidth: 0,
                    maxWidth: '85%',
                }}
            >
                <span
                    style={{
                        color: C.brand,
                        fontSize: 11,
                        fontWeight: 700,
                        letterSpacing: '0.02em',
                        margin: '0 0 3px 2px',
                    }}
                >
                    Anney
                </span>
                {message.tools.length > 0 && (
                    <div style={{ display: 'flex', flexWrap: 'wrap', gap: 4, marginBottom: 6 }}>
                        {Array.from(new Set(message.tools)).map((tool) => (
                            <span
                                key={tool}
                                style={{
                                    fontSize: 10,
                                    color: C.dim,
                                    background: C.bg,
                                    border: `1px solid ${C.border}`,
                                    borderRadius: 6,
                                    padding: '2px 7px',
                                }}
                            >
                                <span style={{ color: C.accent }}>›</span> {TOOL_LABELS[tool] || tool}
                            </span>
                        ))}
                    </div>
                )}
                {message.content !== '' && (
                    <div style={bubble}>
                        <Markdown text={message.content} />
                    </div>
                )}
                {message.actions.map((action) => (
                    <ActionCard key={action.uuid} action={action} busy={busy} onDecide={onDecide} />
                ))}
                {message.content !== '' && meta}
            </div>
        </div>
    );
};

const headerButton: React.CSSProperties = {
    background: 'transparent',
    border: '1px solid transparent',
    borderRadius: 7,
    cursor: 'pointer',
    color: C.dim,
    fontSize: 12,
    lineHeight: 1,
    width: 28,
    height: 28,
    display: 'inline-flex',
    alignItems: 'center',
    justifyContent: 'center',
    fontWeight: 600,
};

const AiChatWidget = () => {
    const location = useLocation();
    const user = useStoreState((state: ApplicationStore) => state.user.data);
    const [config, setConfig] = useState<AiConfig | null>(null);
    const [open, setOpen] = useState(false);
    const [large, setLarge] = useState(() => storage.get('anney:size') !== 'small');
    const [showList, setShowList] = useState(() => storage.get('anney:list') !== 'hidden');
    const [mode, setMode] = useState<AiMode>(() => (storage.get('anney:mode') === 'agent' ? 'agent' : 'ask'));
    const [modelId, setModelId] = useState<number | null>(() => Number(storage.get('anney:model')) || null);
    const [conversations, setConversations] = useState<AiConversationSummary[]>([]);
    const [active, setActive] = useState<AiConversation | null>(null);
    const [input, setInput] = useState('');
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [narrow, setNarrow] = useState(() => window.innerWidth < 760);
    const [dims, setDims] = useState<{ w: number; h: number } | null>(() => {
        try {
            const saved = JSON.parse(storage.get('anney:dims') || 'null');
            return saved && saved.w > 0 && saved.h > 0 ? saved : null;
        } catch {
            return null;
        }
    });
    const panelRef = useRef<HTMLDivElement>(null);

    const startResize = (dir: 'x' | 'y' | 'xy') => (event: React.PointerEvent) => {
        if (!panelRef.current) return;
        event.preventDefault();
        const rect = panelRef.current.getBoundingClientRect();
        const startX = event.clientX;
        const startY = event.clientY;
        const clamp = (v: number, min: number, max: number) => Math.max(min, Math.min(max, v));
        let latest = { w: rect.width, h: rect.height };

        const move = (e: PointerEvent) => {
            latest = {
                w: dir === 'y' ? rect.width : clamp(rect.width + (startX - e.clientX), 340, window.innerWidth - 48),
                h: dir === 'x' ? rect.height : clamp(rect.height + (startY - e.clientY), 380, window.innerHeight - 48),
            };
            setDims(latest);
        };
        const up = () => {
            window.removeEventListener('pointermove', move);
            window.removeEventListener('pointerup', up);
            document.body.style.userSelect = '';
            storage.set('anney:dims', JSON.stringify({ w: Math.round(latest.w), h: Math.round(latest.h) }));
        };

        document.body.style.userSelect = 'none';
        window.addEventListener('pointermove', move);
        window.addEventListener('pointerup', up);
    };
    const scrollRef = useRef<HTMLDivElement>(null);

    const currentServer = serverIdFromPath(location.pathname);
    const hidden = !user || location.pathname.startsWith('/auth');

    useEffect(() => {
        const onResize = () => setNarrow(window.innerWidth < 760);
        window.addEventListener('resize', onResize);

        return () => window.removeEventListener('resize', onResize);
    }, []);

    useEffect(() => {
        if (hidden || config) return;
        getAiConfig()
            .then(setConfig)
            .catch(() => setConfig({ enabled: false, name: 'Anney', models: [] }));
    }, [hidden, config]);

    useEffect(() => storage.set('anney:mode', mode), [mode]);
    useEffect(() => storage.set('anney:size', large ? 'large' : 'small'), [large]);
    useEffect(() => storage.set('anney:list', showList ? 'shown' : 'hidden'), [showList]);
    useEffect(() => {
        if (modelId) storage.set('anney:model', String(modelId));
    }, [modelId]);

    const models = config?.models ?? [];
    const effectiveModel = models.find((m) => m.id === modelId) ?? models.find((m) => m.default) ?? models[0] ?? null;

    const refreshList = useCallback(
        () =>
            getAiConversations()
                .then(setConversations)
                .catch(() => undefined),
        []
    );

    useEffect(() => {
        if (!open || !config?.enabled) return;
        refreshList().then(() => undefined);
    }, [open, config?.enabled]);

    useEffect(() => {
        if (!open || !config?.enabled) return;
        if (active && (!currentServer || active.server?.id === currentServer)) return;

        const match = conversations.find((c) => (currentServer ? c.server?.id === currentServer : true));
        if (match) {
            getAiConversation(match.uuid)
                .then(setActive)
                .catch(() => undefined);
        } else {
            setActive(null);
        }
    }, [open, currentServer, conversations.length, config?.enabled]);

    useEffect(() => {
        scrollRef.current?.scrollTo({ top: scrollRef.current.scrollHeight, behavior: 'smooth' });
    }, [active?.messages.length, busy, open]);

    const run = async (task: () => Promise<AiConversation>) => {
        setBusy(true);
        setError(null);
        try {
            const result = await task();
            setActive(result);
            refreshList().then(() => undefined);
        } catch (e) {
            setError(httpErrorToHuman(e));
            if (active) {
                getAiConversation(active.uuid)
                    .then(setActive)
                    .catch(() => undefined);
            }
        } finally {
            setBusy(false);
        }
    };

    const send = () => {
        const text = input.trim();
        if (!text || busy) return;
        setInput('');

        const optimistic: AiMessage = {
            id: -Date.now(),
            role: 'user',
            content: text,
            created_at: new Date().toISOString(),
            model: null,
            tools: [],
            actions: [],
        };
        setActive((prev) => (prev ? { ...prev, messages: [...prev.messages, optimistic] } : prev));

        run(async () => {
            const conversation = active ?? (await createAiConversation(currentServer));
            if (!active) setActive({ ...conversation, messages: [optimistic] });

            return sendAiMessage(conversation.uuid, text, mode, effectiveModel?.id ?? null);
        });
    };

    const editMessage = (message: AiMessage, content: string) => {
        if (!active || busy) return;
        const index = active.messages.findIndex((m) => m.id === message.id);
        const edited: AiMessage = { ...message, content, id: -Date.now() };
        setActive({ ...active, messages: [...active.messages.slice(0, Math.max(index, 0)), edited] });
        const uuid = active.uuid;
        run(() => editAiMessage(uuid, message.id, content, mode, effectiveModel?.id ?? null));
    };

    const decide = (action: AiAction, decision: 'approve' | 'reject') =>
        run(() => resolveAiAction(action.uuid, decision, mode, effectiveModel?.id ?? null));

    const newChat = () => {
        setError(null);
        run(() => createAiConversation(currentServer));
    };

    const removeChat = (uuid: string) => {
        deleteAiConversation(uuid)
            .then(() => {
                if (active?.uuid === uuid) setActive(null);
                refreshList();
            })
            .catch((e) => setError(httpErrorToHuman(e)));
    };

    if (hidden || !config?.enabled) return null;

    const hasPending = active?.messages.some((m) => m.actions.some((a) => a.status === 'pending')) ?? false;
    const panelStyle: React.CSSProperties = narrow
        ? { inset: 0, width: '100vw', height: '100dvh', borderRadius: 0 }
        : {
              bottom: 24,
              right: 24,
              width: dims?.w ?? (large ? 760 : 410),
              height: dims?.h ?? (large ? 660 : 580),
              maxHeight: 'calc(100vh - 48px)',
              maxWidth: 'calc(100vw - 48px)',
              borderRadius: 16,
          };
    const listVisible = showList && !narrow && (dims ? dims.w >= 600 : large);
    const canSend = !busy && input.trim() !== '';
    const suggestions = currentServer
        ? ['Why is my server lagging?', 'Check the latest log for errors', 'What is my view distance?']
        : ['Which of my servers are online?', 'How much is a 4GB Minecraft server?', 'What nodes can I buy on?'];

    const icon = (d: string, size = 15) => (
        <svg
            width={size}
            height={size}
            viewBox='0 0 24 24'
            fill='none'
            stroke='currentColor'
            strokeWidth='1.9'
            strokeLinecap='round'
            strokeLinejoin='round'
            aria-hidden
        >
            <path d={d} />
        </svg>
    );

    const styles = (
        <style>{`
            .anney-launch{transition:transform .18s ease, box-shadow .18s ease}
            .anney-launch:hover{transform:translate(-2px,-2px);box-shadow:6px 6px 0 #2D4A3E!important}
            .anney-panel{animation:anneyIn .18s ease-out}
            @keyframes anneyIn{from{opacity:0;transform:translateY(8px) scale(.985)}to{opacity:1;transform:none}}
            .anney-icon-btn:hover{background:rgba(75,58,47,.06)!important;color:${C.text}!important}
            .anney-msg .anney-msg-tool{opacity:.55;transition:opacity .15s,background .15s}.anney-msg:hover .anney-msg-tool{opacity:1}.anney-msg-tool:hover{background:rgba(75,58,47,.08)!important;color:#742220!important}
            .anney-resize:hover{background:rgba(116,34,32,.12)}.anney-resize-corner{background:linear-gradient(135deg,rgba(45,74,62,.55) 0 3px,transparent 3px)}
            .anney-chat-item:hover{background:rgba(75,58,47,.035)}
            .anney-chat-item .anney-del{opacity:0;transition:opacity .15s}
            .anney-chat-item:hover .anney-del{opacity:1}
            .anney-suggest:hover{border-color:${C.accentLine}!important;color:${C.text}!important;background:${C.accentSoft}!important}
            .anney-composer:focus-within{border-color:${C.accentLine}!important;box-shadow:0 0 0 3px rgba(116,34,32,.1)}
            .anney-scroll::-webkit-scrollbar{width:8px}.anney-scroll::-webkit-scrollbar-thumb{background:rgba(75,58,47,.08);border-radius:8px}
            @keyframes anneyPulse{0%,80%,100%{opacity:.2}40%{opacity:1}}
            .anney-dot{display:inline-block;width:5px;height:5px;border-radius:50%;background:${C.accent};animation:anneyPulse 1.1s infinite}
        `}</style>
    );

    if (!open) {
        return (
            <>
                {styles}
                <button
                    type='button'
                    className='anney-launch'
                    aria-label={`Chat with ${config.name}`}
                    title={`Chat with ${config.name}`}
                    onClick={() => setOpen(true)}
                    style={{
                        position: 'fixed',
                        right: 24,
                        bottom: 24,
                        zIndex: 9990,
                        width: 60,
                        height: 60,
                        padding: 4,
                        borderRadius: 18,
                        border: `2px solid ${C.borderStrong}`,
                        background: C.raised,
                        boxShadow: `4px 4px 0 ${C.borderStrong}`,
                        cursor: 'pointer',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                    }}
                >
                    <AnneyMark size={48} />
                </button>
            </>
        );
    }

    return (
        <div
            ref={panelRef}
            className='anney-panel'
            style={{
                position: 'fixed',
                zIndex: 9990,
                display: 'flex',
                flexDirection: 'column',
                background: C.panel,
                border: `2px solid ${C.borderStrong}`,
                boxShadow: narrow ? 'none' : `6px 6px 0 ${C.borderStrong}`,
                overflow: 'hidden',
                color: C.text,
                fontFamily: 'inherit',
                ...panelStyle,
            }}
        >
            {styles}
            {!narrow && (
                <>
                    <div
                        className='anney-resize'
                        title='Drag to resize'
                        onPointerDown={startResize('x')}
                        style={{
                            position: 'absolute',
                            left: 0,
                            top: 16,
                            bottom: 16,
                            width: 7,
                            cursor: 'ew-resize',
                            zIndex: 5,
                        }}
                    />
                    <div
                        className='anney-resize'
                        title='Drag to resize'
                        onPointerDown={startResize('y')}
                        style={{
                            position: 'absolute',
                            top: 0,
                            left: 16,
                            right: 16,
                            height: 6,
                            cursor: 'ns-resize',
                            zIndex: 5,
                        }}
                    />
                    <div
                        className='anney-resize-corner'
                        title='Drag to resize'
                        onPointerDown={startResize('xy')}
                        style={{
                            position: 'absolute',
                            top: 0,
                            left: 0,
                            width: 18,
                            height: 18,
                            cursor: 'nwse-resize',
                            zIndex: 6,
                        }}
                    />
                </>
            )}

            <div
                style={{
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'space-between',
                    padding: '12px 12px 12px 16px',
                    background: C.bg,
                    borderBottom: `1px solid ${C.border}`,
                    flexShrink: 0,
                    gap: 8,
                }}
            >
                <div style={{ display: 'flex', alignItems: 'center', gap: 10, minWidth: 0 }}>
                    <AnneyMark size={40} />
                    <div style={{ fontSize: 15, fontWeight: 800, color: C.brand, letterSpacing: '0.02em' }}>
                        {config.name}
                    </div>
                </div>
                <div style={{ display: 'flex', alignItems: 'center', gap: 2 }}>
                    {!narrow && large && (
                        <button
                            type='button'
                            className='anney-icon-btn'
                            style={headerButton}
                            title={showList ? 'Hide chats' : 'Show chats'}
                            onClick={() => setShowList((v) => !v)}
                        >
                            {icon('M4 5h16v14H4z M9 5v14')}
                        </button>
                    )}
                    {!narrow && (
                        <button
                            type='button'
                            className='anney-icon-btn'
                            style={headerButton}
                            title={dims ? 'Reset size' : large ? 'Compact' : 'Expand'}
                            onClick={() => {
                                if (dims) {
                                    setDims(null);
                                    storage.set('anney:dims', 'null');
                                    return;
                                }
                                setLarge((v) => !v);
                            }}
                        >
                            {large
                                ? icon('M9 4v5H4 M15 20v-5h5 M4 9l5-5 M20 15l-5 5')
                                : icon('M15 4h5v5 M9 20H4v-5 M20 4l-6 6 M4 20l6-6')}
                        </button>
                    )}
                    {!listVisible && (
                        <button
                            type='button'
                            className='anney-icon-btn'
                            style={headerButton}
                            title='New chat'
                            onClick={newChat}
                        >
                            {icon('M12 5v14 M5 12h14')}
                        </button>
                    )}
                    <button
                        type='button'
                        className='anney-icon-btn'
                        style={headerButton}
                        title='Close'
                        onClick={() => setOpen(false)}
                    >
                        {icon('M6 6l12 12 M18 6 6 18')}
                    </button>
                </div>
            </div>

            <div style={{ display: 'flex', flex: 1, minHeight: 0 }}>
                {listVisible && (
                    <div
                        style={{
                            width: 188,
                            flexShrink: 0,
                            display: 'flex',
                            flexDirection: 'column',
                            background: C.bg,
                            borderRight: `1px solid ${C.border}`,
                        }}
                    >
                        <div style={{ padding: 10 }}>
                            <button
                                type='button'
                                onClick={newChat}
                                style={{
                                    width: '100%',
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: 6,
                                    justifyContent: 'center',
                                    background: C.accentSoft,
                                    border: `1px solid ${C.accentLine}`,
                                    color: C.accent,
                                    borderRadius: 9,
                                    padding: '7px 0',
                                    fontSize: 11.5,
                                    fontWeight: 700,
                                    cursor: 'pointer',
                                }}
                            >
                                {icon('M12 5v14 M5 12h14', 13)} New chat
                            </button>
                        </div>
                        <div
                            style={{
                                padding: '2px 14px 6px',
                                fontSize: 9.5,
                                color: C.faint,
                                fontWeight: 700,
                                letterSpacing: '0.08em',
                            }}
                        >
                            RECENT
                        </div>
                        <div className='anney-scroll' style={{ flex: 1, overflowY: 'auto', padding: '0 8px 8px' }}>
                            {conversations.length === 0 && (
                                <div style={{ fontSize: 11, color: C.faint, padding: '4px 6px' }}>No chats yet.</div>
                            )}
                            {conversations.map((c) => {
                                const selected = active?.uuid === c.uuid;

                                return (
                                    <div
                                        key={c.uuid}
                                        className='anney-chat-item'
                                        style={{
                                            display: 'flex',
                                            alignItems: 'center',
                                            gap: 4,
                                            padding: '7px 8px',
                                            borderRadius: 8,
                                            marginBottom: 2,
                                            background: selected ? 'rgba(75,58,47,0.05)' : undefined,
                                            boxShadow: selected ? `inset 2px 0 0 ${C.accent}` : undefined,
                                        }}
                                    >
                                        <button
                                            type='button'
                                            onClick={() =>
                                                getAiConversation(c.uuid)
                                                    .then(setActive)
                                                    .catch((e) => setError(httpErrorToHuman(e)))
                                            }
                                            style={{
                                                flex: 1,
                                                minWidth: 0,
                                                background: 'none',
                                                border: 'none',
                                                cursor: 'pointer',
                                                textAlign: 'left',
                                                padding: 0,
                                                color: selected ? C.text : C.dim,
                                            }}
                                        >
                                            <div
                                                style={{
                                                    fontSize: 11.5,
                                                    fontWeight: 600,
                                                    overflow: 'hidden',
                                                    textOverflow: 'ellipsis',
                                                    whiteSpace: 'nowrap',
                                                }}
                                            >
                                                {c.title}
                                            </div>
                                            <div
                                                style={{
                                                    fontSize: 9.5,
                                                    color: C.faint,
                                                    overflow: 'hidden',
                                                    textOverflow: 'ellipsis',
                                                    whiteSpace: 'nowrap',
                                                    marginTop: 1,
                                                }}
                                            >
                                                {c.server ? c.server.name : 'General'}
                                            </div>
                                        </button>
                                        <button
                                            type='button'
                                            className='anney-del'
                                            title='Delete chat'
                                            onClick={() => removeChat(c.uuid)}
                                            style={{
                                                background: 'none',
                                                border: 'none',
                                                color: C.faint,
                                                cursor: 'pointer',
                                                padding: 2,
                                                display: 'flex',
                                            }}
                                        >
                                            {icon('M6 6l12 12 M18 6 6 18', 12)}
                                        </button>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                )}

                <div style={{ flex: 1, display: 'flex', flexDirection: 'column', minWidth: 0 }}>
                    <div
                        ref={scrollRef}
                        className='anney-scroll'
                        style={{
                            flex: 1,
                            overflowY: 'auto',
                            padding: '18px 18px 8px',
                            display: 'flex',
                            flexDirection: 'column',
                            gap: 16,
                            minHeight: 0,
                        }}
                    >
                        {(!active || active.messages.length === 0) && (
                            <div
                                style={{
                                    margin: 'auto 0',
                                    display: 'flex',
                                    flexDirection: 'column',
                                    alignItems: 'center',
                                    textAlign: 'center',
                                    gap: 10,
                                    padding: '10px 6px',
                                }}
                            >
                                <AnneyMark size={64} />
                                <div style={{ fontSize: 15, fontWeight: 700, color: C.text }}>
                                    Hi {user?.username}, I&apos;m {config.name}.
                                </div>
                                <div style={{ fontSize: 12, color: C.dim, maxWidth: 360, lineHeight: 1.6 }}>
                                    {currentServer
                                        ? 'Ask me anything about this server. I can read its logs and config and check its status.'
                                        : 'Ask me about your servers, plans and prices, or open a server and chat from there.'}
                                </div>
                                <div
                                    style={{
                                        display: 'flex',
                                        flexDirection: 'column',
                                        gap: 6,
                                        width: '100%',
                                        maxWidth: 340,
                                        marginTop: 6,
                                    }}
                                >
                                    {suggestions.map((s) => (
                                        <button
                                            key={s}
                                            type='button'
                                            className='anney-suggest'
                                            onClick={() => setInput(s)}
                                            style={{
                                                textAlign: 'left',
                                                background: '#F5EFD5',
                                                border: `1px solid ${C.border}`,
                                                color: C.dim,
                                                borderRadius: 10,
                                                padding: '8px 11px',
                                                fontSize: 11.5,
                                                cursor: 'pointer',
                                                fontFamily: 'inherit',
                                                transition: 'all .15s',
                                            }}
                                        >
                                            {s}
                                        </button>
                                    ))}
                                </div>
                            </div>
                        )}
                        {active?.messages.map((message) => (
                            <MessageBubble
                                key={message.id}
                                message={message}
                                busy={busy}
                                onDecide={decide}
                                onEdit={editMessage}
                            />
                        ))}
                        {busy && (
                            <div style={{ display: 'flex', gap: 10, alignItems: 'center' }}>
                                <AnneyMark size={26} />
                                <span style={{ display: 'inline-flex', gap: 4 }}>
                                    <span className='anney-dot' />
                                    <span className='anney-dot' style={{ animationDelay: '0.15s' }} />
                                    <span className='anney-dot' style={{ animationDelay: '0.3s' }} />
                                </span>
                                <span style={{ fontSize: 11.5, color: C.dim }}>thinking</span>
                            </div>
                        )}
                        {error && (
                            <div
                                style={{
                                    fontSize: 12,
                                    color: C.remove,
                                    background: 'rgba(224, 101, 79, 0.09)',
                                    border: '1px solid rgba(224, 101, 79, 0.3)',
                                    borderRadius: 10,
                                    padding: '9px 12px',
                                    lineHeight: 1.5,
                                }}
                            >
                                {error}
                            </div>
                        )}
                    </div>

                    <div style={{ padding: '8px 14px 10px', flexShrink: 0 }}>
                        <div
                            className='anney-composer'
                            style={{
                                background: C.bg,
                                border: `1px solid ${C.borderStrong}`,
                                borderRadius: 14,
                                padding: '10px 10px 8px 12px',
                                transition: 'border-color .15s, box-shadow .15s',
                            }}
                        >
                            <textarea
                                value={input}
                                rows={2}
                                disabled={busy}
                                onChange={(e) => setInput(e.target.value)}
                                onKeyDown={(e) => {
                                    if (e.key === 'Enter' && !e.shiftKey) {
                                        e.preventDefault();
                                        send();
                                    }
                                }}
                                placeholder={
                                    hasPending
                                        ? 'Approve or reject the action above, or type to cancel it…'
                                        : `Message ${config.name}…`
                                }
                                style={{
                                    width: '100%',
                                    resize: 'none',
                                    maxHeight: 140,
                                    background: 'transparent',
                                    color: C.text,
                                    border: 'none',
                                    fontSize: 12.5,
                                    lineHeight: 1.55,
                                    padding: 0,
                                    outline: 'none',
                                    fontFamily: 'inherit',
                                }}
                            />
                            <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginTop: 6 }}>
                                <div
                                    style={{
                                        display: 'flex',
                                        background: 'rgba(75,58,47,0.04)',
                                        border: `1px solid ${C.border}`,
                                        borderRadius: 8,
                                        padding: 2,
                                    }}
                                >
                                    {(['ask', 'agent'] as AiMode[]).map((m) => {
                                        const on = mode === m;

                                        return (
                                            <button
                                                key={m}
                                                type='button'
                                                onClick={() => setMode(m)}
                                                title={
                                                    m === 'ask'
                                                        ? 'Ask: read-only, never changes anything'
                                                        : 'Agent: proposes changes; you approve each one'
                                                }
                                                style={{
                                                    background: on
                                                        ? m === 'agent'
                                                            ? C.agentSoft
                                                            : C.accentSoft
                                                        : 'transparent',
                                                    color: on ? (m === 'agent' ? C.agent : C.accent) : C.faint,
                                                    border: 'none',
                                                    borderRadius: 6,
                                                    padding: '3px 10px',
                                                    fontSize: 10.5,
                                                    fontWeight: 700,
                                                    cursor: 'pointer',
                                                    fontFamily: 'inherit',
                                                }}
                                            >
                                                {m === 'ask' ? 'Ask' : 'Agent'}
                                            </button>
                                        );
                                    })}
                                </div>
                                <select
                                    value={effectiveModel?.id ?? ''}
                                    onChange={(e) => setModelId(Number(e.target.value) || null)}
                                    title='Choose a model'
                                    style={{
                                        flex: '0 1 auto',
                                        minWidth: 0,
                                        maxWidth: 240,
                                        background: C.raised,
                                        color: C.text,
                                        border: `1px solid ${C.border}`,
                                        borderRadius: 8,
                                        fontSize: 10.5,
                                        fontWeight: 600,
                                        padding: '4px 6px',
                                        fontFamily: 'inherit',
                                        outline: 'none',
                                        cursor: 'pointer',
                                    }}
                                >
                                    {models.map((m) => (
                                        <option key={m.id} value={m.id}>
                                            {m.name}
                                            {m.default ? ' (default)' : ''}
                                        </option>
                                    ))}
                                </select>
                                <span style={{ flex: 1 }} />
                                <button
                                    type='button'
                                    title='Send (Enter)'
                                    disabled={!canSend}
                                    onClick={send}
                                    style={{
                                        width: 32,
                                        height: 32,
                                        borderRadius: 9,
                                        border: 'none',
                                        flexShrink: 0,
                                        background: canSend ? C.accent : 'rgba(75,58,47,0.06)',
                                        color: canSend ? C.accentInk : C.faint,
                                        cursor: canSend ? 'pointer' : 'not-allowed',
                                        display: 'flex',
                                        alignItems: 'center',
                                        justifyContent: 'center',
                                        transition: 'background .15s',
                                    }}
                                >
                                    {icon('M12 19V5 M5 12l7-7 7 7', 16)}
                                </button>
                            </div>
                        </div>
                        <p style={{ margin: '7px 2px 0', color: C.faint, fontSize: 9.5, textAlign: 'center' }}>
                            {config.name} can make mistakes. Review before approving any action.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default AiChatWidget;
