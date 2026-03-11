import React from 'react';
import { useHistory } from 'react-router';
import { ServerDatabase } from '@/api/server/databases/getServerDatabases';
import GreyRowBox from '@/components/elements/GreyRowBox';
import { ServerContext } from '@/state/server';
import tw from 'twin.macro';

interface Props {
    database: ServerDatabase;
    className?: string;
}

export default ({ database, className }: Props) => {
    const history = useHistory();
    const id = ServerContext.useStoreState((state) => state.server.data!.id);

    const openDashboard = () => history.push(`/server/${id}/databases/${database.id}`);

    return (
        <GreyRowBox
            $hoverable
            className={className}
            css={tw`mb-2 cursor-pointer flex-col items-stretch gap-4 p-5 md:flex-row md:items-center md:justify-between`}
            role={'button'}
            tabIndex={0}
            onClick={openDashboard}
            onKeyDown={(event: React.KeyboardEvent<HTMLDivElement>) => {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    openDashboard();
                }
            }}
        >
            <div css={tw`min-w-0`}>
                <div css={tw`flex flex-wrap items-center gap-2`}>
                    <p css={tw`text-lg font-semibold text-[#f8f6ef]`}>{database.name}</p>
                    <span
                        css={tw`rounded-full border border-[color:var(--primary)] px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.18em] text-[color:var(--primary)]`}
                        style={{ backgroundColor: 'rgba(var(--primary-rgb), 0.1)' }}
                    >
                        SQL Dashboard
                    </span>
                </div>
                <p css={tw`mt-1 text-sm text-neutral-400`}>
                    Open this database workspace to view connection details and manage SQL settings.
                </p>
            </div>

            <div css={tw`grid gap-4 text-left sm:grid-cols-3 sm:gap-6 md:min-w-[52%]`}>
                <div css={tw`min-w-0`}>
                    <p css={tw`truncate text-sm font-medium text-[#f8f6ef]`}>{database.connectionString}</p>
                    <p css={tw`mt-1 text-[11px] uppercase tracking-[0.16em] text-neutral-500`}>Endpoint</p>
                </div>
                <div css={tw`min-w-0`}>
                    <p css={tw`truncate text-sm font-medium text-[#f8f6ef]`}>{database.allowConnectionsFrom}</p>
                    <p css={tw`mt-1 text-[11px] uppercase tracking-[0.16em] text-neutral-500`}>Connections From</p>
                </div>
                <div css={tw`min-w-0`}>
                    <p css={tw`truncate text-sm font-medium text-[#f8f6ef]`}>{database.username}</p>
                    <p css={tw`mt-1 text-[11px] uppercase tracking-[0.16em] text-neutral-500`}>Username</p>
                </div>
            </div>
        </GreyRowBox>
    );
};
