import React, { useContext, useEffect, useState } from 'react';
import { ServerContext } from '@/state/server';
import { Dialog, DialogWrapperContext } from '@/components/elements/dialog';
import asDialog from '@/hoc/asDialog';
import listRecycleBin, { RecycleBinFile } from '@/api/server/files/recycle-bin/listRecycleBin';
import recoverFiles from '@/api/server/files/recycle-bin/recoverFiles';
import recoverAll from '@/api/server/files/recycle-bin/recoverAll';
import emptyRecycleBin from '@/api/server/files/recycle-bin/emptyRecycleBin';
import useFlash from '@/plugins/useFlash';
import useFileManagerSwr from '@/plugins/useFileManagerSwr';
import SpinnerOverlay from '@/components/elements/SpinnerOverlay';
import FlashMessageRender from '@/components/FlashMessageRender';
import { InteractiveHoverButton } from '@/components/ui/interactive-hover-button';

const RecycleBinDialog = asDialog({
    title: 'Recycle Bin',
    description: 'Deleted files are moved here and can be recovered before permanent deletion.',
})(() => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const { close } = useContext(DialogWrapperContext);
    const { clearFlashes, clearAndAddHttpError } = useFlash();
    const { mutate: mutateFileManager } = useFileManagerSwr();

    const [files, setFiles] = useState<RecycleBinFile[]>([]);
    const [selectedFiles, setSelectedFiles] = useState<string[]>([]);
    const [loading, setLoading] = useState(false);
    const [showEmptyConfirm, setShowEmptyConfirm] = useState(false);

    const loadFiles = () => {
        setLoading(true);
        clearFlashes('recycle-bin');

        listRecycleBin(uuid)
            .then((data) => {
                setFiles(data);
                setSelectedFiles([]);
            })
            .catch((error) => clearAndAddHttpError({ key: 'recycle-bin', error }))
            .finally(() => setLoading(false));
    };

    useEffect(() => {
        loadFiles();
    }, [uuid]);

    const handleSelectFile = (fileName: string) => {
        setSelectedFiles((previous) =>
            previous.includes(fileName) ? previous.filter((name) => name !== fileName) : [...previous, fileName]
        );
    };

    const handleSelectAll = () => {
        if (selectedFiles.length === files.length) {
            setSelectedFiles([]);
            return;
        }

        setSelectedFiles(files.map((file) => file.name));
    };

    const handleRecoverSelected = () => {
        if (selectedFiles.length === 0) return;

        setLoading(true);
        clearFlashes('recycle-bin');
        recoverFiles(uuid, selectedFiles)
            .then(() => {
                loadFiles();
                mutateFileManager();
            })
            .catch((error) => clearAndAddHttpError({ key: 'recycle-bin', error }))
            .finally(() => setLoading(false));
    };

    const handleRecoverAll = () => {
        setLoading(true);
        clearFlashes('recycle-bin');
        recoverAll(uuid)
            .then(() => {
                loadFiles();
                mutateFileManager();
            })
            .catch((error) => clearAndAddHttpError({ key: 'recycle-bin', error }))
            .finally(() => setLoading(false));
    };

    const handleEmpty = () => {
        setLoading(true);
        clearFlashes('recycle-bin');
        emptyRecycleBin(uuid)
            .then(() => loadFiles())
            .catch((error) => clearAndAddHttpError({ key: 'recycle-bin', error }))
            .finally(() => {
                setLoading(false);
                setShowEmptyConfirm(false);
            });
    };

    return (
        <>
            <SpinnerOverlay visible={loading} fixed size={'large'} />

            <Dialog.Confirm
                open={showEmptyConfirm}
                onClose={() => setShowEmptyConfirm(false)}
                title={'Empty Recycle Bin'}
                confirm={'Empty'}
                onConfirmed={handleEmpty}
            >
                Are you sure you want to permanently delete all files in recycle bin? This action cannot be undone.
            </Dialog.Confirm>

            <FlashMessageRender byKey={'recycle-bin'} className={'mt-4'} />

            <div className={'mt-4 flex max-h-[62vh] flex-col'}>
                {files.length === 0 ? (
                    <div className={'rounded-lg border border-[color:var(--border)] bg-[color:var(--background)] px-4 py-10 text-center text-sm text-[color:var(--muted-foreground)]'}>
                        Recycle bin is empty.
                    </div>
                ) : (
                    <>
                        <div className={'mb-3 flex items-center justify-between'}>
                            <label className={'flex cursor-pointer items-center text-sm text-[color:var(--foreground)]'}>
                                <input
                                    type={'checkbox'}
                                    checked={selectedFiles.length === files.length && files.length > 0}
                                    onChange={handleSelectAll}
                                    className={'mr-2'}
                                />
                                Select all
                            </label>
                            <span className={'text-xs text-[color:var(--muted-foreground)]'}>
                                {selectedFiles.length} of {files.length} selected
                            </span>
                        </div>

                        <div className={'overflow-y-auto rounded-lg border border-[color:var(--border)] bg-[color:var(--background)]'}>
                            {files.map((file) => (
                                <div
                                    key={file.name}
                                    className={
                                        'flex items-start border-b border-[color:var(--border)] px-3 py-3 last:border-b-0'
                                    }
                                >
                                    <input
                                        type={'checkbox'}
                                        checked={selectedFiles.includes(file.name)}
                                        onChange={() => handleSelectFile(file.name)}
                                        className={'mr-3 mt-1'}
                                    />
                                    <div className={'min-w-0 flex-1'}>
                                        <div className={'truncate text-sm font-semibold text-[color:var(--foreground)]'}>
                                            {file.metadata?.originalName || file.name}
                                        </div>
                                        {file.metadata?.originalPath && (
                                            <div className={'mt-1 truncate text-xs text-[color:var(--muted-foreground)]'}>
                                                Original: {file.metadata.originalPath}
                                            </div>
                                        )}
                                        {file.metadata?.deletedAt && (
                                            <div className={'mt-1 text-xs text-[color:var(--muted-foreground)]'}>
                                                Deleted: {new Date(file.metadata.deletedAt).toLocaleString()}
                                            </div>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </>
                )}
            </div>

            <div className={'mt-4 flex flex-wrap items-center justify-between gap-2 border-t border-[color:var(--border)] pt-4'}>
                <InteractiveHoverButton
                    type={'button'}
                    text={'Empty Recycle Bin'}
                    variant={'danger'}
                    disabled={files.length === 0 || loading}
                    onClick={() => setShowEmptyConfirm(true)}
                    className={'!h-10 !min-w-[12rem] !text-xs'}
                />

                <div className={'flex flex-wrap items-center gap-2'}>
                    <InteractiveHoverButton
                        type={'button'}
                        text={'Close'}
                        onClick={close}
                        className={'!h-10 !min-w-[8.5rem] !text-xs'}
                    />
                    <InteractiveHoverButton
                        type={'button'}
                        text={'Recover All'}
                        disabled={files.length === 0 || loading}
                        onClick={handleRecoverAll}
                        className={'!h-10 !min-w-[9.5rem] !text-xs'}
                    />
                    <InteractiveHoverButton
                        type={'button'}
                        text={'Recover Selected'}
                        disabled={selectedFiles.length === 0 || loading}
                        onClick={handleRecoverSelected}
                        className={'!h-10 !min-w-[12rem] !text-xs'}
                    />
                </div>
            </div>
        </>
    );
});

export default ({ open, onClose }: { open: boolean; onClose: () => void }) => {
    return <RecycleBinDialog open={open} onClose={onClose} />;
};
