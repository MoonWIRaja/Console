import React, { useEffect, useState } from 'react';
import tw from 'twin.macro';
import Fade from '@/components/elements/Fade';
import SpinnerOverlay from '@/components/elements/SpinnerOverlay';
import useFileManagerSwr from '@/plugins/useFileManagerSwr';
import useFlash from '@/plugins/useFlash';
import compressFiles from '@/api/server/files/compressFiles';
import { ServerContext } from '@/state/server';
import moveToRecycleBin from '@/api/server/files/recycle-bin/moveToRecycleBin';
import RenameFileModal from '@/components/server/files/RenameFileModal';
import Portal from '@/components/elements/Portal';
import { Dialog } from '@/components/elements/dialog';
import { InteractiveHoverButton } from '@/components/ui/interactive-hover-button';

const RECYCLE_BIN_BATCH_SIZE = 25;

const splitIntoBatches = (items: string[], size: number): string[][] => {
    if (items.length === 0 || size <= 0) return [];

    const batches: string[][] = [];
    for (let index = 0; index < items.length; index += size) {
        batches.push(items.slice(index, index + size));
    }

    return batches;
};

const MassActionsBar = () => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);

    const { mutate } = useFileManagerSwr();
    const { clearFlashes, clearAndAddHttpError } = useFlash();
    const [loading, setLoading] = useState(false);
    const [loadingMessage, setLoadingMessage] = useState('');
    const [showConfirm, setShowConfirm] = useState(false);
    const [showMove, setShowMove] = useState(false);
    const directory = ServerContext.useStoreState((state) => state.files.directory);

    const selectedFiles = ServerContext.useStoreState((state) => state.files.selectedFiles);
    const setSelectedFiles = ServerContext.useStoreActions((actions) => actions.files.setSelectedFiles);

    useEffect(() => {
        if (!loading) setLoadingMessage('');
    }, [loading]);

    const onClickCompress = () => {
        setLoading(true);
        clearFlashes('files');
        setLoadingMessage('Archiving files...');

        compressFiles(uuid, directory, selectedFiles)
            .then(() => mutate())
            .then(() => setSelectedFiles([]))
            .catch((error) => clearAndAddHttpError({ key: 'files', error }))
            .then(() => setLoading(false));
    };

    const onClickConfirmDeletion = async () => {
        setLoading(true);
        setShowConfirm(false);
        clearFlashes('files');
        setLoadingMessage('Deleting files...');

        try {
            const fileBatches = splitIntoBatches(selectedFiles, RECYCLE_BIN_BATCH_SIZE);

            for (let index = 0; index < fileBatches.length; index++) {
                const batch = fileBatches[index];
                setLoadingMessage(`Deleting files... (${index + 1}/${fileBatches.length})`);
                await moveToRecycleBin(uuid, directory, batch);
            }

            mutate((files) => files.filter((f) => selectedFiles.indexOf(f.name) < 0), false);
            setSelectedFiles([]);
        } catch (error) {
            mutate();
            clearAndAddHttpError({ key: 'files', error });
        } finally {
            setLoading(false);
        }
    };

    return (
        <>
            <div css={tw`pointer-events-none fixed bottom-0 z-20 left-0 right-0 flex justify-center`}>
                <SpinnerOverlay visible={loading} size={'large'} fixed>
                    {loadingMessage}
                </SpinnerOverlay>
                <Dialog.Confirm
                    title={'Delete Files'}
                    open={showConfirm}
                    confirm={'Delete'}
                    onClose={() => setShowConfirm(false)}
                    onConfirmed={onClickConfirmDeletion}
                >
                    <p className={'mb-2'}>
                        Delete&nbsp;
                        <span className={'font-semibold text-[color:var(--primary)]'}>
                            {selectedFiles.length} files
                        </span>{' '}
                        and move them to recycle bin? You can recover them later.
                    </p>
                    <ul className={'space-y-1 text-sm text-[color:var(--foreground)]'}>
                        {selectedFiles.slice(0, 15).map((file) => (
                            <li key={file}>{file}</li>
                        ))}
                        {selectedFiles.length > 15 && <li>and {selectedFiles.length - 15} others</li>}
                    </ul>
                </Dialog.Confirm>
                {showMove && (
                    <RenameFileModal
                        files={selectedFiles}
                        visible
                        appear
                        useMoveTerminology
                        onDismissed={() => setShowMove(false)}
                    />
                )}
                <Portal>
                    <div className={'pointer-events-none fixed bottom-0 mb-6 flex justify-center w-full z-50'}>
                        <Fade timeout={75} in={selectedFiles.length > 0} unmountOnExit>
                            <div
                                css={tw`pointer-events-auto flex items-center space-x-3 rounded-xl p-3`}
                                style={{
                                    border: '2px solid #2D4A3E',
                                    backgroundColor: '#FEF9E1',
                                    backgroundImage: [
                                        'repeating-linear-gradient(0deg, transparent, transparent 4.5px, rgba(116, 34, 32, 0.04) 4.5px, rgba(116, 34, 32, 0.04) 5px)',
                                        'repeating-linear-gradient(60deg, transparent, transparent 4.5px, rgba(116, 34, 32, 0.04) 4.5px, rgba(116, 34, 32, 0.04) 5px)',
                                        'repeating-linear-gradient(120deg, transparent, transparent 4.5px, rgba(116, 34, 32, 0.04) 4.5px, rgba(116, 34, 32, 0.04) 5px)',
                                    ].join(', '),
                                    boxShadow: '4px 4px 0px 0px #2D4A3E',
                                }}
                            >
                                <InteractiveHoverButton text={'Move'} onClick={() => setShowMove(true)} />
                                <InteractiveHoverButton text={'Archive'} onClick={onClickCompress} />
                                <InteractiveHoverButton
                                    text={'Delete'}
                                    variant={'danger'}
                                    onClick={() => setShowConfirm(true)}
                                />
                            </div>
                        </Fade>
                    </div>
                </Portal>
            </div>
        </>
    );
};

export default MassActionsBar;
