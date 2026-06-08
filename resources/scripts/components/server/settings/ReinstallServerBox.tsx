import React, { useEffect, useRef, useState } from 'react';
import { ServerContext } from '@/state/server';
import TitledGreyBox from '@/components/elements/TitledGreyBox';
import reinstallServer from '@/api/server/reinstallServer';
import { Actions, useStoreActions } from 'easy-peasy';
import { ApplicationStore } from '@/state';
import { httpErrorToHuman } from '@/api/http';
import tw from 'twin.macro';
import { InteractiveHoverButton } from '@/components/ui/interactive-hover-button';
import Modal from '@/components/elements/Modal';
import Button from '@/components/elements/Button';

export default () => {
    const server = ServerContext.useStoreState((state) => state.server.data!);
    const uuid = server.uuid;
    const getServer = ServerContext.useStoreActions((actions) => actions.server.getServer);
    const setServerFromState = ServerContext.useStoreActions((actions) => actions.server.setServerFromState);
    const [modalVisible, setModalVisible] = useState(false);
    const isMounted = useRef(true);
    const refreshTimer = useRef<number | null>(null);
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

                refreshTimer.current = window.setTimeout(() => {
                    if (!isMounted.current) {
                        return;
                    }

                    getServer(uuid).catch((error) => console.error(error));
                    refreshTimer.current = null;
                }, 1500);
            })
            .catch((error) => {
                console.error(error);

                addFlash({ key: 'settings', type: 'error', message: httpErrorToHuman(error) });
            })
            .then(() => {
                if (isMounted.current) {
                    setModalVisible(false);
                }
            });
    };

    useEffect(() => {
        isMounted.current = true;
        clearFlashes();

        return () => {
            isMounted.current = false;

            if (refreshTimer.current !== null) {
                window.clearTimeout(refreshTimer.current);
                refreshTimer.current = null;
            }
        };
    }, [clearFlashes]);

    return (
        <TitledGreyBox title={'Reinstall Server'} css={tw`relative`}>
            <Modal visible={modalVisible} dismissable top={false} onDismissed={() => setModalVisible(false)}>
                <h2 css={tw`mb-3 text-2xl text-[color:var(--foreground)]`}>Confirm server reinstallation</h2>
                <p css={tw`text-sm leading-7 text-[color:var(--text-subtle)]`}>
                    Your server will be stopped and some files may be deleted or modified during this process, are you
                    sure you wish to continue?
                </p>
                <div css={tw`mt-6 flex flex-wrap justify-end border-t border-[color:var(--primary)] pt-5`}>
                    <Button
                        type={'button'}
                        isSecondary
                        css={tw`w-full sm:w-auto sm:mr-2`}
                        onClick={() => setModalVisible(false)}
                    >
                        Cancel
                    </Button>
                    <Button type={'button'} color={'red'} css={tw`mt-4 w-full sm:mt-0 sm:w-auto`} onClick={reinstall}>
                        Yes, reinstall server
                    </Button>
                </div>
            </Modal>
            <p css={tw`text-sm text-[color:var(--text-subtle)]`}>
                Reinstalling your server will stop it, and then re-run the installation script that initially set it
                up.&nbsp;
                <strong css={tw`font-medium text-[color:var(--foreground)]`}>
                    Some files may be deleted or modified during this process, please back up your data before
                    continuing.
                </strong>
            </p>
            {!server.canReinstall && server.reinstallBlockReason && (
                <p
                    css={tw`mt-4 rounded-xl border border-yellow-500/50 bg-yellow-500/10 px-4 py-3 text-sm text-yellow-100`}
                >
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
