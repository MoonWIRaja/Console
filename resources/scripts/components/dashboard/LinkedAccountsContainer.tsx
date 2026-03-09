import React, { useEffect, useMemo, useState } from 'react';
import Spinner from '@/components/elements/Spinner';
import useFlash, { useFlashKey } from '@/plugins/useFlash';
import { OAuthProviderStatus, unlinkOAuthAccount, useOAuthAccounts } from '@/api/account/oauth';

const providerIcons: Record<'google' | 'discord', string> = {
    google: 'fab fa-google',
    discord: 'fab fa-discord',
};

const providerAccent: Record<'google' | 'discord', string> = {
    google: 'text-[#f8f6ef]',
    discord: 'text-[#5865F2]',
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
        <section className={'mt-6 rounded-xl border border-[color:var(--border)] bg-[color:var(--card)] p-6 shadow-[0_0_0_1px_rgba(var(--primary-rgb),0.05)]'}>
            <div className={'mb-5'}>
                <h2 className={'text-lg font-bold tracking-tight text-[#f8f6ef]'}>Linked Accounts</h2>
                <p className={'mt-2 text-xs text-gray-400'}>
                    Link Google or Discord here. OAuth login only works after the provider has been linked to this panel account.
                </p>
            </div>

            {!data && isValidating ? (
                <Spinner centered />
            ) : (
                <div className={'grid grid-cols-1 gap-4 md:grid-cols-2'}>
                    {providers.map((provider) => (
                        <section
                            key={provider.provider}
                            className={'rounded-xl border border-[color:var(--border)] bg-[color:var(--card)] p-5 shadow-[0_0_0_1px_rgba(var(--primary-rgb),0.03)]'}
                        >
                            <div className={'flex items-start justify-between gap-4'}>
                                <div className={'flex min-w-0 items-center gap-3'}>
                                    {provider.account?.avatar ? (
                                        <img
                                            src={provider.account.avatar}
                                            alt={`${provider.label} avatar`}
                                            className={'h-11 w-11 rounded-full border border-[color:var(--border)] object-cover'}
                                        />
                                    ) : (
                                        <div className={'flex h-11 w-11 items-center justify-center rounded-full border border-[color:var(--border)] bg-[color:var(--accent)]'}>
                                            <i
                                                className={`${providerIcons[provider.provider]} text-base ${providerAccent[provider.provider]}`}
                                            />
                                        </div>
                                    )}
                                    <div className={'min-w-0'}>
                                        <h3 className={'text-base font-bold text-[#f8f6ef]'}>{provider.label}</h3>
                                        <p className={'mt-1 text-xs text-gray-400'}>{describeProvider(provider)}</p>
                                    </div>
                                </div>
                                <span
                                    className={`shrink-0 rounded-full border px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider ${
                                        provider.linked
                                            ? 'border-[color:var(--primary)] bg-[color:var(--primary)]/10 text-[color:var(--primary)]'
                                            : provider.available
                                              ? 'border-slate-500/60 text-slate-300'
                                              : 'border-red-500/40 text-red-300'
                                    }`}
                                >
                                    {provider.linked ? 'Linked' : provider.available ? 'Ready' : 'Unavailable'}
                                </span>
                            </div>

                            {provider.account?.email && (
                                <p className={'mt-4 text-xs text-slate-300'}>
                                    {provider.account.email}
                                </p>
                            )}

                            <div className={'mt-5 flex flex-wrap gap-3'}>
                                <button
                                    type={'button'}
                                    onClick={() => onLink(provider)}
                                    disabled={!provider.available || busyProvider === provider.provider}
                                    className={'group relative inline-flex h-10 min-w-[9rem] items-center justify-center overflow-hidden rounded-full border border-[color:var(--border)] bg-[color:var(--card)] px-5 text-[11px] font-semibold uppercase tracking-wide text-[#f8f6ef] transition-all duration-300 hover:border-[color:var(--primary)] focus:outline-none disabled:cursor-not-allowed disabled:opacity-50'}
                                >
                                    <span className={'relative z-20'}>
                                        {busyProvider === provider.provider ? 'Opening...' : provider.linked ? 'Re-link' : 'Link Account'}
                                    </span>
                                    <span className={'pointer-events-none absolute left-3 top-1/2 z-10 h-1.5 w-1.5 -translate-y-1/2 rounded-full bg-[color:var(--primary)] opacity-95 transition-all duration-300 group-hover:left-0 group-hover:top-0 group-hover:h-full group-hover:w-full group-hover:translate-y-0 group-hover:rounded-none group-hover:opacity-100'} />
                                </button>

                                {provider.linked && (
                                    <button
                                        type={'button'}
                                        onClick={() => void onUnlink(provider)}
                                        disabled={busyProvider === provider.provider}
                                        className={'inline-flex h-10 min-w-[8rem] items-center justify-center rounded-full border border-red-500/35 px-5 text-[11px] font-semibold uppercase tracking-wide text-red-300 transition-colors hover:border-red-500 hover:bg-red-500/10 disabled:cursor-not-allowed disabled:opacity-50'}
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
