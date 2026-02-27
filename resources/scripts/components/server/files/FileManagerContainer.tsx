import React, { useEffect } from 'react';
import { httpErrorToHuman } from '@/api/http';
import { CSSTransition } from 'react-transition-group';
import FileObjectRow from '@/components/server/files/FileObjectRow';
import FileManagerBreadcrumbs from '@/components/server/files/FileManagerBreadcrumbs';
import { FileObject } from '@/api/server/files/loadDirectory';
import NewDirectoryButton from '@/components/server/files/NewDirectoryButton';
import { NavLink, useLocation } from 'react-router-dom';
import Can from '@/components/elements/Can';
import { ServerError } from '@/components/elements/ScreenBlock';
import tw from 'twin.macro';
import { ServerContext } from '@/state/server';
import useFileManagerSwr from '@/plugins/useFileManagerSwr';
import FileManagerStatus from '@/components/server/files/FileManagerStatus';
import MassActionsBar from '@/components/server/files/MassActionsBar';
import UploadButton from '@/components/server/files/UploadButton';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import { useStoreActions } from '@/state/hooks';
import ErrorBoundary from '@/components/elements/ErrorBoundary';
import { FileActionCheckbox } from '@/components/server/files/SelectFileCheckbox';
import { hashToPath } from '@/helpers';
import { InteractiveHoverButton } from '@/components/ui/interactive-hover-button';
import PageLoadingSkeleton from '@/components/elements/PageLoadingSkeleton';
import style from './style.module.css';

const sortFiles = (files: FileObject[]): FileObject[] => {
    const sortedFiles: FileObject[] = files
        .sort((a, b) => a.name.localeCompare(b.name))
        .sort((a, b) => (a.isFile === b.isFile ? 0 : a.isFile ? 1 : -1));
    return sortedFiles.filter((file, index) => index === 0 || file.name !== sortedFiles[index - 1].name);
};

export default () => {
    const id = ServerContext.useStoreState((state) => state.server.data!.id);
    const { hash } = useLocation();
    const { data: files, error, mutate } = useFileManagerSwr();
    const directory = ServerContext.useStoreState((state) => state.files.directory);
    const clearFlashes = useStoreActions((actions) => actions.flashes.clearFlashes);
    const setDirectory = ServerContext.useStoreActions((actions) => actions.files.setDirectory);

    const setSelectedFiles = ServerContext.useStoreActions((actions) => actions.files.setSelectedFiles);
    const selectedFilesLength = ServerContext.useStoreState((state) => state.files.selectedFiles.length);

    useEffect(() => {
        clearFlashes('files');
        setSelectedFiles([]);
        setDirectory(hashToPath(hash));
    }, [hash]);

    useEffect(() => {
        mutate();
    }, [directory]);

    const onSelectAllClick = (e: React.ChangeEvent<HTMLInputElement>) => {
        setSelectedFiles(e.currentTarget.checked ? files?.map((file) => file.name) || [] : []);
    };

    if (error) {
        return <ServerError message={httpErrorToHuman(error)} onRetry={() => mutate()} />;
    }

    return (
        <ServerContentBlock
            title={'File Manager'}
            showFlashKey={'files'}
            hideFooter
            className={'content-container-full px-4 py-4'}
        >
            <ErrorBoundary>
                <div
                    className={
                        'flex h-[calc(100vh-8.5rem)] min-h-[420px] flex-col rounded-xl border border-[#1f2a14] bg-[#000000] lg:h-[calc(100vh-9rem)]'
                    }
                >
                    <div className={'sticky top-0 z-20 border-b border-[#1f2a14] bg-[#000000] px-4 py-3'}>
                        <div className={'flex flex-wrap-reverse items-start md:flex-nowrap'}>
                            <FileManagerBreadcrumbs
                                renderLeft={
                                    <FileActionCheckbox
                                        type={'checkbox'}
                                        css={tw`mx-4`}
                                        checked={selectedFilesLength === (files?.length === 0 ? -1 : files?.length)}
                                        onChange={onSelectAllClick}
                                    />
                                }
                            />
                            <Can action={'file.create'}>
                                <div className={style.manager_actions}>
                                    <FileManagerStatus />
                                    <NewDirectoryButton />
                                    <UploadButton />
                                    <NavLink to={`/server/${id}/files/new${window.location.hash}`}>
                                        <InteractiveHoverButton text={'New File'} />
                                    </NavLink>
                                </div>
                            </Can>
                        </div>
                    </div>

                    <div className={'min-h-0 flex-1 overflow-y-auto p-4'}>
                        {!files ? (
                            <PageLoadingSkeleton showChrome={false} showSpinner={false} rows={8} className='min-h-[320px]' />
                        ) : !files.length ? (
                            <p css={tw`text-center text-sm text-gray-400`}>This directory seems to be empty.</p>
                        ) : (
                            <CSSTransition classNames={'fade'} timeout={150} appear in>
                                <div>
                                    {files.length > 250 && (
                                        <div css={tw`mb-2 rounded-lg border border-[#3d4e21] bg-[#000000] p-3`}>
                                            <p css={tw`text-center text-sm text-[#d9ff93]`}>
                                                This directory is too large to display in the browser, limiting the
                                                output to the first 250 files.
                                            </p>
                                        </div>
                                    )}
                                    {sortFiles(files.slice(0, 250)).map((file) => (
                                        <FileObjectRow key={file.key} file={file} />
                                    ))}
                                    <MassActionsBar />
                                </div>
                            </CSSTransition>
                        )}
                    </div>
                </div>
            </ErrorBoundary>
        </ServerContentBlock>
    );
};
