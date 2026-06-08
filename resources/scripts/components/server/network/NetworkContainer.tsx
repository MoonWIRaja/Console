import React, { useEffect, useState } from 'react';
import tw from 'twin.macro';
import isEqual from 'react-fast-compare';
import Can from '@/components/elements/Can';
import FlashMessageRender from '@/components/FlashMessageRender';
import PageLoadingSkeleton from '@/components/elements/PageLoadingSkeleton';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import SpinnerOverlay from '@/components/elements/SpinnerOverlay';
import AllocationRow from '@/components/server/network/AllocationRow';
import SubdomainRow from '@/components/server/network/SubdomainRow';
import CreateSubdomainButton from '@/components/server/network/CreateSubdomainButton';
import { InteractiveHoverButton } from '@/components/ui/interactive-hover-button';
import { ServerContext } from '@/state/server';
import { useFlashKey } from '@/plugins/useFlash';
import { usePermissions } from '@/plugins/usePermissions';
import { useDeepCompareEffect } from '@/plugins/useDeepCompareEffect';
import getServerAllocations from '@/api/swr/getServerAllocations';
import getServerSubdomains from '@/api/server/network/getServerSubdomains';
import createServerAllocation from '@/api/server/network/createServerAllocation';

const NetworkContainer = () => {
    const [loading, setLoading] = useState(false);
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const allocationLimit = ServerContext.useStoreState((state) => state.server.data!.featureLimits.allocations);
    const allocations = ServerContext.useStoreState((state) => state.server.data!.allocations, isEqual);
    const setServerFromState = ServerContext.useStoreActions((actions) => actions.server.setServerFromState);
    const [canViewAllocations] = usePermissions(['allocation.read']);
    const [canViewSubdomains] = usePermissions(['subdomain.read']);

    const { clearFlashes, clearAndAddHttpError } = useFlashKey('server:network');
    const { data, error, mutate } = getServerAllocations(canViewAllocations);
    const {
        data: subdomainData,
        error: subdomainError,
        mutate: mutateSubdomains,
    } = getServerSubdomains(canViewSubdomains);

    useEffect(() => {
        if (canViewAllocations) {
            mutate(allocations);
        }
    }, [canViewAllocations]);

    useEffect(() => {
        if (canViewAllocations) {
            clearAndAddHttpError(error);
        }
    }, [error, canViewAllocations]);

    useEffect(() => {
        if (canViewSubdomains) {
            clearAndAddHttpError(subdomainError);
        }
    }, [subdomainError, canViewSubdomains]);

    useDeepCompareEffect(() => {
        if (!data) return;

        setServerFromState((state) => ({ ...state, allocations: data }));
    }, [data]);

    const onCreateAllocation = () => {
        clearFlashes();

        setLoading(true);
        createServerAllocation(uuid)
            .then((allocation) => {
                setServerFromState((state) => ({ ...state, allocations: state.allocations.concat(allocation) }));
                return mutate(data?.concat(allocation), false);
            })
            .catch((requestError) => clearAndAddHttpError(requestError))
            .then(() => setLoading(false));
    };

    const isLoading =
        (canViewAllocations && !data && !error) || (canViewSubdomains && !subdomainData && !subdomainError);

    return (
        <ServerContentBlock
            showFlashKey={'server:network'}
            title={'Network'}
            className={'content-container-full px-4 py-4 xl:px-6'}
        >
            <FlashMessageRender byKey={'server:network'} />

            {isLoading ? (
                <PageLoadingSkeleton showChrome={false} showSpinner={false} rows={7} className='min-h-[320px]' />
            ) : (
                <div className={'grid gap-6 xl:grid-cols-2'}>
                    <section className={'sc-card p-5'}>
                        <div className={'mb-4 flex flex-wrap items-start justify-between gap-4'}>
                            <div>
                                <p className={'text-xs uppercase tracking-[0.28em] sc-muted'}>
                                    Ports
                                </p>
                                <h2 className={'mt-2 text-xl font-semibold sc-text'}>
                                    Allocations
                                </h2>
                                <p className={'mt-2 max-w-2xl text-sm sc-muted'}>
                                    Manage the primary connection details and any extra ports attached to this server.
                                </p>
                            </div>
                        </div>

                        {!canViewAllocations ? (
                            <div className={'sc-card-inner px-4 py-6 text-sm sc-muted'}>
                                You do not have permission to view or manage allocations for this server.
                            </div>
                        ) : (
                            <>
                                <div>
                                    {(data ?? []).map((allocation) => (
                                        <AllocationRow
                                            key={`${allocation.ip}:${allocation.port}`}
                                            allocation={allocation}
                                        />
                                    ))}
                                </div>

                                {allocationLimit > 0 && (
                                    <Can action={'allocation.create'}>
                                        <SpinnerOverlay visible={loading} />
                                        <div css={tw`mt-6 sm:flex items-center justify-end`}>
                                            <p css={tw`mb-4 text-sm text-[color:var(--text-subtle)] sm:mb-0 sm:mr-6`}>
                                                You are currently using {(data ?? []).length} of {allocationLimit}{' '}
                                                allowed allocations for this server.
                                            </p>
                                            {allocationLimit > (data ?? []).length && (
                                                <InteractiveHoverButton
                                                    className={'w-full sm:w-auto'}
                                                    text={'Create Allocation'}
                                                    onClick={onCreateAllocation}
                                                />
                                            )}
                                        </div>
                                    </Can>
                                )}
                            </>
                        )}
                    </section>

                    <section className={'sc-card p-5'}>
                        <div className={'mb-4 flex flex-wrap items-start justify-between gap-4'}>
                            <div>
                                <p className={'text-xs uppercase tracking-[0.28em] sc-muted'}>
                                    DNS
                                </p>
                                <h2 className={'mt-2 text-xl font-semibold sc-text'}>
                                    Subdomains
                                </h2>
                                <p className={'mt-2 max-w-2xl text-sm sc-muted'}>
                                    Create branded connection hosts for this server using the subdomain templates
                                    matched to its nest.
                                </p>
                            </div>
                        </div>

                        {!canViewSubdomains ? (
                            <div className={'sc-card-inner px-4 py-6 text-sm sc-muted'}>
                                You do not have permission to view or manage subdomains for this server.
                            </div>
                        ) : subdomainError ? (
                            <div className={'sc-card-inner px-4 py-6 text-sm sc-muted'}>
                                The subdomain section could not be loaded right now. Check the flash message for the DNS
                                error details.
                            </div>
                        ) : (
                            <>
                                {subdomainData && subdomainData.items.length > 0 ? (
                                    subdomainData.items.map((subdomain) => (
                                        <SubdomainRow
                                            key={subdomain.id}
                                            subdomain={subdomain}
                                            onDeleted={() => mutateSubdomains()}
                                        />
                                    ))
                                ) : (
                                    <div className={'sc-card-inner px-4 py-6 text-sm sc-muted'}>
                                        {subdomainData && subdomainData.templates.length > 0
                                            ? 'No subdomains have been created for this server yet.'
                                            : 'No subdomain template matches this server nest yet. Configure a domain and template in the admin panel first.'}
                                    </div>
                                )}

                                <div css={tw`mt-6 sm:flex items-center justify-end`}>
                                    <p css={tw`mb-4 text-sm text-[color:var(--text-subtle)] sm:mb-0 sm:mr-6`}>
                                        {subdomainData?.items.length ?? 0} active subdomain(s) currently point to this
                                        server.
                                    </p>
                                    <Can action={'subdomain.create'}>
                                        <CreateSubdomainButton
                                            templates={subdomainData?.templates ?? []}
                                            onCreated={() => mutateSubdomains()}
                                        />
                                    </Can>
                                </div>
                            </>
                        )}
                    </section>
                </div>
            )}
        </ServerContentBlock>
    );
};

export default NetworkContainer;
