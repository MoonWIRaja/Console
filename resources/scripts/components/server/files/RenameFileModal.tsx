import React, { useEffect, useState } from 'react';
import Modal, { RequiredModalProps } from '@/components/elements/Modal';
import { Form, Formik, FormikHelpers } from 'formik';
import Field from '@/components/elements/Field';
import { dirname, join, relative } from 'pathe';
import renameFiles from '@/api/server/files/renameFiles';
import { ServerContext } from '@/state/server';
import tw from 'twin.macro';
import useFileManagerSwr from '@/plugins/useFileManagerSwr';
import useFlash from '@/plugins/useFlash';
import loadDirectory, { FileObject } from '@/api/server/files/loadDirectory';
import { cleanDirectoryPath } from '@/helpers';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faFolder, faLevelUpAlt } from '@fortawesome/free-solid-svg-icons';

interface FormikValues {
    name: string;
}

type OwnProps = RequiredModalProps & { files: string[]; useMoveTerminology?: boolean };

const RenameFileModal = ({ files, useMoveTerminology, ...props }: OwnProps) => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const { mutate } = useFileManagerSwr();
    const { clearFlashes, clearAndAddHttpError } = useFlash();
    const directory = ServerContext.useStoreState((state) => state.files.directory);
    const setSelectedFiles = ServerContext.useStoreActions((actions) => actions.files.setSelectedFiles);
    const [moveDirectory, setMoveDirectory] = useState(cleanDirectoryPath(directory));
    const [moveFolders, setMoveFolders] = useState<FileObject[]>([]);
    const [moveLoading, setMoveLoading] = useState(false);

    const getMoveValue = (targetDirectory: string) => {
        const target = cleanDirectoryPath(targetDirectory);
        const destination = files.length > 1 ? target : join(target, files[0] || '');
        const value = relative(cleanDirectoryPath(directory), cleanDirectoryPath(destination));

        return value || (files.length > 1 ? '.' : files[0] || '');
    };

    useEffect(() => {
        if (!useMoveTerminology) return;

        setMoveLoading(true);
        loadDirectory(uuid, moveDirectory)
            .then((data) => setMoveFolders(data.filter((file) => !file.isFile)))
            .catch((error) => clearAndAddHttpError({ key: 'files', error }))
            .finally(() => setMoveLoading(false));
    }, [uuid, moveDirectory, useMoveTerminology]);

    const submit = ({ name }: FormikValues, { setSubmitting }: FormikHelpers<FormikValues>) => {
        clearFlashes('files');

        const len = name.split('/').length;
        if (files.length === 1) {
            if (!useMoveTerminology && len === 1) {
                // Rename the file within this directory.
                mutate((data) => data.map((f) => (f.name === files[0] ? { ...f, name } : f)), false);
            } else if (useMoveTerminology || len > 1) {
                // Remove the file from this directory since they moved it elsewhere.
                mutate((data) => data.filter((f) => f.name !== files[0]), false);
            }
        }

        let data;
        if (useMoveTerminology && files.length > 1) {
            data = files.map((f) => ({ from: f, to: join(name, f) }));
        } else {
            data = files.map((f) => ({ from: f, to: name }));
        }

        renameFiles(uuid, directory, data)
            .then((): Promise<any> => (files.length > 0 ? mutate() : Promise.resolve()))
            .then(() => setSelectedFiles([]))
            .catch((error) => {
                mutate();
                setSubmitting(false);
                clearAndAddHttpError({ key: 'files', error });
            })
            .then(() => props.onDismissed());
    };

    return (
        <Formik onSubmit={submit} initialValues={{ name: files.length > 1 ? '' : files[0] || '' }}>
            {({ isSubmitting, values, setFieldValue }) => {
                const selectMoveDirectory = (path: string) => {
                    const next = cleanDirectoryPath(path);
                    setMoveDirectory(next);
                    setFieldValue('name', getMoveValue(next));
                };

                return (
                <Modal {...props} dismissable={!isSubmitting} showSpinnerOverlay={isSubmitting}>
                    <Form css={tw`m-0`}>
                        <div css={[tw`flex flex-wrap`, tw`items-end`]}>
                            <div css={tw`w-full sm:flex-1 sm:mr-4`}>
                                {useMoveTerminology ? (
                                    <div>
                                        <label className={'Label-sc-g780ms-0 eqavvQ'}>Move Destination</label>
                                        <div
                                            className={
                                                'rounded-[16px] border-2 px-4 py-3 font-mono text-sm font-bold text-[#742220]'
                                            }
                                            style={{ borderColor: '#2D4A3E', backgroundColor: '#F5EFD5' }}
                                        >
                                            /home/container/{moveDirectory.replace(/^\/+/, '') || ''}
                                        </div>
                                        <input type={'hidden'} name={'name'} value={values.name} readOnly />
                                        <p className={'input-help'}>
                                            Choose a folder below. The file will move to the folder currently shown here.
                                        </p>
                                    </div>
                                ) : (
                                    <Field
                                        type={'string'}
                                        id={'file_name'}
                                        name={'name'}
                                        label={'File Name'}
                                        className={
                                            '!border-[#2D4A3E] !bg-[#FEF9E1] !text-[#742220] focus:!border-[#2D4A3E] focus:!ring-[#2D4A3E]'
                                        }
                                        autoFocus
                                    />
                                )}
                            </div>
                            <div css={tw`w-full sm:w-auto mt-4 sm:mt-0`}>
                                <button
                                    type={'submit'}
                                    disabled={isSubmitting}
                                    className={
                                        'w-full rounded-full border border-[#742220] bg-[#742220] px-5 py-2 text-sm font-black uppercase tracking-wide text-[#FEF9E1] transition-colors hover:border-[#5f1c1a] hover:bg-[#5f1c1a] disabled:cursor-not-allowed disabled:opacity-60'
                                    }
                                >
                                    {useMoveTerminology ? 'Move' : 'Rename'}
                                </button>
                            </div>
                        </div>
                        {useMoveTerminology && (
                            <>
                                <div
                                    className={'mt-4 rounded-[18px] border-2 p-3'}
                                    style={{ borderColor: '#2D4A3E', backgroundColor: '#F5EFD5' }}
                                >
                                    <div className={'flex flex-wrap items-center justify-between gap-2'}>
                                        <div className={'min-w-0 font-mono text-xs font-black uppercase tracking-wide text-[#742220]'}>
                                            Folder Browser
                                        </div>
                                        <button
                                            type={'button'}
                                            disabled={moveDirectory === '/'}
                                            onClick={() => selectMoveDirectory(dirname(moveDirectory) || '/')}
                                            className={
                                                'inline-flex items-center gap-2 rounded-full border border-[#2D4A3E] bg-[#FEF9E1] px-3 py-1.5 text-xs font-black uppercase tracking-wide text-[#742220] transition-colors hover:bg-[rgba(45,74,62,0.12)] disabled:cursor-not-allowed disabled:opacity-50'
                                            }
                                        >
                                            <FontAwesomeIcon icon={faLevelUpAlt} />
                                            Up
                                        </button>
                                    </div>
                                    <div className={'mt-3 max-h-56 space-y-2 overflow-y-auto pr-1'}>
                                        {moveLoading ? (
                                            <div className={'rounded-[14px] border border-[#2D4A3E] bg-[#FEF9E1] px-3 py-3 text-sm font-bold text-[#742220]'}>
                                                Loading folders...
                                            </div>
                                        ) : moveFolders.length === 0 ? (
                                            <div className={'rounded-[14px] border border-[#2D4A3E] bg-[#FEF9E1] px-3 py-3 text-sm font-bold text-[rgba(116,34,32,0.62)]'}>
                                                No folders inside this location.
                                            </div>
                                        ) : (
                                            moveFolders.map((folder) => (
                                                <button
                                                    key={folder.key}
                                                    type={'button'}
                                                    onClick={() => selectMoveDirectory(join(moveDirectory, folder.name))}
                                                    className={
                                                        'flex w-full items-center gap-3 rounded-[14px] border border-transparent px-3 py-2 text-left text-sm font-bold text-[#742220] transition-colors hover:border-[#2D4A3E] hover:bg-[#FEF9E1]'
                                                    }
                                                >
                                                    <FontAwesomeIcon icon={faFolder} className={'text-[#2D4A3E]'} />
                                                    <span className={'min-w-0 truncate'}>{folder.name}</span>
                                                </button>
                                            ))
                                        )}
                                    </div>
                                </div>
                                <p css={tw`mt-2 text-xs text-[color:var(--text-subtle)]`}>
                                    <strong css={tw`text-[color:var(--foreground)]`}>New location:</strong>
                                    &nbsp;/home/container/{join(directory, values.name).replace(/^(\.\.\/|\/)+/, '')}
                                </p>
                            </>
                        )}
                    </Form>
                </Modal>
                );
            }}
        </Formik>
    );
};

export default RenameFileModal;
