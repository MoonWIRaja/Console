import React, { useEffect, useState } from 'react';
import { ServerContext } from '@/state/server';
import TitledGreyBox from '@/components/elements/TitledGreyBox';
import reinstallServer from '@/api/server/reinstallServer';
import { Actions, useStoreActions } from 'easy-peasy';
import { ApplicationStore } from '@/state';
import { httpErrorToHuman } from '@/api/http';
import tw from 'twin.macro';
import { Dialog } from '@/components/elements/dialog';
import { InteractiveHoverButton } from '@/components/ui/interactive-hover-button';

export default () => {
    const server = ServerContext.useStoreState((state) => state.server.data!);
    const uuid = server.uuid;
    const getServer = ServerContext.useStoreActions((actions) => actions.server.getServer);
    const setServerFromState = ServerContext.useStoreActions((actions) => actions.server.setServerFromState);
    const [modalVisible, setModalVisible] = useState(false);
    const { addFlash, clearFlashes } = useStoreActions((actions: Actions<ApplicationStore>) => actions.flashes);

    const reinstall = () => {
        clearFlashes('settings');
        reinstallServer(uuid)
            .then(() => {
                setServerFromState((state) => ({ ...state, status: 'installing' }));
                addFlash({
                    key: 'settings',
                    type: 'success',
                    message: 'Your server has begun the reinstallation process.',
                });

                window.setTimeout(() => {
                    getServer(uuid).catch((error) => console.error(error));
                }, 1500);
            })
            .catch((error) => {
                console.error(error);

                addFlash({ key: 'settings', type: 'error', message: httpErrorToHuman(error) });
            })
            .then(() => setModalVisible(false));
    };

    useEffect(() => {
        clearFlashes();
    }, []);

    return (
        <TitledGreyBox title={'Reinstall Server'} css={tw`relative`}>
            <Dialog.Confirm
                open={modalVisible}
                title={'Confirm server reinstallation'}
                confirm={'Yes, reinstall server'}
                onClose={() => setModalVisible(false)}
                onConfirmed={reinstall}
            >
                Your server will be stopped and some files may be deleted or modified during this process, are you sure
                you wish to continue?
            </Dialog.Confirm>
            <p css={tw`text-sm text-neutral-300`}>
                Reinstalling your server will stop it, and then re-run the installation script that initially set it
                up.&nbsp;
                <strong css={tw`font-medium text-[#f8f6ef]`}>
                    Some files may be deleted or modified during this process, please back up your data before
                    continuing.
                </strong>
            </p>
            {!server.canReinstall && server.reinstallBlockReason && (
                <p css={tw`mt-4 rounded-xl border border-yellow-500 bg-yellow-900 px-4 py-3 text-sm text-yellow-100`}>
                    {server.reinstallBlockReason}
                </p>
            )}
            <div css={tw`mt-6 text-right`}>
                <InteractiveHoverButton
                    text={'Reinstall Server'}
                    variant={'danger'}
                    disabled={!server.canReinstall}
                    onClick={() => setModalVisible(true)}
                />
            </div>
        </TitledGreyBox>
    );
};
