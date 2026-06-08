import React, { useEffect } from 'react';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import PageLoadingSkeleton from '@/components/elements/PageLoadingSkeleton';
import FlashMessageRender from '@/components/FlashMessageRender';
import { useFlashKey } from '@/plugins/useFlash';
import getSplitOverview from '@/api/server/split/getSplitOverview';
import SplitRow from '@/components/server/split/SplitRow';
import AddSplitButton from '@/components/server/split/AddSplitButton';
import { ServerContext } from '@/state/server';
import tw from 'twin.macro';
import Can from '@/components/elements/Can';

const MetricCard = ({ label, value }: { label: string; value: string }) => (
    <div className={'sc-card-inner p-4'}>
        <div className={'text-[10px] uppercase tracking-[0.18em] sc-muted'}>{label}</div>
        <div className={'mt-2 text-lg font-semibold sc-text'}>{value}</div>
    </div>
);

const SplitContainer = () => {
    const serverUuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const setServerFromState = ServerContext.useStoreActions((actions) => actions.server.setServerFromState);
    const { clearFlashes, clearAndAddHttpError } = useFlashKey('server:split');
    const { data, error, mutate } = getSplitOverview();

    useEffect(() => {
        clearFlashes();
    }, []);

    useEffect(() => {
        if (error) {
            clearAndAddHttpError(error);
        }
    }, [error]);

    useEffect(() => {
        if (!data) {
            return;
        }

        const current = data.servers.find((member) => member.isCurrent);
        if (!current) {
            return;
        }

        setServerFromState((state) => ({
            ...state,
            limits: {
                ...state.limits,
                memory: current.memory,
                disk: current.disk,
                cpu: current.cpu,
                swap: current.swap,
            },
        }));
    }, [data]);

    if (!data) {
        return (
            <ServerContentBlock title={'Split'} className={'content-container-full px-4 py-4 xl:px-6'}>
                <PageLoadingSkeleton showChrome={false} showSpinner={false} rows={6} className='min-h-[320px]' />
            </ServerContentBlock>
        );
    }

    return (
        <ServerContentBlock title={'Split'} className={'content-container-full px-4 py-4 xl:px-6'}>
            <FlashMessageRender byKey={'server:split'} />

            <div css={tw`grid gap-4 lg:grid-cols-4`}>
                <MetricCard label={'Family CPU'} value={`${data.familyTotals.cpu}%`} />
                <MetricCard label={'Family Memory'} value={`${data.familyTotals.memory} MiB`} />
                <MetricCard label={'Family Disk'} value={`${data.familyTotals.disk} MiB`} />
                <MetricCard label={'Family Swap'} value={`${data.familyTotals.swap} MiB`} />
            </div>

            <div css={tw`mt-4 grid gap-4 lg:grid-cols-3`}>
                <div className={'sc-card p-4 lg:col-span-2'}>
                    <div className={'sc-card-header -m-4 mb-3 px-4 py-3'}>
                        <div className={'text-[10px] font-bold uppercase tracking-[0.18em] sc-accent'}>Current Server Available To Split</div>
                    </div>
                    <div className={'mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-4'}>
                        <MetricCard label={'CPU'} value={`${data.available.cpu}%`} />
                        <MetricCard label={'Memory'} value={`${data.available.memory} MiB`} />
                        <MetricCard label={'Disk'} value={`${data.available.disk} MiB`} />
                        <MetricCard label={'Swap'} value={`${data.available.swap} MiB`} />
                    </div>
                </div>

                <div className={'sc-card p-4'}>
                    <div className={'sc-card-header -m-4 mb-3 px-4 py-3'}>
                        <div className={'text-[10px] font-bold uppercase tracking-[0.18em] sc-accent'}>Split Slots</div>
                    </div>
                    <div className={'mt-3 text-sm sc-text'}>
                        Using {data.splitsUsed} of {data.splitLimit} split slots.
                    </div>
                    <div className={'mt-2 text-sm sc-muted'}>
                        Remaining: {data.remainingSplits}
                    </div>
                    {!data.canCreate && (
                        <div className={'mt-3 rounded-lg border border-[#f59e0b] bg-[#f59e0b]/15 px-3 py-2 text-sm text-[#92400e]'}>
                            {data.createBlockedReason || 'This server cannot create another split right now.'}
                        </div>
                    )}
                </div>
            </div>

            <div css={tw`mt-6`}>
                {data.servers.map((member) => (
                    <SplitRow key={member.id} member={member} serverUuid={serverUuid} overview={data} onUpdated={mutate} onDeleted={mutate} />
                ))}
            </div>

            <Can action={'split.create'}>
                <div css={tw`mt-6 flex justify-end`}>
                    {data.canCreate ? <AddSplitButton overview={data} onCreated={mutate} /> : null}
                </div>
            </Can>
        </ServerContentBlock>
    );
};

export default SplitContainer;
