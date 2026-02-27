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
        background: #000000;
        color: #e5e7eb;
        font-size: 13px;
        line-height: 1.5rem;
    }

    .CodeMirror-scroll,
    .CodeMirror-sizer,
    .CodeMirror-lines,
    .CodeMirror-code,
    .CodeMirror-linebackground,
    .CodeMirror-line {
        background: #000000 !important;
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
        background: #000000 !important;
        background-color: #000000 !important;
        border-right-color: #1f2a14 !important;
    }

    .CodeMirror-gutters {
        border-right: 1px solid #1f2a14 !important;
    }

    .CodeMirror-cursor {
        border-left: 2px solid #a3ff12 !important;
    }

    .CodeMirror-selected {
        background: rgba(163, 255, 18, 0.18) !important;
    }

    .CodeMirror-activeline-background {
        background: rgba(163, 255, 18, 0.06);
    }

    .CodeMirror-hints {
        ${tw`rounded-lg border border-[#1f2a14]`};
        background: #050505 !important;
        color: #e5e7eb;
        box-shadow: 0 16px 28px rgba(0, 0, 0, 0.55);
        z-index: 60;
    }

    .CodeMirror-hint {
        color: #e5e7eb;
    }

    .CodeMirror-hint-active {
        background: rgba(163, 255, 18, 0.16) !important;
        color: #d9ff93 !important;
    }

    .CodeMirror-linenumber {
        padding: 1px 12px 0 12px !important;
        color: #9ca3af;
    }

    .CodeMirror-foldmarker {
        color: #d9ff93;
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

export default ({ style, initialContent, filename, mode, fetchContent, onContentSaved, onModeChanged }: Props) => {
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
            spellcheck: true,
            autocorrect: false,
            autocapitalize: false,
            lint: false,
            // @ts-expect-error this property is actually used, the d.ts file for CodeMirror is incorrect.
            autoCloseBrackets: true,
            matchBrackets: true,
            gutters: ['CodeMirror-linenumbers', 'CodeMirror-foldgutter'],
            extraKeys: {
                'Ctrl-Space': 'autocomplete',
                'Cmd-Space': 'autocomplete',
            },
            hintOptions: {
                completeSingle: false,
                alignWithWord: true,
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

        editor.addKeyMap({
            'Ctrl-S': () => onContentSaved(),
            'Cmd-S': () => onContentSaved(),
        });

        editor.on('inputRead', (cm, change) => {
            if (!change.text || !change.text.length) {
                return;
            }

            const typed = change.text[0];
            if (!typed || typed.length !== 1) {
                return;
            }

            if (!/[A-Za-z0-9_.\-]/.test(typed)) {
                return;
            }

            if (!cm.state.completionActive) {
                cm.showHint({ completeSingle: false });
            }
        });

        fetchContent(() => Promise.resolve(editor.getValue()));
    }, [editor, fetchContent, onContentSaved]);

    return (
        <EditorContainer style={style}>
            <textarea ref={ref} />
        </EditorContainer>
    );
};
