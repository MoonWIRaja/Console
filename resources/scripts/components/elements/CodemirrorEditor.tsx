import React, { useCallback, useEffect, useState } from 'react';
import CodeMirror from 'codemirror';
import styled from 'styled-components/macro';
import tw from 'twin.macro';
import modes from '@/modes';

require('codemirror/lib/codemirror.css');
require('codemirror/theme/ayu-mirage.css');
require('codemirror/addon/edit/closebrackets');
require('codemirror/addon/edit/closetag');
require('codemirror/addon/edit/matchbrackets');
require('codemirror/addon/edit/matchtags');
require('codemirror/addon/edit/trailingspace');
require('codemirror/addon/fold/foldcode');
require('codemirror/addon/fold/foldgutter.css');
require('codemirror/addon/fold/foldgutter');
require('codemirror/addon/fold/brace-fold');
require('codemirror/addon/fold/comment-fold');
require('codemirror/addon/fold/indent-fold');
require('codemirror/addon/fold/markdown-fold');
require('codemirror/addon/fold/xml-fold');
require('codemirror/addon/hint/css-hint');
require('codemirror/addon/hint/html-hint');
require('codemirror/addon/hint/javascript-hint');
require('codemirror/addon/hint/anyword-hint');
require('codemirror/addon/hint/show-hint.css');
require('codemirror/addon/hint/show-hint');
require('codemirror/addon/hint/sql-hint');
require('codemirror/addon/hint/xml-hint');
require('codemirror/addon/mode/simple');
require('codemirror/addon/dialog/dialog.css');
require('codemirror/addon/dialog/dialog');
require('codemirror/addon/scroll/annotatescrollbar');
require('codemirror/addon/scroll/scrollpastend');
require('codemirror/addon/scroll/simplescrollbars.css');
require('codemirror/addon/scroll/simplescrollbars');
require('codemirror/addon/search/jump-to-line');
require('codemirror/addon/search/match-highlighter');
require('codemirror/addon/search/matchesonscrollbar.css');
require('codemirror/addon/search/matchesonscrollbar');
require('codemirror/addon/search/search');
require('codemirror/addon/search/searchcursor');

require('codemirror/mode/brainfuck/brainfuck');
require('codemirror/mode/clike/clike');
require('codemirror/mode/css/css');
require('codemirror/mode/dart/dart');
require('codemirror/mode/diff/diff');
require('codemirror/mode/dockerfile/dockerfile');
require('codemirror/mode/erlang/erlang');
require('codemirror/mode/gfm/gfm');
require('codemirror/mode/go/go');
require('codemirror/mode/handlebars/handlebars');
require('codemirror/mode/htmlembedded/htmlembedded');
require('codemirror/mode/htmlmixed/htmlmixed');
require('codemirror/mode/http/http');
require('codemirror/mode/javascript/javascript');
require('codemirror/mode/jsx/jsx');
require('codemirror/mode/julia/julia');
require('codemirror/mode/lua/lua');
require('codemirror/mode/markdown/markdown');
require('codemirror/mode/nginx/nginx');
require('codemirror/mode/perl/perl');
require('codemirror/mode/php/php');
require('codemirror/mode/properties/properties');
require('codemirror/mode/protobuf/protobuf');
require('codemirror/mode/pug/pug');
require('codemirror/mode/python/python');
require('codemirror/mode/rpm/rpm');
require('codemirror/mode/ruby/ruby');
require('codemirror/mode/rust/rust');
require('codemirror/mode/sass/sass');
require('codemirror/mode/shell/shell');
require('codemirror/mode/smarty/smarty');
require('codemirror/mode/sql/sql');
require('codemirror/mode/swift/swift');
require('codemirror/mode/toml/toml');
require('codemirror/mode/twig/twig');
require('codemirror/mode/vue/vue');
require('codemirror/mode/xml/xml');
require('codemirror/mode/yaml/yaml');

const EditorContainer = styled.div`
    min-height: 20rem;
    height: 100%;
    ${tw`relative`};

    > div {
        ${tw`h-full`};
    }

    .CodeMirror {
        height: 100% !important;
        ${tw`border-0`};
        background: var(--card);
        color: #e5e7eb;
        font-size: 13px;
        line-height: 1.5rem;
    }

    .CodeMirror-scroll,
    .CodeMirror-sizer,
    .CodeMirror-lines {
        background: var(--card) !important;
    }

    .CodeMirror-code,
    .CodeMirror-line {
        background: transparent !important;
    }

    .CodeMirror-gutters,
    .CodeMirror-gutter,
    .CodeMirror-linenumbers,
    .CodeMirror-linenumber,
    .CodeMirror-gutter-wrapper,
    .CodeMirror-gutter-elt,
    .CodeMirror-foldgutter,
    .CodeMirror-foldgutter-open,
    .CodeMirror-foldgutter-folded,
    .cm-s-ayu-mirage .CodeMirror-gutters,
    .cm-s-ayu-mirage .CodeMirror-gutter,
    .cm-s-ayu-mirage .CodeMirror-linenumbers,
    .cm-s-ayu-mirage .CodeMirror-linenumber,
    .cm-s-ayu-mirage .CodeMirror-foldgutter,
    .cm-s-ayu-mirage .CodeMirror-foldgutter-open,
    .cm-s-ayu-mirage .CodeMirror-foldgutter-folded {
        background: var(--card) !important;
        background-color: var(--card) !important;
        border-right-color: var(--border) !important;
    }

    .CodeMirror-gutters {
        border-right: 1px solid var(--border) !important;
    }

    .CodeMirror-cursor {
        border-left: 2px solid var(--primary) !important;
    }

    .CodeMirror-selected {
        background: rgba(var(--primary-rgb), 0.18) !important;
    }

    .CodeMirror-activeline-background {
        background: rgba(var(--primary-rgb), 0.06);
    }

    .CodeMirror-hints {
        ${tw`rounded-lg border border-[color:var(--border)]`};
        background: var(--card) !important;
        color: #e5e7eb;
        box-shadow: 0 16px 28px rgba(12, 12, 12, 0.55);
        z-index: 60;
    }

    .CodeMirror-hint {
        color: #e5e7eb;
    }

    .CodeMirror-hint-active {
        background: rgba(var(--primary-rgb), 0.16) !important;
        color: var(--primary) !important;
    }

    .CodeMirror-linenumber {
        padding: 1px 12px 0 12px !important;
        color: #9ca3af;
    }

    .CodeMirror-foldmarker {
        color: var(--primary);
        text-shadow: none;
        margin-left: 0.25rem;
        margin-right: 0.25rem;
    }
`;

export interface Props {
    style?: React.CSSProperties;
    initialContent?: string;
    mode: string;
    filename?: string;
    onModeChanged: (mode: string) => void;
    onContentChanged?: (content: string) => void;
    fetchContent: (callback: () => Promise<string>) => void;
    onContentSaved: () => void;
}

const findModeByFilename = (filename: string) => {
    for (let i = 0; i < modes.length; i++) {
        const info = modes[i];

        if (info.file && info.file.test(filename)) {
            return info;
        }
    }

    const dot = filename.lastIndexOf('.');
    const ext = dot > -1 && filename.substring(dot + 1, filename.length);

    if (ext) {
        for (let i = 0; i < modes.length; i++) {
            const info = modes[i];
            if (info.ext) {
                for (let j = 0; j < info.ext.length; j++) {
                    if (info.ext[j] === ext) {
                        return info;
                    }
                }
            }
        }
    }

    return undefined;
};

const COMPLETION_TRIGGER = /[A-Za-z0-9_.$:@/-]/;
const COMPLETION_WORD = /[$A-Za-z_./:@-][\w$./:@-]*$/;
const DOCUMENT_WORD = /[$A-Za-z_][\w$./:@-]*/g;
const COMPLETION_SCAN_RADIUS = 250;
const COMPLETION_ITEM_LIMIT = 80;
const COMPLETION_CLOSE_CHARACTERS = new RegExp('[\\s(){};>,\\[\\]]');

const renderHintItem =
    (source: string) => (element: HTMLElement, _self: unknown, data: { displayText?: string; text?: string }) => {
        const label = data.displayText || data.text || '';
        const wrapper = document.createElement('div');
        wrapper.style.display = 'flex';
        wrapper.style.alignItems = 'center';
        wrapper.style.justifyContent = 'space-between';
        wrapper.style.gap = '0.75rem';

        const value = document.createElement('span');
        value.textContent = label;
        value.style.overflow = 'hidden';
        value.style.textOverflow = 'ellipsis';
        value.style.whiteSpace = 'nowrap';

        const meta = document.createElement('span');
        meta.textContent = source;
        meta.style.flexShrink = '0';
        meta.style.fontSize = '10px';
        meta.style.letterSpacing = '0.08em';
        meta.style.opacity = '0.68';
        meta.style.textTransform = 'uppercase';

        wrapper.appendChild(value);
        wrapper.appendChild(meta);
        element.appendChild(wrapper);
    };

const getCompletionPrefix = (tokenText: string, offset: number) => {
    const fragment = tokenText.slice(0, offset);
    return fragment.match(COMPLETION_WORD)?.[0] || '';
};

const getCompletionScore = (candidate: string, prefix: string) => {
    if (!prefix) {
        return 0;
    }

    const lowerCandidate = candidate.toLowerCase();
    const lowerPrefix = prefix.toLowerCase();

    if (candidate === prefix) {
        return 0;
    }

    if (candidate.startsWith(prefix)) {
        return 1;
    }

    if (lowerCandidate.startsWith(lowerPrefix)) {
        return 2;
    }

    if (lowerCandidate.includes(lowerPrefix)) {
        return 3;
    }

    return Number.POSITIVE_INFINITY;
};

const collectDocumentWords = (editor: CodeMirror.Editor, cursor: CodeMirror.Position) => {
    const words = new Set<string>();
    const startLine = Math.max(0, cursor.line - COMPLETION_SCAN_RADIUS);
    const endLine = Math.min(editor.lineCount() - 1, cursor.line + COMPLETION_SCAN_RADIUS);

    for (let line = startLine; line <= endLine; line++) {
        const matches = editor.getLine(line).match(DOCUMENT_WORD) || [];

        matches.forEach((word) => {
            if (word.length > 1) {
                words.add(word);
            }
        });
    }

    return [...words];
};

const createWordHints = (
    words: string[],
    prefix: string,
    from: CodeMirror.Position,
    to: CodeMirror.Position,
    source: string
) =>
    words
        .filter((word) => getCompletionScore(word, prefix) !== Number.POSITIVE_INFINITY)
        .sort((left, right) => {
            const scoreDelta = getCompletionScore(left, prefix) - getCompletionScore(right, prefix);
            if (scoreDelta !== 0) {
                return scoreDelta;
            }

            return left.localeCompare(right);
        })
        .map((word) => ({
            text: word,
            displayText: word,
            from,
            to,
            render: renderHintItem(source),
        }));

const normalizeHelperHints = (
    result:
        | {
              list?: Array<
                  | string
                  | {
                        text?: string;
                        displayText?: string;
                        from?: CodeMirror.Position;
                        to?: CodeMirror.Position;
                        render?: unknown;
                    }
              >;
          }
        | null
        | undefined,
    fallbackFrom: CodeMirror.Position,
    fallbackTo: CodeMirror.Position
) =>
    (result?.list || []).map((entry) => {
        if (typeof entry === 'string') {
            return {
                text: entry,
                displayText: entry,
                from: fallbackFrom,
                to: fallbackTo,
                render: renderHintItem('Mode'),
            };
        }

        const text = entry.text || entry.displayText;
        if (!text) {
            return null;
        }

        return {
            ...entry,
            text,
            displayText: entry.displayText || text,
            from: entry.from || fallbackFrom,
            to: entry.to || fallbackTo,
            render: typeof entry.render === 'function' ? entry.render : renderHintItem('Mode'),
        };
    });

const buildSmartHints = (editor: CodeMirror.Editor, options?: Record<string, unknown>) => {
    const cursor = editor.getCursor();
    const token = editor.getTokenAt(cursor);
    const tokenOffset = Math.max(0, cursor.ch - token.start);
    const prefix = getCompletionPrefix(token.string || '', tokenOffset);
    const from = CodeMirror.Pos(cursor.line, cursor.ch - prefix.length);
    const to = cursor;
    const helperApi = CodeMirror as typeof CodeMirror & {
        hint?: {
            auto?: {
                resolve?: (
                    cm: CodeMirror.Editor,
                    pos: CodeMirror.Position
                ) => ((cm: CodeMirror.Editor, options?: unknown) => { list?: unknown[] } | undefined) | undefined;
            };
        };
    };
    const resolvedHelper = helperApi.hint?.auto?.resolve?.(editor, cursor);
    const modeHints = normalizeHelperHints(resolvedHelper?.(editor, options), from, to).filter(Boolean) as Array<{
        text: string;
        displayText: string;
        from: CodeMirror.Position;
        to: CodeMirror.Position;
        render: (element: HTMLElement) => void;
    }>;
    const keywordHints = createWordHints(
        (editor.getHelper(cursor, 'hintWords') as string[] | undefined) || [],
        prefix,
        from,
        to,
        'Keyword'
    );
    const documentHints = createWordHints(collectDocumentWords(editor, cursor), prefix, from, to, 'File');
    const seen = new Set<string>();
    const list = [...modeHints, ...keywordHints, ...documentHints].filter((entry) => {
        const key = entry.text.toLowerCase();
        if (seen.has(key)) {
            return false;
        }

        seen.add(key);
        return true;
    });

    return {
        list: list.slice(0, COMPLETION_ITEM_LIMIT),
        from,
        to,
    };
};

export default ({
    style,
    initialContent,
    filename,
    mode,
    fetchContent,
    onContentSaved,
    onModeChanged,
    onContentChanged,
}: Props) => {
    const [editor, setEditor] = useState<CodeMirror.Editor>();

    const ref = useCallback((node) => {
        if (!node) return;

        const e = CodeMirror.fromTextArea(node, {
            mode: 'text/plain',
            theme: 'ayu-mirage',
            indentUnit: 4,
            smartIndent: true,
            tabSize: 4,
            indentWithTabs: false,
            lineWrapping: true,
            lineNumbers: true,
            foldGutter: true,
            fixedGutter: true,
            scrollbarStyle: 'overlay',
            coverGutterNextToScrollbar: false,
            readOnly: false,
            showCursorWhenSelecting: false,
            autofocus: false,
            spellcheck: false,
            autocorrect: false,
            autocapitalize: false,
            lint: false,
            resetSelectionOnContextMenu: false,
            // @ts-expect-error this property is actually used, the d.ts file for CodeMirror is incorrect.
            autoCloseBrackets: true,
            matchBrackets: true,
            gutters: ['CodeMirror-linenumbers', 'CodeMirror-foldgutter'],
            extraKeys: {
                'Ctrl-Space': 'autocomplete',
                'Cmd-Space': 'autocomplete',
            },
            hintOptions: {
                hint: buildSmartHints,
                completeSingle: false,
                alignWithWord: true,
                closeCharacters: COMPLETION_CLOSE_CHARACTERS,
            },
        });

        setEditor(e);
    }, []);

    useEffect(() => {
        if (filename === undefined) {
            return;
        }

        onModeChanged(findModeByFilename(filename)?.mime || 'text/plain');
    }, [filename]);

    useEffect(() => {
        editor && editor.setOption('mode', mode);
    }, [editor, mode]);

    useEffect(() => {
        if (editor) {
            editor.setValue(initialContent || '');
            // Reset the history so that "Ctrl+Z" doesn't delete the intial content
            // we just set above.
            editor.setHistory({ done: [], undone: [] });
        }
    }, [editor, initialContent]);

    useEffect(() => {
        if (!editor) {
            fetchContent(() => Promise.reject(new Error('no editor session has been configured')));
            return;
        }

        const saveKeymap = {
            'Ctrl-S': () => onContentSaved(),
            'Cmd-S': () => onContentSaved(),
        };
        const handleInputRead = (cm: CodeMirror.Editor, change: CodeMirror.EditorChange) => {
            if (!change.text || !change.text.length) {
                return;
            }

            const typed = change.text[0];
            if (!typed || typed.length !== 1) {
                return;
            }

            if (!COMPLETION_TRIGGER.test(typed)) {
                return;
            }

            if (!cm.state.completionActive) {
                cm.showHint({
                    hint: buildSmartHints,
                    completeSingle: false,
                    closeCharacters: COMPLETION_CLOSE_CHARACTERS,
                });
            }
        };
        const handleChange = (cm: CodeMirror.Editor) => onContentChanged?.(cm.getValue());

        editor.addKeyMap(saveKeymap);
        editor.on('inputRead', handleInputRead);
        editor.on('change', handleChange);

        fetchContent(() => Promise.resolve(editor.getValue()));

        return () => {
            editor.removeKeyMap(saveKeymap);
            editor.off('inputRead', handleInputRead);
            editor.off('change', handleChange);
        };
    }, [editor, fetchContent, onContentChanged, onContentSaved]);

    return (
        <EditorContainer style={style}>
            <textarea ref={ref} />
        </EditorContainer>
    );
};
