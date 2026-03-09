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
        <div className='min-h-screen bg-[color:var(--card)] px-6 py-8 font-mono text-white md:px-10'>
            <style>{`
                .dashboard-theme {
                    --neon-green: var(--primary);
                }
                .shine-border {
                    border-radius: 12px;
                    background: linear-gradient(180deg, rgba(255, 255, 255, 0.03), rgba(255, 255, 255, 0.01));
                    transition: background 0.35s ease;
                }
                .shine-border:hover {
                    background: linear-gradient(180deg, rgba(255, 255, 255, 0.04), rgba(255, 255, 255, 0.01));
                }
                .neon-glow-text {
                    text-shadow: 0 0 8px rgba(var(--primary-rgb), 0.5);
                }
                .progress-neon {
                    box-shadow: 0 0 10px rgba(var(--primary-rgb), 0.55);
                }
            `}</style>
            <div className='dashboard-theme'>
                <FlashMessageRender byKey={'dashboard'} />

                <div className='mb-8 flex flex-wrap items-center justify-between gap-4'>
                    <div>
                        <h1 className='text-2xl font-black tracking-tight text-white'>YOUR SERVERS</h1>
                        <p className='mt-1 text-[11px] text-gray-400'>
                            {showOnlyAdmin ? "Showing others' servers" : 'Showing your servers'}
                        </p>
                    </div>
                    {rootAdmin && (
                        <ToggleSwitch
                            id='toggle-admin-servers'
                            checked={!!showOnlyAdmin}
                            onChange={(value) => setShowOnlyAdmin(value)}
                            label={showOnlyAdmin ? 'Showing All Servers' : 'Showing My Servers'}
                        />
                    )}
                </div>

                {!servers ? (
                    <PageLoadingSkeleton showChrome={false} rows={8} className='min-h-[420px]' />
                ) : (
                    <Pagination data={servers} onPageSelect={setPage}>
                        {({ items }) =>
                            items.length > 0 ? (
                                    <div className='flex flex-col gap-3'>
                                    {items.map((server) => (
                                        <ServerRow key={server.uuid} server={server} />
                                    ))}
                                </div>
                                ) : (
                                <div className='py-16 text-center'>
                                    <p className='text-xs text-gray-500'>
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
    );
};
