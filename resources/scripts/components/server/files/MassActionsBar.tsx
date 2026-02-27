import React, { useEffect, useState } from 'react';
import tw from 'twin.macro';
import Fade from '@/components/elements/Fade';
import SpinnerOverlay from '@/components/elements/SpinnerOverlay';
import useFileManagerSwr from '@/plugins/useFileManagerSwr';
import useFlash from '@/plugins/useFlash';
import compressFiles from '@/api/server/files/compressFiles';
import { ServerContext } from '@/state/server';
import deleteFiles from '@/api/server/files/deleteFiles';
import RenameFileModal from '@/components/server/files/RenameFileModal';
import Portal from '@/components/elements/Portal';
import { Dialog } from '@/components/elements/dialog';
import { InteractiveHoverButton } from '@/components/ui/interactive-hover-button';

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

    const onClickConfirmDeletion = () => {
        setLoading(true);
        setShowConfirm(false);
        clearFlashes('files');
        setLoadingMessage('Deleting files...');

        deleteFiles(uuid, directory, selectedFiles)
            .then(() => {
                mutate((files) => files.filter((f) => selectedFiles.indexOf(f.name) < 0), false);
                setSelectedFiles([]);
            })
            .catch((error) => {
                mutate();
                clearAndAddHttpError({ key: 'files', error });
            })
            .then(() => setLoading(false));
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
                        Are you sure you want to delete&nbsp;
                        <span className={'font-semibold text-[#d9ff93]'}>{selectedFiles.length} files</span>? This is a
                        permanent action and the files cannot be recovered.
                    </p>
                    <ul className={'space-y-1 text-sm text-gray-300'}>
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
                                css={tw`pointer-events-auto flex items-center space-x-3 rounded-xl border border-[#1f2a14] bg-[#000000] p-3 shadow-xl`}
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
