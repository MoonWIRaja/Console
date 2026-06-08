import React, { useMemo, useState } from 'react';
import Modal from '@/components/elements/Modal';
import { Form, Formik, FormikHelpers } from 'formik';
import Field from '@/components/elements/Field';
import FormikSwitch from '@/components/elements/FormikSwitch';
import { boolean, number, object, string } from 'yup';
import { ServerContext } from '@/state/server';
import { httpErrorToHuman } from '@/api/http';
import Button from '@/components/elements/Button';
import tw from 'twin.macro';
import FlashMessageRender from '@/components/FlashMessageRender';
import { Actions, useStoreActions } from 'easy-peasy';
import { ApplicationStore } from '@/state';
import Can from '@/components/elements/Can';
import { InteractiveHoverButton } from '@/components/ui/interactive-hover-button';
import createSplitServer from '@/api/server/split/createSplitServer';
import { SplitOverview } from '@/api/server/split/types';

interface Props {
    overview: SplitOverview;
    onCreated: () => Promise<unknown>;
}

interface Values {
    name: string;
    cpu: number;
    memory: number;
    disk: number;
    swap: number;
    copy_subusers: boolean;
}

const AddSplitButton = ({ overview, onCreated }: Props) => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const serverName = ServerContext.useStoreState((state) => state.server.data!.name);
    const [visible, setVisible] = useState(false);
    const { clearFlashes, addFlash } = useStoreActions((actions: Actions<ApplicationStore>) => actions.flashes);

    const schema = useMemo(
        () =>
            object().shape({
                name: string().required('A server name must be provided.').max(191, 'Server name is too long.'),
                cpu: number()
                    .required('CPU is required.')
                    .min(1, 'Split server CPU must be at least 1%.')
                    .max(Math.max(1, overview.available.cpu), 'There is not enough CPU available on this server.'),
                memory: number()
                    .required('Memory is required.')
                    .min(512, 'Split server memory must be at least 512 MiB.')
                    .max(
                        Math.max(512, overview.available.memory),
                        'There is not enough memory available on this server.'
                    ),
                disk: number()
                    .required('Disk is required.')
                    .min(1, 'Split server disk must be at least 1 MiB.')
                    .max(Math.max(1, overview.available.disk), 'There is not enough disk available on this server.'),
                swap: number()
                    .required('Swap is required.')
                    .min(0, 'Swap cannot be negative.')
                    .max(Math.max(0, overview.available.swap), 'There is not enough swap available on this server.'),
                copy_subusers: boolean().required(),
            }),
        [overview]
    );

    const submit = (values: Values, { setSubmitting, resetForm }: FormikHelpers<Values>) => {
        clearFlashes('server:split');

        createSplitServer(uuid, values)
            .then(() => {
                addFlash({
                    key: 'server:split',
                    type: 'success',
                    title: 'Success',
                    message: 'Split server created successfully.',
                });

                return onCreated().then(() => {
                    resetForm();
                    setVisible(false);
                });
            })
            .catch((error) => {
                addFlash({
                    key: 'server:split',
                    type: 'error',
                    title: 'Error',
                    message: httpErrorToHuman(error),
                });
            })
            .then(() => setSubmitting(false));
    };

    return (
        <>
            <Formik
                onSubmit={submit}
                initialValues={{
                    name: `${serverName} Split`,
                    cpu: 1,
                    memory: 512,
                    disk: 1024,
                    swap: 0,
                    copy_subusers: false,
                }}
                validationSchema={schema}
            >
                {({ isSubmitting, resetForm }) => (
                    <Modal
                        visible={visible}
                        dismissable={!isSubmitting}
                        showSpinnerOverlay={isSubmitting}
                        onDismissed={() => {
                            resetForm();
                            setVisible(false);
                        }}
                    >
                        <FlashMessageRender byKey={'server:split'} />
                        <h2 css={tw`mb-6 text-2xl text-[color:var(--foreground)]`}>Create split server</h2>
                        <Form css={tw`m-0`}>
                            <div css={tw`grid gap-4 md:grid-cols-2`}>
                                <Field
                                    type={'number'}
                                    id={'split_cpu'}
                                    name={'cpu'}
                                    label={'CPU'}
                                    description={`Available from this server: ${overview.available.cpu}%`}
                                />
                                <Field
                                    type={'number'}
                                    id={'split_memory'}
                                    name={'memory'}
                                    label={'Memory'}
                                    description={`Available from this server: ${overview.available.memory} MiB`}
                                />
                                <Field
                                    type={'number'}
                                    id={'split_disk'}
                                    name={'disk'}
                                    label={'Disk'}
                                    description={`Available from this server: ${overview.available.disk} MiB`}
                                />
                                <Field
                                    type={'number'}
                                    id={'split_swap'}
                                    name={'swap'}
                                    label={'Swap'}
                                    description={`Available from this server: ${overview.available.swap} MiB`}
                                />
                            </div>

                            <div css={tw`mt-6`}>
                                <Field
                                    type={'string'}
                                    id={'split_name'}
                                    name={'name'}
                                    label={'Server Name'}
                                    description={'Choose the name for the new split child server.'}
                                />
                            </div>

                            <Can action={['user.read', 'user.create']}>
                                <div
                                    css={tw`mt-6 rounded-xl border border-[color:var(--surface-border)] bg-[color:var(--surface-subtle)] p-4`}
                                >
                                    <FormikSwitch
                                        name={'copy_subusers'}
                                        label={'Copy Subusers'}
                                        description={
                                            'Duplicate current subusers and their permissions onto the new split server.'
                                        }
                                    />
                                </div>
                            </Can>

                            <div css={tw`mt-6 flex flex-wrap justify-end border-t border-[color:var(--primary)] pt-5`}>
                                <Button
                                    type={'button'}
                                    isSecondary
                                    disabled={isSubmitting}
                                    css={tw`w-full sm:w-auto sm:mr-2`}
                                    onClick={() => setVisible(false)}
                                >
                                    Cancel
                                </Button>
                                <Button css={tw`mt-4 w-full sm:mt-0 sm:w-auto`} type={'submit'} disabled={isSubmitting}>
                                    Create Split Server
                                </Button>
                            </div>
                        </Form>
                    </Modal>
                )}
            </Formik>

            <InteractiveHoverButton text={'Create Split'} onClick={() => setVisible(true)} variant={'success'} />
        </>
    );
};

export default AddSplitButton;
