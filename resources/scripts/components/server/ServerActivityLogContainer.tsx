import React, { useEffect, useState } from 'react';
import { useActivityLogs } from '@/api/server/activity';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import { useFlashKey } from '@/plugins/useFlash';
import FlashMessageRender from '@/components/FlashMessageRender';
import ActivityLogEntry from '@/components/elements/activity/ActivityLogEntry';
import PaginationFooter from '@/components/elements/table/PaginationFooter';
import { ActivityLogFilters } from '@/api/account/activity';
import { Link } from 'react-router-dom';
import classNames from 'classnames';
import { styles as btnStyles } from '@/components/elements/button/index';
import { XCircleIcon } from '@heroicons/react/solid';
import useLocationHash from '@/plugins/useLocationHash';
import PageLoadingSkeleton from '@/components/elements/PageLoadingSkeleton';

export default () => {
    const { hash } = useLocationHash();
    const { clearAndAddHttpError } = useFlashKey('server:activity');
    const [filters, setFilters] = useState<ActivityLogFilters>({ page: 1, sorts: { timestamp: -1 } });

    const { data, isValidating, error } = useActivityLogs(filters, {
        revalidateOnMount: true,
        revalidateOnFocus: false,
    });

    useEffect(() => {
        setFilters((value) => ({ ...value, filters: { ip: hash.ip, event: hash.event } }));
    }, [hash]);

    useEffect(() => {
        clearAndAddHttpError(error);
    }, [error]);

    return (
        <ServerContentBlock
            title={'Activity Log'}
            className={'content-container-full px-4 py-4 xl:px-6 xl:py-4'}
        >
            <div className={'flex h-[calc(100dvh-8.75rem)] min-h-[420px] flex-col lg:h-[calc(100dvh-9.5rem)]'}>
                <FlashMessageRender byKey={'server:activity'} />
                {(filters.filters?.event || filters.filters?.ip) && (
                    <div className={'flex justify-end mb-2'}>
                        <Link
                            to={'#'}
                            className={classNames(btnStyles.button, btnStyles.text, 'w-full sm:w-auto')}
                            onClick={() => setFilters((value) => ({ ...value, filters: {} }))}
                        >
                            Clear Filters <XCircleIcon className={'w-4 h-4 ml-2'} />
                        </Link>
                    </div>
                )}
                {!data && isValidating ? (
                    <PageLoadingSkeleton
                        showChrome={false}
                        showSpinner={false}
                        rows={7}
                        className='min-h-0 flex-1'
                    />
                ) : !data?.items.length ? (
                    <p className={'text-center text-sm text-neutral-400'}>No activity logs available for this server.</p>
                ) : (
                    <div
                        className={'min-h-0 flex-1 overflow-hidden rounded-xl border border-[color:var(--border)] bg-[color:var(--card)] p-4'}
                    >
                        <div className={'h-full overflow-y-auto pr-1'}>
                            {data?.items.map((activity) => (
                                <ActivityLogEntry key={activity.id} activity={activity}>
                                    <span />
                                </ActivityLogEntry>
                            ))}
                        </div>
                    </div>
                )}
                {data && (
                    <PaginationFooter
                        className={'my-0 mt-2 shrink-0'}
                        pagination={data.pagination}
                        onPageSelect={(page) => setFilters((value) => ({ ...value, page }))}
                    />
                )}
            </div>
        </ServerContentBlock>
    );
};
