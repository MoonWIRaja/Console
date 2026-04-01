import React, { useEffect, useMemo, useState } from 'react';
import { Server } from '@/api/server/getServer';
import getServers from '@/api/getServers';
import ServerRow from '@/components/dashboard/ServerRow';
import useFlash from '@/plugins/useFlash';
import { joinDiscordCommunity, useDiscordCommunityStatus } from '@/api/account/discordCommunity';
import { useOAuthAccounts } from '@/api/account/oauth';
import { useStoreState } from 'easy-peasy';
import { usePersistedState } from '@/plugins/usePersistedState';
import useSWR from 'swr';
import { PaginatedResult } from '@/api/http';
import Pagination from '@/components/elements/Pagination';
import { useLocation } from 'react-router-dom';
import FlashMessageRender from '@/components/FlashMessageRender';
import ToggleSwitch from '@/components/ui/toggle-switch';
import PageLoadingSkeleton from '@/components/elements/PageLoadingSkeleton';

interface DashboardContainerProps {
    searchQuery?: string;
}

export default ({ searchQuery = '' }: DashboardContainerProps) => {
    const { search } = useLocation();
    const defaultPage = Number(new URLSearchParams(search).get('page') || '1');

    const [page, setPage] = useState(!isNaN(defaultPage) && defaultPage > 0 ? defaultPage : 1);
    const [discordBusy, setDiscordBusy] = useState(false);
    const { addFlash, clearFlashes, clearAndAddHttpError } = useFlash();
    const uuid = useStoreState((state) => state.user.data!.uuid);
    const username = useStoreState((state) => state.user.data!.username);
    const rootAdmin = useStoreState((state) => state.user.data!.rootAdmin);
    const dashboardWebsite = useStoreState((state) => state.settings.data?.website);
    const [showOnlyAdmin, setShowOnlyAdmin] = usePersistedState(`${uuid}:show_all_servers`, false);
    const websiteHref = dashboardWebsite || (typeof window !== 'undefined' ? window.location.origin : '/');
    const { data: servers, error } = useSWR<PaginatedResult<Server>>(
        ['/api/client/servers', showOnlyAdmin && rootAdmin, page],
        () => getServers({ page, type: showOnlyAdmin && rootAdmin ? 'admin' : undefined })
    );
    const { data: community, error: communityError, mutate: mutateCommunity } = useDiscordCommunityStatus();
    const { data: providers, error: providersError } = useOAuthAccounts();
    const discordProvider = useMemo(
        () => (providers || []).find((provider) => provider.provider === 'discord') || null,
        [providers]
    );
    const discordShortcutLabel = useMemo(() => {
        if (discordBusy) {
            return 'Working...';
        }

        if (!community?.enabled || !community?.configured || !community?.oauthReady) {
            return 'Discord Unavailable';
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
            return 'Apply Discord Role';
        }

        return 'Join Our Discord';
    }, [community, discordBusy, discordProvider]);
    const discordShortcutDisabled = discordBusy || !community;

    useEffect(() => {
        setPage(1);
    }, [showOnlyAdmin]);

    useEffect(() => {
        if (!servers) return;
        if (servers.pagination.currentPage > 1 && !servers.items.length) {
            setPage(1);
        }
    }, [servers?.pagination.currentPage]);

    useEffect(() => {
        window.history.replaceState(null, document.title, `/${page <= 1 ? '' : `?page=${page}`}`);
    }, [page]);

    useEffect(() => {
        if (error) clearAndAddHttpError({ key: 'dashboard', error });
        if (!error) clearFlashes('dashboard');
    }, [error]);

    useEffect(() => {
        if (communityError || providersError) {
            clearAndAddHttpError({ key: 'dashboard', error: communityError || providersError });
        }
    }, [communityError, providersError]);

    const onDiscordShortcutClick = async () => {
        if (discordBusy || !community) {
            return;
        }

        clearFlashes('dashboard');

        if (!community.enabled || !community.configured || !community.oauthReady) {
            addFlash({
                key: 'dashboard',
                type: 'error',
                title: 'Discord Unavailable',
                message: 'Discord community access is not configured right now.',
            });

            return;
        }

        if (!discordProvider?.linked || community.requiresRelink) {
            if (discordProvider?.linkUrl) {
                window.location.assign(discordProvider.linkUrl);
                return;
            }

            addFlash({
                key: 'dashboard',
                type: 'error',
                title: 'Discord Unavailable',
                message: 'Discord linking is not available right now.',
            });

            return;
        }

        if (community.roleAssigned) {
            if (community.inviteUrl) {
                window.location.assign(community.inviteUrl);
                return;
            }

            addFlash({
                key: 'dashboard',
                type: 'error',
                title: 'Discord Unavailable',
                message: 'No Discord invite URL is configured yet.',
            });

            return;
        }

        setDiscordBusy(true);

        try {
            const response = await joinDiscordCommunity();

            if (!response.success) {
                addFlash({
                    key: 'dashboard',
                    type: 'error',
                    title: 'Discord Error',
                    message: response.error || 'Unable to join the Discord community right now.',
                });

                return;
            }

            await mutateCommunity();

            const redirectUrl = response.redirectUrl || community.inviteUrl;
            if (redirectUrl) {
                window.location.assign(redirectUrl);
                return;
            }

            addFlash({
                key: 'dashboard',
                type: 'success',
                title: 'Discord Ready',
                message: response.roleAssigned
                    ? 'Your Discord role was applied successfully.'
                    : 'Your Discord account is now connected to the community.',
            });
        } catch (error) {
            clearAndAddHttpError({ key: 'dashboard', error });
        } finally {
            setDiscordBusy(false);
        }
    };

    return (
        <div className='dashboard-auth-shell flex-1 h-full min-h-0 px-4 pb-8 pt-6 text-white md:px-8 md:pt-8'>
            <style>{`
                .dashboard-theme {
                    --dashboard-card-bg: #2D2D2D;
                    --dashboard-card-surface: rgba(255, 255, 255, 0.02);
                    --dashboard-card-border: rgba(245, 231, 198, 0.12);
                    --dashboard-card-border-soft: rgba(245, 231, 198, 0.08);
                    --dashboard-card-text: #F5E7C6;
                    --dashboard-card-muted: #A0A0A0;
                    position: relative;
                    z-index: 2;
                    display: flex;
                    flex: 1;
                    flex-direction: column;
                    height: 100%;
                    min-height: 0;
                }

                .dashboard-auth-shell {
                    position: relative;
                    display: flex;
                    flex: 1;
                    flex-direction: column;
                    overflow: hidden;
                    height: 100%;
                    min-height: 0;
                    min-width: 0;
                    background:
                        radial-gradient(circle at 11% 0%, rgba(var(--primary-rgb), 0.08), transparent 34%),
                        linear-gradient(180deg, rgba(var(--background-rgb), 1), rgba(var(--background-rgb), 0.985));
                    font-family: var(--font-sans, 'Inter', sans-serif);
                }

                .dashboard-auth-shell::before {
                    content: '';
                    position: absolute;
                    inset: 0;
                    pointer-events: none;
                    background:
                        repeating-linear-gradient(
                            90deg,
                            rgba(var(--primary-rgb), 0.018) 0,
                            rgba(var(--primary-rgb), 0.018) 1px,
                            transparent 1px,
                            transparent 56px
                        );
                    opacity: 0.08;
                }

                .dashboard-auth-shell::after {
                    content: '';
                    position: absolute;
                    left: 50%;
                    top: -22%;
                    width: min(980px, 92vw);
                    height: 100%;
                    transform: translateX(-50%);
                    pointer-events: none;
                    border-radius: 999px;
                    background: radial-gradient(
                        ellipse at center,
                        rgba(var(--primary-rgb), 0.05) 0%,
                        rgba(var(--primary-rgb), 0.015) 42%,
                        transparent 72%
                    );
                }

                .dashboard-auth-wrap {
                    margin: 0 auto;
                    display: flex;
                    flex: 1;
                    flex-direction: column;
                    overflow: hidden;
                    width: 100%;
                    max-width: 100%;
                    height: 100%;
                    min-height: 0;
                    min-width: 0;
                }

                .dashboard-auth-topbar {
                    margin-bottom: 18px;
                    display: grid;
                    gap: 12px;
                    grid-template-columns: minmax(0, 1fr);
                }

                .dashboard-auth-hero-card,
                .dashboard-auth-shortcut {
                    border-radius: 24px;
                    border: 1px solid var(--dashboard-card-border);
                    background:
                        linear-gradient(160deg, rgba(245, 231, 198, 0.045), rgba(255, 255, 255, 0.014) 46%),
                        var(--dashboard-card-bg);
                    box-shadow:
                        inset 0 1px 0 rgba(255, 255, 255, 0.04),
                        inset 0 -18px 28px rgba(0, 0, 0, 0.1),
                        0 24px 44px -28px rgba(0, 0, 0, 0.42);
                    backdrop-filter: blur(10px);
                }

                .dashboard-auth-hero-card {
                    padding: 20px 22px;
                    min-width: 0;
                }

                .dashboard-auth-topbar-main {
                    display: grid;
                    gap: 16px;
                    grid-template-columns: minmax(0, 1fr);
                    align-items: center;
                }

                .dashboard-auth-title {
                    margin: 0;
                    font-size: clamp(1.65rem, 3.2vw, 2.2rem);
                    line-height: 1.02;
                    letter-spacing: 0.02em;
                    font-weight: 900;
                    color: var(--dashboard-card-text);
                }

                .dashboard-auth-subtitle {
                    margin-top: 6px;
                    font-size: 0.85rem;
                    color: var(--dashboard-card-muted);
                    letter-spacing: 0.03em;
                    font-weight: 500;
                }

                .dashboard-auth-toggle-wrap {
                    align-self: start;
                    min-width: 238px;
                    border-radius: 16px;
                    border: 1px solid var(--dashboard-card-border-soft);
                    background:
                        linear-gradient(160deg, rgba(245, 231, 198, 0.04), rgba(255, 255, 255, 0.014)),
                        var(--dashboard-card-surface);
                    padding: 12px 14px;
                    box-shadow:
                        inset 0 1px 0 rgba(255, 255, 255, 0.04),
                        0 18px 30px -24px rgba(0, 0, 0, 0.32);
                }

                .dashboard-auth-shortcuts {
                    display: grid;
                    gap: 12px;
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                }

                .dashboard-auth-shortcut {
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    min-height: 92px;
                    min-width: 112px;
                    padding: 0 18px;
                    appearance: none;
                    cursor: pointer;
                    color: var(--dashboard-card-text);
                    text-decoration: none;
                    font: inherit;
                    transition: transform 0.22s ease, border-color 0.22s ease, box-shadow 0.22s ease;
                }

                .dashboard-auth-shortcut:hover {
                    transform: translateY(-1px);
                    border-color: rgba(245, 231, 198, 0.2);
                    box-shadow:
                        inset 0 1px 0 rgba(255, 255, 255, 0.08),
                        inset 0 -18px 28px rgba(0, 0, 0, 0.12),
                        0 20px 30px -22px rgba(0, 0, 0, 0.3);
                }

                .dashboard-auth-shortcut-icon {
                    font-size: 2rem;
                }

                .dashboard-auth-shortcut:disabled {
                    cursor: wait;
                    opacity: 0.7;
                }

                .dashboard-rows-wrap {
                    display: flex;
                    flex-direction: column;
                    gap: 12px;
                }

                .dashboard-scroll-region {
                    flex: 1 1 0%;
                    min-height: 0;
                    height: 0;
                    max-height: 100%;
                    overflow-y: auto;
                    overflow-x: hidden;
                    -webkit-overflow-scrolling: touch;
                    overscroll-behavior-y: contain;
                    touch-action: pan-y;
                    -ms-overflow-style: none;
                    scrollbar-width: none;
                    padding-bottom: 10px;
                }

                .dashboard-scroll-region::-webkit-scrollbar {
                    width: 0;
                    height: 0;
                    display: none;
                }

                .dashboard-empty-panel {
                    border-radius: 22px;
                    border: 1px solid var(--dashboard-card-border);
                    background:
                        radial-gradient(circle at 8% 0%, rgba(245, 231, 198, 0.08), transparent 24%),
                        linear-gradient(170deg, rgba(245, 231, 198, 0.04), rgba(255, 255, 255, 0.01) 50%),
                        var(--dashboard-card-bg);
                    padding: 2.8rem 1.2rem;
                    text-align: center;
                    box-shadow:
                        inset 0 1px 0 rgba(255, 255, 255, 0.06),
                        inset 0 -26px 32px rgba(0, 0, 0, 0.12),
                        0 28px 40px -34px rgba(0, 0, 0, 0.42);
                }

                .dashboard-server-row {
                    position: relative;
                    overflow: hidden;
                    border-radius: 22px;
                    border: 1px solid var(--dashboard-card-border);
                    background:
                        radial-gradient(circle at 8% 0%, rgba(245, 231, 198, 0.08), transparent 22%),
                        linear-gradient(160deg, rgba(245, 231, 198, 0.04), rgba(255, 255, 255, 0.012) 44%),
                        var(--dashboard-card-bg);
                    box-shadow:
                        inset 0 1px 0 rgba(255, 255, 255, 0.06),
                        inset 0 -26px 32px rgba(0, 0, 0, 0.12),
                        0 28px 40px -34px rgba(0, 0, 0, 0.42),
                        0 0 0 rgba(var(--primary-rgb), 0);
                    transition: transform 0.24s ease, border-color 0.24s ease, box-shadow 0.24s ease;
                    padding: 20px 22px;
                }

                .dashboard-server-row::after {
                    content: '';
                    position: absolute;
                    inset: 0;
                    pointer-events: none;
                    background:
                        radial-gradient(
                            460px 160px at 12% -10%,
                            rgba(245, 231, 198, 0.1),
                            transparent 68%
                        );
                    opacity: 0.42;
                }

                .dashboard-server-row:hover {
                    transform: translateY(-1px);
                    border-color: rgba(245, 231, 198, 0.2);
                    box-shadow:
                        inset 0 1px 0 rgba(255, 255, 255, 0.08),
                        inset 0 -26px 32px rgba(0, 0, 0, 0.14),
                        0 26px 38px -26px rgba(0, 0, 0, 0.52),
                        0 0 18px rgba(245, 231, 198, 0.08);
                }

                .dashboard-server-grid {
                    position: relative;
                    z-index: 1;
                    display: grid;
                    grid-template-columns: minmax(0, 248px) minmax(0, 1fr);
                    gap: 24px;
                    align-items: center;
                }

                .dashboard-server-summary {
                    display: flex;
                    align-items: center;
                    gap: 14px;
                    min-width: 0;
                }

                .dashboard-server-iconbox {
                    display: flex;
                    height: 3.35rem;
                    width: 3.35rem;
                    align-items: center;
                    justify-content: center;
                    border-radius: 16px;
                    border: 1px solid var(--dashboard-card-border-soft);
                    background:
                        linear-gradient(160deg, rgba(245, 231, 198, 0.05), rgba(255, 255, 255, 0.02)),
                        var(--dashboard-card-surface);
                    box-shadow:
                        inset 0 1px 0 rgba(255, 255, 255, 0.08),
                        inset 0 -10px 16px rgba(0, 0, 0, 0.12),
                        0 16px 24px -18px rgba(0, 0, 0, 0.24);
                    transition: border-color 0.25s ease, transform 0.25s ease, box-shadow 0.25s ease;
                }

                .dashboard-server-row:hover .dashboard-server-iconbox {
                    border-color: rgba(245, 231, 198, 0.22);
                    transform: translateY(-1px);
                    box-shadow:
                        inset 0 1px 0 rgba(255, 255, 255, 0.1),
                        inset 0 -10px 16px rgba(0, 0, 0, 0.14),
                        0 20px 28px -20px rgba(0, 0, 0, 0.32);
                }

                .dashboard-server-icon {
                    color: var(--dashboard-card-muted);
                    transition: color 0.25s ease;
                }

                .dashboard-server-row:hover .dashboard-server-icon {
                    color: var(--dashboard-card-text);
                }

                .dashboard-server-name {
                    font-size: 1.08rem;
                    font-weight: 800;
                    color: var(--dashboard-card-text);
                    letter-spacing: 0.02em;
                }

                .dashboard-server-meta-row {
                    margin-top: 0.35rem;
                    display: flex;
                    align-items: center;
                    flex-wrap: wrap;
                    gap: 0.5rem;
                    min-width: 0;
                }

                .dashboard-server-status {
                    font-size: 0.66rem;
                    font-weight: 800;
                    letter-spacing: 0.16em;
                    text-transform: uppercase;
                }

                .dashboard-server-separator {
                    color: var(--dashboard-card-muted);
                    font-size: 0.72rem;
                }

                .dashboard-server-address {
                    color: var(--dashboard-card-muted);
                    font-size: 0.68rem;
                }

                .dashboard-server-status-dot-wrap {
                    position: absolute;
                    right: -0.2rem;
                    top: -0.2rem;
                    display: flex;
                    height: 1rem;
                    width: 1rem;
                }

                .dashboard-server-status-dot-glow {
                    position: absolute;
                    display: inline-flex;
                    height: 100%;
                    width: 100%;
                    border-radius: 999px;
                    opacity: 0.38;
                }

                .dashboard-server-status-dot {
                    position: relative;
                    display: inline-flex;
                    height: 1rem;
                    width: 1rem;
                    border-radius: 999px;
                    border: 2px solid var(--dashboard-card-bg);
                }

                .dashboard-neon-glow-text {
                    text-shadow: 0 0 8px rgba(var(--primary-rgb), 0.5);
                }

                .dashboard-server-metrics {
                    display: grid;
                    grid-template-columns: repeat(3, minmax(0, 1fr));
                    gap: 20px;
                    min-width: 0;
                    font-family: var(--font-mono, 'IBM Plex Mono', monospace);
                }

                .dashboard-server-metric {
                    min-width: 0;
                }

                .dashboard-server-metric-label {
                    margin-bottom: 0.55rem;
                    color: var(--dashboard-card-muted);
                    font-size: 0.62rem;
                    font-weight: 700;
                    letter-spacing: 0.16em;
                    text-transform: uppercase;
                }

                .dashboard-server-metric-value-row {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 0.75rem;
                    margin-bottom: 0.5rem;
                    color: var(--dashboard-card-text);
                    font-size: 0.68rem;
                }

                .dashboard-progress-track {
                    height: 0.38rem;
                    width: 100%;
                    overflow: hidden;
                    border-radius: 999px;
                    background: rgba(245, 231, 198, 0.08);
                    box-shadow: inset 0 1px 1px rgba(255, 255, 255, 0.03);
                }

                .dashboard-progress-fill {
                    height: 100%;
                    border-radius: inherit;
                    transition: width 0.7s ease;
                }

                .dashboard-progress-cyan {
                    background: linear-gradient(90deg, rgba(var(--primary-rgb), 0.9), rgba(var(--primary-rgb), 0.58));
                    box-shadow: 0 0 10px rgba(var(--primary-rgb), 0.18);
                }

                .dashboard-progress-amber {
                    background: linear-gradient(90deg, rgba(var(--primary-rgb), 0.72), rgba(var(--primary-rgb), 0.42));
                    box-shadow: 0 0 10px rgba(var(--primary-rgb), 0.14);
                }

                .dashboard-progress-lime {
                    background: linear-gradient(90deg, rgba(var(--primary-rgb), 1), rgba(var(--primary-rgb), 0.72));
                    box-shadow: 0 0 10px rgba(var(--primary-rgb), 0.24);
                }

                .dashboard-progress-alert {
                    background: linear-gradient(90deg, rgba(var(--primary-rgb), 0.62), rgba(var(--primary-rgb), 0.3));
                    box-shadow: 0 0 10px rgba(var(--primary-rgb), 0.12);
                }

                .dashboard-server-metric-hint {
                    margin-top: 0.45rem;
                    color: var(--dashboard-card-muted);
                    font-size: 0.58rem;
                    letter-spacing: 0.08em;
                    text-transform: uppercase;
                }

                @media (max-width: 640px) {
                    .dashboard-auth-hero-card {
                        border-radius: 18px;
                        padding: 16px 16px 14px;
                    }

                    .dashboard-auth-shortcut {
                        min-height: 78px;
                        border-radius: 18px;
                    }

                    .dashboard-server-row {
                        padding: 16px;
                    }
                }

                @media (max-width: 1023px) {
                    .dashboard-scroll-region {
                        padding-right: 0;
                        padding-bottom: 18px;
                    }

                    .dashboard-auth-toggle-wrap {
                        width: 100%;
                        min-width: 0;
                    }

                    .dashboard-server-grid {
                        grid-template-columns: minmax(0, 1fr);
                        gap: 18px;
                    }
                }

                @media (min-width: 1024px) {
                    .dashboard-auth-topbar {
                        grid-template-columns: minmax(0, 1fr) auto;
                        align-items: stretch;
                    }

                    .dashboard-auth-topbar-main {
                        grid-template-columns: minmax(0, 1fr) auto;
                    }

                    .dashboard-auth-shortcuts {
                        grid-auto-flow: column;
                        grid-auto-columns: 118px;
                    }
                }

                @media (max-width: 767px) {
                    .dashboard-server-metrics {
                        grid-template-columns: minmax(0, 1fr);
                        gap: 16px;
                    }
                }
            `}</style>
            <div className='dashboard-theme dashboard-auth-wrap'>
                <FlashMessageRender byKey={'dashboard'} />

                <div className='dashboard-auth-topbar'>
                    <div className='dashboard-auth-hero-card'>
                        <div className='dashboard-auth-topbar-main'>
                            <div>
                                <h1 className='dashboard-auth-title'>Hi {username || 'there'}!</h1>
                                <p className='dashboard-auth-subtitle'>
                                    {showOnlyAdmin
                                        ? 'Your full server inventory is available below.'
                                        : 'Your servers are available below.'}
                                </p>
                            </div>
                            {rootAdmin && (
                                <div className='dashboard-auth-toggle-wrap'>
                                    <ToggleSwitch
                                        id='toggle-admin-servers'
                                        checked={!!showOnlyAdmin}
                                        onChange={(value) => setShowOnlyAdmin(value)}
                                        label={showOnlyAdmin ? 'Showing All Servers' : 'Showing My Servers'}
                                    />
                                </div>
                            )}
                        </div>
                    </div>

                    <div className='dashboard-auth-shortcuts'>
                        <button
                            type='button'
                            className='dashboard-auth-shortcut'
                            onClick={() => void onDiscordShortcutClick()}
                            disabled={discordShortcutDisabled}
                            title={discordShortcutLabel}
                        >
                            <span className='material-icons-round dashboard-auth-shortcut-icon'>sports_esports</span>
                            <span className='sr-only'>{discordShortcutLabel}</span>
                        </button>
                        <a
                            className='dashboard-auth-shortcut'
                            href={websiteHref}
                            target='_blank'
                            rel='noreferrer'
                            title='Website'
                        >
                            <span className='material-icons-round dashboard-auth-shortcut-icon'>language</span>
                            <span className='sr-only'>Website</span>
                        </a>
                    </div>
                </div>

                <div className='dashboard-scroll-region'>
                    {!servers ? (
                        <PageLoadingSkeleton showChrome={false} rows={8} className='min-h-[420px]' />
                    ) : (
                        <Pagination data={servers} onPageSelect={setPage}>
                            {({ items }) => {
                                const searchValue = searchQuery.trim().toLowerCase();
                                const filteredItems = searchValue
                                    ? items.filter((server) =>
                                          [
                                              server.name,
                                              String(server.id || ''),
                                              String(server.identifier || ''),
                                              String(server.internalId || ''),
                                              server.__deprecatedUuidShort || '',
                                              server.uuid,
                                              server.node,
                                              server.description || '',
                                              server.allocations
                                                  .map(
                                                      (allocation) =>
                                                          `${allocation.alias || allocation.ip}:${allocation.port}`
                                                  )
                                                  .join(' '),
                                          ]
                                              .join(' ')
                                              .toLowerCase()
                                              .includes(searchValue)
                                      )
                                    : items;

                                return filteredItems.length > 0 ? (
                                    <div className='dashboard-rows-wrap'>
                                        {filteredItems.map((server) => (
                                            <ServerRow key={server.uuid} server={server} />
                                        ))}
                                    </div>
                                ) : (
                                    <div className='dashboard-empty-panel'>
                                        <p className='text-xs text-[rgba(174,183,194,0.78)]'>
                                            {searchValue
                                                ? 'No servers on this page match your search.'
                                                : showOnlyAdmin
                                                ? 'There are no other servers to display.'
                                                : 'There are no servers associated with your account.'}
                                        </p>
                                    </div>
                                );
                            }}
                        </Pagination>
                    )}
                </div>
            </div>
        </div>
    );
};
