import React, { useContext, useEffect } from 'react';
import { ServerContext } from '@/state/server';
import { CloudUploadIcon, XIcon } from '@heroicons/react/solid';
import asDialog from '@/hoc/asDialog';
import { Dialog, DialogWrapperContext } from '@/components/elements/dialog';
import { Button } from '@/components/elements/button/index';
import Tooltip from '@/components/elements/tooltip/Tooltip';
import Code from '@/components/elements/Code';
import { useSignal } from '@preact/signals-react';

const svgProps = {
    cx: 16,
    cy: 16,
    r: 14,
    strokeWidth: 3,
    fill: 'none',
    stroke: 'currentColor',
};

const Spinner = ({ progress, className }: { progress: number; className?: string }) => (
    <svg viewBox={'0 0 32 32'} className={className}>
        <circle {...svgProps} className={'opacity-25'} />
        <circle
            {...svgProps}
            stroke={'#2D4A3E'}
            strokeDasharray={28 * Math.PI}
            className={'rotate-[-90deg] origin-[50%_50%] transition-[stroke-dashoffset] duration-300'}
            style={{ strokeDashoffset: ((100 - progress) / 100) * 28 * Math.PI }}
        />
    </svg>
);

const FileUploadList = () => {
    const { close } = useContext(DialogWrapperContext);
    const cancelFileUpload = ServerContext.useStoreActions((actions) => actions.files.cancelFileUpload);
    const clearFileUploads = ServerContext.useStoreActions((actions) => actions.files.clearFileUploads);
    const uploads = ServerContext.useStoreState((state) =>
        Object.entries(state.files.uploads).sort(([a], [b]) => a.localeCompare(b))
    );

    return (
        <div className={'mt-4'}>
            <div className={'space-y-2 overflow-y-auto pr-1'} style={{ maxHeight: 'min(52vh, 560px)' }}>
                {uploads.map(([name, file]) => (
                    <div
                        key={name}
                        className={'flex items-center space-x-3 rounded-[18px] border-2 p-3'}
                        style={{
                            borderColor: '#2D4A3E',
                            backgroundColor: '#F5EFD5',
                            boxShadow: 'inset 0 0 0 1px rgba(116, 34, 32, 0.04)',
                        }}
                    >
                        <Tooltip content={`${Math.floor((file.loaded / file.total) * 100)}%`} placement={'left'}>
                            <div className={'flex-shrink-0'}>
                                <Spinner
                                    progress={(file.loaded / file.total) * 100}
                                    className={'h-6 w-6 text-[#742220]'}
                                />
                            </div>
                        </Tooltip>
                        <Code
                            className={'flex-1 truncate border !border-[#2D4A3E] !bg-[#FEF9E1] !text-[#742220]'}
                        >
                            {name}
                        </Code>
                        <button
                            onClick={cancelFileUpload.bind(this, name)}
                            className={
                                'rounded-[10px] p-1 text-[rgba(116,34,32,0.5)] transition-colors duration-75 hover:bg-[rgba(45,74,62,0.12)] hover:text-[#2D4A3E]'
                            }
                        >
                            <XIcon className={'h-5 w-5'} />
                        </button>
                    </div>
                ))}
            </div>
            <Dialog.Footer>
                <Button.Danger
                    variant={Button.Variants.Secondary}
                    className={
                        '!border-[#742220] !bg-[#742220] !text-[#FEF9E1] hover:!border-[#5f1c1a] hover:!bg-[#5f1c1a]'
                    }
                    onClick={() => clearFileUploads()}
                >
                    Cancel Uploads
                </Button.Danger>
                <Button.Text
                    className={
                        '!border-[#2D4A3E] !bg-[#F5EFD5] !text-[#742220] hover:!border-[#2D4A3E] hover:!bg-[rgba(45,74,62,0.12)] hover:!text-[#2D4A3E]'
                    }
                    onClick={close}
                >
                    Close
                </Button.Text>
            </Dialog.Footer>
        </div>
    );
};

const FileUploadListDialog = asDialog({
    title: 'File Uploads',
    description: 'The following files are being uploaded to your server.',
})(FileUploadList);

export default () => {
    const open = useSignal(false);

    const count = ServerContext.useStoreState((state) => Object.keys(state.files.uploads).length);
    const progress = ServerContext.useStoreState((state) => ({
        uploaded: Object.values(state.files.uploads).reduce((count, file) => count + file.loaded, 0),
        total: Object.values(state.files.uploads).reduce((count, file) => count + file.total, 0),
    }));

    useEffect(() => {
        if (count === 0) {
            open.value = false;
        }
    }, [count]);

    return (
        <>
            {count > 0 && (
                <Tooltip content={`${count} files are uploading, click to view`}>
                    <button
                        className={
                            'flex h-10 w-10 items-center justify-center rounded-lg border-2 border-[#2D4A3E] bg-[#F5EFD5] text-[#2D4A3E] transition-colors duration-150 hover:bg-[#EDE6D0]'
                        }
                        onClick={() => (open.value = true)}
                    >
                        <Spinner progress={(progress.uploaded / progress.total) * 100} className={'h-8 w-8'} />
                        <CloudUploadIcon className={'absolute mx-auto h-3 animate-pulse'} />
                    </button>
                </Tooltip>
            )}
            <FileUploadListDialog open={open.value} onClose={() => (open.value = false)} />
        </>
    );
};
