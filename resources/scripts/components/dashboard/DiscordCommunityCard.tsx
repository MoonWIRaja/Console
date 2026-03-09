import React, { useEffect, useMemo, useRef, useState } from 'react';
import useFlash, { useFlashKey } from '@/plugins/useFlash';
import { joinDiscordCommunity, useDiscordCommunityStatus } from '@/api/account/discordCommunity';
import { useOAuthAccounts } from '@/api/account/oauth';

const cardClass =
    'h-full min-h-[96px] w-full rounded-xl border border-[color:var(--border)] bg-[color:var(--card)] p-4 shadow-[0_0_0_1px_rgba(var(--primary-rgb), 0.05)]';

const discordLogoPath =
    'M20.317 4.3698a19.7913 19.7913 0 00-4.8851-1.5152.0741.0741 0 00-.0785.0371c-.211.3753-.4447.8648-.6083 1.2495-1.8447-.2762-3.68-.2762-5.4868 0-.1636-.3933-.4058-.8742-.6177-1.2495a.077.077 0 00-.0785-.037 19.7363 19.7363 0 00-4.8852 1.515.0699.0699 0 00-.0321.0277C.5334 9.0458-.319 13.5799.0992 18.0578a.0824.0824 0 00.0312.0561c2.0528 1.5076 4.0413 2.4228 5.9929 3.0294a.0777.0777 0 00.0842-.0276c.4616-.6304.8731-1.2952 1.226-1.9942a.076.076 0 00-.0416-.1057c-.6528-.2476-1.2743-.5495-1.8722-.8923a.077.077 0 01-.0076-.1277c.1258-.0943.2517-.1923.3718-.2914a.0743.0743 0 01.0776-.0105c3.9278 1.7933 8.18 1.7933 12.0614 0a.0739.0739 0 01.0785.0095c.1202.099.246.1981.3728.2924a.077.077 0 01-.0066.1276 12.2986 12.2986 0 01-1.873.8914.0766.0766 0 00-.0407.1067c.3604.698.7719 1.3628 1.225 1.9932a.076.076 0 00.0842.0286c1.961-.6067 3.9495-1.5219 6.0023-3.0294a.077.077 0 00.0313-.0552c.5004-5.177-.8382-9.6739-3.5485-13.6604a.061.061 0 00-.0312-.0286zM8.02 15.3312c-1.1825 0-2.1569-1.0857-2.1569-2.419 0-1.3332.9555-2.4189 2.157-2.4189 1.2108 0 2.1757 1.0952 2.1568 2.419 0 1.3332-.9555 2.4189-2.1569 2.4189zm7.9748 0c-1.1825 0-2.1569-1.0857-2.1569-2.419 0-1.3332.9554-2.4189 2.1569-2.4189 1.2108 0 2.1757 1.0952 2.1568 2.419 0 1.3332-.946 2.4189-2.1568 2.4189Z';

export default () => {
    const { addFlash } = useFlash();
    const { addError, clearAndAddHttpError } = useFlashKey('account');
    const [busy, setBusy] = useState(false);
    const isMounted = useRef(true);

    useEffect(() => {
        return () => {
            isMounted.current = false;
        };
    }, []);

    const { data: community, error: communityError, mutate } = useDiscordCommunityStatus();
    const { data: providers, error: providersError } = useOAuthAccounts();

    useEffect(() => {
        clearAndAddHttpError(communityError || providersError);
    }, [communityError, providersError]);

    const discordProvider = useMemo(
        () => (providers || []).find((provider) => provider.provider === 'discord') || null,
        [providers]
    );

    const title = useMemo(() => {
        if (community?.roleAssigned) {
            return 'Discord Connected';
        }

        if (community?.member) {
            return 'Finish Discord Role';
        }

        return 'Join Our Discord';
    }, [community]);

    const description = useMemo(() => {
        if (!community) {
            return 'Checking Discord community status...';
        }

        if (!community.enabled) {
            return 'Discord community access is currently disabled by the panel administrator.';
        }

        if (!community.configured || !community.oauthReady) {
            return 'Discord community setup is not fully configured yet.';
        }

        if (!discordProvider?.linked) {
            return 'Link your Discord account first, then join the community and receive the configured role automatically.';
        }

        if (community.requiresRelink) {
            return 'Reconnect your Discord account once so the panel can complete server join and role assignment.';
        }

        if (community.roleAssigned) {
            return 'Your Discord account is already linked to the community and the configured role has been applied.';
        }

        if (community.member) {
            return 'Your Discord account is already in the server. Click below to ensure the configured role is applied.';
        }

        return 'Join the Discord community from here and the panel will add your linked Discord account to the server with the configured role.';
    }, [community, discordProvider]);

    const buttonLabel = useMemo(() => {
        if (busy) {
            return 'Working...';
        }

        if (!community?.enabled || !community?.configured || !community?.oauthReady) {
            return 'Unavailable';
        }

        if (!discordProvider?.linked) {
            return 'Link Discord';
        }

        if (community.requiresRelink) {
            return 'Reconnect Discord';
        }

        if (community.roleAssigned) {
            return 'Open Discord';
        }

        if (community.member) {
            return 'Apply Role';
        }

        return 'Join Discord';
    }, [busy, community, discordProvider]);

    const buttonDisabled = !community || !community.enabled || !community.configured || !community.oauthReady || busy;

    const onClick = async () => {
        if (!community) {
            return;
        }

        if (!community.enabled || !community.configured || !community.oauthReady) {
            return;
        }

        if (!discordProvider?.linked || community.requiresRelink) {
            if (discordProvider?.linkUrl) {
                window.location.assign(discordProvider.linkUrl);
            }

            return;
        }

        if (community.roleAssigned) {
            if (community.inviteUrl) {
                window.location.assign(community.inviteUrl);
            }

            return;
        }

        setBusy(true);
        clearAndAddHttpError();

        try {
            const response = await joinDiscordCommunity();
            if (!isMounted.current) {
                return;
            }

            if (!response.success) {
                addError(response.error || 'Unable to join the Discord community right now.');
                return;
            }

            await mutate();
            if (!isMounted.current) {
                return;
            }

            addFlash({
                key: 'account',
                type: 'success',
                title: 'Discord Ready',
                message: 'Your Discord role was applied successfully. Use Open Discord when you want to open the invite.',
            });
        } catch (error) {
            if (!isMounted.current) {
                return;
            }

            clearAndAddHttpError(error as Error);
        } finally {
            if (isMounted.current) {
                setBusy(false);
            }
        }
    };

    return (
        <section className={cardClass}>
            <div className={'flex h-full flex-col justify-between gap-4 md:flex-row md:items-center'}>
                <div className={'flex min-w-0 items-center gap-4'}>
                    <div className={'flex h-16 w-16 shrink-0 items-center justify-center rounded-lg border border-[#5865F2]/50 bg-[#5865F2]/10 text-[#5865F2]'}>
                        <svg
                            viewBox={'0 0 24 24'}
                            aria-hidden={'true'}
                            className={'h-8 w-8 fill-current'}
                        >
                            <path d={discordLogoPath} />
                        </svg>
                    </div>
                    <div className={'min-w-0'}>
                        <div className={'flex flex-wrap items-center gap-2'}>
                            <h2 className={'truncate text-2xl font-black tracking-tight text-[#f8f6ef]'}>{title}</h2>
                            {community?.roleAssigned ? (
                                <span className={'rounded-lg border border-[color:var(--primary)] bg-[color:var(--primary)]/10 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-[color:var(--primary)]'}>
                                    Joined
                                </span>
                            ) : community?.member ? (
                                <span className={'rounded-lg border border-[#5865F2]/60 bg-[#5865F2]/10 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-[#95a5ff]'}>
                                    In Server
                                </span>
                            ) : null}
                        </div>
                        <p className={'mt-1 text-xs text-gray-400'}>{description}</p>
                    </div>
                </div>

                <button
                    type={'button'}
                    onClick={() => void onClick()}
                    disabled={buttonDisabled}
                    className={'inline-flex h-11 min-w-[11rem] shrink-0 items-center justify-center rounded-full border border-[#5865F2]/50 bg-[#5865F2]/12 px-5 text-[11px] font-semibold uppercase tracking-wide text-[#f8f6ef] transition-all duration-300 hover:border-[#5865F2] hover:bg-[#5865F2]/18 md:ml-auto disabled:cursor-not-allowed disabled:opacity-50'}
                >
                    {buttonLabel}
                </button>
            </div>
        </section>
    );
};
