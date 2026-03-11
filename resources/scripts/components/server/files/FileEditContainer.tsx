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
import { Braces, Code2 } from 'lucide-react';
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
        return <ServerError message={error} onBack={() => history.goBack()} />;
    }

    return (
        <PageContentBlock hideFooter className={'content-container-full px-4 pb-5 pt-4 xl:px-6'} fullHeight>
            <style>{`
                .server-file-editor-shell {
                    position: relative;
                    overflow: hidden;
                    border-radius: 1.4rem;
                    border: 1px solid rgba(255, 255, 255, 0.08);
                    background:
                        linear-gradient(165deg, rgba(255, 255, 255, 0.04), rgba(255, 255, 255, 0.01) 42%),
                        rgba(5, 8, 14, 0.82);
                    box-shadow:
                        inset 0 1px 0 rgba(255, 255, 255, 0.07),
                        0 24px 38px -32px rgba(0, 0, 0, 0.88),
                        0 0 44px rgba(var(--primary-rgb), 0.08);
                }

                .server-file-editor-shell::before {
                    content: '';
                    position: absolute;
                    inset: 0;
                    pointer-events: none;
                    background:
                        radial-gradient(360px 130px at 8% -10%, rgba(var(--primary-rgb), 0.2), transparent 66%),
                        radial-gradient(320px 120px at 92% -12%, rgba(102, 141, 255, 0.16), transparent 68%);
                    opacity: 0.5;
                }
            `}</style>
            <FlashMessageRender byKey={'files:view'} css={tw`mb-0`} />
            <ErrorBoundary>
                <div
                    className='server-file-editor-shell'
                    css={tw`flex h-full min-h-0 w-full flex-col overflow-hidden border-0 bg-[color:var(--card)]`}
                >
                    <div
                        css={tw`flex items-center justify-between border-b border-[color:var(--border)] bg-[color:var(--background)] px-4 py-3`}
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
                                        : tw`border-[color:var(--border)] bg-[color:var(--card)] text-gray-300`,
                                ]}
                            >
                                {hasUnsavedChanges ? 'Unsaved Changes' : 'All Changes Saved'}
                            </div>
                            <div
                                css={tw`rounded-md border border-[color:var(--border)] bg-[color:var(--card)] px-3 py-1 text-xs text-gray-300`}
                            >
                                {selectedMode?.name || 'Plain Text'}
                            </div>
                            <div
                                css={tw`rounded-md border border-[color:var(--border)] bg-[color:var(--card)] px-3 py-1 text-xs text-gray-400`}
                            >
                                Ctrl/Cmd+S
                            </div>
                        </div>
                    </div>

                    {hash.replace(/^#/, '').endsWith('.pteroignore') && (
                        <div css={tw`border-b border-[color:var(--border)] bg-[#031204] px-4 py-3`}>
                            <p css={tw`text-sm text-gray-300`}>
                                You&apos;re editing a{' '}
                                <code
                                    css={tw`rounded border border-[color:var(--border)] bg-[color:var(--background)] px-1 py-px font-mono`}
                                >
                                    .pteroignore
                                </code>{' '}
                                file. Any files or directories listed in here will be excluded from backups. Wildcards
                                are supported by using an asterisk (
                                <code
                                    css={tw`rounded border border-[color:var(--border)] bg-[color:var(--background)] px-1 py-px font-mono`}
                                >
                                    *
                                </code>
                                ). You can negate a prior rule by prepending an exclamation point (
                                <code
                                    css={tw`rounded border border-[color:var(--border)] bg-[color:var(--background)] px-1 py-px font-mono`}
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
                        css={tw`flex flex-wrap items-center justify-between gap-3 border-t border-[color:var(--border)] bg-[color:var(--background)] px-4 py-3`}
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
                            <div css={tw`flex flex-wrap items-center gap-2 text-xs text-gray-400`}>
                                <span
                                    css={tw`rounded-md border border-[color:var(--border)] bg-[color:var(--card)] px-3 py-2 font-mono`}
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
                                css={tw`inline-flex h-11 min-w-[10rem] items-center justify-center rounded-full border border-[color:var(--border)] bg-[color:var(--card)] px-5 text-sm font-semibold uppercase tracking-wide text-gray-200 transition-all hover:border-[#f59e0b] hover:text-[#fcd34d] disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:border-[color:var(--border)] disabled:hover:text-gray-200`}
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
