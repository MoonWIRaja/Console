import React, { useEffect, useState } from 'react';
import { Server } from '@/api/server/getServer';
import getServers from '@/api/getServers';
import ServerRow from '@/components/dashboard/ServerRow';
import useFlash from '@/plugins/useFlash';
import { useStoreState } from 'easy-peasy';
import { usePersistedState } from '@/plugins/usePersistedState';
import useSWR from 'swr';
import { PaginatedResult } from '@/api/http';
import Pagination from '@/components/elements/Pagination';
import { useLocation } from 'react-router-dom';
import FlashMessageRender from '@/components/FlashMessageRender';
import ToggleSwitch from '@/components/ui/toggle-switch';
import PageLoadingSkeleton from '@/components/elements/PageLoadingSkeleton';

export default () => {
    const { search } = useLocation();
    const defaultPage = Number(new URLSearchParams(search).get('page') || '1');

    const [page, setPage] = useState(!isNaN(defaultPage) && defaultPage > 0 ? defaultPage : 1);
    const { clearFlashes, clearAndAddHttpError } = useFlash();
    const uuid = useStoreState((state) => state.user.data!.uuid);
    const rootAdmin = useStoreState((state) => state.user.data!.rootAdmin);
    const [showOnlyAdmin, setShowOnlyAdmin] = usePersistedState(`${uuid}:show_all_servers`, false);

    const { data: servers, error } = useSWR<PaginatedResult<Server>>(
        ['/api/client/servers', showOnlyAdmin && rootAdmin, page],
        () => getServers({ page, type: showOnlyAdmin && rootAdmin ? 'admin' : undefined })
    );

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

    return (
        <div className='dashboard-auth-shell min-h-screen px-4 pb-8 pt-6 text-white md:px-8 md:pt-8'>
            <style>{`
                .dashboard-theme {
                    position: relative;
                    z-index: 2;
                    display: flex;
                    flex-direction: column;
                    height: 100%;
                    min-height: 0;
                }

                .dashboard-auth-shell {
                    position: relative;
                    overflow: hidden;
                    height: 100dvh;
                    min-height: 100dvh;
                    background:
                        radial-gradient(circle at 11% 0%, rgba(var(--primary-rgb), 0.18), transparent 38%),
                        radial-gradient(circle at 85% 100%, rgba(84, 140, 255, 0.2), transparent 42%),
                        linear-gradient(180deg, rgba(4, 7, 12, 0.98), rgba(1, 2, 5, 1));
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
                            rgba(255, 255, 255, 0.015) 0,
                            rgba(255, 255, 255, 0.015) 1px,
                            transparent 1px,
                            transparent 42px
                        );
                    opacity: 0.22;
                }

                .dashboard-auth-shell::after {
                    content: '';
                    position: absolute;
                    left: 50%;
                    top: -18%;
                    width: min(1100px, 96vw);
                    height: 110%;
                    transform: translateX(-50%);
                    pointer-events: none;
                    border-radius: 999px;
                    background: radial-gradient(
                        ellipse at center,
                        rgba(112, 168, 255, 0.08) 0%,
                        rgba(112, 168, 255, 0.03) 40%,
                        transparent 72%
                    );
                }

                .dashboard-auth-wrap {
                    margin: 0 auto;
                    width: 100%;
                    max-width: 100%;
                    height: 100%;
                    min-height: 0;
                }

                .dashboard-auth-topbar {
                    margin-bottom: 18px;
                    border-radius: 24px;
                    border: 1px solid rgba(255, 255, 255, 0.09);
                    background:
                        linear-gradient(160deg, rgba(255, 255, 255, 0.045), rgba(255, 255, 255, 0.01) 46%),
                        rgba(4, 8, 14, 0.76);
                    box-shadow:
                        inset 0 1px 0 rgba(255, 255, 255, 0.08),
                        0 30px 46px -32px rgba(0, 0, 0, 0.82),
                        0 0 56px rgba(var(--primary-rgb), 0.1);
                    padding: 18px 20px;
                    backdrop-filter: blur(8px);
                }

                .dashboard-auth-pill-row {
                    display: flex;
                    flex-wrap: wrap;
                    align-items: center;
                    justify-content: space-between;
                    gap: 10px;
                    margin-bottom: 14px;
                }

                .dashboard-auth-pill {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    min-height: 34px;
                    border-radius: 999px;
                    border: 1px solid rgba(var(--primary-rgb), 0.52);
                    background: linear-gradient(120deg, rgba(var(--primary-rgb), 0.36), rgba(var(--primary-rgb), 0.14));
                    color: rgba(230, 252, 180, 0.95);
                    font-size: 0.7rem;
                    font-weight: 800;
                    letter-spacing: 0.2em;
                    text-transform: uppercase;
                    padding: 0 14px;
                    text-shadow: 0 0 10px rgba(var(--primary-rgb), 0.58);
                }

                .dashboard-auth-route {
                    color: rgba(248, 246, 239, 0.56);
                    font-size: 0.7rem;
                    letter-spacing: 0.18em;
                    text-transform: uppercase;
                    font-weight: 700;
                }

                .dashboard-auth-title {
                    margin: 0;
                    font-size: clamp(1.4rem, 3.2vw, 2rem);
                    line-height: 1.02;
                    letter-spacing: 0.02em;
                    text-transform: uppercase;
                    font-weight: 900;
                    color: rgba(248, 246, 239, 0.97);
                    text-shadow: 0 0 18px rgba(248, 246, 239, 0.19);
                }

                .dashboard-auth-subtitle {
                    margin-top: 8px;
                    font-size: 0.82rem;
                    color: rgba(174, 183, 194, 0.82);
                    letter-spacing: 0.03em;
                    font-weight: 500;
                }

                .dashboard-auth-toggle-wrap {
                    margin-top: 14px;
                    border-radius: 14px;
                    border: 1px solid rgba(255, 255, 255, 0.08);
                    background: rgba(255, 255, 255, 0.02);
                    padding: 10px 12px;
                }

                .dashboard-rows-wrap {
                    display: flex;
                    flex-direction: column;
                    gap: 14px;
                }

                .dashboard-scroll-region {
                    flex: 1;
                    min-height: 0;
                    overflow-y: auto;
                    overflow-x: hidden;
                    padding-right: 4px;
                    padding-bottom: 10px;
                }

                .dashboard-empty-panel {
                    border-radius: 20px;
                    border: 1px solid rgba(255, 255, 255, 0.08);
                    background:
                        linear-gradient(170deg, rgba(255, 255, 255, 0.04), rgba(255, 255, 255, 0.01) 50%),
                        rgba(4, 8, 14, 0.78);
                    padding: 2.8rem 1.2rem;
                    text-align: center;
                    box-shadow:
                        inset 0 1px 0 rgba(255, 255, 255, 0.06),
                        0 18px 36px -30px rgba(0, 0, 0, 0.86);
                }

                .dashboard-server-row {
                    position: relative;
                    overflow: hidden;
                    border-radius: 22px;
                    border: 1px solid rgba(255, 255, 255, 0.085);
                    background:
                        radial-gradient(circle at 8% 0%, rgba(var(--primary-rgb), 0.12), transparent 24%),
                        linear-gradient(160deg, rgba(255, 255, 255, 0.04), rgba(255, 255, 255, 0.01) 44%),
                        rgba(4, 8, 14, 0.82);
                    box-shadow:
                        inset 0 1px 0 rgba(255, 255, 255, 0.09),
                        0 30px 40px -34px rgba(0, 0, 0, 0.82),
                        0 0 0 rgba(var(--primary-rgb), 0);
                    transition: transform 0.25s ease, border-color 0.25s ease, box-shadow 0.25s ease;
                }

                .dashboard-server-row::after {
                    content: '';
                    position: absolute;
                    inset: 0;
                    pointer-events: none;
                    background:
                        radial-gradient(
                            460px 160px at 12% -10%,
                            rgba(var(--primary-rgb), 0.16),
                            transparent 68%
                        );
                    opacity: 0.58;
                }

                .dashboard-server-row:hover {
                    transform: translateY(-2px);
                    border-color: rgba(var(--primary-rgb), 0.28);
                    box-shadow:
                        inset 0 1px 0 rgba(255, 255, 255, 0.12),
                        0 26px 38px -26px rgba(0, 0, 0, 0.9),
                        0 0 42px rgba(var(--primary-rgb), 0.16);
                }

                .dashboard-server-iconbox {
                    display: flex;
                    height: 3.25rem;
                    width: 3.25rem;
                    align-items: center;
                    justify-content: center;
                    border-radius: 14px;
                    border: 1px solid rgba(255, 255, 255, 0.1);
                    background: linear-gradient(160deg, rgba(255, 255, 255, 0.06), rgba(255, 255, 255, 0.02));
                    transition: border-color 0.25s ease, transform 0.25s ease;
                }

                .dashboard-server-row:hover .dashboard-server-iconbox {
                    border-color: rgba(var(--primary-rgb), 0.45);
                    transform: translateY(-1px);
                }

                .dashboard-server-icon {
                    color: rgba(182, 192, 205, 0.9);
                    transition: color 0.25s ease;
                }

                .dashboard-server-row:hover .dashboard-server-icon {
                    color: var(--primary);
                }

                .dashboard-server-name {
                    font-size: 1.08rem;
                    font-weight: 800;
                    color: rgba(248, 246, 239, 0.97);
                    letter-spacing: 0.02em;
                }

                .dashboard-neon-glow-text {
                    text-shadow: 0 0 8px rgba(var(--primary-rgb), 0.5);
                }

                .dashboard-progress-track {
                    height: 0.36rem;
                    width: 100%;
                    overflow: hidden;
                    border-radius: 999px;
                    background: rgba(255, 255, 255, 0.08);
                }

                .dashboard-progress-neon {
                    box-shadow: 0 0 10px rgba(var(--primary-rgb), 0.55);
                }

                @media (max-width: 640px) {
                    .dashboard-auth-topbar {
                        border-radius: 18px;
                        padding: 14px 14px 12px;
                    }

                    .dashboard-auth-pill-row {
                        margin-bottom: 10px;
                    }
                }

                @media (max-width: 1023px) {
                    .dashboard-auth-shell {
                        height: calc(100dvh - 48px);
                        min-height: calc(100dvh - 48px);
                    }
                }
            `}</style>
            <div className='dashboard-theme dashboard-auth-wrap'>
                <FlashMessageRender byKey={'dashboard'} />

                <div className='dashboard-auth-topbar'>
                    <div>
                        <div className='dashboard-auth-pill-row'>
                            <span className='dashboard-auth-pill'>Secure server grid</span>
                            <span className='dashboard-auth-route'>Route /dashboard</span>
                        </div>
                        <h1 className='dashboard-auth-title'>Your Servers</h1>
                        <p className='dashboard-auth-subtitle'>
                            {showOnlyAdmin ? "Showing others' servers" : 'Showing your servers'}
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

                <div className='dashboard-scroll-region'>
                    {!servers ? (
                        <PageLoadingSkeleton showChrome={false} rows={8} className='min-h-[420px]' />
                    ) : (
                        <Pagination data={servers} onPageSelect={setPage}>
                            {({ items }) =>
                                items.length > 0 ? (
                                    <div className='dashboard-rows-wrap'>
                                        {items.map((server) => (
                                            <ServerRow key={server.uuid} server={server} />
                                        ))}
                                    </div>
                                ) : (
                                    <div className='dashboard-empty-panel'>
                                        <p className='text-xs text-[rgba(174,183,194,0.78)]'>
                                            {showOnlyAdmin
                                                ? 'There are no other servers to display.'
                                                : 'There are no servers associated with your account.'}
                                        </p>
                                    </div>
                                )
                            }
                        </Pagination>
                    )}
                </div>
            </div>
        </div>
    );
};
