import React, { useEffect, useState } from 'react';
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
    const [modalVisible, setModalVisible] = useState(false);
    const [mode, setMode] = useState('text/plain');

    const history = useHistory();
    const { hash } = useLocation();

    const id = ServerContext.useStoreState((state) => state.server.data!.id);
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const setDirectory = ServerContext.useStoreActions((actions) => actions.files.setDirectory);
    const { addError, clearFlashes } = useFlash();

    let fetchFileContent: null | (() => Promise<string>) = null;

    useEffect(() => {
        if (action === 'new') return;

        setError('');
        setLoading(true);
        const path = hashToPath(hash);
        setDirectory(dirname(path));
        getFileContents(uuid, path)
            .then(setContent)
            .catch((error) => {
                console.error(error);
                setError(httpErrorToHuman(error));
            })
            .then(() => setLoading(false));
    }, [action, uuid, hash]);

    const save = (name?: string) => {
        if (!fetchFileContent) {
            return;
        }

        setLoading(true);
        clearFlashes('files:view');
        fetchFileContent()
            .then((content) => saveFileContents(uuid, name || hashToPath(hash), content))
            .then(() => {
                if (name) {
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

    if (error) {
        return <ServerError message={error} onBack={() => history.goBack()} />;
    }

    return (
        <PageContentBlock hideFooter className={'content-container-full h-screen px-0 py-0'} fullHeight>
            <FlashMessageRender byKey={'files:view'} css={tw`mb-0`} />
            <ErrorBoundary>
                <div
                    css={tw`flex h-screen min-h-0 w-full flex-col overflow-hidden border-0 bg-[color:var(--card)]`}
                >
                    <div css={tw`flex items-center justify-between border-b border-[color:var(--border)] bg-[color:var(--background)] px-4 py-3`}>
                        <div css={tw`min-w-0 pr-4`}>
                            <FileManagerBreadcrumbs withinFileEditor isNewFile={action !== 'edit'} />
                        </div>
                        <div css={tw`hidden items-center rounded-md border border-[color:var(--border)] bg-[color:var(--card)] px-3 py-1 text-xs text-gray-300 md:flex`}>
                            VSCode Style Editor
                        </div>
                    </div>

                    {hash.replace(/^#/, '').endsWith('.pteroignore') && (
                        <div css={tw`border-b border-[color:var(--border)] bg-[#031204] px-4 py-3`}>
                            <p css={tw`text-sm text-gray-300`}>
                                You&apos;re editing a{' '}
                                <code css={tw`rounded border border-[color:var(--border)] bg-[color:var(--background)] px-1 py-px font-mono`}>
                                    .pteroignore
                                </code>{' '}
                                file. Any files or directories listed in here will be excluded from backups. Wildcards
                                are supported by using an asterisk (
                                <code css={tw`rounded border border-[color:var(--border)] bg-[color:var(--background)] px-1 py-px font-mono`}>*</code>).
                                You can negate a prior rule by prepending an exclamation point (
                                <code css={tw`rounded border border-[color:var(--border)] bg-[color:var(--background)] px-1 py-px font-mono`}>!</code>).
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
                                    initialContent={content}
                                    fetchContent={(value) => {
                                        fetchFileContent = value;
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

                    <div css={tw`flex flex-wrap items-center justify-between gap-3 border-t border-[color:var(--border)] bg-[color:var(--background)] px-4 py-3`}>
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
                                    icon: entry.name.toLowerCase().includes('plain') ? <Code2 size={14} /> : <Braces size={14} />,
                                }))}
                            />
                        </div>
                        {action === 'edit' ? (
                            <Can action={'file.update'}>
                                <InteractiveHoverButton text={'Save Content'} onClick={() => save()} />
                            </Can>
                        ) : (
                            <Can action={'file.create'}>
                                <InteractiveHoverButton text={'Create File'} onClick={() => setModalVisible(true)} />
                            </Can>
                        )}
                    </div>
                </div>
            </ErrorBoundary>
        </PageContentBlock>
    );
};
