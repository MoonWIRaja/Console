import React, { useMemo, useState } from 'react';
import Modal from '@/components/elements/Modal';
import { Form, Formik, FormikHelpers } from 'formik';
import Field from '@/components/elements/Field';
import { number, object, string } from 'yup';
import { httpErrorToHuman } from '@/api/http';
import Button from '@/components/elements/Button';
import GreyRowBox from '@/components/elements/GreyRowBox';
import tw from 'twin.macro';
import { Link } from 'react-router-dom';
import FlashMessageRender from '@/components/FlashMessageRender';
import { SplitFamilyServer, SplitOverview } from '@/api/server/split/types';
import deleteSplitServer from '@/api/server/split/deleteSplitServer';
import updateSplitServer from '@/api/server/split/updateSplitServer';
import { Actions, useStoreActions } from 'easy-peasy';
import { ApplicationStore } from '@/state';
import Can from '@/components/elements/Can';

interface Props {
    member: SplitFamilyServer;
    serverUuid: string;
    overview: SplitOverview;
    onUpdated: () => Promise<SplitOverview | undefined>;
    onDeleted: () => Promise<SplitOverview | undefined>;
}

interface EditValues {
    name: string;
    cpu: number;
    memory: number;
    disk: number;
    swap: number;
}

const SplitRow = ({ member, serverUuid, overview, onUpdated, onDeleted }: Props) => {
    const [deleteVisible, setDeleteVisible] = useState(false);
    const [editVisible, setEditVisible] = useState(false);
    const { clearFlashes, addFlash } = useStoreActions((actions: Actions<ApplicationStore>) => actions.flashes);

    // Available = what parent has free + what this split currently holds (since edit replaces)
    // Parent available from overview.available (that's what the CURRENT server can give out).
    // But for editing a child from a different parent context, use overview.available + member's current values.
    const availableCpu    = overview.available.cpu + member.cpu;
    const availableMemory = overview.available.memory + member.memory;
    const availableDisk   = overview.available.disk + member.disk;
    const availableSwap   = overview.available.swap + member.swap;

    const editSchema = useMemo(
        () =>
            object().shape({
                name: string().required('A server name is required.').max(191, 'Server name is too long.'),
                cpu: number()
                    .required('CPU is required.')
                    .min(1, 'CPU must be at least 1%.')
                    .max(availableCpu, `Max available CPU: ${availableCpu}%`),
                memory: number()
                    .required('Memory is required.')
                    .min(512, 'Memory must be at least 512 MiB.')
                    .max(availableMemory, `Max available memory: ${availableMemory} MiB`),
                disk: number()
                    .required('Disk is required.')
                    .min(1, 'Disk must be at least 1 MiB.')
                    .max(availableDisk, `Max available disk: ${availableDisk} MiB`),
                swap: number()
                    .required('Swap is required.')
                    .min(0, 'Swap cannot be negative.')
                    .max(availableSwap, `Max available swap: ${availableSwap} MiB`),
            }),
        [availableCpu, availableMemory, availableDisk, availableSwap]
    );

    const submitEdit = (values: EditValues, { setSubmitting, resetForm }: FormikHelpers<EditValues>) => {
        clearFlashes('server:split');

        updateSplitServer(serverUuid, member.id, values)
            .then(() => {
                addFlash({
                    key: 'server:split',
                    type: 'success',
                    title: 'Success',
                    message: 'Split server updated successfully.',
                });

                return onUpdated().then(() => {
                    resetForm();
                    setEditVisible(false);
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

    const submitDelete = (values: { confirm: string }, { setSubmitting, resetForm }: FormikHelpers<{ confirm: string }>) => {
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
                    setDeleteVisible(false);
                });
            })
            .catch((error) => {
                return onDeleted()
                    .then((ov) => {
                        const stillExists = ov?.servers.some((server) => server.id === member.id) ?? true;
                        if (!stillExists) {
                            addFlash({
                                key: 'server:split',
                                type: 'success',
                                title: 'Success',
                                message: 'Split server was deleted successfully.',
                            });

                            resetForm();
                            setDeleteVisible(false);

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
            {/* Edit Modal */}
            <Formik
                onSubmit={submitEdit}
                enableReinitialize
                initialValues={{
                    name: member.name,
                    cpu: member.cpu,
                    memory: member.memory,
                    disk: member.disk,
                    swap: member.swap,
                }}
                validationSchema={editSchema}
            >
                {({ isSubmitting, resetForm }) => (
                    <Modal
                        visible={editVisible}
                        dismissable={!isSubmitting}
                        showSpinnerOverlay={isSubmitting}
                        onDismissed={() => {
                            resetForm();
                            setEditVisible(false);
                        }}
                    >
                        <FlashMessageRender byKey={'server:split'} />
                        <h2 css={tw`mb-6 text-2xl text-[#742220]`}>Edit split server</h2>
                        <Form css={tw`m-0`}>
                            <div css={tw`grid gap-4 md:grid-cols-2`}>
                                <Field
                                    type={'number'}
                                    id={`edit_split_cpu_${member.id}`}
                                    name={'cpu'}
                                    label={'CPU'}
                                    description={`Max: ${availableCpu}%`}
                                />
                                <Field
                                    type={'number'}
                                    id={`edit_split_memory_${member.id}`}
                                    name={'memory'}
                                    label={'Memory'}
                                    description={`Max: ${availableMemory} MiB`}
                                />
                                <Field
                                    type={'number'}
                                    id={`edit_split_disk_${member.id}`}
                                    name={'disk'}
                                    label={'Disk'}
                                    description={`Max: ${availableDisk} MiB`}
                                />
                                <Field
                                    type={'number'}
                                    id={`edit_split_swap_${member.id}`}
                                    name={'swap'}
                                    label={'Swap'}
                                    description={`Max: ${availableSwap} MiB`}
                                />
                            </div>
                            <div css={tw`mt-6`}>
                                <Field
                                    type={'text'}
                                    id={`edit_split_name_${member.id}`}
                                    name={'name'}
                                    label={'Server Name'}
                                    description={'The display name for this split server.'}
                                />
                            </div>
                            <div css={tw`mt-6 flex flex-wrap justify-end`}>
                                <Button
                                    type={'button'}
                                    isSecondary
                                    disabled={isSubmitting}
                                    css={tw`w-full sm:w-auto sm:mr-2`}
                                    onClick={() => setEditVisible(false)}
                                >
                                    Cancel
                                </Button>
                                <Button css={tw`mt-4 w-full sm:mt-0 sm:w-auto`} type={'submit'} disabled={isSubmitting}>
                                    Save Changes
                                </Button>
                            </div>
                        </Form>
                    </Modal>
                )}
            </Formik>

            {/* Delete Modal */}
            <Formik
                onSubmit={submitDelete}
                initialValues={{ confirm: '' }}
                validationSchema={object().shape({
                    confirm: string()
                        .required('Enter the server name to confirm deletion.')
                        .oneOf([member.name], 'Enter the exact server name to continue.'),
                })}
            >
                {({ isSubmitting, resetForm }) => (
                    <Modal
                        visible={deleteVisible}
                        dismissable={!isSubmitting}
                        showSpinnerOverlay={isSubmitting}
                        onDismissed={() => {
                            resetForm();
                            setDeleteVisible(false);
                        }}
                    >
                        <FlashMessageRender byKey={'server:split'} />
                        <h2 css={tw`mb-6 text-2xl text-[#742220]`}>Delete split server</h2>
                        <p css={tw`text-sm text-[rgba(116,34,32,0.7)]`}>
                            This will permanently delete <strong css={tw`text-[#742220]`}>{member.name}</strong> and
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
                                    onClick={() => setDeleteVisible(false)}
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
                            className={'text-lg font-semibold text-[color:var(--foreground)] no-underline'}
                        >
                            {member.name}
                        </Link>
                        {member.isRoot && (
                            <span
                                className={
                                    'rounded-full border border-[#166534] bg-[#dcfce7] px-2 py-1 text-xs font-semibold text-[#166534]'
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
                                    'rounded-full border border-[#92400e] bg-[#fef3c7] px-2 py-1 text-xs font-semibold text-[#92400e]'
                                }
                            >
                                Has Children
                            </span>
                        )}
                    </div>
                    <p className={'mt-2 text-sm text-[color:var(--text-subtle)]'}>{member.allocation.label}</p>
                </div>

                <div className={'grid min-w-[18rem] grid-cols-2 gap-3 text-sm md:w-[22rem]'}>
                    <div className={'sc-card-inner px-3 py-2'}>
                        <div className={'text-[10px] uppercase tracking-[0.18em] text-[color:var(--text-subtle)]'}>
                            Memory
                        </div>
                        <div className={'mt-1 text-[color:var(--foreground)]'}>{member.memory} MiB</div>
                    </div>
                    <div className={'sc-card-inner px-3 py-2'}>
                        <div className={'text-[10px] uppercase tracking-[0.18em] text-[color:var(--text-subtle)]'}>
                            CPU
                        </div>
                        <div className={'mt-1 text-[color:var(--foreground)]'}>{member.cpu}%</div>
                    </div>
                    <div className={'sc-card-inner px-3 py-2'}>
                        <div className={'text-[10px] uppercase tracking-[0.18em] text-[color:var(--text-subtle)]'}>
                            Disk
                        </div>
                        <div className={'mt-1 text-[color:var(--foreground)]'}>{member.disk} MiB</div>
                    </div>
                    <div className={'sc-card-inner px-3 py-2'}>
                        <div className={'text-[10px] uppercase tracking-[0.18em] text-[color:var(--text-subtle)]'}>
                            Swap
                        </div>
                        <div className={'mt-1 text-[color:var(--foreground)]'}>{member.swap} MiB</div>
                    </div>
                </div>

                <div className={'flex items-center gap-2'}>
                    {!member.isRoot && (
                        <Can action={'split.update'}>
                            <button
                                type={'button'}
                                className={
                                    'rounded-lg border border-[#2D4A3E] bg-[#F5EFD5] px-3 py-2 text-sm font-semibold text-[#2D4A3E] transition hover:border-[#2D4A3E] hover:bg-[#EDE6D0]'
                                }
                                onClick={() => setEditVisible(true)}
                            >
                                Edit
                            </button>
                        </Can>
                    )}
                    {member.canDelete ? (
                        <button
                            type={'button'}
                            className={
                                'rounded-lg border border-[#991b1b] bg-[#fee2e2] px-3 py-2 text-sm font-semibold text-[#991b1b] transition hover:border-[#7f1d1d] hover:bg-[#fecaca]'
                            }
                            onClick={() => setDeleteVisible(true)}
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
