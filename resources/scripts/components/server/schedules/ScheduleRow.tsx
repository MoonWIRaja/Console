import React from 'react';
import { Schedule } from '@/api/server/schedules/getServerSchedules';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faCalendarAlt } from '@fortawesome/free-solid-svg-icons';
import { format } from 'date-fns';
import tw from 'twin.macro';
import ScheduleCronRow from '@/components/server/schedules/ScheduleCronRow';

export default ({ schedule }: { schedule: Schedule }) => (
    <>
        <div css={tw`hidden md:block`}>
            <FontAwesomeIcon icon={faCalendarAlt} fixedWidth />
        </div>
        <div css={tw`flex-1 md:ml-4`}>
            <p css={tw`text-[#f8f6ef]`}>{schedule.name}</p>
            <p css={tw`text-xs text-[color:var(--text-subtle)]`}>
                Last run at: {schedule.lastRunAt ? format(schedule.lastRunAt, "MMM do 'at' h:mma") : 'never'}
            </p>
        </div>
        <div>
            <p
                css={[
                    tw`rounded px-3 py-1 text-xs uppercase sm:hidden`,
                    schedule.isActive
                        ? tw`bg-green-700 text-green-100`
                        : tw`border border-[color:var(--border)] bg-[color:var(--card)] text-[color:var(--foreground)]`,
                ]}
            >
                {schedule.isActive ? 'Active' : 'Inactive'}
            </p>
        </div>
        <ScheduleCronRow cron={schedule.cron} css={tw`mx-auto sm:mx-8 w-full sm:w-auto mt-4 sm:mt-0`} />
        <div>
            <p
                css={[
                    tw`hidden rounded px-3 py-1 text-xs uppercase sm:block`,
                    schedule.isActive && !schedule.isProcessing
                        ? tw`bg-green-700 text-green-100`
                        : tw`border border-[color:var(--border)] bg-[color:var(--card)] text-[color:var(--foreground)]`,
                ]}
            >
                {schedule.isProcessing ? 'Processing' : schedule.isActive ? 'Active' : 'Inactive'}
            </p>
        </div>
    </>
);
