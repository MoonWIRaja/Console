import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useLocation } from 'react-router-dom';
import { useStoreState } from 'easy-peasy';
import useSiteBranding from '@/hooks/useSiteBranding';
import Avatar from '@/components/Avatar';
import { formatBytes, uploadCloudFile } from '@/api/cloud';
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
    continueAiTask,
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
    web_search: 'searched the web',
    read_web_page: 'read a web page',
    search_modpacks: 'looked up modpacks',
    get_plans_and_pricing: 'checked plans & prices',
    get_my_billing: 'checked your billing',
    get_download_link: 'made a download link',
};

// Consecutive tool-only steps are folded into the next reply so a long task shows one row, not one bubble per step.
const foldToolSteps = (messages: AiMessage[]): AiMessage[] => {
    const out: AiMessage[] = [];
    let pending: string[] = [];
    messages.forEach((m) => {
        const toolOnly =
            m.role === 'assistant' && m.content === '' && m.actions.length === 0 && !(m.attachments || []).length;
        if (toolOnly) {
            pending = pending.concat(m.tools);
            return;
        }
        if (m.role === 'assistant' && pending.length) {
            out.push({ ...m, tools: pending.concat(m.tools) });
        } else {
            if (pending.length) {
                out.push({ id: -out.length - 1, role: 'assistant', content: '', created_at: null, model: null, tools: pending, actions: [] });
            }
            out.push(m);
        }
        pending = [];
    });
    if (pending.length) {
        out.push({ id: -out.length - 1, role: 'assistant', content: '', created_at: null, model: null, tools: pending, actions: [] });
    }
    return out;
};

const ACTION_TITLES: Record<string, string> = {
    write_file: 'Edit file',
    create_directory: 'Create folder',
    delete_files: 'Delete files',
    rename_file: 'Rename / move',
    send_console_command: 'Run console command',
    power_action: 'Power action',
    download_to_server: 'Download into server',
    copy_file: 'Copy file',
    compress_files: 'Create archive',
    decompress_file: 'Extract archive',
    chmod_file: 'Change permissions',
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

// ---------------------------------------------------------------- Markdown (no dependencies)

const escapeRe = (s: string) => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

// Wraps search matches in <mark>; used on every plain text run.
const highlightText = (text: string, query: string, key: string): React.ReactNode => {
    if (!query) return text;
    const parts = text.split(new RegExp(`(${escapeRe(query)})`, 'ig'));
    if (parts.length === 1) return text;
    return parts.map((p, i) =>
        i % 2 === 1 ? (
            <mark key={`${key}-h${i}`} className='anney-hit'>
                {p}
            </mark>
        ) : (
            <React.Fragment key={`${key}-h${i}`}>{p}</React.Fragment>
        )
    );
};

const INLINE_RE =
    /(`[^`\n]+`|\*\*[^*\n]+?\*\*|__[^_\n]+?__|~~[^~\n]+?~~|\[[^\]\n]+\]\([^)\s]+\)|https?:\/\/[^\s<>()]+[^\s<>().,;:!?'"]|\*[^*\s][^*\n]*?\*|(?<![A-Za-z0-9])_[^_\s][^_\n]*?_(?![A-Za-z0-9]))/g;

const renderInline = (text: string, keyBase: string, query = ''): React.ReactNode[] =>
    text.split(INLINE_RE).map((part, i) => {
        const key = `${keyBase}-${i}`;
        if (!part) return null;
        if (i % 2 === 0) return <React.Fragment key={key}>{highlightText(part, query, key)}</React.Fragment>;
        if (part.startsWith('`')) {
            return (
                <code key={key} className='anney-code'>
                    {highlightText(part.slice(1, -1), query, key)}
                </code>
            );
        }
        if (part.startsWith('**') || part.startsWith('__')) {
            return <strong key={key}>{renderInline(part.slice(2, -2), key, query)}</strong>;
        }
        if (part.startsWith('~~')) return <del key={key}>{renderInline(part.slice(2, -2), key, query)}</del>;
        if (part.startsWith('[')) {
            const m = part.match(/^\[([^\]]+)\]\(([^)\s]+)\)$/);
            if (m && /^https?:\/\//i.test(m[2])) {
                return (
                    <a key={key} href={m[2]} target='_blank' rel='noopener noreferrer' className='anney-link'>
                        {renderInline(m[1], key, query)}
                    </a>
                );
            }
            return <React.Fragment key={key}>{part}</React.Fragment>;
        }
        if (/^https?:\/\//i.test(part)) {
            return (
                <a key={key} href={part} target='_blank' rel='noopener noreferrer' className='anney-link'>
                    {highlightText(part, query, key)}
                </a>
            );
        }
        return <em key={key}>{renderInline(part.slice(1, -1), key, query)}</em>;
    });

type MdBlock =
    | { kind: 'code'; lang: string; text: string }
    | { kind: 'heading'; level: number; text: string }
    | { kind: 'hr' }
    | { kind: 'quote'; lines: string[] }
    | { kind: 'list'; items: { indent: number; ordered: boolean; num: number; text: string }[] }
    | { kind: 'table'; head: string[]; rows: string[][]; align: ('left' | 'center' | 'right')[] }
    | { kind: 'para'; lines: string[] };

const LIST_RE = /^(\s*)([-*+]|\d+[.)])\s+(.*)$/;
const splitRow = (line: string) =>
    line
        .trim()
        .replace(/^\||\|$/g, '')
        .split('|')
        .map((c) => c.trim());

const parseMarkdown = (src: string): MdBlock[] => {
    const lines = src.replace(/\r\n/g, '\n').split('\n');
    const out: MdBlock[] = [];
    let i = 0;
    while (i < lines.length) {
        const line = lines[i];
        const fence = line.match(/^\s*```\s*([\w+-]*)/);
        if (fence) {
            const body: string[] = [];
            i++;
            while (i < lines.length && !/^\s*```/.test(lines[i])) body.push(lines[i++]);
            i++;
            out.push({ kind: 'code', lang: fence[1], text: body.join('\n') });
            continue;
        }
        if (!line.trim()) {
            i++;
            continue;
        }
        const h = line.match(/^\s*(#{1,6})\s+(.*?)\s*#*\s*$/);
        if (h) {
            out.push({ kind: 'heading', level: h[1].length, text: h[2] });
            i++;
            continue;
        }
        if (/^\s*([-*_])(\s*\1){2,}\s*$/.test(line)) {
            out.push({ kind: 'hr' });
            i++;
            continue;
        }
        if (/^\s*>/.test(line)) {
            const q: string[] = [];
            while (i < lines.length && /^\s*>/.test(lines[i])) q.push(lines[i++].replace(/^\s*>\s?/, ''));
            out.push({ kind: 'quote', lines: q });
            continue;
        }
        if (line.includes('|') && i + 1 < lines.length && /^\s*\|?\s*:?-{2,}:?\s*(\|\s*:?-{2,}:?\s*)*\|?\s*$/.test(lines[i + 1])) {
            const head = splitRow(line);
            const align = splitRow(lines[i + 1]).map((c) =>
                c.startsWith(':') && c.endsWith(':') ? 'center' : c.endsWith(':') ? 'right' : 'left'
            ) as ('left' | 'center' | 'right')[];
            i += 2;
            const rows: string[][] = [];
            while (i < lines.length && lines[i].includes('|') && lines[i].trim()) rows.push(splitRow(lines[i++]));
            out.push({ kind: 'table', head, rows, align });
            continue;
        }
        if (LIST_RE.test(line)) {
            const items: { indent: number; ordered: boolean; num: number; text: string }[] = [];
            while (i < lines.length) {
                const m = lines[i].match(LIST_RE);
                if (m) {
                    items.push({
                        indent: m[1].replace(/\t/g, '    ').length,
                        ordered: /\d/.test(m[2]),
                        num: parseInt(m[2], 10) || 1,
                        text: m[3],
                    });
                    i++;
                } else if (lines[i].trim() && /^\s{2,}/.test(lines[i]) && items.length) {
                    items[items.length - 1].text += ' ' + lines[i].trim();
                    i++;
                } else break;
            }
            out.push({ kind: 'list', items });
            continue;
        }
        const para: string[] = [];
        while (
            i < lines.length &&
            lines[i].trim() &&
            !/^\s*(```|#{1,6}\s|>)/.test(lines[i]) &&
            !LIST_RE.test(lines[i]) &&
            !/^\s*([-*_])(\s*\1){2,}\s*$/.test(lines[i])
        )
            para.push(lines[i++]);
        if (para.length === 0) para.push(lines[i++]);
        out.push({ kind: 'para', lines: para });
    }
    return out;
};

// Nested list from flat items using indentation.
const renderList = (items: { indent: number; ordered: boolean; num: number; text: string }[], key: string, query: string): React.ReactNode => {
    const build = (start: number, indent: number): [React.ReactNode, number] => {
        const ordered = items[start].ordered;
        const children: React.ReactNode[] = [];
        let i = start;
        while (i < items.length && items[i].indent >= indent) {
            if (items[i].indent > indent) {
                const [nested, next] = build(i, items[i].indent);
                children.push(<React.Fragment key={`${key}-n${i}`}>{nested}</React.Fragment>);
                i = next;
                continue;
            }
            const item = items[i];
            const sub: React.ReactNode[] = [];
            let j = i + 1;
            if (j < items.length && items[j].indent > indent) {
                const [nested, next] = build(j, items[j].indent);
                sub.push(nested);
                j = next;
            }
            const task = item.text.match(/^\[( |x|X)\]\s+(.*)$/);
            children.push(
                <li key={`${key}-${i}`}>
                    {task ? (
                        <>
                            <span className='anney-task'>{task[1].trim() ? '☑' : '☐'}</span> {renderInline(task[2], `${key}-${i}`, query)}
                        </>
                    ) : (
                        renderInline(item.text, `${key}-${i}`, query)
                    )}
                    {sub}
                </li>
            );
            i = j;
        }
        return [
            ordered ? (
                <ol key={key + start} start={items[start].num} className='anney-ol'>
                    {children}
                </ol>
            ) : (
                <ul key={key + start} className='anney-ul'>
                    {children}
                </ul>
            ),
            i,
        ];
    };
    return build(0, items[0].indent)[0];
};

const CodeBlock = ({ lang, text }: { lang: string; text: string }) => {
    const [copied, setCopied] = useState(false);
    return (
        <div className='anney-pre'>
            <div className='anney-pre-head'>
                <span>{lang || 'code'}</span>
                <button
                    type='button'
                    onClick={() => {
                        navigator.clipboard?.writeText(text).then(() => {
                            setCopied(true);
                            setTimeout(() => setCopied(false), 1200);
                        });
                    }}
                >
                    {copied ? 'Copied' : 'Copy'}
                </button>
            </div>
            <pre>{text}</pre>
        </div>
    );
};

const Markdown = ({ text, query = '' }: { text: string; query?: string }) => (
    <div className='anney-md'>
        {parseMarkdown(text).map((b, i) => {
            const k = `b${i}`;
            switch (b.kind) {
                case 'code':
                    return <CodeBlock key={k} lang={b.lang} text={b.text} />;
                case 'heading': {
                    const Tag = (`h${Math.min(b.level + 2, 6)}` as unknown) as keyof JSX.IntrinsicElements;
                    return (
                        <Tag key={k} className={`anney-h anney-h${b.level}`}>
                            {renderInline(b.text, k, query)}
                        </Tag>
                    );
                }
                case 'hr':
                    return <hr key={k} className='anney-hr' />;
                case 'quote':
                    return (
                        <blockquote key={k} className='anney-quote'>
                            <Markdown text={b.lines.join('\n')} query={query} />
                        </blockquote>
                    );
                case 'list':
                    return <React.Fragment key={k}>{renderList(b.items, k, query)}</React.Fragment>;
                case 'table':
                    return (
                        <div key={k} className='anney-table-wrap'>
                            <table className='anney-table'>
                                <thead>
                                    <tr>
                                        {b.head.map((c, ci) => (
                                            <th key={ci} style={{ textAlign: b.align[ci] || 'left' }}>
                                                {renderInline(c, `${k}h${ci}`, query)}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {b.rows.map((r, ri) => (
                                        <tr key={ri}>
                                            {b.head.map((_, ci) => (
                                                <td key={ci} style={{ textAlign: b.align[ci] || 'left' }}>
                                                    {renderInline(r[ci] || '', `${k}r${ri}c${ci}`, query)}
                                                </td>
                                            ))}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    );
                default:
                    return (
                        <p key={k} className='anney-p'>
                            {b.lines.map((l, li) => (
                                <React.Fragment key={li}>
                                    {li > 0 && <br />}
                                    {renderInline(l, `${k}l${li}`, query)}
                                </React.Fragment>
                            ))}
                        </p>
                    );
            }
        })}
    </div>
);

const MARKDOWN_CSS = `
.anney-md { font-size: 12.5px; line-height: 1.6; word-break: break-word; }
.anney-md > :first-child { margin-top: 0 !important; }
.anney-md > :last-child { margin-bottom: 0 !important; }
.anney-p { margin: 0 0 8px; }
.anney-h { margin: 12px 0 6px; font-weight: 900; color: ${C.brand}; line-height: 1.3; }
.anney-h1 { font-size: 16px; } .anney-h2 { font-size: 14.5px; } .anney-h3 { font-size: 13.5px; } .anney-h4, .anney-h5, .anney-h6 { font-size: 12.5px; }
.anney-hr { border: 0; border-top: 1px dashed ${C.border}; margin: 10px 0; }
.anney-ul, .anney-ol { margin: 0 0 8px; padding-left: 20px; }
.anney-ul .anney-ul, .anney-ol .anney-ol, .anney-ul .anney-ol, .anney-ol .anney-ul { margin: 2px 0 2px; }
.anney-ul { list-style: disc; } .anney-ul .anney-ul { list-style: circle; } .anney-ol { list-style: decimal; }
.anney-md li { margin: 2px 0; } .anney-md li::marker { color: ${C.brand}; }
.anney-task { color: ${C.brand}; }
.anney-quote { margin: 6px 0 8px; padding: 4px 10px; border-left: 3px solid ${C.brand}; background: rgba(116,34,32,.05); border-radius: 0 6px 6px 0; color: inherit; font-size: inherit; }
.anney-code { background: rgba(75,58,47,0.08); padding: 1px 5px; border-radius: 4px; font-size: .92em; }
.anney-link { color: ${C.agent} !important; text-decoration: underline; text-underline-offset: 2px; word-break: break-all; }
.anney-pre { margin: 6px 0 8px; border: 1px solid ${C.border}; border-radius: 8px; overflow: hidden; background: ${C.bg}; }
.anney-pre-head { display: flex; justify-content: space-between; align-items: center; padding: 3px 8px; font-size: 10px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: ${C.dim}; border-bottom: 1px solid ${C.border}; }
.anney-pre-head button { border: 0; background: none; color: ${C.brand}; font-size: 10px; font-weight: 800; cursor: pointer; padding: 0; }
.anney-pre pre { margin: 0; padding: 8px 10px; overflow-x: auto; font-size: 11.5px; white-space: pre; background: transparent; border: 0; }
.anney-table-wrap { overflow-x: auto; margin: 6px 0 8px; border: 1px solid ${C.border}; border-radius: 8px; }
.anney-table { border-collapse: collapse; width: 100%; font-size: 11.5px; }
.anney-table th { background: rgba(116,34,32,.06); font-weight: 800; color: ${C.brand}; }
.anney-table th, .anney-table td { padding: 5px 8px; border-bottom: 1px solid ${C.border}; vertical-align: top; }
.anney-table tr:last-child td { border-bottom: 0; }
mark.anney-hit { background: #F7D774; color: inherit; border-radius: 3px; padding: 0 1px; }
mark.anney-hit.current { background: #E8A23B; box-shadow: 0 0 0 1px #9A5B00; }
`;

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
                {action.tool === 'download_to_server' && (
                    <div style={{ wordBreak: 'break-all' }}>
                        <code>{String(args.url)}</code> → <code>{String(args.directory || '/')}{args.filename ? '/' + String(args.filename) : ''}</code>
                    </div>
                )}
                {(action.tool === 'copy_file' || action.tool === 'decompress_file') && <code>{String(args.path)}</code>}
                {action.tool === 'chmod_file' && (
                    <span>
                        <code>{String(args.path)}</code> → <code>{String(args.mode)}</code>
                    </span>
                )}
                {action.tool === 'compress_files' &&
                    (Array.isArray(args.paths) ? args.paths : []).map((p: string) => (
                        <div key={p}>
                            <code>{p}</code>
                        </div>
                    ))}
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
const REPLY_ICON = 'M9 14 4 9l5-5 M4 9h10.5A5.5 5.5 0 0 1 20 14.5V20';
const FILE_ICON = 'M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z M14 3v5h5';

const Attachments = ({ message }: { message: AiMessage }) =>
    message.attachments && message.attachments.length > 0 ? (
        <div style={{ display: 'flex', flexWrap: 'wrap', gap: 6, marginTop: 6 }}>
            {message.attachments.map((file, index) => {
                const body = (
                    <>
                        {smallIcon(FILE_ICON)}
                        <span style={{ overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                            {file.name}
                        </span>
                        <span style={{ color: file.error ? C.remove : C.faint, flexShrink: 0 }}>
                            {file.error ? 'not saved' : formatBytes(file.size)}
                        </span>
                    </>
                );
                const style: React.CSSProperties = {
                    display: 'inline-flex',
                    alignItems: 'center',
                    gap: 6,
                    fontSize: 11,
                    maxWidth: 240,
                    color: C.text,
                    background: C.bg,
                    border: `1px solid ${file.error ? C.remove : C.border}`,
                    borderRadius: 8,
                    padding: '4px 8px',
                    textDecoration: 'none',
                };

                return file.download_url ? (
                    <a key={index} href={file.download_url} style={style} title='Download'>
                        {body}
                    </a>
                ) : (
                    <span key={index} style={style} title={file.error || undefined}>
                        {body}
                    </span>
                );
            })}
        </div>
    ) : null;

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
    onReply,
    query = '',
}: {
    message: AiMessage;
    query?: string;
    busy: boolean;
    onDecide: (action: AiAction, decision: 'approve' | 'reject') => void;
    onEdit: (message: AiMessage, content: string) => void;
    onReply: (message: AiMessage) => void;
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
            {!editing && message.id > 0 && (
                <MessageTool title='Reply' onClick={() => onReply(message)}>
                    {smallIcon(REPLY_ICON)}
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
                        message.content !== '' && (
                            <div style={bubble}>
                                <Markdown text={message.content} query={query} />
                            </div>
                        )
                    )}
                    <Attachments message={message} />
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
                        {Array.from(new Set(message.tools)).map((tool) => {
                            const count = message.tools.filter((t) => t === tool).length;
                            return (
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
                                <span style={{ color: C.accent }}>›</span> {TOOL_LABELS[tool] || tool.replace(/_/g, ' ')}
                                {count > 1 && <span style={{ color: C.accent, fontWeight: 700 }}> ×{count}</span>}
                            </span>
                            );
                        })}
                    </div>
                )}
                {message.content !== '' && (
                    <div style={bubble}>
                        <Markdown text={message.content} query={query} />
                    </div>
                )}
                {message.actions.map((action) => (
                    <ActionCard key={action.uuid} action={action} busy={busy} onDecide={onDecide} />
                ))}
                <Attachments message={message} />
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
    const [files, setFiles] = useState<File[]>([]);
    const [uploadNote, setUploadNote] = useState<string | null>(null);
    const [replyTo, setReplyTo] = useState<AiMessage | null>(null);
    const fileInputRef = useRef<HTMLInputElement>(null);
    const [dragging, setDragging] = useState(false);
    const dragDepth = useRef(0);
    const hasFiles = (e: React.DragEvent) => Array.from(e.dataTransfer?.types || []).includes('Files');
    const [busy, setBusy] = useState(false);
    const [searchOpen, setSearchOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [hit, setHit] = useState(0);
    const [hitCount, setHitCount] = useState(0);
    const searchRef = useRef<HTMLInputElement>(null);
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

    // Search inside the open chat: count matches, mark the current one and scroll to it.
    useEffect(() => {
        const marks = Array.from(scrollRef.current?.querySelectorAll<HTMLElement>('mark.anney-hit') || []);
        setHitCount(marks.length);
        marks.forEach((m) => m.classList.remove('current'));
        if (!marks.length) return;
        const index = ((hit % marks.length) + marks.length) % marks.length;
        marks[index].classList.add('current');
        marks[index].scrollIntoView({ block: 'center', behavior: 'smooth' });
    }, [query, hit, active?.messages, searchOpen]);

    useEffect(() => {
        if (!open) return;
        const onKey = (e: KeyboardEvent) => {
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'f' && document.activeElement?.closest('.anney-panel')) {
                e.preventDefault();
                setSearchOpen(true);
                setTimeout(() => searchRef.current?.focus(), 30);
            }
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [open]);

    const closeSearch = () => {
        setSearchOpen(false);
        setQuery('');
        setHit(0);
    };

    const run = async (task: () => Promise<AiConversation>) => {
        setBusy(true);
        setError(null);
        try {
            let result = await task();
            setActive(result);
            // Long tasks are split across requests; keep going until the model has answered.
            for (let i = 0; result.continuing && i < 50; i++) {
                result = await continueAiTask(result.uuid, mode, effectiveModel?.id ?? null);
                setActive(result);
            }
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
        const typed = input.trim();
        if ((!typed && files.length === 0) || busy) return;
        const quote = replyTo
            ? replyTo.content
                  .trim()
                  .split('\n')
                  .slice(0, 6)
                  .map((line) => `> ${line}`)
                  .join('\n') + '\n\n'
            : '';
        const text = quote + typed;
        const attached = files;
        setInput('');
        setFiles([]);
        setReplyTo(null);

        const optimistic: AiMessage = {
            id: -Date.now(),
            role: 'user',
            content: text,
            created_at: new Date().toISOString(),
            model: null,
            tools: [],
            actions: [],
            attachments: attached.map((f) => ({ name: f.name, size: f.size, download_url: null })),
        };
        setActive((prev) => (prev ? { ...prev, messages: [...prev.messages, optimistic] } : prev));

        run(async () => {
            const conversation = active ?? (await createAiConversation(currentServer));
            if (!active) setActive({ ...conversation, messages: [optimistic] });

            const direct: File[] = [];
            const cloudIds: string[] = [];
            const failed: { name: string; size: number; error: string }[] = [];

            for (const file of attached) {
                try {
                    const stored = await uploadCloudFile(file, (percent) =>
                        setUploadNote(`Uploading ${file.name}… ${percent}%`)
                    );
                    cloudIds.push(stored.uuid);
                } catch (e) {
                    const reason = httpErrorToHuman(e);
                    if (/not available/i.test(reason) && file.size <= 100 * 1024 * 1024) {
                        direct.push(file);
                    } else {
                        failed.push({ name: file.name, size: file.size, error: reason });
                    }
                }
            }
            setUploadNote(null);

            return sendAiMessage(conversation.uuid, text, mode, effectiveModel?.id ?? null, direct, cloudIds, failed);
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
    const canSend = !busy && (input.trim() !== '' || files.length > 0);
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
        <style>{`${MARKDOWN_CSS}
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
            onDragEnter={(e) => {
                if (!hasFiles(e) || !active) return;
                e.preventDefault();
                dragDepth.current += 1;
                setDragging(true);
            }}
            onDragOver={(e) => {
                if (hasFiles(e) && active) e.preventDefault();
            }}
            onDragLeave={(e) => {
                if (!hasFiles(e)) return;
                dragDepth.current = Math.max(0, dragDepth.current - 1);
                if (dragDepth.current === 0) setDragging(false);
            }}
            onDrop={(e) => {
                if (!hasFiles(e)) return;
                e.preventDefault();
                dragDepth.current = 0;
                setDragging(false);
                if (!active || busy) return;
                const dropped = Array.from(e.dataTransfer.files || []);
                if (dropped.length) setFiles((list) => [...list, ...dropped].slice(0, 10));
            }}
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
            {dragging && (
                <div
                    style={{
                        position: 'absolute',
                        inset: 0,
                        zIndex: 50,
                        background: 'rgba(254,249,225,.92)',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        pointerEvents: 'none',
                    }}
                >
                    <div
                        style={{
                            border: `3px dashed ${C.borderStrong}`,
                            borderRadius: 16,
                            padding: '26px 34px',
                            textAlign: 'center',
                            color: C.borderStrong,
                            fontWeight: 900,
                            fontSize: 13,
                            letterSpacing: '.08em',
                            textTransform: 'uppercase',
                            background: C.bg,
                            boxShadow: `5px 5px 0 ${C.borderStrong}`,
                        }}
                    >
                        Drop files to attach
                        <div style={{ fontSize: 10, fontWeight: 600, letterSpacing: 0, textTransform: 'none', marginTop: 6, color: C.dim }}>
                            Images, logs, audio, PDF - up to 10 files
                        </div>
                    </div>
                </div>
            )}
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
                        style={{ ...headerButton, ...(searchOpen ? { color: C.brand, background: 'rgba(116,34,32,.08)' } : {}) }}
                        title='Search in chat (Ctrl+F)'
                        onClick={() => {
                            if (searchOpen) {
                                closeSearch();
                            } else {
                                setSearchOpen(true);
                                setTimeout(() => searchRef.current?.focus(), 30);
                            }
                        }}
                    >
                        {icon('M11 4a7 7 0 1 0 0 14 7 7 0 0 0 0-14z M20 20l-4.2-4.2')}
                    </button>
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
                    {searchOpen && (
                        <div
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                gap: 6,
                                padding: '8px 12px',
                                borderBottom: `1px solid ${C.border}`,
                                background: C.raised,
                            }}
                        >
                            {icon('M11 4a7 7 0 1 0 0 14 7 7 0 0 0 0-14z M20 20l-4.2-4.2', 13)}
                            <input
                                ref={searchRef}
                                value={query}
                                onChange={(e) => {
                                    setQuery(e.target.value);
                                    setHit(0);
                                }}
                                onKeyDown={(e) => {
                                    if (e.key === 'Enter') {
                                        e.preventDefault();
                                        setHit((h) => h + (e.shiftKey ? -1 : 1));
                                    } else if (e.key === 'Escape') {
                                        closeSearch();
                                    }
                                }}
                                placeholder='Search this chat...'
                                style={{
                                    flex: 1,
                                    minWidth: 0,
                                    border: `1px solid ${C.border}`,
                                    borderRadius: 999,
                                    background: C.bg,
                                    color: C.text,
                                    fontSize: 12,
                                    padding: '5px 12px',
                                    outline: 'none',
                                    fontFamily: 'inherit',
                                }}
                            />
                            <span style={{ fontSize: 11, color: C.dim, minWidth: 48, textAlign: 'center' }}>
                                {query ? (hitCount ? `${((hit % hitCount) + hitCount) % hitCount + 1}/${hitCount}` : '0/0') : ''}
                            </span>
                            <button type='button' className='anney-icon-btn' style={headerButton} title='Previous (Shift+Enter)' onClick={() => setHit((h) => h - 1)} disabled={!hitCount}>
                                {icon('M6 15l6-6 6 6', 13)}
                            </button>
                            <button type='button' className='anney-icon-btn' style={headerButton} title='Next (Enter)' onClick={() => setHit((h) => h + 1)} disabled={!hitCount}>
                                {icon('M6 9l6 6 6-6', 13)}
                            </button>
                            <button type='button' className='anney-icon-btn' style={headerButton} title='Close search' onClick={closeSearch}>
                                {icon('M6 6l12 12 M18 6 6 18', 13)}
                            </button>
                        </div>
                    )}
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
                        {foldToolSteps(active?.messages || []).map((message) => (
                            <MessageBubble
                                key={message.id}
                                message={message}
                                busy={busy}
                                onDecide={decide}
                                onEdit={editMessage}
                                onReply={setReplyTo}
                                query={searchOpen ? query.trim() : ''}
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
                                <span style={{ fontSize: 11.5, color: C.dim }}>{uploadNote ?? 'thinking'}</span>
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
                            {replyTo && (
                                <div
                                    style={{
                                        display: 'flex',
                                        alignItems: 'flex-start',
                                        gap: 8,
                                        marginBottom: 8,
                                        padding: '6px 8px',
                                        borderLeft: `3px solid ${C.accent}`,
                                        background: C.raised,
                                        borderRadius: 6,
                                    }}
                                >
                                    <div style={{ flex: 1, minWidth: 0 }}>
                                        <div style={{ fontSize: 10, fontWeight: 700, color: C.accent }}>
                                            Replying to {replyTo.role === 'user' ? 'yourself' : config.name}
                                        </div>
                                        <div
                                            style={{
                                                fontSize: 11,
                                                color: C.dim,
                                                overflow: 'hidden',
                                                textOverflow: 'ellipsis',
                                                whiteSpace: 'nowrap',
                                            }}
                                        >
                                            {replyTo.content}
                                        </div>
                                    </div>
                                    <button
                                        type='button'
                                        title='Cancel reply'
                                        onClick={() => setReplyTo(null)}
                                        style={{
                                            background: 'none',
                                            border: 'none',
                                            color: C.faint,
                                            cursor: 'pointer',
                                            padding: 0,
                                        }}
                                    >
                                        {icon('M6 6l12 12 M18 6 6 18', 13)}
                                    </button>
                                </div>
                            )}
                            {files.length > 0 && (
                                <div style={{ display: 'flex', flexWrap: 'wrap', gap: 6, marginBottom: 8 }}>
                                    {files.map((file, index) => (
                                        <span
                                            key={`${file.name}-${index}`}
                                            style={{
                                                display: 'inline-flex',
                                                alignItems: 'center',
                                                gap: 6,
                                                fontSize: 11,
                                                background: C.raised,
                                                border: `1px solid ${C.border}`,
                                                borderRadius: 8,
                                                padding: '3px 6px 3px 8px',
                                                maxWidth: 220,
                                            }}
                                        >
                                            <span
                                                style={{
                                                    overflow: 'hidden',
                                                    textOverflow: 'ellipsis',
                                                    whiteSpace: 'nowrap',
                                                }}
                                            >
                                                {file.name}
                                            </span>
                                            <span style={{ color: C.faint, flexShrink: 0 }}>
                                                {formatBytes(file.size)}
                                            </span>
                                            <button
                                                type='button'
                                                title='Remove'
                                                onClick={() => setFiles((list) => list.filter((_, i) => i !== index))}
                                                style={{
                                                    background: 'none',
                                                    border: 'none',
                                                    color: C.faint,
                                                    cursor: 'pointer',
                                                    padding: 0,
                                                    display: 'flex',
                                                }}
                                            >
                                                {icon('M6 6l12 12 M18 6 6 18', 11)}
                                            </button>
                                        </span>
                                    ))}
                                </div>
                            )}
                            <input
                                ref={fileInputRef}
                                type='file'
                                multiple
                                style={{ display: 'none' }}
                                onChange={(e) => {
                                    const picked = Array.from(e.target.files || []);
                                    setFiles((list) => [...list, ...picked].slice(0, 10));
                                    e.target.value = '';
                                }}
                            />
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
                                <button
                                    type='button'
                                    title='Attach files - or drag and drop them here (up to 10, any size). They are saved to your Cloud Storage; Anney reads text files.'
                                    disabled={busy || files.length >= 10}
                                    onClick={() => fileInputRef.current?.click()}
                                    className='anney-icon-btn'
                                    style={{ ...headerButton, border: `1px solid ${C.border}`, width: 30, height: 28 }}
                                >
                                    {icon(
                                        'M20.5 11.5 12 20a5 5 0 0 1-7-7l8.5-8.5a3.3 3.3 0 0 1 4.7 4.7L9.6 17.8a1.7 1.7 0 0 1-2.4-2.4L15 7.6',
                                        15
                                    )}
                                </button>
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
