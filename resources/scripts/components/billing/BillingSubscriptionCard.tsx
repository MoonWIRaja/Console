import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import BillingResourceSlider from '@/components/billing/BillingResourceSlider';
import { BillingSubscription } from '@/api/account/billing';

interface Props {
    subscription: BillingSubscription;
    renewing: boolean;
    upgrading: boolean;
    onRenew: (subscription: BillingSubscription) => void;
    onUpgrade: (
        subscription: BillingSubscription,
        payload: { cpuCores: number; memoryGb: number; diskGb: number }
    ) => void;
}

const moneyFormatter = new Intl.NumberFormat('ms-MY', {
    style: 'currency',
    currency: 'MYR',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

const formatMoney = (value: number): string => moneyFormatter.format(Number.isFinite(value) ? value : 0);

const getStatusClasses = (status: string): string => {
    if (status === 'active') {
        return 'billing-status billing-status-active';
    }

    if (status === 'suspended') {
        return 'billing-status billing-status-suspended';
    }

    return 'billing-status billing-status-deleted';
};

const getStatusLabel = (status: string): string => {
    if (status === 'active') {
        return 'Active';
    }

    if (status === 'suspended') {
        return 'Suspended';
    }

    return 'Deleted';
};

export default ({ subscription, renewing, upgrading, onRenew, onUpgrade }: Props) => {
    const [upgradeOpen, setUpgradeOpen] = useState(false);
    const [cpuCores, setCpuCores] = useState(subscription.cpuCores);
    const [memoryGb, setMemoryGb] = useState(subscription.memoryGb);
    const [diskGb, setDiskGb] = useState(subscription.diskGb);

    useEffect(() => {
        setCpuCores(subscription.cpuCores);
        setMemoryGb(subscription.memoryGb);
        setDiskGb(subscription.diskGb);
        setUpgradeOpen(false);
    }, [
        subscription.id,
        subscription.cpuCores,
        subscription.memoryGb,
        subscription.diskGb,
        subscription.upgradedAt?.getTime(),
    ]);

    const diskUnits = Math.ceil(Math.max(diskGb, 0) / 10);
    const nextTotal = Number(
        (
            cpuCores * subscription.pricing.perVcore +
            memoryGb * subscription.pricing.perGbRam +
            diskUnits * subscription.pricing.per10gbDisk
        ).toFixed(2)
    );
    const additionalUpgradeTotal = Number(Math.max(nextTotal - subscription.recurringTotal, 0).toFixed(2));

    const hasUpgradeChanges =
        cpuCores !== subscription.cpuCores || memoryGb !== subscription.memoryGb || diskGb !== subscription.diskGb;
    const renewWindowMessage =
        !subscription.canRenew && subscription.status === 'active'
            ? subscription.renewAvailableAt
                ? `Renewal opens on ${subscription.renewAvailableAt.toLocaleString()}.`
                : 'Renewal opens 2 days before the billing deadline.'
            : null;

    return (
        <article className={'billing-subscription-card'}>
            <div className={'flex flex-wrap items-start justify-between gap-4'}>
                <div>
                    <div className={'flex flex-wrap items-center gap-3'}>
                        <h3 className={'billing-subscription-title'}>{subscription.serverName}</h3>
                        <span
                            className={`rounded-full px-3 py-1 text-[10px] font-bold uppercase tracking-[0.24em] ${getStatusClasses(
                                subscription.status
                            )}`}
                        >
                            {getStatusLabel(subscription.status)}
                        </span>
                    </div>
                    <p className={'mt-2 text-xs text-[color:var(--muted-foreground)]'}>
                        {subscription.nodeName} • {subscription.gameName}
                        {subscription.nestName ? ` • ${subscription.nestName}` : ''}
                    </p>
                </div>
                <div className={'text-right'}>
                    <p
                        className={
                            'text-[10px] font-bold uppercase tracking-[0.26em] text-[color:var(--muted-foreground)]'
                        }
                    >
                        Monthly Total
                    </p>
                    <p className={'mt-2 text-2xl font-black text-[color:var(--primary)]'}>
                        {formatMoney(subscription.recurringTotal)}
                    </p>
                </div>
            </div>

            <div className={'mt-5 grid gap-3 text-sm text-[color:var(--muted-foreground)] lg:grid-cols-2'}>
                <div className={'flex items-center justify-between gap-3'}>
                    <span>Resources</span>
                    <span className={'font-semibold text-[#f8f6ef]'}>
                        {subscription.cpuCores} vCore / {subscription.memoryGb} GB / {subscription.diskGb} GB
                    </span>
                </div>
                <div className={'flex items-center justify-between gap-3'}>
                    <span>Renews At</span>
                    <span className={'font-semibold text-[#f8f6ef]'}>
                        {subscription.renewsAt ? subscription.renewsAt.toLocaleString() : 'Unknown'}
                    </span>
                </div>
                {subscription.deletionScheduledAt && (
                    <div className={'flex items-center justify-between gap-3 lg:col-span-2'}>
                        <span>Delete Scheduled</span>
                        <span className={'font-semibold text-red-300'}>
                            {subscription.deletionScheduledAt.toLocaleString()}
                        </span>
                    </div>
                )}
            </div>

            {subscription.status === 'suspended' && (
                <div
                    className={
                        'mt-4 rounded-xl border border-amber-500/25 bg-amber-500/10 px-4 py-3 text-sm leading-6 text-amber-100'
                    }
                >
                    This server is suspended because the renewal deadline passed. Renew it before the delete time if you
                    want to keep the data.
                </div>
            )}

            {subscription.status === 'deleted' && (
                <div
                    className={
                        'mt-4 rounded-xl border border-red-500/25 bg-red-500/10 px-4 py-3 text-sm leading-6 text-red-100'
                    }
                >
                    This billing server has already been deleted because it was not renewed in time.
                </div>
            )}

            <div className={'mt-5 flex flex-wrap items-center gap-3'}>
                <button
                    type={'button'}
                    disabled={!subscription.canRenew || renewing}
                    onClick={() => onRenew(subscription)}
                    className={'billing-primary-btn'}
                >
                    {renewing
                        ? 'Creating...'
                        : subscription.status === 'suspended'
                        ? 'Create Renewal Invoice'
                        : 'Create Renewal Invoice'}
                </button>

                <button
                    type={'button'}
                    disabled={!subscription.canUpgrade || upgrading}
                    onClick={() => setUpgradeOpen((value) => !value)}
                    className={'billing-secondary-btn'}
                >
                    {upgradeOpen ? 'Close Upgrade' : 'Upgrade Plan'}
                </button>

                {subscription.serverIdentifier && subscription.status !== 'deleted' && (
                    <Link to={`/server/${subscription.serverIdentifier}`} className={'billing-ghost-btn'}>
                        Open Server
                    </Link>
                )}
            </div>

            {renewWindowMessage && (
                <p className={'mt-3 text-xs leading-6 text-[color:var(--muted-foreground)]'}>{renewWindowMessage}</p>
            )}

            {upgradeOpen && subscription.canUpgrade && (
                <div className={'billing-upgrade-panel'}>
                    <div className={'mb-4'}>
                        <h4 className={'text-lg font-black tracking-tight text-[#f8f6ef]'}>Upgrade Plan</h4>
                        <p className={'mt-2 text-xs leading-6 text-[color:var(--muted-foreground)]'}>
                            Only upgrades are allowed. The white marker shows the current plan, while the draggable
                            slider sets the upgrade target. You only pay the difference now.
                        </p>
                    </div>

                    <div className={'grid gap-4 xl:grid-cols-3'}>
                        <BillingResourceSlider
                            label={'vCore'}
                            value={cpuCores}
                            min={subscription.cpuCores}
                            max={subscription.limits.maxCpu}
                            unit={'vCore'}
                            helper={'Maximum follows the node billing setup.'}
                            baselineValue={subscription.cpuCores}
                            baselineLabel={'Current'}
                            disabled={subscription.limits.maxCpu <= subscription.cpuCores}
                            onChange={setCpuCores}
                        />
                        <BillingResourceSlider
                            label={'RAM'}
                            value={memoryGb}
                            min={subscription.memoryGb}
                            max={subscription.limits.maxMemoryGb}
                            unit={'GB'}
                            helper={'Node capacity and billing stock are both enforced.'}
                            baselineValue={subscription.memoryGb}
                            baselineLabel={'Current'}
                            disabled={subscription.limits.maxMemoryGb <= subscription.memoryGb}
                            onChange={setMemoryGb}
                        />
                        <BillingResourceSlider
                            label={'Storage'}
                            value={diskGb}
                            min={subscription.diskGb}
                            max={subscription.limits.maxDiskGb}
                            step={subscription.limits.diskStepGb}
                            unit={'GB'}
                            helper={'Storage stays in 10 GB billing steps.'}
                            baselineValue={subscription.diskGb}
                            baselineLabel={'Current'}
                            disabled={subscription.limits.maxDiskGb <= subscription.diskGb}
                            onChange={setDiskGb}
                        />
                    </div>

                    <div className={'billing-upgrade-summary'}>
                        <div className={'grid gap-4 lg:grid-cols-3'}>
                            <div className={'text-sm text-[color:var(--muted-foreground)]'}>
                                Current monthly total
                                <div className={'mt-1 text-xl font-black text-[#f8f6ef]'}>
                                    {formatMoney(subscription.recurringTotal)}
                                </div>
                            </div>
                            <div className={'text-sm text-[color:var(--muted-foreground)]'}>
                                Upgrade charge now
                                <div className={'mt-1 text-xl font-black text-[color:var(--primary)]'}>
                                    {formatMoney(additionalUpgradeTotal)}
                                </div>
                            </div>
                            <div className={'text-sm text-[color:var(--muted-foreground)]'}>
                                New monthly total
                                <div className={'mt-1 text-xl font-black text-[#f8f6ef]'}>{formatMoney(nextTotal)}</div>
                            </div>
                        </div>
                        <div className={'mt-4 flex flex-wrap items-center justify-between gap-4'}>
                            <p className={'text-xs leading-6 text-[color:var(--muted-foreground)]'}>
                                Future renewals will use the new monthly total after this upgrade is applied.
                            </p>
                            <button
                                type={'button'}
                                disabled={!hasUpgradeChanges || upgrading}
                                onClick={() => onUpgrade(subscription, { cpuCores, memoryGb, diskGb })}
                                className={'billing-primary-btn'}
                            >
                                {upgrading ? 'Creating...' : 'Create Upgrade Invoice'}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </article>
    );
};
