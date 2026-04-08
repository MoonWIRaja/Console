import React, { useState } from 'react';
import Modal from '@/components/elements/Modal';
import { Form, Formik, FormikHelpers } from 'formik';
import Field from '@/components/elements/Field';
import { object, string } from 'yup';
import { httpErrorToHuman } from '@/api/http';
import Button from '@/components/elements/Button';
import GreyRowBox from '@/components/elements/GreyRowBox';
import tw from 'twin.macro';
import { Link } from 'react-router-dom';
import FlashMessageRender from '@/components/FlashMessageRender';
import { SplitFamilyServer, SplitOverview } from '@/api/server/split/types';
import deleteSplitServer from '@/api/server/split/deleteSplitServer';
import { Actions, useStoreActions } from 'easy-peasy';
import { ApplicationStore } from '@/state';

interface Props {
    member: SplitFamilyServer;
    serverUuid: string;
    onDeleted: () => Promise<SplitOverview | undefined>;
}

const SplitRow = ({ member, serverUuid, onDeleted }: Props) => {
    const [visible, setVisible] = useState(false);
    const { clearFlashes, addFlash } = useStoreActions((actions: Actions<ApplicationStore>) => actions.flashes);

    const submit = (values: { confirm: string }, { setSubmitting, resetForm }: FormikHelpers<{ confirm: string }>) => {
        clearFlashes('server:split');

        deleteSplitServer(serverUuid, member.id, values.confirm)
            .then(() => {
                addFlash({
                    key: 'server:split',
                    type: 'success',
                    title: 'Success',
                    message: 'Split server deleted successfully.',
                });

                return onDeleted().then(() => {
                    resetForm();
                    setVisible(false);
                });
            })
            .catch((error) => {
                return onDeleted()
                    .then((overview) => {
                        const stillExists = overview?.servers.some((server) => server.id === member.id) ?? true;
                        if (!stillExists) {
                            addFlash({
                                key: 'server:split',
                                type: 'success',
                                title: 'Success',
                                message: 'Split server was deleted successfully.',
                            });

                            resetForm();
                            setVisible(false);

                            return;
                        }

                        addFlash({
                            key: 'server:split',
                            type: 'error',
                            title: 'Error',
                            message: httpErrorToHuman(error),
                        });
                    })
                    .catch(() => {
                        addFlash({
                            key: 'server:split',
                            type: 'error',
                            title: 'Error',
                            message: httpErrorToHuman(error),
                        });
                    });
            })
            .then(() => setSubmitting(false));
    };

    return (
        <>
            <Formik
                onSubmit={submit}
                initialValues={{ confirm: '' }}
                validationSchema={object().shape({
                    confirm: string()
                        .required('Enter the server name to confirm deletion.')
                        .oneOf([member.name], 'Enter the exact server name to continue.'),
                })}
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
                        <h2 css={tw`mb-6 text-2xl text-[#f8f6ef]`}>Delete split server</h2>
                        <p css={tw`text-sm text-[color:var(--text-subtle)]`}>
                            This will permanently delete <strong css={tw`text-[#f8f6ef]`}>{member.name}</strong> and
                            refund its resources back to its parent server.
                        </p>
                        <Form css={tw`m-0 mt-6`}>
                            <Field
                                type={'text'}
                                id={`confirm_split_${member.id}`}
                                name={'confirm'}
                                label={'Confirm Server Name'}
                                description={'Type the exact server name to confirm deletion.'}
                            />
                            <div css={tw`mt-6 flex flex-wrap justify-end`}>
                                <Button
                                    type={'button'}
                                    isSecondary
                                    disabled={isSubmitting}
                                    css={tw`w-full sm:w-auto sm:mr-2`}
                                    onClick={() => setVisible(false)}
                                >
                                    Cancel
                                </Button>
                                <Button css={tw`mt-4 w-full sm:mt-0 sm:w-auto`} type={'submit'} color={'red'}>
                                    Delete Split Server
                                </Button>
                            </div>
                        </Form>
                    </Modal>
                )}
            </Formik>

            <GreyRowBox $hoverable={false} className={'mt-3 flex-wrap gap-4 md:flex-nowrap'}>
                <div className={'min-w-0 flex-1'}>
                    <div className={'flex flex-wrap items-center gap-2'}>
                        <Link
                            to={`/server/${member.identifier}`}
                            className={'text-lg font-semibold text-[#f8f6ef] no-underline'}
                        >
                            {member.name}
                        </Link>
                        {member.isRoot && (
                            <span
                                className={
                                    'rounded-full border border-[#22c55e]/45 bg-[#22c55e]/12 px-2 py-1 text-xs font-semibold text-[#bbf7d0]'
                                }
                            >
                                Root
                            </span>
                        )}
                        {member.isCurrent && (
                            <span
                                className={
                                    'rounded-full border border-[color:var(--primary)]/45 bg-[color:var(--primary)]/12 px-2 py-1 text-xs font-semibold text-[color:var(--primary)]'
                                }
                            >
                                Current
                            </span>
                        )}
                        {member.hasChildren && (
                            <span
                                className={
                                    'rounded-full border border-[#f59e0b]/40 bg-[#f59e0b]/10 px-2 py-1 text-xs font-semibold text-[#fde68a]'
                                }
                            >
                                Has Children
                            </span>
                        )}
                    </div>
                    <p className={'mt-2 text-sm text-[color:var(--text-subtle)]'}>{member.allocation.label}</p>
                </div>

                <div className={'grid min-w-[18rem] grid-cols-2 gap-3 text-sm md:w-[22rem]'}>
                    <div className={'rounded-lg border border-[color:var(--border)] bg-[color:var(--card)] px-3 py-2'}>
                        <div className={'text-[10px] uppercase tracking-[0.18em] text-[color:var(--text-subtle)]'}>
                            Memory
                        </div>
                        <div className={'mt-1 text-[#f8f6ef]'}>{member.memory} MiB</div>
                    </div>
                    <div className={'rounded-lg border border-[color:var(--border)] bg-[color:var(--card)] px-3 py-2'}>
                        <div className={'text-[10px] uppercase tracking-[0.18em] text-[color:var(--text-subtle)]'}>
                            CPU
                        </div>
                        <div className={'mt-1 text-[#f8f6ef]'}>{member.cpu}%</div>
                    </div>
                    <div className={'rounded-lg border border-[color:var(--border)] bg-[color:var(--card)] px-3 py-2'}>
                        <div className={'text-[10px] uppercase tracking-[0.18em] text-[color:var(--text-subtle)]'}>
                            Disk
                        </div>
                        <div className={'mt-1 text-[#f8f6ef]'}>{member.disk} MiB</div>
                    </div>
                    <div className={'rounded-lg border border-[color:var(--border)] bg-[color:var(--card)] px-3 py-2'}>
                        <div className={'text-[10px] uppercase tracking-[0.18em] text-[color:var(--text-subtle)]'}>
                            Swap
                        </div>
                        <div className={'mt-1 text-[#f8f6ef]'}>{member.swap} MiB</div>
                    </div>
                </div>

                <div className={'flex items-center'}>
                    {member.canDelete ? (
                        <button
                            type={'button'}
                            className={
                                'rounded-lg border border-[#ef4444]/40 bg-[#ef4444]/10 px-3 py-2 text-sm font-semibold text-[#fecaca] transition hover:border-[#ef4444] hover:bg-[#ef4444]/16'
                            }
                            onClick={() => setVisible(true)}
                        >
                            Delete
                        </button>
                    ) : (
                        <span className={'text-xs text-[color:var(--text-subtle)]'}>
                            {member.isRoot
                                ? 'Root server'
                                : member.isCurrent
                                ? 'Current server'
                                : member.hasChildren
                                ? 'Delete children first'
                                : 'Protected'}
                        </span>
                    )}
                </div>
            </GreyRowBox>
        </>
    );
};

export default SplitRow;
