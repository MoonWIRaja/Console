import React, { memo, useRef, useState } from 'react';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import {
    faBoxOpen,
    faClone,
    faEllipsisH,
    faFileArchive,
    faFileCode,
    faFileDownload,
    faLevelUpAlt,
    faPencilAlt,
    faTrashAlt,
    IconDefinition,
} from '@fortawesome/free-solid-svg-icons';
import RenameFileModal from '@/components/server/files/RenameFileModal';
import { ServerContext } from '@/state/server';
import { join } from 'pathe';
import moveToRecycleBin from '@/api/server/files/recycle-bin/moveToRecycleBin';
import SpinnerOverlay from '@/components/elements/SpinnerOverlay';
import copyFile from '@/api/server/files/copyFile';
import deleteFiles from '@/api/server/files/deleteFiles';
import renameFiles from '@/api/server/files/renameFiles';
import Can from '@/components/elements/Can';
import getFileDownloadUrl from '@/api/server/files/getFileDownloadUrl';
import useFlash from '@/plugins/useFlash';
import tw from 'twin.macro';
import loadDirectory, { FileObject } from '@/api/server/files/loadDirectory';
import useFileManagerSwr from '@/plugins/useFileManagerSwr';
import DropdownMenu from '@/components/elements/DropdownMenu';
import styled from 'styled-components/macro';
import useEventListener from '@/plugins/useEventListener';
import compressFiles from '@/api/server/files/compressFiles';
import decompressFiles from '@/api/server/files/decompressFiles';
import isEqual from 'react-fast-compare';
import ChmodFileModal from '@/components/server/files/ChmodFileModal';
import { Dialog } from '@/components/elements/dialog';

type ModalType = 'rename' | 'move' | 'chmod';

const duplicateNameFor = (name: string, existingNames: Set<string>) => {
    const candidate = `${name} copy`;
    if (!existingNames.has(candidate)) return candidate;

    let index = 2;
    while (existingNames.has(`${name} copy ${index}`)) {
        index += 1;
    }

    return `${name} copy ${index}`;
};

const temporarySourceNameFor = (name: string, existingNames: Set<string>) => {
    let index = 0;
    let candidate = `.ptero-duplicate-source-${Date.now()}-${name}`;

    while (existingNames.has(candidate)) {
        index += 1;
        candidate = `.ptero-duplicate-source-${Date.now()}-${index}-${name}`;
    }

    return candidate;
};

const StyledRow = styled.div<{ $danger?: boolean }>`
    ${tw`flex items-center rounded-md border border-transparent p-2 text-[color:var(--foreground)]`};
    ${(props) =>
        props.$danger
            ? tw`hover:border-red-500 hover:bg-red-500/10 hover:text-red-700`
            : tw`hover:border-[#2D4A3E] hover:bg-[#EDE6D0] hover:text-[#2D4A3E]`};
`;

interface RowProps extends React.HTMLAttributes<HTMLDivElement> {
    icon: IconDefinition;
    title: string;
    $danger?: boolean;
}

const Row = ({ icon, title, ...props }: RowProps) => (
    <StyledRow {...props}>
        <FontAwesomeIcon icon={icon} css={tw`text-xs`} fixedWidth />
        <span css={tw`ml-2`}>{title}</span>
    </StyledRow>
);

const FileDropdownMenu = ({ file }: { file: FileObject }) => {
    const onClickRef = useRef<DropdownMenu>(null);
    const [showSpinner, setShowSpinner] = useState(false);
    const [modal, setModal] = useState<ModalType | null>(null);
    const [showConfirmation, setShowConfirmation] = useState(false);
    const [duplicateFileName, setDuplicateFileName] = useState<string | null>(null);

    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const { data: files, mutate } = useFileManagerSwr();
    const { clearAndAddHttpError, clearFlashes } = useFlash();
    const directory = ServerContext.useStoreState((state) => state.files.directory);

    useEventListener(`pterodactyl:files:ctx:${file.key}`, (e: CustomEvent<{ x: number; y: number }>) => {
        if (onClickRef.current) {
            onClickRef.current.triggerMenu(e.detail.x, e.detail.y, 'open');
        }
    });

    const doDeletion = () => {
        clearFlashes('files');

        // For UI speed, immediately remove the file from the listing before calling the deletion function.
        // If the delete actually fails, we'll fetch the current directory contents again automatically.
        mutate((files) => files.filter((f) => f.key !== file.key), false);

        moveToRecycleBin(uuid, directory, [file.name]).catch((error) => {
            mutate();
            clearAndAddHttpError({ key: 'files', error });
        });
    };

    const doDuplicate = async () => {
        setShowSpinner(true);
        clearFlashes('files');

        const namesBeforeCopy = new Set((files || []).map((item) => item.name));

        try {
            if (file.isFile) {
                await copyFile(uuid, join(directory, file.name));
            } else {
                const duplicateName = duplicateNameFor(file.name, namesBeforeCopy);
                const temporarySourceName = temporarySourceNameFor(file.name, namesBeforeCopy);
                const archive = await compressFiles(uuid, directory, [file.name]);
                let sourceMoved = false;

                try {
                    await renameFiles(uuid, directory, [{ from: file.name, to: temporarySourceName }]);
                    sourceMoved = true;

                    await decompressFiles(uuid, directory, archive.name);
                    await renameFiles(uuid, directory, [{ from: file.name, to: duplicateName }]);
                    await renameFiles(uuid, directory, [{ from: temporarySourceName, to: file.name }]);
                    sourceMoved = false;

                    await deleteFiles(uuid, directory, [archive.name]).catch(() => undefined);

                    const refreshedFiles = await loadDirectory(uuid, directory);
                    await mutate(refreshedFiles, false);
                    setDuplicateFileName(duplicateName);
                    return;
                } catch (error) {
                    if (sourceMoved) {
                        await renameFiles(uuid, directory, [{ from: temporarySourceName, to: file.name }]).catch(
                            () => undefined
                        );
                    }

                    await deleteFiles(uuid, directory, [archive.name]).catch(() => undefined);
                    throw error;
                }
            }

            const refreshedFiles = await loadDirectory(uuid, directory);
            await mutate(refreshedFiles, false);

            const duplicatedFile = refreshedFiles.find((item) => !namesBeforeCopy.has(item.name));

            if (!duplicatedFile) {
                clearAndAddHttpError({
                    key: 'files',
                    error: new Error('Duplicate created, but the copied item name could not be detected.'),
                });
                return;
            }

            setDuplicateFileName(duplicatedFile.name);
        } catch (error) {
            mutate();
            clearAndAddHttpError({ key: 'files', error });
        } finally {
            setShowSpinner(false);
        }
    };

    const doDownload = () => {
        setShowSpinner(true);
        clearFlashes('files');

        getFileDownloadUrl(uuid, join(directory, file.name))
            .then((url) => {
                // @ts-expect-error this is valid
                window.location = url;
            })
            .catch((error) => clearAndAddHttpError({ key: 'files', error }))
            .then(() => setShowSpinner(false));
    };

    const doArchive = () => {
        setShowSpinner(true);
        clearFlashes('files');

        compressFiles(uuid, directory, [file.name])
            .then(() => mutate())
            .catch((error) => clearAndAddHttpError({ key: 'files', error }))
            .then(() => setShowSpinner(false));
    };

    const doUnarchive = () => {
        setShowSpinner(true);
        clearFlashes('files');

        decompressFiles(uuid, directory, file.name)
            .then(() => mutate())
            .catch((error) => clearAndAddHttpError({ key: 'files', error }))
            .then(() => setShowSpinner(false));
    };

    return (
        <>
            <Dialog.Confirm
                open={showConfirmation}
                onClose={() => setShowConfirmation(false)}
                title={`Delete ${file.isFile ? 'File' : 'Directory'}`}
                confirm={'Delete'}
                onConfirmed={doDeletion}
            >
                <span className={'font-semibold text-[color:var(--primary)]'}>{file.name}</span> will be deleted and
                moved to recycle bin. You can recover it later.
            </Dialog.Confirm>
            <DropdownMenu
                ref={onClickRef}
                renderToggle={(onClick) => (
                    <div
                        css={tw`px-4 py-2 text-[color:var(--text-subtle)] transition-colors duration-150 hover:text-[color:var(--primary)]`}
                        onClick={onClick}
                    >
                        <FontAwesomeIcon icon={faEllipsisH} />
                        {modal ? (
                            modal === 'chmod' ? (
                                <ChmodFileModal
                                    visible
                                    appear
                                    files={[{ file: file.name, mode: file.modeBits }]}
                                    onDismissed={() => setModal(null)}
                                />
                            ) : (
                                <RenameFileModal
                                    visible
                                    appear
                                    files={[file.name]}
                                    useMoveTerminology={modal === 'move'}
                                    onDismissed={() => setModal(null)}
                                />
                            )
                        ) : null}
                        {duplicateFileName && (
                            <RenameFileModal
                                visible
                                appear
                                files={[duplicateFileName]}
                                onDismissed={() => setDuplicateFileName(null)}
                            />
                        )}
                        <SpinnerOverlay visible={showSpinner} fixed size={'large'} />
                    </div>
                )}
            >
                <Can action={'file.update'}>
                    <Row onClick={() => setModal('rename')} icon={faPencilAlt} title={'Rename'} />
                    <Row onClick={() => setModal('move')} icon={faLevelUpAlt} title={'Move'} />
                    <Row onClick={() => setModal('chmod')} icon={faFileCode} title={'Permissions'} />
                </Can>
                <Can action={'file.create'}>
                    {file.isFile ? (
                        <Row onClick={doDuplicate} icon={faClone} title={'Duplicate'} />
                    ) : (
                        <Can action={['file.archive', 'file.update', 'file.delete']}>
                            <Row onClick={doDuplicate} icon={faClone} title={'Duplicate'} />
                        </Can>
                    )}
                </Can>
                {file.isArchiveType() ? (
                    <Can action={'file.create'}>
                        <Row onClick={doUnarchive} icon={faBoxOpen} title={'Unarchive'} />
                    </Can>
                ) : (
                    <Can action={'file.archive'}>
                        <Row onClick={doArchive} icon={faFileArchive} title={'Archive'} />
                    </Can>
                )}
                {file.isFile && <Row onClick={doDownload} icon={faFileDownload} title={'Download'} />}
                <Can action={'file.delete'}>
                    <Row onClick={() => setShowConfirmation(true)} icon={faTrashAlt} title={'Delete'} $danger />
                </Can>
            </DropdownMenu>
        </>
    );
};

export default memo(FileDropdownMenu, isEqual);
