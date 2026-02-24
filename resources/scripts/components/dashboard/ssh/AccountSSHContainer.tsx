import React, { useEffect } from 'react';
import SpinnerOverlay from '@/components/elements/SpinnerOverlay';
import FlashMessageRender from '@/components/FlashMessageRender';
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
        <div css={tw`my-0 font-mono`}>
            <FlashMessageRender byKey={'account'} />
            <div css={tw`grid grid-cols-1 gap-6 md:grid-cols-2`}>
                <section
                    css={tw`rounded-xl border border-[#1f2a14] bg-[#000000] p-6 shadow-[0_0_0_1px_rgba(163,255,18,0.05)] flex flex-col`}
                >
                    <h2 css={tw`mb-4 text-center text-xl font-bold text-neutral-100`}>Add SSH Key</h2>
                    <div css={tw`mt-2`}>
                        <CreateSSHKeyForm />
                    </div>
                </section>

                <section
                    css={tw`rounded-xl border border-[#1f2a14] bg-[#000000] p-6 shadow-[0_0_0_1px_rgba(163,255,18,0.05)] flex flex-col`}
                >
                    <h2 css={tw`mb-4 text-center text-xl font-bold text-neutral-100`}>SSH Keys</h2>
                    <div css={tw`relative mt-2`}>
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
                                        tw`flex items-center gap-3 border-b border-[#1f2a14] px-1 py-3`,
                                        index === data.length - 1 && tw`border-b-0`,
                                    ]}
                                >
                                    <FontAwesomeIcon icon={faKey} css={tw`text-[#a3ff12]`} />
                                    <div css={tw`flex-1`}>
                                        <p css={tw`break-words text-sm font-bold text-neutral-100`}>{key.name}</p>
                                        <p css={tw`mt-1 truncate font-mono text-xs text-neutral-300`}>
                                            SHA256:{key.fingerprint}
                                        </p>
                                        <p css={tw`mt-1 text-xs uppercase tracking-wide text-neutral-500`}>
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
    );
};
