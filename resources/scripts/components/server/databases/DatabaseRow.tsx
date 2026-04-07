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
            className={['group', className].filter(Boolean).join(' ')}
            css={tw`mb-2 cursor-pointer flex-col items-stretch gap-5 p-5 md:flex-row md:items-center md:justify-between`}
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
            <div css={tw`min-w-0 md:max-w-[37%]`}>
                <p css={tw`text-[11px] font-semibold uppercase tracking-[0.28em] text-[color:var(--text-subtle)]`}>
                    Database
                </p>
                <div css={tw`mt-2 flex flex-wrap items-center gap-3`}>
                    <p css={tw`text-xl font-semibold leading-tight text-[#f8f6ef]`}>{database.name}</p>
                    <span
                        css={tw`inline-flex items-center rounded-full border border-[color:var(--primary)] px-3 py-1 text-[10px] font-bold uppercase tracking-[0.18em] text-[color:var(--primary)]`}
                        style={{ backgroundColor: 'rgba(var(--primary-rgb), 0.1)' }}
                    >
                        SQL Dashboard
                    </span>
                </div>
                <p css={tw`mt-3 max-w-xl text-sm leading-6 text-[color:var(--text-subtle)]`}>
                    Open this database workspace to view connection details and manage SQL settings.
                </p>
                <p
                    css={tw`mt-4 text-[11px] font-semibold uppercase tracking-[0.2em] text-[color:var(--primary)] transition-colors duration-150 group-hover:text-[#f8f6ef]`}
                >
                    Open workspace
                </p>
            </div>

            <div css={tw`grid gap-2.5 text-left sm:grid-cols-3 md:min-w-[56%] md:max-w-[60%] md:self-center`}>
                <div
                    css={tw`min-w-0 rounded-[0.9rem] border border-[color:var(--border)] bg-[color:var(--background)] px-3 py-2.5 transition-colors duration-150`}
                    style={{ borderColor: 'rgba(var(--primary-rgb), 0.18)' }}
                >
                    <p css={tw`text-[10px] font-semibold uppercase tracking-[0.14em] text-[color:var(--text-subtle)]`}>
                        Endpoint
                    </p>
                    <p css={tw`mt-1.5 truncate text-[13px] font-medium leading-5 text-[#f8f6ef]`}>
                        {database.connectionString}
                    </p>
                </div>
                <div
                    css={tw`min-w-0 rounded-[0.9rem] border border-[color:var(--border)] bg-[color:var(--background)] px-3 py-2.5 transition-colors duration-150`}
                    style={{ borderColor: 'rgba(var(--primary-rgb), 0.18)' }}
                >
                    <p css={tw`text-[10px] font-semibold uppercase tracking-[0.14em] text-[color:var(--text-subtle)]`}>
                        Connections From
                    </p>
                    <p css={tw`mt-1.5 truncate text-[13px] font-medium leading-5 text-[#f8f6ef]`}>
                        {database.allowConnectionsFrom}
                    </p>
                </div>
                <div
                    css={tw`min-w-0 rounded-[0.9rem] border border-[color:var(--border)] bg-[color:var(--background)] px-3 py-2.5 transition-colors duration-150`}
                    style={{ borderColor: 'rgba(var(--primary-rgb), 0.18)' }}
                >
                    <p css={tw`text-[10px] font-semibold uppercase tracking-[0.14em] text-[color:var(--text-subtle)]`}>
                        Username
                    </p>
                    <p css={tw`mt-1.5 truncate text-[13px] font-medium leading-5 text-[#f8f6ef]`}>{database.username}</p>
                </div>
            </div>
        </GreyRowBox>
    );
};
