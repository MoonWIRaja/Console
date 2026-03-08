import React, { useEffect, useMemo, useState } from 'react';
import { httpErrorToHuman } from '@/api/http';
import { CSSTransition } from 'react-transition-group';
import FileObjectRow from '@/components/server/files/FileObjectRow';
import FileManagerBreadcrumbs from '@/components/server/files/FileManagerBreadcrumbs';
import { FileObject } from '@/api/server/files/loadDirectory';
import loadDirectory from '@/api/server/files/loadDirectory';
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
import RecycleBinButton from '@/components/server/files/recycle-bin/RecycleBinButton';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import { useStoreActions } from '@/state/hooks';
import ErrorBoundary from '@/components/elements/ErrorBoundary';
import { FileActionCheckbox } from '@/components/server/files/SelectFileCheckbox';
import { hashToPath } from '@/helpers';
import { InteractiveHoverButton } from '@/components/ui/interactive-hover-button';
import PageLoadingSkeleton from '@/components/elements/PageLoadingSkeleton';
import style from './style.module.css';

const MAX_SEARCH_DIRS = 150;
const MAX_SEARCH_RESULTS = 500;
const SEARCH_MAX_DURATION_MS = 8000;
const SEARCH_REQUEST_TIMEOUT_MS = 3500;
const HIDDEN_DIRECTORY_NAMES = new Set(['.recycle_bin']);

const pathHasHiddenSegment = (path: string): boolean => {
    return path
        .split('/')
        .filter((segment) => segment.length > 0)
        .some((segment) => HIDDEN_DIRECTORY_NAMES.has(segment));
};

const isHiddenRootEntry = (entryName: string, directory: string): boolean => {
    const normalized = directory === '' ? '/' : directory;
    return normalized === '/' && HIDDEN_DIRECTORY_NAMES.has(entryName);
};

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
    const [search, setSearch] = useState('');
    const [indexing, setIndexing] = useState(false);
    const [indexedEntries, setIndexedEntries] = useState<FileObject[]>([]);

    useEffect(() => {
        clearFlashes('files');
        setSelectedFiles([]);
        setDirectory(hashToPath(hash));
    }, [hash]);

    useEffect(() => {
        mutate();
    }, [directory]);

    useEffect(() => {
        if (!files) {
            setIndexing(false);
            setIndexedEntries([]);
            return;
        }
        let active = true;
        setIndexing(true);
        setIndexedEntries([]);

        const run = async () => {
            try {
                const baseDirectory = directory || '/';
                const queue: string[] = [baseDirectory];
                const results: FileObject[] = [];
                let scannedDirectories = 0;
                const deadline = Date.now() + SEARCH_MAX_DURATION_MS;

                const toAbsolute = (current: string, name: string): string => {
                    if (current === '/') return `/${name.replace(/^\/+/, '')}`;
                    return `${current.replace(/\/+$/, '')}/${name.replace(/^\/+/, '')}`;
                };

                const toRelative = (absolutePath: string): string => {
                    if (baseDirectory === '/') return absolutePath.replace(/^\/+/, '');
                    const normalizedBase = baseDirectory.replace(/\/+$/, '');
                    return absolutePath.startsWith(`${normalizedBase}/`)
                        ? absolutePath.slice(normalizedBase.length + 1)
                        : absolutePath.replace(/^\/+/, '');
                };

                while (queue.length > 0 && scannedDirectories < MAX_SEARCH_DIRS && results.length < MAX_SEARCH_RESULTS) {
                    if (!active) return;
                    if (Date.now() > deadline) break;

                    const currentDirectory = queue.shift()!;
                    scannedDirectories += 1;
                    const entries =
                        currentDirectory === baseDirectory
                            ? files
                            : await Promise.race([
                                  loadDirectory(id, currentDirectory),
                                  new Promise<FileObject[]>((resolve) =>
                                      window.setTimeout(() => resolve([]), SEARCH_REQUEST_TIMEOUT_MS)
                                  ),
                              ]);

                    for (const entry of entries) {
                        const absolutePath = toAbsolute(currentDirectory, entry.name);
                        if (pathHasHiddenSegment(absolutePath)) {
                            continue;
                        }

                        const relativePath = toRelative(absolutePath);

                        results.push({
                            ...entry,
                            name: relativePath,
                            key: `search:${relativePath}:${entry.isFile ? 'file' : 'dir'}`,
                        });

                        if (!entry.isFile && queue.length < MAX_SEARCH_DIRS) {
                            queue.push(absolutePath);
                        }

                        if (results.length >= MAX_SEARCH_RESULTS) {
                            break;
                        }
                    }

                    if (active && (scannedDirectories % 8 === 0 || queue.length === 0)) {
                        setIndexedEntries([...results]);
                    }
                }

                if (active) {
                    setIndexedEntries(results);
                }
            } finally {
                if (active) {
                    setIndexing(false);
                }
            }
        };
        run();

        return () => {
            active = false;
        };
    }, [directory, id, files]);

    const filteredCurrentFiles = useMemo(() => {
        if (!files) return [];
        const visibleFiles = files.filter((file) => !isHiddenRootEntry(file.name, directory));
        const keyword = search.trim().toLowerCase();
        if (!keyword) return visibleFiles;

        return visibleFiles.filter((file) => file.name.toLowerCase().includes(keyword));
    }, [files, search, directory]);

    const visibleFiles = useMemo(() => {
        if (!search.trim()) return filteredCurrentFiles;

        const keyword = search.trim().toLowerCase();
        const deepMatches = indexedEntries.filter((entry) =>
            `${entry.name} ${entry.name.split('/').pop() ?? ''}`.toLowerCase().includes(keyword)
        );

        const merged = [...filteredCurrentFiles, ...deepMatches];
        const seen = new Set<string>();

        return merged.filter((file) => {
            const fileId = `${file.isFile ? 'f' : 'd'}:${file.name}`;
            if (seen.has(fileId)) return false;
            seen.add(fileId);
            return true;
        });
    }, [search, filteredCurrentFiles, indexedEntries]);

    const onSelectAllClick = (e: React.ChangeEvent<HTMLInputElement>) => {
        setSelectedFiles(e.currentTarget.checked ? visibleFiles.map((file) => file.name) : []);
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
                        'flex h-[calc(100dvh-8.5rem)] min-h-[420px] flex-col rounded-xl border border-[color:var(--border)] bg-[color:var(--card)] lg:h-[calc(100dvh-9rem)]'
                    }
                >
                    <div className={'sticky top-0 z-20 border-b border-[color:var(--border)] bg-[color:var(--card)] px-4 py-3'}>
                        <div className={'flex flex-wrap-reverse items-start xl:flex-nowrap'}>
                            <FileManagerBreadcrumbs
                                renderLeft={
                                    <FileActionCheckbox
                                        type={'checkbox'}
                                        css={tw`mx-4`}
                                        checked={selectedFilesLength === (visibleFiles.length === 0 ? -1 : visibleFiles.length)}
                                        onChange={onSelectAllClick}
                                    />
                                }
                            />
                            <Can action={'file.create'}>
                                <div className={style.manager_actions}>
                                    <label className={style.search_shell}>
                                        <span className='material-icons-round'>search</span>
                                        <input
                                            type={'text'}
                                            value={search}
                                            onChange={(event) => setSearch(event.currentTarget.value)}
                                            placeholder={'Search files...'}
                                            className={style.search_input}
                                        />
                                    </label>
                                    <FileManagerStatus />
                                    <NewDirectoryButton />
                                    <RecycleBinButton />
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
                        ) : !visibleFiles.length && search.trim() && indexing ? (
                            <p css={tw`text-center text-sm text-gray-400`}>Searching in folders...</p>
                        ) : !visibleFiles.length ? (
                            <p css={tw`text-center text-sm text-gray-400`}>No files found.</p>
                        ) : (
                            <CSSTransition classNames={'fade'} timeout={150} appear in>
                                <div>
                                    {search.trim() && indexing && (
                                        <p css={tw`mb-3 text-center text-sm text-gray-400`}>Searching in folders...</p>
                                    )}
                                    {sortFiles(visibleFiles).map((file) => (
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
