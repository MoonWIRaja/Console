import React, { useEffect, useMemo, useState } from 'react';
import Spinner from '@/components/elements/Spinner';
import useFlash, { useFlashKey } from '@/plugins/useFlash';
import { OAuthProviderStatus, unlinkOAuthAccount, useOAuthAccounts } from '@/api/account/oauth';

const providerIcons: Record<'google' | 'discord', string> = {
    google: 'fab fa-google',
    discord: 'fab fa-discord',
};

const providerAccent: Record<'google' | 'discord', string> = {
    google: 'text-[#742220]',
    discord: 'text-[#2D4A3E]',
};

export default () => {
    const { addFlash } = useFlash();
    const { clearAndAddHttpError } = useFlashKey('account');
    const [busyProvider, setBusyProvider] = useState<string | null>(null);

    const { data, error, isValidating, mutate } = useOAuthAccounts();

    useEffect(() => {
        clearAndAddHttpError(error);
    }, [error]);

    const providers = useMemo(() => data || [], [data]);

    const onLink = (provider: OAuthProviderStatus) => {
        setBusyProvider(provider.provider);
        window.location.assign(provider.linkUrl);
    };

    const onUnlink = async (provider: OAuthProviderStatus) => {
        setBusyProvider(provider.provider);
        clearAndAddHttpError();

        try {
            await unlinkOAuthAccount(provider.provider);
            await mutate();

            addFlash({
                key: 'account',
                type: 'success',
                title: 'Account Unlinked',
                message: `${provider.label} sign-in has been removed from this account.`,
            });
        } catch (err) {
            clearAndAddHttpError(err as Error);
        } finally {
            setBusyProvider(null);
        }
    };

    const describeProvider = (provider: OAuthProviderStatus): string => {
        if (provider.linked) {
            return provider.account?.displayName || provider.account?.email || `${provider.label} account linked`;
        }

        if (!provider.enabled) {
            return `${provider.label} sign-in is disabled by the panel administrator.`;
        }

        if (!provider.configured) {
            return `${provider.label} credentials are not configured yet in admin settings.`;
        }

        return `Link your ${provider.label} account here before using ${provider.label} login on the sign-in page.`;
    };

    return (
        <section
            className={
                'sc-card mt-6 min-w-0 overflow-hidden p-6'
            }
        >
            <div className={'mb-5'}>
                <h2 className={'text-lg font-bold tracking-tight text-[#742220]'}>Linked Accounts</h2>
                <p className={'mt-2 text-xs text-[rgba(116,34,32,0.58)]'}>
                    Link or re-link Google and Discord here for an existing panel account. New signups can also link
                    these providers during signup before the first login.
                </p>
            </div>

            {!data && isValidating ? (
                <Spinner centered />
            ) : (
                <div className={'grid min-w-0 grid-cols-1 gap-4 2xl:grid-cols-2'}>
                    {providers.map((provider) => (
                        <section
                            key={provider.provider}
                            className={
                                'sc-card-inner min-w-0 p-5'
                            }
                        >
                            <div className={'flex items-start justify-between gap-4'}>
                                <div className={'flex min-w-0 items-center gap-3'}>
                                    {provider.account?.avatar ? (
                                        <img
                                            src={provider.account.avatar}
                                            alt={`${provider.label} avatar`}
                                            className={
                                                'h-11 w-11 rounded-full border border-[#2D4A3E] object-cover'
                                            }
                                        />
                                    ) : (
                                        <div
                                            className={
                                                'flex h-11 w-11 items-center justify-center rounded-full border border-[#2D4A3E] bg-[#EDE6D0]'
                                            }
                                        >
                                            <i
                                                className={`${providerIcons[provider.provider]} text-base ${
                                                    providerAccent[provider.provider]
                                                }`}
                                            />
                                        </div>
                                    )}
                                    <div className={'min-w-0'}>
                                        <h3 className={'text-base font-bold text-[#742220]'}>{provider.label}</h3>
                                        <p className={'mt-1 break-words text-xs text-[rgba(116,34,32,0.58)]'}>
                                            {describeProvider(provider)}
                                        </p>
                                    </div>
                                </div>
                                <span
                                    className={`shrink-0 rounded-full border px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider ${
                                        provider.linked
                                            ? 'border-[color:var(--primary)] bg-[color:var(--primary)]/10 text-[color:var(--primary)]'
                                            : provider.available
                                            ? 'border-[#2D4A3E]/60 text-[#2D4A3E]'
                                            : 'border-red-500/40 text-red-700'
                                    }`}
                                >
                                    {provider.linked ? 'Linked' : provider.available ? 'Ready' : 'Unavailable'}
                                </span>
                            </div>

                            {provider.account?.email && (
                                <p className={'mt-4 break-all text-xs text-[rgba(116,34,32,0.72)]'}>{provider.account.email}</p>
                            )}

                            <div className={'mt-5 flex min-w-0 flex-wrap gap-3'}>
                                <button
                                    type={'button'}
                                    onClick={() => onLink(provider)}
                                    disabled={!provider.available || busyProvider === provider.provider}
                                    className={
                                        'group relative inline-flex h-10 w-full min-w-0 items-center justify-center overflow-hidden rounded-full border border-[#2D4A3E] bg-[#FEF9E1] px-5 text-[11px] font-semibold uppercase tracking-wide text-[#742220] transition-all duration-300 hover:border-[#2D4A3E] focus:outline-none sm:w-auto sm:min-w-[9rem] disabled:cursor-not-allowed disabled:opacity-50'
                                    }
                                >
                                    <span className={'relative z-20'}>
                                        {busyProvider === provider.provider
                                            ? 'Opening...'
                                            : provider.linked
                                            ? 'Re-link'
                                            : 'Link Account'}
                                    </span>
                                    <span
                                        className={
                                            'pointer-events-none absolute left-3 top-1/2 z-10 h-1.5 w-1.5 -translate-y-1/2 rounded-full bg-[color:var(--primary)] opacity-95 transition-all duration-300 group-hover:left-0 group-hover:top-0 group-hover:h-full group-hover:w-full group-hover:translate-y-0 group-hover:rounded-none group-hover:opacity-100'
                                        }
                                    />
                                </button>

                                {provider.linked && (
                                    <button
                                        type={'button'}
                                        onClick={() => void onUnlink(provider)}
                                        disabled={busyProvider === provider.provider}
                                        className={
                                            'inline-flex h-10 w-full min-w-0 items-center justify-center rounded-full border border-red-600/40 px-5 text-[11px] font-semibold uppercase tracking-wide text-red-700 transition-colors hover:border-red-600 hover:bg-red-500/10 sm:w-auto sm:min-w-[8rem] disabled:cursor-not-allowed disabled:opacity-50'
                                        }
                                    >
                                        {busyProvider === provider.provider ? 'Working...' : 'Unlink'}
                                    </button>
                                )}
                            </div>
                        </section>
                    ))}
                </div>
            )}
        </section>
    );
};
