import React, { useEffect, useState } from 'react';
import CreateApiKeyForm from '@/components/dashboard/forms/CreateApiKeyForm';
import getApiKeys, { ApiKey } from '@/api/account/getApiKeys';
import SpinnerOverlay from '@/components/elements/SpinnerOverlay';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faKey, faTrashAlt } from '@fortawesome/free-solid-svg-icons';
import deleteApiKey from '@/api/account/deleteApiKey';
import FlashMessageRender from '@/components/FlashMessageRender';
import { format } from 'date-fns';
import PageContentBlock from '@/components/elements/PageContentBlock';
import tw from 'twin.macro';
import { Dialog } from '@/components/elements/dialog';
import { useFlashKey } from '@/plugins/useFlash';
import Code from '@/components/elements/Code';

export default () => {
    const [deleteIdentifier, setDeleteIdentifier] = useState('');
    const [keys, setKeys] = useState<ApiKey[]>([]);
    const [loading, setLoading] = useState(true);
    const { clearAndAddHttpError } = useFlashKey('account');

    useEffect(() => {
        getApiKeys()
            .then((keys) => setKeys(keys))
            .then(() => setLoading(false))
            .catch((error) => clearAndAddHttpError(error));
    }, []);

    const doDeletion = (identifier: string) => {
        setLoading(true);

        clearAndAddHttpError();
        deleteApiKey(identifier)
            .then(() => setKeys((s) => [...(s || []).filter((key) => key.identifier !== identifier)]))
            .catch((error) => clearAndAddHttpError(error))
            .then(() => {
                setLoading(false);
                setDeleteIdentifier('');
            });
    };

    return (
        <PageContentBlock title={'Account API'}>
            <style>{`
                @import url('https://fonts.googleapis.com/css2?family=Space+Mono:ital,wght@0,400;0,700;1,400&display=swap');
            `}</style>
            <FlashMessageRender byKey={'account'} />
            <div css={tw`my-0 font-mono`}>
                <div css={tw`md:flex bg-white`}>
                    <section css={tw`w-full md:w-1/2 p-6 md:border-r-2 border-black flex flex-col`}>
                        <h2 css={tw`text-black text-center mb-4 text-[1.65rem]`}>Create API Key</h2>
                        <div css={tw`mt-2`}>
                            <CreateApiKeyForm onKeyCreated={(key) => setKeys((s) => [...s!, key])} />
                        </div>
                    </section>

                    <section css={tw`w-full md:w-1/2 p-6 flex flex-col`}>
                        <h2 css={tw`text-black text-center mb-4 text-[1.65rem]`}>API Keys</h2>
                        <div css={tw`mt-2 relative`}>
                            <SpinnerOverlay visible={loading} />
                            <Dialog.Confirm
                                title={'Delete API Key'}
                                confirm={'Delete Key'}
                                open={!!deleteIdentifier}
                                onClose={() => setDeleteIdentifier('')}
                                onConfirmed={() => doDeletion(deleteIdentifier)}
                            >
                                All requests using the <Code>{deleteIdentifier}</Code> key will be invalidated.
                            </Dialog.Confirm>
                            {keys.length === 0 ? (
                                <p css={tw`text-center text-sm text-neutral-500`}>
                                    {loading ? 'Loading...' : 'No API keys exist for this account.'}
                                </p>
                            ) : (
                                keys.map((key, index) => (
                                    <div
                                        key={key.identifier}
                                        css={[
                                            tw`flex items-center px-1 py-3 border-b border-black`,
                                            index === keys.length - 1 && tw`border-b-0`,
                                        ]}
                                    >
                                        <FontAwesomeIcon icon={faKey} css={tw`text-black`} />
                                        <div css={tw`ml-4 flex-1 overflow-hidden`}>
                                            <p css={tw`text-sm break-words text-black font-bold`}>{key.description}</p>
                                            <p css={tw`text-2xs text-neutral-500 uppercase tracking-wide`}>
                                                Last used:&nbsp;
                                                {key.lastUsedAt
                                                    ? format(key.lastUsedAt, 'MMM do, yyyy HH:mm')
                                                    : 'Never'}
                                            </p>
                                        </div>
                                        <p css={tw`text-sm ml-4 hidden md:block`}>
                                            <code css={tw`font-mono py-1 px-2 bg-black text-white`}>
                                                {key.identifier}
                                            </code>
                                        </p>
                                        <button
                                            css={tw`ml-4 p-2 text-sm border border-transparent hover:border-black transition-colors duration-150`}
                                            onClick={() => setDeleteIdentifier(key.identifier)}
                                        >
                                            <FontAwesomeIcon
                                                icon={faTrashAlt}
                                                css={tw`text-neutral-500 hover:text-red-500 transition-colors duration-150`}
                                            />
                                        </button>
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
