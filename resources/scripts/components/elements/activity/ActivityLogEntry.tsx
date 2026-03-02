import React from 'react';
import { Link } from 'react-router-dom';
import Tooltip from '@/components/elements/tooltip/Tooltip';
import Translate from '@/components/elements/Translate';
import { format, formatDistanceToNowStrict } from 'date-fns';
import { ActivityLog } from '@definitions/user';
import ActivityLogMetaButton from '@/components/elements/activity/ActivityLogMetaButton';
import { FolderOpenIcon, TerminalIcon } from '@heroicons/react/solid';
import classNames from 'classnames';
import style from './style.module.css';
import Avatar from '@/components/Avatar';
import useLocationHash from '@/plugins/useLocationHash';
import { getObjectKeys, isObject } from '@/lib/objects';
import { useStoreState } from 'easy-peasy';

interface Props {
    activity: ActivityLog;
    children?: React.ReactNode;
}

function wrapProperties(value: unknown): any {
    if (value === null || typeof value === 'string' || typeof value === 'number') {
        return `<strong>${String(value)}</strong>`;
    }

    if (isObject(value)) {
        return getObjectKeys(value).reduce((obj, key) => {
            if (key === 'count' || (typeof key === 'string' && key.endsWith('_count'))) {
                return { ...obj, [key]: value[key] };
            }
            return { ...obj, [key]: wrapProperties(value[key]) };
        }, {} as Record<string, unknown>);
    }

    if (Array.isArray(value)) {
        return value.map(wrapProperties);
    }

    return value;
}

export default ({ activity, children }: Props) => {
    const { pathTo } = useLocationHash();
    const actor = activity.relationships.actor;
    const currentUser = useStoreState((state) => state.user.data);
    const fallbackImage =
        actor?.uuid && currentUser?.uuid && actor.uuid === currentUser.uuid ? currentUser.image : undefined;
    const actorImage = actor?.image || fallbackImage;
    const properties = wrapProperties(activity.properties);

    return (
        <div className={'group grid grid-cols-10 border-b border-[color:var(--border)] py-4 last:rounded-b-xl last:border-0'}>
            <div className={'hidden sm:flex sm:col-span-1 items-center justify-center select-none'}>
                <div className={'flex h-10 w-10 items-center overflow-hidden rounded-full border border-[color:var(--border)] bg-[color:var(--background)]'}>
                    {actorImage ? (
                        <img
                            src={actorImage}
                            alt={actor?.username || 'User avatar'}
                            className={'h-full w-full object-cover'}
                        />
                    ) : (
                        <Avatar name={actor?.uuid || 'system'} />
                    )}
                </div>
            </div>
            <div className={'col-span-10 sm:col-span-9 flex'}>
                <div className={'flex-1 px-4 sm:px-0'}>
                    <div className={'flex items-center text-[#f8f6ef]'}>
                        <Tooltip placement={'top'} content={actor?.email || 'System User'}>
                            <span>{actor?.username || 'System'}</span>
                        </Tooltip>
                        <span className={'text-neutral-500'}>&nbsp;&mdash;&nbsp;</span>
                        <Link
                            to={`#${pathTo({ event: activity.event })}`}
                            className={'transition-colors duration-75 hover:text-[color:var(--primary)] active:text-[color:var(--primary)]'}
                        >
                            {activity.event}
                        </Link>
                        <div className={classNames(style.icons, 'group-hover:text-[color:var(--primary)]')}>
                            {activity.isApi && (
                                <Tooltip placement={'top'} content={'Using API Key'}>
                                    <TerminalIcon />
                                </Tooltip>
                            )}
                            {activity.event.startsWith('server:sftp.') && (
                                <Tooltip placement={'top'} content={'Using SFTP'}>
                                    <FolderOpenIcon />
                                </Tooltip>
                            )}
                            {children}
                        </div>
                    </div>
                    <p className={style.description}>
                        <Translate ns={'activity'} values={properties} i18nKey={activity.event.replace(':', '.')} />
                    </p>
                    <div className={'mt-1 flex items-center text-sm'}>
                        {activity.ip && (
                            <span>
                                {activity.ip}
                                <span className={'text-neutral-500'}>&nbsp;|&nbsp;</span>
                            </span>
                        )}
                        <Tooltip placement={'right'} content={format(activity.timestamp, 'MMM do, yyyy H:mm:ss')}>
                            <span>{formatDistanceToNowStrict(activity.timestamp, { addSuffix: true })}</span>
                        </Tooltip>
                    </div>
                </div>
                {activity.hasAdditionalMetadata && <ActivityLogMetaButton meta={activity.properties} />}
            </div>
        </div>
    );
};
