import React, { useEffect } from 'react';
import SpinnerOverlay from '@/components/elements/SpinnerOverlay';
import FlashMessageRender from '@/components/FlashMessageRender';
import PageContentBlock from '@/components/elements/PageContentBlock';
import tw from 'twin.macro';
import { useSSHKeys } from '@/api/account/ssh-keys';
import { useFlashKey } from '@/plugins/useFlash';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faKey } from '@fortawesome/free-solid-svg-icons';
import { format } from 'date-fns';
import CreateSSHKeyForm from '@/components/dashboard/ssh/CreateSSHKeyForm';
import DeleteSSHKeyButton from '@/components/dashboard/ssh/DeleteSSHKeyButton';

export default () => {
    const { clearAndAddHttpError } = useFlashKey('account');
    const { data, isValidating, error } = useSSHKeys({
        revalidateOnMount: true,
        revalidateOnFocus: false,
    });

    useEffect(() => {
        clearAndAddHttpError(error);
    }, [error]);

    return (
        <PageContentBlock title={'SSH Keys'}>
            <style>{`
                @import url('https://fonts.googleapis.com/css2?family=Space+Mono:ital,wght@0,400;0,700;1,400&display=swap');
            `}</style>
            <FlashMessageRender byKey={'account'} />
            <div css={tw`my-0 font-mono`}>
                <div css={tw`md:flex bg-white`}>
                    <section css={tw`w-full md:w-1/2 p-6 md:border-r-2 border-black flex flex-col`}>
                        <h2 css={tw`text-black text-center mb-4 text-[1.65rem]`}>Add SSH Key</h2>
                        <div css={tw`mt-2`}>
                            <CreateSSHKeyForm />
                        </div>
                    </section>

                    <section css={tw`w-full md:w-1/2 p-6 flex flex-col`}>
                        <h2 css={tw`text-black text-center mb-4 text-[1.65rem]`}>SSH Keys</h2>
                        <div css={tw`mt-2 relative`}>
                            <SpinnerOverlay visible={!data && isValidating} />
                            {!data || !data.length ? (
                                <p css={tw`text-center text-sm text-neutral-500`}>
                                    {!data ? 'Loading...' : 'No SSH Keys exist for this account.'}
                                </p>
                            ) : (
                                data.map((key, index) => (
                                    <div
                                        key={key.fingerprint}
                                        css={[
                                            tw`flex items-center px-1 py-3 border-b border-black`,
                                            index === data.length - 1 && tw`border-b-0`,
                                        ]}
                                    >
                                        <FontAwesomeIcon icon={faKey} css={tw`text-black`} />
                                        <div css={tw`flex-1`}>
                                            <p css={tw`text-sm break-words font-bold text-black`}>{key.name}</p>
                                            <p css={tw`text-xs mt-1 font-mono truncate text-neutral-700`}>
                                                SHA256:{key.fingerprint}
                                            </p>
                                            <p css={tw`text-xs mt-1 text-neutral-500 uppercase tracking-wide`}>
                                                Added on:&nbsp;
                                                {format(key.createdAt, 'MMM do, yyyy HH:mm')}
                                            </p>
                                        </div>
                                        <DeleteSSHKeyButton name={key.name} fingerprint={key.fingerprint} />
                                    </div>
                                ))
                            )}
                        </div>
                    </section>
                </div>
            </div>
        </PageContentBlock>
    );
};
