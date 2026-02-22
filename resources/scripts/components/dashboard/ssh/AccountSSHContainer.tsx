import React, { useEffect } from 'react';
import ContentBox from '@/components/elements/ContentBox';
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
            <div css={tw`md:flex flex-nowrap my-10 gap-8 font-mono`}>
                <ContentBox
                    title={'Add SSH Key'}
                    css={tw`flex-none w-full md:w-1/2 [&>h2]:text-black [&>h2]:text-center [&>h2]:mb-3 [&>h2]:px-0 [&>h2]:text-[1.65rem] [&>div]:bg-white [&>div]:rounded-none [&>div]:shadow-none [&>div]:border-2 [&>div]:border-black`}
                >
                    <CreateSSHKeyForm />
                </ContentBox>
                <ContentBox
                    title={'SSH Keys'}
                    css={tw`flex-1 overflow-hidden mt-8 md:mt-0 md:ml-0 [&>h2]:text-black [&>h2]:text-center [&>h2]:mb-3 [&>h2]:px-0 [&>h2]:text-[1.65rem] [&>div]:bg-white [&>div]:rounded-none [&>div]:shadow-none [&>div]:border-2 [&>div]:border-black`}
                >
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
                                    tw`flex items-center border border-black bg-white px-4 py-3`,
                                    index > 0 && tw`mt-3`,
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
                </ContentBox>
            </div>
        </PageContentBlock>
    );
};
