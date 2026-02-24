import React, { useEffect, useState } from 'react';
import CreateApiKeyForm from '@/components/dashboard/forms/CreateApiKeyForm';
import getApiKeys, { ApiKey } from '@/api/account/getApiKeys';
import SpinnerOverlay from '@/components/elements/SpinnerOverlay';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faKey, faTrashAlt } from '@fortawesome/free-solid-svg-icons';
import deleteApiKey from '@/api/account/deleteApiKey';
import FlashMessageRender from '@/components/FlashMessageRender';
import { format } from 'date-fns';
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
        <div css={tw`my-0 font-mono`}>
            <FlashMessageRender byKey={'account'} />
            <div css={tw`grid grid-cols-1 gap-6 md:grid-cols-2`}>
                <section
                    css={tw`rounded-xl border border-[#1f2a14] bg-[#000000] p-6 shadow-[0_0_0_1px_rgba(163,255,18,0.05)] flex flex-col`}
                >
                    <h2 css={tw`mb-4 text-center text-xl font-bold text-neutral-100`}>Create API Key</h2>
                    <div css={tw`mt-2`}>
                        <CreateApiKeyForm onKeyCreated={(key) => setKeys((s) => [...s!, key])} />
                    </div>
                </section>

                <section
                    css={tw`rounded-xl border border-[#1f2a14] bg-[#000000] p-6 shadow-[0_0_0_1px_rgba(163,255,18,0.05)] flex flex-col`}
                >
                    <h2 css={tw`mb-4 text-center text-xl font-bold text-neutral-100`}>API Keys</h2>
                    <div css={tw`relative mt-2`}>
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
                                        tw`flex items-center gap-3 border-b border-[#1f2a14] px-1 py-3`,
                                        index === keys.length - 1 && tw`border-b-0`,
                                    ]}
                                >
                                    <FontAwesomeIcon icon={faKey} css={tw`text-[#a3ff12]`} />
                                    <div css={tw`ml-1 flex-1 overflow-hidden`}>
                                        <p css={tw`break-words text-sm font-bold text-neutral-100`}>
                                            {key.description}
                                        </p>
                                        <p css={tw`text-2xs uppercase tracking-wide text-neutral-500`}>
                                            Last used:&nbsp;
                                            {key.lastUsedAt ? format(key.lastUsedAt, 'MMM do, yyyy HH:mm') : 'Never'}
                                        </p>
                                    </div>
                                    <p css={tw`ml-2 hidden text-sm md:block`}>
                                        <code
                                            css={tw`rounded-md border border-[#2f3f17] bg-[#0a1104] px-2 py-1 font-mono text-[#d9ff93]`}
                                        >
                                            {key.identifier}
                                        </code>
                                    </p>
                                    <button
                                        css={tw`ml-2 rounded-md border border-transparent p-2 text-sm transition-colors duration-150 hover:border-red-700/70`}
                                        onClick={() => setDeleteIdentifier(key.identifier)}
                                        type={'button'}
                                    >
                                        <FontAwesomeIcon
                                            icon={faTrashAlt}
                                            css={tw`text-neutral-500 transition-colors duration-150 hover:text-red-500`}
                                        />
                                    </button>
                                </div>
                            ))
                        )}
                    </div>
                </section>
            </div>
        </div>
    );
};
