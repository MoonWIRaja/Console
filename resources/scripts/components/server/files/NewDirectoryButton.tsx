import React, { useEffect, useState } from 'react';
import { ServerContext } from '@/state/server';
import { Form, Formik, FormikHelpers } from 'formik';
import Field from '@/components/elements/Field';
import { join } from 'pathe';
import { object, string } from 'yup';
import createDirectory from '@/api/server/files/createDirectory';
import tw from 'twin.macro';
import Button from '@/components/elements/Button';
import { FileObject } from '@/api/server/files/loadDirectory';
import { useFlashKey } from '@/plugins/useFlash';
import useFileManagerSwr from '@/plugins/useFileManagerSwr';
import { WithClassname } from '@/components/types';
import FlashMessageRender from '@/components/FlashMessageRender';
import Modal from '@/components/elements/Modal';
import Code from '@/components/elements/Code';
import { InteractiveHoverButton } from '@/components/ui/interactive-hover-button';

interface Values {
    directoryName: string;
}

const schema = object().shape({
    directoryName: string().required('A valid directory name must be provided.'),
});

const generateDirectoryData = (name: string): FileObject => ({
    key: `dir_${name.split('/', 1)[0] ?? name}`,
    name: name.replace(/^(\/*)/, '').split('/', 1)[0] ?? name,
    mode: 'drwxr-xr-x',
    modeBits: '0755',
    size: 0,
    isFile: false,
    isSymlink: false,
    mimetype: '',
    createdAt: new Date(),
    modifiedAt: new Date(),
    isArchiveType: () => false,
    isEditable: () => false,
});

export default ({ className }: WithClassname) => {
    const [open, setOpen] = useState(false);
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const directory = ServerContext.useStoreState((state) => state.files.directory);
    const { mutate } = useFileManagerSwr();
    const { clearAndAddHttpError } = useFlashKey('files:directory-modal');

    useEffect(() => {
        return () => {
            clearAndAddHttpError();
        };
    }, []);

    const submit = ({ directoryName }: Values, { setSubmitting, resetForm }: FormikHelpers<Values>) => {
        createDirectory(uuid, directory, directoryName)
            .then(() => mutate((data) => [...data, generateDirectoryData(directoryName)], false))
            .then(() => {
                resetForm();
                setOpen(false);
            })
            .catch((error) => {
                setSubmitting(false);
                clearAndAddHttpError(error);
            });
    };

    return (
        <>
            <Formik onSubmit={submit} validationSchema={schema} initialValues={{ directoryName: '' }}>
                {({ isSubmitting, submitForm, values, resetForm }) => (
                    <Modal
                        visible={open}
                        dismissable={!isSubmitting}
                        showSpinnerOverlay={isSubmitting}
                        onDismissed={() => {
                            resetForm();
                            setOpen(false);
                        }}
                    >
                        <FlashMessageRender byKey={'files:directory-modal'} css={tw`mb-6`} />
                        <h2 css={tw`mb-6 text-2xl text-[color:var(--foreground)]`}>Create Directory</h2>
                        <Form css={tw`m-0`}>
                            <Field autoFocus id={'directoryName'} name={'directoryName'} label={'Name'} />
                            <p css={tw`mt-2 break-all text-sm md:text-base`}>
                                <span css={tw`text-[color:var(--foreground)]`}>
                                    This directory will be created as&nbsp;
                                </span>
                                <Code
                                    className={
                                        '!border !border-[color:var(--surface-border)] !bg-[color:var(--surface-subtle)] !text-[color:var(--foreground)]'
                                    }
                                >
                                    /home/container/
                                    <span css={tw`text-[color:var(--primary)]`}>
                                        {join(directory, values.directoryName).replace(/^(\.\.\/|\/)+/, '')}
                                    </span>
                                </Code>
                            </p>
                        </Form>
                        <div css={tw`mt-6 flex flex-wrap justify-end border-t border-[color:var(--primary)] pt-5`}>
                            <Button
                                type={'button'}
                                isSecondary
                                css={tw`w-full sm:w-auto sm:mr-2`}
                                onClick={() => setOpen(false)}
                            >
                                Cancel
                            </Button>
                            <Button
                                type={'button'}
                                css={tw`mt-4 w-full sm:mt-0 sm:w-auto`}
                                onClick={submitForm}
                                disabled={isSubmitting}
                            >
                                Create
                            </Button>
                        </div>
                    </Modal>
                )}
            </Formik>
            <InteractiveHoverButton
                onClick={() => setOpen(true)}
                text={'Create Directory'}
                className={className ?? ''}
            />
        </>
    );
};
