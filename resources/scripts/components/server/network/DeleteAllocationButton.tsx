import React, { useState } from 'react';
import { faTrashAlt } from '@fortawesome/free-solid-svg-icons';
import tw from 'twin.macro';
import Icon from '@/components/elements/Icon';
import { ServerContext } from '@/state/server';
import deleteServerAllocation from '@/api/server/network/deleteServerAllocation';
import getServerAllocations from '@/api/swr/getServerAllocations';
import { useFlashKey } from '@/plugins/useFlash';
import Modal from '@/components/elements/Modal';
import Button from '@/components/elements/Button';
import { Button as UiButton } from '@/components/elements/button/index';

interface Props {
    allocation: number;
}

const DeleteAllocationButton = ({ allocation }: Props) => {
    const [confirm, setConfirm] = useState(false);

    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const setServerFromState = ServerContext.useStoreActions((actions) => actions.server.setServerFromState);

    const { mutate } = getServerAllocations();
    const { clearFlashes, clearAndAddHttpError } = useFlashKey('server:network');

    const deleteAllocation = () => {
        clearFlashes();

        mutate((data) => data?.filter((a) => a.id !== allocation), false);
        setServerFromState((s) => ({ ...s, allocations: s.allocations.filter((a) => a.id !== allocation) }));

        deleteServerAllocation(uuid, allocation).catch((error) => {
            clearAndAddHttpError(error);
            mutate();
        });
    };

    return (
        <>
            <Modal visible={confirm} dismissable top={false} onDismissed={() => setConfirm(false)}>
                <h2 css={tw`mb-3 text-2xl text-[color:var(--foreground)]`}>Remove Allocation</h2>
                <p css={tw`text-sm leading-7 text-[color:var(--text-subtle)]`}>
                    This allocation will be immediately removed from your server.
                </p>

                <div css={tw`mt-6 border-t border-[color:var(--primary)] pt-5 flex flex-wrap justify-end`}>
                    <Button
                        type={'button'}
                        isSecondary
                        css={tw`w-full sm:w-auto sm:mr-2`}
                        onClick={() => setConfirm(false)}
                    >
                        Cancel
                    </Button>
                    <Button
                        type={'button'}
                        color={'red'}
                        css={tw`mt-4 w-full sm:mt-0 sm:w-auto`}
                        onClick={deleteAllocation}
                    >
                        Delete
                    </Button>
                </div>
            </Modal>
            <UiButton.Danger
                variant={UiButton.Variants.Secondary}
                size={UiButton.Sizes.Small}
                shape={UiButton.Shapes.IconSquare}
                type={'button'}
                onClick={() => setConfirm(true)}
            >
                <Icon icon={faTrashAlt} css={tw`w-3 h-auto`} />
            </UiButton.Danger>
        </>
    );
};

export default DeleteAllocationButton;
