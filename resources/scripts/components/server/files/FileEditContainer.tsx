import React, { useEffect, useMemo, useRef, useState } from 'react';
import getFileContents from '@/api/server/files/getFileContents';
import { httpErrorToHuman } from '@/api/http';
import SpinnerOverlay from '@/components/elements/SpinnerOverlay';
import saveFileContents from '@/api/server/files/saveFileContents';
import FileManagerBreadcrumbs from '@/components/server/files/FileManagerBreadcrumbs';
import { useHistory, useLocation, useParams } from 'react-router';
import FileNameModal from '@/components/server/files/FileNameModal';
import Can from '@/components/elements/Can';
import FlashMessageRender from '@/components/FlashMessageRender';
import PageContentBlock from '@/components/elements/PageContentBlock';
import { ServerError } from '@/components/elements/ScreenBlock';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import tw from 'twin.macro';
import modes from '@/modes';
import useFlash from '@/plugins/useFlash';
import { ServerContext } from '@/state/server';
import ErrorBoundary from '@/components/elements/ErrorBoundary';
import { encodePathSegments, hashToPath } from '@/helpers';
import { dirname } from 'pathe';
import CodemirrorEditor from '@/components/elements/CodemirrorEditor';
import { InteractiveHoverButton } from '@/components/ui/interactive-hover-button';
import Select from '@/components/ui/select';
import { Braces, Code2, Search } from 'lucide-react';
import PageLoadingSkeleton from '@/components/elements/PageLoadingSkeleton';

export default () => {
    const [error, setError] = useState('');
    const { action } = useParams<{ action: 'new' | string }>();
    const [loading, setLoading] = useState(action === 'edit');
    const [content, setContent] = useState('');
    const [savedContent, setSavedContent] = useState('');
    const [hasUnsavedChanges, setHasUnsavedChanges] = useState(false);
    const [modalVisible, setModalVisible] = useState(false);
    const [mode, setMode] = useState('text/plain');
    const fetchFileContent = useRef<null | (() => Promise<string>)>(null);
    const suppressLeaveWarning = useRef(false);
    const editorRef = useRef<import('codemirror').Editor | null>(null);

    const history = useHistory();
    const { hash } = useLocation();

    const id = ServerContext.useStoreState((state) => state.server.data!.id);
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const setDirectory = ServerContext.useStoreActions((actions) => actions.files.setDirectory);
    const { addError, clearFlashes } = useFlash();
    const selectedMode = useMemo(() => modes.find((entry) => entry.mime === mode), [mode]);
    const filePath = hashToPath(hash);

    useEffect(() => {
        suppressLeaveWarning.current = false;

        if (action === 'new') {
            setError('');
            setLoading(false);
            setContent('');
            setSavedContent('');
            setHasUnsavedChanges(false);
            return;
        }

        setError('');
        setLoading(true);
        setDirectory(dirname(filePath));
        getFileContents(uuid, filePath)
            .then((value) => {
                setContent(value);
                setSavedContent(value);
                setHasUnsavedChanges(false);
            })
            .catch((error) => {
                console.error(error);
                setError(httpErrorToHuman(error));
            })
            .then(() => setLoading(false));
    }, [action, filePath, hash, setDirectory, uuid]);

    useEffect(() => {
        if (!hasUnsavedChanges) {
            return;
        }

        const handleBeforeUnload = (event: BeforeUnloadEvent) => {
            if (suppressLeaveWarning.current) {
                return;
            }

            event.preventDefault();
            event.returnValue = '';
        };
        const unblock = history.block(() => {
            if (suppressLeaveWarning.current) {
                return undefined;
            }

            return 'You have unsaved changes. Leave without saving?';
        });

        window.addEventListener('beforeunload', handleBeforeUnload);

        return () => {
            window.removeEventListener('beforeunload', handleBeforeUnload);
            unblock();
        };
    }, [hasUnsavedChanges, history]);

    const save = (name?: string) => {
        if (!fetchFileContent.current) {
            return;
        }

        setLoading(true);
        clearFlashes('files:view');
        fetchFileContent
            .current()
            .then((nextContent) =>
                saveFileContents(uuid, name || filePath, nextContent).then(() => {
                    setSavedContent(nextContent);
                    setHasUnsavedChanges(false);

                    return nextContent;
                })
            )
            .then(() => {
                if (name) {
                    suppressLeaveWarning.current = true;
                    history.push(`/server/${id}/files/edit#/${encodePathSegments(name)}`);
                    return;
                }

                return Promise.resolve();
            })
            .catch((error) => {
                console.error(error);
                addError({ message: httpErrorToHuman(error), key: 'files:view' });
            })
            .then(() => setLoading(false));
    };

    const revertChanges = () => {
        clearFlashes('files:view');
        setContent(savedContent);
        setHasUnsavedChanges(false);
    };

    if (error) {
        return (
            <ServerContentBlock title={'File Editor'} className={'content-container-full px-4 pb-5 pt-4 xl:px-6'}>
                <div css={tw`flex min-h-[360px] items-center justify-center`}>
                    <ServerError message={error} onBack={() => history.goBack()} />
                </div>
            </ServerContentBlock>
        );
    }

    return (
        <PageContentBlock hideFooter className={'content-container-full px-4 pb-5 pt-4 xl:px-6'} fullHeight>
            <style>{`
                .server-file-editor-shell {
                    position: relative;
                    overflow: hidden;
                    border-radius: 22px;
                    border: 2px solid #2D4A3E;
                    background-color: #FEF9E1;
                    background-image:
                        repeating-linear-gradient(0deg, transparent, transparent 4.5px, rgba(116, 34, 32, 0.04) 4.5px, rgba(116, 34, 32, 0.04) 5px),
                        repeating-linear-gradient(60deg, transparent, transparent 4.5px, rgba(116, 34, 32, 0.04) 4.5px, rgba(116, 34, 32, 0.04) 5px),
                        repeating-linear-gradient(120deg, transparent, transparent 4.5px, rgba(116, 34, 32, 0.04) 4.5px, rgba(116, 34, 32, 0.04) 5px);
                    box-shadow: 4px 4px 0px 0px #2D4A3E;
                    color: #742220;
                }
            `}</style>
            <FlashMessageRender byKey={'files:view'} css={tw`mb-0`} />
            <ErrorBoundary>
                <div
                    className='server-file-editor-shell'
                    css={tw`flex h-full min-h-0 w-full flex-col overflow-hidden`}
                >
                    <div
                        css={tw`flex items-center justify-between px-4 py-3`}
                        style={{ borderBottom: '2px solid #2D4A3E', background: '#F5EFD5', borderRadius: '20px 20px 0 0' }}
                    >
                        <div css={tw`min-w-0 pr-4`}>
                            <FileManagerBreadcrumbs withinFileEditor isNewFile={action !== 'edit'} />
                        </div>
                        <div css={tw`hidden flex-wrap items-center justify-end gap-2 md:flex`}>
                            <div
                                css={[
                                    tw`rounded-md border px-3 py-1 text-xs font-semibold uppercase tracking-wide`,
                                    hasUnsavedChanges
                                        ? {
                                              borderColor: '#f59e0b',
                                              backgroundColor: 'rgba(245, 158, 11, 0.10)',
                                              color: '#fcd34d',
                                          }
                                        : tw`border-[#EDE6D0] bg-[#F5EFD5] text-[#742220]`,
                                ]}
                            >
                                {hasUnsavedChanges ? 'Unsaved Changes' : 'All Changes Saved'}
                            </div>
                            <div
                                css={tw`rounded-md border border-[#EDE6D0] bg-[#F5EFD5] px-3 py-1 text-xs text-[#742220]`}
                            >
                                {selectedMode?.name || 'Plain Text'}
                            </div>
                            <button
                                type='button'
                                title='Find (Ctrl+F / Cmd+F)'
                                onClick={() => editorRef.current?.execCommand('findPersistent')}
                                css={tw`inline-flex items-center gap-1.5 rounded-md border border-[#EDE6D0] bg-[#F5EFD5] px-3 py-1 text-xs text-[#742220] transition-all hover:border-[#2D4A3E] hover:bg-[#EDE6D0]`}
                            >
                                <Search size={11} />
                                Find
                                <span css={tw`ml-1 opacity-50`}>Ctrl+F</span>
                            </button>
                            <div
                                css={tw`rounded-md border border-[#EDE6D0] bg-[#F5EFD5] px-3 py-1 text-xs`} style={{ color: 'rgba(116,34,32,0.55)' }}
                            >
                                Ctrl/Cmd+S
                            </div>
                        </div>
                    </div>

                    {hash.replace(/^#/, '').endsWith('.pteroignore') && (
                        <div css={tw`px-4 py-3`} style={{ borderBottom: '1px solid #EDE6D0', background: '#F5EFD5' }}>
                            <p css={tw`text-sm text-[color:var(--foreground)]`}>
                                You&apos;re editing a{' '}
                                <code
                                    css={tw`rounded border border-[#2D4A3E] bg-[#F5EFD5] px-1 py-px font-mono`}
                                >
                                    .pteroignore
                                </code>{' '}
                                file. Any files or directories listed in here will be excluded from backups. Wildcards
                                are supported by using an asterisk (
                                <code
                                    css={tw`rounded border border-[#2D4A3E] bg-[#F5EFD5] px-1 py-px font-mono`}
                                >
                                    *
                                </code>
                                ). You can negate a prior rule by prepending an exclamation point (
                                <code
                                    css={tw`rounded border border-[#2D4A3E] bg-[#F5EFD5] px-1 py-px font-mono`}
                                >
                                    !
                                </code>
                                ).
                            </p>
                        </div>
                    )}

                    <FileNameModal
                        visible={modalVisible}
                        onDismissed={() => setModalVisible(false)}
                        onFileNamed={(name) => {
                            setModalVisible(false);
                            save(name);
                        }}
                    />

                    <div css={tw`relative min-h-0 flex-1`}>
                        {loading && action === 'edit' && !content ? (
                            <PageLoadingSkeleton
                                showChrome={false}
                                showSpinner={false}
                                rows={10}
                                className='h-full min-h-0 rounded-none border-0'
                            />
                        ) : (
                            <>
                                <SpinnerOverlay visible={loading} />
                                <CodemirrorEditor
                                    mode={mode}
                                    filename={hash.replace(/^#/, '')}
                                    onModeChanged={setMode}
                                    onContentChanged={(value) => setHasUnsavedChanges(value !== savedContent)}
                                    initialContent={content}
                                    fetchContent={(value) => {
                                        fetchFileContent.current = value;
                                    }}
                                    onEditorCreated={(e) => { editorRef.current = e; }}
                                    onContentSaved={() => {
                                        if (action !== 'edit') {
                                            setModalVisible(true);
                                        } else {
                                            save();
                                        }
                                    }}
                                />
                            </>
                        )}
                    </div>

                    <div
                        css={tw`flex flex-wrap items-center justify-between gap-3 px-4 py-3`}
                        style={{ borderTop: '2px solid #2D4A3E', background: '#F5EFD5' }}
                    >
                        <div css={tw`flex w-full flex-col gap-3 lg:w-auto lg:flex-row lg:items-center`}>
                            <div css={tw`w-full sm:w-[320px]`}>
                                <Select
                                    title={'Choose Language'}
                                    defaultValue={mode}
                                    onChange={(value) => setMode(value)}
                                    data={modes.map((entry) => ({
                                        id: `${entry.name}_${entry.mime}`,
                                        label: entry.name,
                                        value: entry.mime,
                                        description: entry.mime,
                                        icon: entry.name.toLowerCase().includes('plain') ? (
                                            <Code2 size={14} />
                                        ) : (
                                            <Braces size={14} />
                                        ),
                                    }))}
                                />
                            </div>
                            <div css={tw`flex flex-wrap items-center gap-2 text-xs text-[color:var(--text-subtle)]`}>
                                <span
                                    css={tw`rounded-md border border-[#EDE6D0] bg-[#F5EFD5] px-3 py-2 font-mono`}
                                >
                                    {filePath}
                                </span>
                            </div>
                        </div>
                        <div css={tw`flex w-full flex-wrap items-center justify-end gap-2 lg:w-auto`}>
                            <button
                                type={'button'}
                                onClick={revertChanges}
                                disabled={!hasUnsavedChanges || loading}
                                css={tw`inline-flex h-11 min-w-[10rem] items-center justify-center rounded-full border border-[#2D4A3E] bg-[#F5EFD5] px-5 text-sm font-semibold uppercase tracking-wide text-[#742220] transition-all hover:border-[#f59e0b] hover:text-[#9a5a00] disabled:cursor-not-allowed disabled:opacity-50`}
                            >
                                Revert Changes
                            </button>
                            {action === 'edit' ? (
                                <Can action={'file.update'}>
                                    <InteractiveHoverButton
                                        text={'Save Content'}
                                        onClick={() => save()}
                                        disabled={loading || !hasUnsavedChanges}
                                    />
                                </Can>
                            ) : (
                                <Can action={'file.create'}>
                                    <InteractiveHoverButton
                                        text={'Create File'}
                                        onClick={() => setModalVisible(true)}
                                        disabled={loading}
                                    />
                                </Can>
                            )}
                        </div>
                    </div>
                </div>
            </ErrorBoundary>
        </PageContentBlock>
    );
};
