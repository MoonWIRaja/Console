import React, { useEffect, useState } from 'react';
import { Server } from '@/api/server/getServer';
import getServers from '@/api/getServers';
import ServerRow from '@/components/dashboard/ServerRow';
import Spinner from '@/components/elements/Spinner';
import useFlash from '@/plugins/useFlash';
import { useStoreState } from 'easy-peasy';
import { usePersistedState } from '@/plugins/usePersistedState';
import useSWR from 'swr';
import { PaginatedResult } from '@/api/http';
import Pagination from '@/components/elements/Pagination';
import { useLocation } from 'react-router-dom';
import FlashMessageRender from '@/components/FlashMessageRender';

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
        <div style={{ padding: '32px 40px', fontFamily: "'Space Mono', monospace", minHeight: '100vh', backgroundColor: '#ffffff' }}>
            <FlashMessageRender byKey={'dashboard'} />

            <div style={{ marginBottom: '24px', display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                <div>
                    <h1 style={{ fontSize: '24px', fontWeight: 'bold', color: '#000000', margin: 0, letterSpacing: '-0.02em' }}>
                        YOUR SERVERS
                    </h1>
                    <p style={{ fontSize: '11px', color: '#6b7280', marginTop: '4px' }}>
                        {showOnlyAdmin ? "Showing others' servers" : 'Showing your servers'}
                    </p>
                </div>
                {rootAdmin && (
                    <button
                        onClick={() => setShowOnlyAdmin((s: boolean) => !s)}
                        style={{
                            padding: '8px 16px',
                            fontSize: '10px',
                            fontWeight: 'bold',
                            letterSpacing: '0.05em',
                            textTransform: 'uppercase',
                            border: '1px solid #000000',
                            backgroundColor: showOnlyAdmin ? '#000000' : 'transparent',
                            color: showOnlyAdmin ? '#ffffff' : '#000000',
                            cursor: 'pointer',
                            fontFamily: "'Space Mono', monospace",
                            transition: 'all 0.15s',
                        }}
                    >
                        {showOnlyAdmin ? 'SHOW MINE' : 'SHOW ALL'}
                    </button>
                )}
            </div>

            {!servers ? (
                <div style={{ display: 'flex', justifyContent: 'center', padding: '60px 0' }}>
                    <Spinner centered size={'large'} />
                </div>
            ) : (
                <Pagination data={servers} onPageSelect={setPage}>
                    {({ items }) =>
                        items.length > 0 ? (
                                <div style={{ display: 'flex', flexDirection: 'column', gap: '8px' }}>
                                    {items.map((server) => (
                                        <ServerRow key={server.uuid} server={server} />
                                    ))}
                                </div>
                        ) : (
                                    <div style={{ textAlign: 'center', padding: '60px 0' }}>
                                        <p style={{ color: '#9ca3af', fontSize: '12px' }}>
                                            {showOnlyAdmin
                                                ? 'There are no other servers to display.'
                                                : 'There are no servers associated with your account.'}
                                        </p>
                                    </div>
                        )
                    }
                </Pagination>
            )}

            <div style={{ marginTop: '40px', textAlign: 'center' }}>
                <p style={{ fontSize: '10px', color: '#d1d5db' }}>
                    <a
                        rel={'noopener nofollow noreferrer'}
                        href={'https://pterodactyl.io'}
                        target={'_blank'}
                        style={{ color: '#d1d5db', textDecoration: 'none' }}
                    >
                        Pterodactyl&reg;
                    </a>
                    &nbsp;&copy; 2015 - {new Date().getFullYear()}
                </p>
            </div>
        </div>
    );
};
