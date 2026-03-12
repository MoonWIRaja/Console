import React, { useEffect, useState } from 'react';
import { useStoreState } from 'easy-peasy';
import { ApplicationStore } from '@/state';
import FlashMessageRender from '@/components/FlashMessageRender';
import Spinner from '@/components/elements/Spinner';
import Input from '@/components/elements/Input';
import Select from '@/components/elements/Select';
import { Dialog } from '@/components/elements/dialog';
import useFlash, { useFlashKey } from '@/plugins/useFlash';
import {
    BillingCheckout,
    BillingInvoice,
    BillingNodeCatalog,
    BillingProfile,
    createBillingOrder,
    retryBillingInvoicePayment,
    toggleBillingSubscriptionAutoRenew,
    updateBillingProfile,
    useBillingCatalog,
    useBillingInvoices,
    useBillingOrders,
    useBillingProfile,
    useBillingSubscriptions,
    renewBillingSubscription,
    upgradeBillingSubscription,
} from '@/api/account/billing';
import BillingVariableBox from '@/components/billing/BillingVariableBox';
import BillingResourceSlider from '@/components/billing/BillingResourceSlider';
import BillingSubscriptionCard from '@/components/billing/BillingSubscriptionCard';
import {
    billingProfileFieldLabels,
    emptyBillingProfile,
    getMissingBillingProfileFields,
    normalizeBillingProfile,
} from '@/components/billing/billingProfileUtils';

type NestOption = {
    id: number;
    name: string;
};

type UpgradePayload = {
    cpuCores: number;
    memoryGb: number;
    diskGb: number;
};

type PendingBillingAction =
    | { type: 'create' }
    | { type: 'renew'; subscriptionId: number }
    | { type: 'upgrade'; subscriptionId: number; payload: UpgradePayload };

const moneyFormatter = new Intl.NumberFormat('ms-MY', {
    style: 'currency',
    currency: 'MYR',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

const formatMoney = (value: number): string => moneyFormatter.format(Number.isFinite(value) ? value : 0);

const clamp = (value: number, min: number, max: number): number => {
    if (max <= min) {
        return min;
    }

    return Math.min(Math.max(value, min), max);
};

const getNestOptions = (node: BillingNodeCatalog | null): NestOption[] => {
    if (!node) {
        return [];
    }

    const map = new Map<number, string>();
    node.games.forEach((game) => {
        if (!map.has(game.nestId)) {
            map.set(game.nestId, game.nestName);
        }
    });

    return Array.from(map.entries())
        .map(([id, name]) => ({ id, name }))
        .sort((left, right) => left.name.localeCompare(right.name));
};

const getOrderStatusLabel = (status: string): string =>
    status.replace(/_/g, ' ').replace(/\b\w/g, (value) => value.toUpperCase());

const billingModalInputClass =
    'w-full rounded-xl border border-[color:var(--border)] bg-[rgba(5,8,14,0.72)] px-3 py-2 text-sm text-[#f8f6ef] outline-none transition focus:border-[color:var(--primary)] focus:shadow-[0_0_0_1px_rgba(var(--primary-rgb),0.35)]';

const formatMissingBillingFields = (fields: Array<keyof BillingProfile>): string =>
    fields.map((field) => billingProfileFieldLabels[field]).join(', ');

const ACTIVE_SUBSCRIPTIONS_PAGE_SIZE = 1;
const INVOICES_PAGE_SIZE = 4;
const ORDERS_PAGE_SIZE = 6;

const getOrderStatusClasses = (status: string): string => {
    if (status === 'provisioned') {
        return 'billing-status billing-status-active';
    }

    if (status === 'provisioning') {
        return 'billing-status billing-status-provisioning';
    }

    if (status === 'paid' || status === 'queued_provision') {
        return 'billing-status billing-status-provisioning';
    }

    if (status === 'rejected' || status === 'failed' || status === 'provision_failed' || status === 'cancelled') {
        return 'billing-status billing-status-rejected';
    }

    if (status === 'refunded') {
        return 'billing-status billing-status-deleted';
    }

    return 'billing-status billing-status-suspended';
};

const getInvoiceStatusClasses = (status: string): string => {
    if (status === 'paid') {
        return 'billing-status billing-status-active';
    }

    if (status === 'open' || status === 'draft' || status === 'processing') {
        return 'billing-status billing-status-provisioning';
    }

    if (status === 'refunded' || status === 'partially_refunded') {
        return 'billing-status billing-status-suspended';
    }

    return 'billing-status billing-status-rejected';
};

const clampPage = (page: number, totalItems: number, pageSize: number): number => {
    const totalPages = Math.max(1, Math.ceil(totalItems / pageSize));

    return Math.min(Math.max(page, 1), totalPages);
};

const paginateItems = <T,>(items: T[], page: number, pageSize: number): T[] => {
    const startIndex = (page - 1) * pageSize;

    return items.slice(startIndex, startIndex + pageSize);
};

type BillingPaginationProps = {
    currentPage: number;
    onPageChange: (page: number) => void;
    pageSize: number;
    totalItems: number;
};

const BillingPagination = ({ currentPage, onPageChange, pageSize, totalItems }: BillingPaginationProps) => {
    const totalPages = Math.max(1, Math.ceil(totalItems / pageSize));
    const safePage = clampPage(currentPage, totalItems, pageSize);
    const start = totalItems < 1 ? 0 : (safePage - 1) * pageSize + 1;
    const end = totalItems < 1 ? 0 : Math.min(safePage * pageSize, totalItems);
    const pages = Array.from({ length: totalPages }, (_, index) => index + 1);

    return (
        <div className={'billing-pagination-top'}>
            <div className={'billing-pagination-bar'}>
                <p className={'billing-pagination-copy'}>
                    Showing <span className={'billing-pagination-value'}>{start}</span> to{' '}
                    <span className={'billing-pagination-value'}>{end}</span> of{' '}
                    <span className={'billing-pagination-value'}>{totalItems}</span> results.
                </p>
                <div className={'billing-pagination-actions'}>
                    <button
                        type={'button'}
                        className={'billing-page-btn'}
                        disabled={safePage <= 1}
                        onClick={() => onPageChange(1)}
                        aria-label={'Go to first page'}
                    >
                        <svg xmlns={'http://www.w3.org/2000/svg'} viewBox={'0 0 20 20'} fill={'currentColor'} className={'h-3 w-3'}>
                            <path
                                fillRule={'evenodd'}
                                d={'M15.707 15.707a1 1 0 01-1.414 0l-5-5a1 1 0 010-1.414l5-5a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 010 1.414zm-6 0a1 1 0 01-1.414 0l-5-5a1 1 0 010-1.414l5-5a1 1 0 011.414 1.414L5.414 10l4.293 4.293a1 1 0 010 1.414z'}
                                clipRule={'evenodd'}
                            />
                        </svg>
                    </button>
                    {pages.map((page) => (
                        <button
                            key={page}
                            type={'button'}
                            className={`billing-page-btn ${page === safePage ? 'billing-page-btn-active' : ''}`}
                            onClick={() => onPageChange(page)}
                        >
                            {page}
                        </button>
                    ))}
                    <button
                        type={'button'}
                        className={'billing-page-btn'}
                        disabled={safePage >= totalPages}
                        onClick={() => onPageChange(totalPages)}
                        aria-label={'Go to last page'}
                    >
                        <svg xmlns={'http://www.w3.org/2000/svg'} viewBox={'0 0 20 20'} fill={'currentColor'} className={'h-3 w-3'}>
                            <path
                                fillRule={'evenodd'}
                                d={'M10.293 15.707a1 1 0 010-1.414L14.586 10l-4.293-4.293a1 1 0 111.414-1.414l5 5a1 1 0 010 1.414l-5 5a1 1 0 01-1.414 0z'}
                                clipRule={'evenodd'}
                            />
                            <path
                                fillRule={'evenodd'}
                                d={'M4.293 15.707a1 1 0 010-1.414L8.586 10 4.293 5.707a1 1 0 011.414-1.414l5 5a1 1 0 010 1.414l-5 5a1 1 0 01-1.414 0z'}
                                clipRule={'evenodd'}
                            />
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    );
};

const launchHostedCheckout = (checkout: BillingCheckout | null): boolean => {
    if (!checkout || typeof document === 'undefined' || typeof window === 'undefined') {
        return false;
    }

    const method = (checkout.method || 'POST').toUpperCase();
    if (method === 'GET') {
        const url = new URL(checkout.url, window.location.origin);
        Object.entries(checkout.payload || {}).forEach(([key, value]) => {
            url.searchParams.set(key, value);
        });
        window.location.assign(url.toString());

        return true;
    }

    const form = document.createElement('form');
    form.method = method;
    form.action = checkout.url;
    form.style.display = 'none';

    Object.entries(checkout.payload || {}).forEach(([key, value]) => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = key;
        input.value = value;
        form.appendChild(input);
    });

    document.body.appendChild(form);
    form.submit();

    return true;
};

export default () => {
    const { addFlash } = useFlash();
    const { clearFlashes, clearAndAddHttpError, addError } = useFlashKey('billing');
    const {
        data: catalog,
        error: catalogError,
        isValidating: catalogLoading,
        mutate: mutateCatalog,
    } = useBillingCatalog();
    const { data: orders, error: ordersError, isValidating: ordersLoading, mutate: mutateOrders } = useBillingOrders();
    const {
        data: subscriptions,
        error: subscriptionsError,
        isValidating: subscriptionsLoading,
        mutate: mutateSubscriptions,
    } = useBillingSubscriptions();
    const {
        data: invoices,
        error: invoicesError,
        isValidating: invoicesLoading,
        mutate: mutateInvoices,
    } = useBillingInvoices();
    const {
        data: billingProfile,
        isValidating: billingProfileLoading,
        mutate: mutateBillingProfile,
    } = useBillingProfile();
    const rootAdmin = useStoreState((state: ApplicationStore) => !!state.user.data?.rootAdmin);

    const [selectedNodeId, setSelectedNodeId] = useState<number | null>(null);
    const [selectedNestId, setSelectedNestId] = useState<number | null>(null);
    const [selectedGameId, setSelectedGameId] = useState<number | null>(null);
    const [serverName, setServerName] = useState('');
    const [cpuCores, setCpuCores] = useState(1);
    const [memoryGb, setMemoryGb] = useState(1);
    const [diskGb, setDiskGb] = useState(10);
    const [variables, setVariables] = useState<Record<string, string>>({});
    const [submitting, setSubmitting] = useState(false);
    const [renewingSubscriptionId, setRenewingSubscriptionId] = useState<number | null>(null);
    const [upgradingSubscriptionId, setUpgradingSubscriptionId] = useState<number | null>(null);
    const [togglingSubscriptionId, setTogglingSubscriptionId] = useState<number | null>(null);
    const [retryingInvoiceId, setRetryingInvoiceId] = useState<number | null>(null);
    const [subscriptionsPage, setSubscriptionsPage] = useState(1);
    const [invoicesPage, setInvoicesPage] = useState(1);
    const [ordersPage, setOrdersPage] = useState(1);
    const [billingModalOpen, setBillingModalOpen] = useState(false);
    const [billingModalSaving, setBillingModalSaving] = useState(false);
    const [billingModalForm, setBillingModalForm] = useState<BillingProfile>(emptyBillingProfile);
    const [pendingBillingAction, setPendingBillingAction] = useState<PendingBillingAction | null>(null);

    useEffect(() => {
        clearFlashes();
    }, [clearFlashes]);

    useEffect(() => {
        if (catalogError) {
            clearAndAddHttpError(catalogError);
        }
    }, [catalogError, clearAndAddHttpError]);

    useEffect(() => {
        if (ordersError) {
            clearAndAddHttpError(ordersError);
        }
    }, [ordersError, clearAndAddHttpError]);

    useEffect(() => {
        if (subscriptionsError) {
            clearAndAddHttpError(subscriptionsError);
        }
    }, [subscriptionsError, clearAndAddHttpError]);

    useEffect(() => {
        if (invoicesError) {
            clearAndAddHttpError(invoicesError);
        }
    }, [invoicesError, clearAndAddHttpError]);

    useEffect(() => {
        if (billingProfile) {
            setBillingModalForm(billingProfile);
        }
    }, [billingProfile]);

    useEffect(() => {
        setSubscriptionsPage((current) =>
            clampPage(current, subscriptions?.length ?? 0, ACTIVE_SUBSCRIPTIONS_PAGE_SIZE)
        );
    }, [subscriptions?.length]);

    useEffect(() => {
        setInvoicesPage((current) => clampPage(current, invoices?.length ?? 0, INVOICES_PAGE_SIZE));
    }, [invoices?.length]);

    useEffect(() => {
        setOrdersPage((current) => clampPage(current, orders?.length ?? 0, ORDERS_PAGE_SIZE));
    }, [orders?.length]);

    useEffect(() => {
        if (!catalog || catalog.length < 1) {
            return;
        }

        if (selectedNodeId && catalog.some((node) => node.id === selectedNodeId)) {
            return;
        }

        const fallback = catalog.find((node) => node.availability.isAvailable) || catalog[0];
        setSelectedNodeId(fallback.id);
    }, [catalog, selectedNodeId]);

    const selectedNode = (catalog || []).find((node) => node.id === selectedNodeId) || null;
    const nestOptions = getNestOptions(selectedNode);
    const availableGames = (selectedNode?.games || []).filter(
        (game) => !selectedNestId || game.nestId === selectedNestId
    );
    const selectedGame = availableGames.find((game) => game.id === selectedGameId) || null;

    useEffect(() => {
        if (!selectedNode) {
            setSelectedNestId(null);
            return;
        }

        if (selectedNestId && nestOptions.some((nest) => nest.id === selectedNestId)) {
            return;
        }

        setSelectedNestId(nestOptions[0]?.id ?? null);
    }, [selectedNode, selectedNestId, nestOptions]);

    useEffect(() => {
        if (availableGames.length < 1) {
            setSelectedGameId(null);
            return;
        }

        if (selectedGameId && availableGames.some((game) => game.id === selectedGameId)) {
            return;
        }

        setSelectedGameId(availableGames[0].id);
    }, [availableGames, selectedGameId]);

    useEffect(() => {
        if (!selectedGame) {
            setVariables({});
            return;
        }

        const nextVariables: Record<string, string> = {};
        selectedGame.variables.forEach((variable) => {
            nextVariables[variable.envVariable] = variable.serverValue ?? variable.defaultValue ?? '';
        });

        setVariables(nextVariables);
        setServerName((current) => (current.trim().length > 0 ? current : `${selectedGame.displayName} Server`));
    }, [selectedGame?.id, selectedNode?.id]);

    useEffect(() => {
        const maxCpu = selectedNode?.limits.maxCpu ?? 0;
        const maxMemory = selectedNode?.limits.maxMemoryGb ?? 0;
        const diskStep = selectedNode?.limits.diskStepGb ?? 10;
        const maxDisk = selectedNode?.limits.maxDiskGb ?? 0;

        const nextCpuMin = maxCpu > 0 ? 1 : 0;
        const nextMemoryMin = maxMemory > 0 ? 1 : 0;
        const nextDiskMin = maxDisk >= diskStep ? diskStep : 0;

        setCpuCores((current) => clamp(current, nextCpuMin, maxCpu));
        setMemoryGb((current) => clamp(current, nextMemoryMin, maxMemory));
        setDiskGb((current) => clamp(current, nextDiskMin, maxDisk));
    }, [
        selectedNode?.id,
        selectedNode?.limits.maxCpu,
        selectedNode?.limits.maxMemoryGb,
        selectedNode?.limits.maxDiskGb,
        selectedNode?.limits.diskStepGb,
    ]);

    const diskUnits = Math.ceil(Math.max(diskGb, 0) / 10);
    const cpuTotal = selectedNode ? Number((cpuCores * selectedNode.pricing.perVcore).toFixed(2)) : 0;
    const memoryTotal = selectedNode ? Number((memoryGb * selectedNode.pricing.perGbRam).toFixed(2)) : 0;
    const diskTotal = selectedNode ? Number((diskUnits * selectedNode.pricing.per10gbDisk).toFixed(2)) : 0;
    const total = Number((cpuTotal + memoryTotal + diskTotal).toFixed(2));

    const soldOutReason = (() => {
        if (!selectedNode) {
            return null;
        }

        if (selectedNode.games.length < 1) {
            return 'This node does not have any nests enabled yet.';
        }

        if (selectedNode.availability.freeAllocations < 1) {
            return 'This node has no free allocations available right now.';
        }

        if (
            selectedNode.availability.memoryRemainingGb < 1 ||
            selectedNode.availability.diskRemainingGb < selectedNode.limits.diskStepGb
        ) {
            return 'This node is sold out because RAM or storage has reached its billing limit.';
        }

        if (!selectedNode.availability.isAvailable) {
            return 'This node is currently unavailable for new billing orders.';
        }

        return null;
    })();

    const getCurrentBillingProfile = (): BillingProfile => normalizeBillingProfile(billingProfile || emptyBillingProfile);

    const setBillingModalField = <K extends keyof BillingProfile>(field: K, value: BillingProfile[K]) => {
        setBillingModalForm((state) => ({ ...state, [field]: value }));
    };

    const closeBillingModal = () => {
        setBillingModalOpen(false);
        setPendingBillingAction(null);
    };

    const requireCompleteBillingProfile = (action: PendingBillingAction): boolean => {
        if (billingProfileLoading && !billingProfile) {
            addError('Billing profile is still loading. Please wait a moment and try again.', 'Billing');
            return true;
        }

        const profile = getCurrentBillingProfile();
        const missingFields = getMissingBillingProfileFields(profile);
        if (missingFields.length < 1) {
            return false;
        }

        clearFlashes();
        addError(`Complete your billing profile first: ${formatMissingBillingFields(missingFields)}.`, 'Billing');
        setBillingModalForm(profile);
        setPendingBillingAction(action);
        setBillingModalOpen(true);

        return true;
    };

    const performCreateInvoice = async () => {
        if (!selectedNode || !selectedGame) {
            return;
        }

        setSubmitting(true);

        try {
            const response = await createBillingOrder({
                billingNodeConfigId: selectedNode.id,
                billingGameProfileId: selectedGame.id,
                serverName: serverName.trim(),
                cpuCores,
                memoryGb,
                diskGb,
                variables,
            });

            addFlash({
                key: 'billing',
                type: 'success',
                title: response.autoSettled
                    ? 'Provisioning Started'
                    : response.checkout
                    ? 'Invoice Created'
                    : 'Order Created',
                message: response.autoSettled
                    ? `Invoice ${response.invoice?.invoiceNumber ?? '#'} required no payment and was settled automatically. Provisioning has started.`
                    : response.checkout
                    ? `Invoice ${response.invoice?.invoiceNumber ?? '#'} has been created. Redirecting to checkout now.`
                    : response.checkoutError
                    ? `Invoice ${response.invoice?.invoiceNumber ?? '#'} was created, but checkout is not ready yet: ${response.checkoutError}`
                    : `Invoice ${response.invoice?.invoiceNumber ?? '#'} was created successfully. Complete payment to continue provisioning.`,
            });

            void mutateCatalog();
            void mutateOrders();
            void mutateInvoices();

            if (launchHostedCheckout(response.checkout)) {
                return;
            }
        } catch (error) {
            clearAndAddHttpError(error as Error);
        } finally {
            setSubmitting(false);
        }
    };

    const performRenewSubscription = async (subscriptionId: number) => {
        clearFlashes();
        setRenewingSubscriptionId(subscriptionId);

        try {
            const response = await renewBillingSubscription(subscriptionId);
            addFlash({
                key: 'billing',
                type: 'success',
                title: response.autoSettled
                    ? 'Renewal Applied'
                    : response.checkout
                    ? 'Renewal Invoice Created'
                    : 'Renewal Pending Payment',
                message: response.autoSettled
                    ? `${response.subscription.serverName} renewal required no payment and was applied immediately.`
                    : response.checkout
                    ? `${response.subscription.serverName} renewal invoice ${response.invoice?.invoiceNumber ?? '#'} is ready. Redirecting to checkout now.`
                    : response.checkoutError
                    ? `${response.subscription.serverName} renewal invoice ${response.invoice?.invoiceNumber ?? '#'} was created, but checkout is unavailable: ${response.checkoutError}`
                    : `${response.subscription.serverName} renewal invoice ${response.invoice?.invoiceNumber ?? '#'} was created. Complete payment to renew the server.`,
            });

            void mutateSubscriptions();
            void mutateInvoices();

            if (launchHostedCheckout(response.checkout)) {
                return;
            }
        } catch (error) {
            clearAndAddHttpError(error as Error);
        } finally {
            setRenewingSubscriptionId(null);
        }
    };

    const performUpgradeSubscription = async (subscriptionId: number, payload: UpgradePayload) => {
        clearFlashes();
        setUpgradingSubscriptionId(subscriptionId);
        const currentSubscription = subscriptions?.find((item) => item.id === subscriptionId) || null;

        try {
            const response = await upgradeBillingSubscription({
                id: subscriptionId,
                cpuCores: payload.cpuCores,
                memoryGb: payload.memoryGb,
                diskGb: payload.diskGb,
            });
            const additionalCharge = Number(
                Math.max(response.subscription.recurringTotal - (currentSubscription?.recurringTotal ?? 0), 0).toFixed(2)
            );

            addFlash({
                key: 'billing',
                type: 'success',
                title: response.autoSettled
                    ? 'Upgrade Applied'
                    : response.checkout
                    ? 'Upgrade Invoice Created'
                    : 'Upgrade Pending Payment',
                message: response.autoSettled
                    ? `${response.subscription.serverName} upgrade required no payment and was applied immediately.`
                    : response.checkout
                    ? `${response.subscription.serverName} upgrade invoice ${response.invoice?.invoiceNumber ?? '#'} is ready. Redirecting to checkout now.`
                    : response.checkoutError
                    ? `${response.subscription.serverName} upgrade invoice ${response.invoice?.invoiceNumber ?? '#'} was created, but checkout is unavailable: ${response.checkoutError}`
                    : `${response.subscription.serverName} upgrade invoice ${response.invoice?.invoiceNumber ?? '#'} was created. Prorated amount due now: ${formatMoney(
                          additionalCharge
                      )}.`,
            });

            void mutateSubscriptions();
            void mutateCatalog();
            void mutateInvoices();

            if (launchHostedCheckout(response.checkout)) {
                return;
            }
        } catch (error) {
            clearAndAddHttpError(error as Error);
        } finally {
            setUpgradingSubscriptionId(null);
        }
    };

    const onToggleAutoRenew = async (subscriptionId: number, enabled: boolean) => {
        clearFlashes();
        setTogglingSubscriptionId(subscriptionId);

        try {
            const subscription = await toggleBillingSubscriptionAutoRenew(subscriptionId, enabled);
            addFlash({
                key: 'billing',
                type: 'success',
                title: enabled ? 'Auto Renew Enabled' : 'Auto Renew Disabled',
                message: enabled
                    ? `${subscription.serverName} will now try automatic renewal when a stored payment token is available.`
                    : `${subscription.serverName} will no longer attempt automatic renewal.`,
            });

            void mutateSubscriptions();
        } catch (error) {
            clearAndAddHttpError(error as Error);
        } finally {
            setTogglingSubscriptionId(null);
        }
    };

    const onRetryInvoicePayment = async (invoiceId: number) => {
        clearFlashes();
        setRetryingInvoiceId(invoiceId);

        try {
            const checkout = await retryBillingInvoicePayment(invoiceId);
            addFlash({
                key: 'billing',
                type: 'success',
                title: 'Retrying Payment',
                message: 'Redirecting back to the hosted Fiuu checkout now.',
            });

            if (launchHostedCheckout(checkout)) {
                return;
            }
        } catch (error) {
            clearAndAddHttpError(error as Error);
        } finally {
            setRetryingInvoiceId(null);
        }
    };

    const runPendingBillingAction = async (action: PendingBillingAction) => {
        switch (action.type) {
            case 'create':
                await performCreateInvoice();
                break;
            case 'renew':
                await performRenewSubscription(action.subscriptionId);
                break;
            case 'upgrade':
                await performUpgradeSubscription(action.subscriptionId, action.payload);
                break;
        }
    };

    const submit = async (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        clearFlashes();

        if (!selectedNode) {
            addError('Choose a billing node first.', 'Billing');
            return;
        }

        if (!selectedGame) {
            addError('Choose a game first.', 'Billing');
            return;
        }

        if (!serverName.trim()) {
            addError('Enter a server name before placing the order.', 'Billing');
            return;
        }

        if (!selectedNode.availability.isAvailable) {
            addError(soldOutReason || 'This billing node is not available right now.', 'Billing');
            return;
        }

        if (requireCompleteBillingProfile({ type: 'create' })) {
            return;
        }

        await performCreateInvoice();
    };

    const onRenewSubscription = async (subscriptionId: number) => {
        if (requireCompleteBillingProfile({ type: 'renew', subscriptionId })) {
            return;
        }

        await performRenewSubscription(subscriptionId);
    };

    const onUpgradeSubscription = async (subscriptionId: number, payload: UpgradePayload) => {
        if (requireCompleteBillingProfile({ type: 'upgrade', subscriptionId, payload })) {
            return;
        }

        await performUpgradeSubscription(subscriptionId, payload);
    };

    const onBillingModalSubmit = async (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        clearFlashes();

        const payload = normalizeBillingProfile(billingModalForm);
        const missingFields = getMissingBillingProfileFields(payload);
        if (missingFields.length > 0) {
            addError(`Fill all required billing fields first: ${formatMissingBillingFields(missingFields)}.`, 'Billing');
            return;
        }

        setBillingModalSaving(true);

        try {
            const updated = await updateBillingProfile(payload);
            await mutateBillingProfile(updated, false);
            setBillingModalForm(updated);

            const nextAction = pendingBillingAction;
            setBillingModalOpen(false);
            setPendingBillingAction(null);

            if (nextAction) {
                await runPendingBillingAction(nextAction);
            }
        } catch (error) {
            clearAndAddHttpError(error as Error);
        } finally {
            setBillingModalSaving(false);
        }
    };

    const renderBillingModalLabel = (label: string, required = false) => (
        <span className={'mb-2 block text-[10px] font-bold uppercase tracking-[0.18em] text-gray-500'}>
            {label}
            {required && <span className={'ml-1 text-[color:var(--primary)]'}>*</span>}
        </span>
    );

    if (!catalog && catalogLoading) {
        return (
            <div
                className={
                    'min-h-screen bg-[radial-gradient(circle_at_8%_0%,rgba(var(--primary-rgb),0.18),transparent_40%),radial-gradient(circle_at_94%_100%,rgba(84,140,255,0.2),transparent_44%),linear-gradient(180deg,rgba(4,7,12,0.98),rgba(1,2,5,1))] px-6 py-8 text-[color:var(--foreground)] md:px-10'
                }
            >
                <Spinner centered size={Spinner.Size.LARGE} />
            </div>
        );
    }

    return (
        <div className={'billing-shell min-h-screen px-4 pb-8 pt-6 text-white md:px-8 md:pt-8'}>
            <style>{`
                .billing-shell {
                    position: relative;
                    overflow: hidden;
                    background:
                        radial-gradient(circle at 8% 0%, rgba(var(--primary-rgb), 0.18), transparent 40%),
                        radial-gradient(circle at 94% 100%, rgba(84, 140, 255, 0.2), transparent 44%),
                        linear-gradient(180deg, rgba(4, 7, 12, 0.98), rgba(1, 2, 5, 1));
                    font-family: var(--font-sans, 'Inter', sans-serif);
                }

                .billing-shell::before {
                    content: '';
                    position: absolute;
                    inset: 0;
                    pointer-events: none;
                    background:
                        repeating-linear-gradient(
                            90deg,
                            rgba(255, 255, 255, 0.014) 0,
                            rgba(255, 255, 255, 0.014) 1px,
                            transparent 1px,
                            transparent 40px
                        );
                    opacity: 0.2;
                }

                .billing-shell::after {
                    content: '';
                    position: absolute;
                    left: 50%;
                    top: -18%;
                    width: min(1120px, 96vw);
                    height: 110%;
                    transform: translateX(-50%);
                    pointer-events: none;
                    border-radius: 999px;
                    background: radial-gradient(
                        ellipse at center,
                        rgba(112, 168, 255, 0.08) 0%,
                        rgba(112, 168, 255, 0.03) 42%,
                        transparent 72%
                    );
                }

                .billing-wrap {
                    position: relative;
                    z-index: 2;
                    width: 100%;
                }

                .billing-hero {
                    margin-bottom: 18px;
                    border-radius: 24px;
                    border: 1px solid rgba(255, 255, 255, 0.09);
                    background:
                        linear-gradient(160deg, rgba(255, 255, 255, 0.045), rgba(255, 255, 255, 0.012) 46%),
                        rgba(4, 8, 14, 0.76);
                    box-shadow:
                        inset 0 1px 0 rgba(255, 255, 255, 0.08),
                        0 30px 46px -32px rgba(0, 0, 0, 0.82),
                        0 0 56px rgba(var(--primary-rgb), 0.1);
                    padding: 18px 20px;
                    backdrop-filter: blur(8px);
                }

                .billing-hero-pill-row {
                    display: flex;
                    flex-wrap: wrap;
                    align-items: center;
                    justify-content: space-between;
                    gap: 10px;
                    margin-bottom: 12px;
                }

                .billing-hero-pill {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    min-height: 34px;
                    border-radius: 999px;
                    border: 1px solid rgba(var(--primary-rgb), 0.52);
                    background: linear-gradient(120deg, rgba(var(--primary-rgb), 0.36), rgba(var(--primary-rgb), 0.14));
                    color: rgba(230, 252, 180, 0.95);
                    font-size: 0.7rem;
                    font-weight: 800;
                    letter-spacing: 0.2em;
                    text-transform: uppercase;
                    padding: 0 14px;
                    text-shadow: 0 0 10px rgba(var(--primary-rgb), 0.58);
                }

                .billing-hero-route {
                    color: rgba(248, 246, 239, 0.56);
                    font-size: 0.7rem;
                    letter-spacing: 0.18em;
                    text-transform: uppercase;
                    font-weight: 700;
                }

                .billing-hero-title {
                    margin: 0;
                    font-size: clamp(1.4rem, 3.2vw, 2rem);
                    line-height: 1.04;
                    letter-spacing: 0.02em;
                    text-transform: uppercase;
                    font-weight: 900;
                    color: rgba(248, 246, 239, 0.97);
                    text-shadow: 0 0 18px rgba(248, 246, 239, 0.19);
                }

                .billing-hero-copy {
                    margin-top: 8px;
                    max-width: 70ch;
                    font-size: 0.82rem;
                    color: rgba(174, 183, 194, 0.82);
                    letter-spacing: 0.03em;
                    line-height: 1.7;
                }

                .billing-panel {
                    border-radius: 22px;
                    border: 1px solid rgba(255, 255, 255, 0.09);
                    background:
                        linear-gradient(170deg, rgba(255, 255, 255, 0.04), rgba(255, 255, 255, 0.012) 50%),
                        rgba(4, 8, 14, 0.78);
                    box-shadow:
                        inset 0 1px 0 rgba(255, 255, 255, 0.06),
                        0 24px 38px -32px rgba(0, 0, 0, 0.86);
                }

                .billing-status {
                    border-radius: 999px;
                    border-width: 1px;
                }

                .billing-status-active {
                    border-color: rgba(16, 185, 129, 0.35);
                    background: rgba(16, 185, 129, 0.15);
                    color: rgb(167 243 208);
                }

                .billing-status-provisioning {
                    border-color: rgba(56, 189, 248, 0.35);
                    background: rgba(56, 189, 248, 0.13);
                    color: rgb(186 230 253);
                }

                .billing-status-suspended {
                    border-color: rgba(245, 158, 11, 0.35);
                    background: rgba(245, 158, 11, 0.13);
                    color: rgb(254 215 170);
                }

                .billing-status-rejected {
                    border-color: rgba(239, 68, 68, 0.35);
                    background: rgba(239, 68, 68, 0.14);
                    color: rgb(252 165 165);
                }

                .billing-status-deleted {
                    border-color: rgba(115, 115, 115, 0.35);
                    background: rgba(115, 115, 115, 0.14);
                    color: rgb(212 212 212);
                }

                .billing-primary-btn,
                .billing-secondary-btn,
                .billing-ghost-btn {
                    display: inline-flex;
                    min-width: 11rem;
                    align-items: center;
                    justify-content: center;
                    border-radius: 999px;
                    border: 1px solid transparent;
                    padding: 0.7rem 1.25rem;
                    font-size: 11px;
                    font-weight: 900;
                    letter-spacing: 0.22em;
                    text-transform: uppercase;
                    transition: all 0.2s ease;
                }

                .billing-primary-btn {
                    border-color: rgba(var(--primary-rgb), 0.4);
                    background: linear-gradient(100deg, rgba(var(--primary-rgb), 0.95), rgba(var(--primary-rgb), 0.74));
                    color: rgb(10 13 16);
                    box-shadow: 0 0 26px rgba(var(--primary-rgb), 0.24);
                }

                .billing-primary-btn:hover:not(:disabled) {
                    transform: translateY(-1px);
                    filter: brightness(1.05);
                    box-shadow: 0 0 32px rgba(var(--primary-rgb), 0.32);
                }

                .billing-secondary-btn {
                    border-color: rgba(255, 255, 255, 0.1);
                    background: rgba(255, 255, 255, 0.03);
                    color: rgba(248, 246, 239, 0.92);
                }

                .billing-secondary-btn:hover:not(:disabled) {
                    border-color: rgba(var(--primary-rgb), 0.34);
                    background: linear-gradient(90deg, rgba(var(--primary-rgb), 0.24), rgba(var(--primary-rgb), 0.08));
                    color: #eff7dc;
                }

                .billing-ghost-btn {
                    border-color: rgba(255, 255, 255, 0.12);
                    background: transparent;
                    color: rgba(174, 183, 194, 0.9);
                }

                .billing-ghost-btn:hover:not(:disabled) {
                    border-color: rgba(var(--primary-rgb), 0.34);
                    color: rgba(248, 246, 239, 0.95);
                    background: rgba(var(--primary-rgb), 0.08);
                }

                .billing-primary-btn:disabled,
                .billing-secondary-btn:disabled,
                .billing-ghost-btn:disabled {
                    cursor: not-allowed;
                    opacity: 0.5;
                }

                .billing-slider-card {
                    border-radius: 18px;
                    border: 1px solid rgba(255, 255, 255, 0.08);
                    background: linear-gradient(165deg, rgba(255, 255, 255, 0.03), rgba(255, 255, 255, 0.01) 50%),
                        rgba(4, 8, 14, 0.72);
                    padding: 16px;
                }

                .billing-slider-value {
                    display: inline-flex;
                    min-height: 46px;
                    min-width: 56px;
                    align-items: center;
                    justify-content: center;
                    border-radius: 999px;
                    border: 1px solid rgba(var(--primary-rgb), 0.44);
                    background: rgba(var(--primary-rgb), 0.16);
                    padding: 0 10px;
                    font-size: 0.9rem;
                    font-weight: 900;
                    color: var(--primary);
                }

                .billing-subscription-card {
                    border-radius: 20px;
                    border: 1px solid rgba(255, 255, 255, 0.09);
                    background:
                        linear-gradient(170deg, rgba(255, 255, 255, 0.04), rgba(255, 255, 255, 0.012) 54%),
                        rgba(4, 8, 14, 0.76);
                    padding: 1.25rem;
                    box-shadow:
                        inset 0 1px 0 rgba(255, 255, 255, 0.06),
                        0 20px 34px -28px rgba(0, 0, 0, 0.85);
                }

                .billing-subscription-title {
                    font-size: 1.45rem;
                    font-weight: 900;
                    letter-spacing: 0.01em;
                    color: #f8f6ef;
                }

                .billing-upgrade-panel {
                    margin-top: 1.25rem;
                    border-radius: 18px;
                    border: 1px solid rgba(255, 255, 255, 0.08);
                    background: rgba(255, 255, 255, 0.02);
                    padding: 1.25rem;
                }

                .billing-upgrade-summary {
                    margin-top: 1.25rem;
                    border-radius: 14px;
                    border: 1px solid rgba(255, 255, 255, 0.08);
                    background: rgba(3, 7, 12, 0.74);
                    padding: 1rem;
                }

                .billing-variable-card {
                    border-radius: 14px;
                    border: 1px solid rgba(255, 255, 255, 0.09);
                    background: rgba(4, 8, 14, 0.72);
                    overflow: hidden;
                }

                .billing-variable-head {
                    border-bottom: 1px solid rgba(255, 255, 255, 0.07);
                    background: rgba(255, 255, 255, 0.03);
                    padding: 0.65rem 0.8rem;
                }

                .billing-variable-title {
                    display: flex;
                    align-items: center;
                    gap: 0.45rem;
                    font-size: 0.78rem;
                    letter-spacing: 0.08em;
                    text-transform: uppercase;
                    font-weight: 800;
                    color: #f8f6ef;
                }

                .billing-variable-badge {
                    border-radius: 999px;
                    border: 1px solid rgba(255, 255, 255, 0.15);
                    background: rgba(255, 255, 255, 0.09);
                    padding: 0.14rem 0.52rem;
                    font-size: 0.6rem;
                    font-weight: 800;
                    letter-spacing: 0.07em;
                }

                .billing-variable-body {
                    padding: 0.85rem;
                    color: #f8f6ef;
                }

                .billing-chip {
                    border-radius: 999px;
                    border: 1px solid rgba(255, 255, 255, 0.1);
                    background: rgba(255, 255, 255, 0.035);
                    padding: 0.5rem 0.9rem;
                    font-size: 11px;
                    font-weight: 800;
                    letter-spacing: 0.18em;
                    text-transform: uppercase;
                    color: rgba(174, 183, 194, 0.82);
                }

                .billing-soft-card {
                    border-radius: 14px;
                    border: 1px solid rgba(255, 255, 255, 0.08);
                    background: rgba(3, 7, 12, 0.72);
                    padding: 0.95rem;
                }

                .billing-empty-card {
                    border-radius: 16px;
                    border: 1px dashed rgba(255, 255, 255, 0.2);
                    background: rgba(3, 7, 12, 0.56);
                    padding: 1.5rem 1rem;
                    text-align: center;
                    font-size: 0.88rem;
                    color: rgba(174, 183, 194, 0.82);
                }

                .billing-order-card {
                    border-radius: 18px;
                    border: 1px solid rgba(255, 255, 255, 0.09);
                    background:
                        linear-gradient(170deg, rgba(255, 255, 255, 0.03), rgba(255, 255, 255, 0.01) 54%),
                        rgba(4, 8, 14, 0.72);
                    padding: 1.1rem;
                }

                .billing-pagination-top {
                    width: 100%;
                }

                .billing-pagination-bar {
                    display: flex;
                    flex-wrap: wrap;
                    align-items: center;
                    justify-content: space-between;
                    gap: 0.9rem;
                    margin: 0.5rem 0 0;
                }

                .billing-pagination-copy {
                    font-size: 0.875rem;
                    color: rgb(163 163 163);
                }

                .billing-pagination-value {
                    font-weight: 700;
                    color: #f8f6ef;
                }

                .billing-pagination-actions {
                    display: flex;
                    align-items: center;
                    gap: 0.25rem;
                }

                .billing-page-btn {
                    display: inline-flex;
                    min-width: 2.2rem;
                    height: 2.2rem;
                    align-items: center;
                    justify-content: center;
                    border-radius: 0.85rem;
                    border: 1px solid rgba(255, 255, 255, 0.12);
                    background: rgba(255, 255, 255, 0.03);
                    color: rgba(248, 246, 239, 0.86);
                    font-size: 0.82rem;
                    font-weight: 800;
                    transition: all 0.2s ease;
                }

                .billing-page-btn:hover:not(:disabled) {
                    border-color: rgba(var(--primary-rgb), 0.34);
                    background: rgba(var(--primary-rgb), 0.12);
                    color: #f8f6ef;
                }

                .billing-page-btn:disabled {
                    cursor: not-allowed;
                    opacity: 0.45;
                }

                .billing-page-btn-active {
                    border-color: rgba(var(--primary-rgb), 0.44);
                    background: linear-gradient(100deg, rgba(var(--primary-rgb), 0.95), rgba(var(--primary-rgb), 0.74));
                    color: rgb(10 13 16);
                    box-shadow: 0 0 20px rgba(var(--primary-rgb), 0.24);
                }

                @media (max-width: 640px) {
                    .billing-hero {
                        border-radius: 18px;
                        padding: 14px 14px 12px;
                    }

                    .billing-hero-pill-row {
                        margin-bottom: 10px;
                    }

                    .billing-pagination-bar {
                        align-items: flex-start;
                    }

                    .billing-pagination-actions {
                        flex-wrap: wrap;
                    }
                }
            `}</style>
            <div className={'billing-wrap'}>
                <FlashMessageRender byKey={'billing'} />
                <Dialog
                    open={billingModalOpen}
                    onClose={closeBillingModal}
                    title={'Complete Billing Profile'}
                    description={'Fill the required billing details below before checkout can continue.'}
                    panelClassName={
                        '!max-w-3xl !rounded-[1.5rem] !border !border-[color:var(--border)] !bg-[color:var(--card)] !shadow-[0_24px_48px_rgba(0,0,0,0.55)]'
                    }
                    contentClassName={'max-h-[75vh] overflow-y-auto'}
                    preventExternalClose={billingModalSaving}
                    hideCloseIcon={billingModalSaving}
                >
                    <form className={'space-y-5 pt-4'} onSubmit={onBillingModalSubmit}>
                        <div
                            className={
                                'rounded-xl border border-[rgba(var(--primary-rgb),0.22)] bg-[rgba(var(--primary-rgb),0.08)] px-4 py-3 text-xs leading-6 text-[rgba(248,246,239,0.82)]'
                            }
                        >
                            Fields marked with <span className={'font-black text-[color:var(--primary)]'}>*</span> are
                            required before any invoice can be created or sent to Fiuu checkout.
                        </div>

                        <div className={'grid grid-cols-1 gap-4 md:grid-cols-2'}>
                            <label className={'block'}>
                                {renderBillingModalLabel('Legal Name', true)}
                                <input
                                    className={billingModalInputClass}
                                    value={billingModalForm.legalName}
                                    onChange={(event) => setBillingModalField('legalName', event.currentTarget.value)}
                                    maxLength={191}
                                    required
                                />
                            </label>
                            <label className={'block'}>
                                {renderBillingModalLabel('Company Name')}
                                <input
                                    className={billingModalInputClass}
                                    value={billingModalForm.companyName ?? ''}
                                    onChange={(event) =>
                                        setBillingModalField('companyName', event.currentTarget.value || null)
                                    }
                                    maxLength={191}
                                />
                            </label>
                            <label className={'block'}>
                                {renderBillingModalLabel('Invoice Email', true)}
                                <input
                                    type={'email'}
                                    className={billingModalInputClass}
                                    value={billingModalForm.email}
                                    onChange={(event) => setBillingModalField('email', event.currentTarget.value)}
                                    maxLength={191}
                                    required
                                />
                            </label>
                            <label className={'block'}>
                                {renderBillingModalLabel('Phone', true)}
                                <input
                                    className={billingModalInputClass}
                                    value={billingModalForm.phone ?? ''}
                                    onChange={(event) => setBillingModalField('phone', event.currentTarget.value || null)}
                                    maxLength={32}
                                    required
                                />
                            </label>
                        </div>

                        <div className={'grid grid-cols-1 gap-4 md:grid-cols-2'}>
                            <label className={'block md:col-span-2'}>
                                {renderBillingModalLabel('Address Line 1', true)}
                                <input
                                    className={billingModalInputClass}
                                    value={billingModalForm.addressLine1 ?? ''}
                                    onChange={(event) =>
                                        setBillingModalField('addressLine1', event.currentTarget.value || null)
                                    }
                                    maxLength={191}
                                    required
                                />
                            </label>
                            <label className={'block md:col-span-2'}>
                                {renderBillingModalLabel('Address Line 2')}
                                <input
                                    className={billingModalInputClass}
                                    value={billingModalForm.addressLine2 ?? ''}
                                    onChange={(event) =>
                                        setBillingModalField('addressLine2', event.currentTarget.value || null)
                                    }
                                    maxLength={191}
                                />
                            </label>
                            <label className={'block'}>
                                {renderBillingModalLabel('City', true)}
                                <input
                                    className={billingModalInputClass}
                                    value={billingModalForm.city ?? ''}
                                    onChange={(event) => setBillingModalField('city', event.currentTarget.value || null)}
                                    maxLength={191}
                                    required
                                />
                            </label>
                            <label className={'block'}>
                                {renderBillingModalLabel('State')}
                                <input
                                    className={billingModalInputClass}
                                    value={billingModalForm.state ?? ''}
                                    onChange={(event) => setBillingModalField('state', event.currentTarget.value || null)}
                                    maxLength={191}
                                />
                            </label>
                            <label className={'block'}>
                                {renderBillingModalLabel('Postcode', true)}
                                <input
                                    className={billingModalInputClass}
                                    value={billingModalForm.postcode ?? ''}
                                    onChange={(event) =>
                                        setBillingModalField('postcode', event.currentTarget.value || null)
                                    }
                                    maxLength={32}
                                    required
                                />
                            </label>
                            <label className={'block'}>
                                {renderBillingModalLabel('Country Code', true)}
                                <input
                                    className={billingModalInputClass}
                                    value={billingModalForm.countryCode}
                                    onChange={(event) =>
                                        setBillingModalField('countryCode', event.currentTarget.value.toUpperCase())
                                    }
                                    maxLength={2}
                                    required
                                />
                            </label>
                        </div>

                        <div className={'grid grid-cols-1 gap-4 md:grid-cols-[1fr_auto]'}>
                            <label className={'block'}>
                                {renderBillingModalLabel('Tax ID')}
                                <input
                                    className={billingModalInputClass}
                                    value={billingModalForm.taxId ?? ''}
                                    onChange={(event) => setBillingModalField('taxId', event.currentTarget.value || null)}
                                    maxLength={191}
                                />
                            </label>
                            <label
                                className={
                                    'flex items-center gap-3 rounded-xl border border-[color:var(--border)] bg-[rgba(255,255,255,0.03)] px-4 py-3 text-sm text-gray-200'
                                }
                            >
                                <input
                                    type={'checkbox'}
                                    className={'h-4 w-4 rounded border-[color:var(--border)] bg-transparent'}
                                    checked={billingModalForm.isBusiness}
                                    onChange={(event) => setBillingModalField('isBusiness', event.currentTarget.checked)}
                                />
                                <span>Business billing entity</span>
                            </label>
                        </div>

                        <div className={'flex flex-wrap items-center justify-end gap-3 border-t border-[color:var(--border)] pt-5'}>
                            <button
                                type={'button'}
                                className={'billing-ghost-btn min-w-[10rem]'}
                                onClick={closeBillingModal}
                                disabled={billingModalSaving}
                            >
                                Cancel
                            </button>
                            <button type={'submit'} className={'billing-primary-btn min-w-[13rem]'} disabled={billingModalSaving}>
                                {billingModalSaving ? 'Saving...' : 'Save & Continue'}
                            </button>
                        </div>
                    </form>
                </Dialog>

                <div className={'billing-hero'}>
                    <div className={'billing-hero-pill-row'}>
                        <span className={'billing-hero-pill'}>Secure billing panel</span>
                        <span className={'billing-hero-route'}>Route /billing</span>
                    </div>
                    <h1 className={'billing-hero-title'}>Billing</h1>
                    <p className={'billing-hero-copy'}>
                        Build a server plan from the node stock that is currently available. vCore is only a per-order
                        limit, while RAM and storage determine whether a node is sold out.
                    </p>
                </div>

                {!catalog || catalog.length < 1 ? (
                    <section className={'billing-panel p-8'}>
                        <h2 className={'text-2xl font-black tracking-tight text-[#f8f6ef]'}>No Billing Nodes</h2>
                        <p className={'mt-4 text-sm leading-7 text-[color:var(--muted-foreground)]'}>
                            Billing has not been enabled on any node yet. Ask an administrator to finish the node setup
                            first.
                        </p>
                    </section>
                ) : (
                    <div className={'grid gap-6 xl:grid-cols-[minmax(0,1.35fr)_minmax(360px,420px)] xl:items-start'}>
                        <form onSubmit={submit} className={'billing-panel p-6 md:p-8'}>
                            <div className={'grid gap-6 lg:grid-cols-2'}>
                                <div>
                                    <label
                                        className={
                                            'mb-2 block text-xs font-bold uppercase tracking-[0.24em] text-[color:var(--muted-foreground)]'
                                        }
                                    >
                                        Choose A Node
                                    </label>
                                    <Select
                                        value={selectedNode?.id ?? ''}
                                        onChange={(event) => setSelectedNodeId(parseInt(event.currentTarget.value, 10))}
                                    >
                                        {(catalog || []).map((node) => (
                                            <option key={node.id} value={node.id}>
                                                {node.displayName}
                                            </option>
                                        ))}
                                    </Select>
                                    <p className={'mt-2 text-xs text-[color:var(--muted-foreground)]'}>
                                        {selectedNode?.description ||
                                            'Choose the node that matches the region and billing stock you want to use.'}
                                    </p>
                                </div>

                                <div>
                                    <label
                                        className={
                                            'mb-2 block text-xs font-bold uppercase tracking-[0.24em] text-[color:var(--muted-foreground)]'
                                        }
                                    >
                                        Server Name
                                    </label>
                                    <Input
                                        value={serverName}
                                        onChange={(event) => setServerName(event.currentTarget.value)}
                                        placeholder={'My Billing Server'}
                                        maxLength={191}
                                    />
                                    <p className={'mt-2 text-xs text-[color:var(--muted-foreground)]'}>
                                        This name will be used when the order is approved and provisioned.
                                    </p>
                                </div>
                            </div>

                            <div className={'mt-8 grid gap-6 lg:grid-cols-2'}>
                                <div>
                                    <label
                                        className={
                                            'mb-2 block text-xs font-bold uppercase tracking-[0.24em] text-[color:var(--muted-foreground)]'
                                        }
                                    >
                                        Choose A Nest
                                    </label>
                                    <Select
                                        value={selectedNestId ?? ''}
                                        onChange={(event) => setSelectedNestId(parseInt(event.currentTarget.value, 10))}
                                        disabled={!selectedNode || nestOptions.length < 1}
                                    >
                                        {nestOptions.map((nest) => (
                                            <option key={nest.id} value={nest.id}>
                                                {nest.name}
                                            </option>
                                        ))}
                                    </Select>
                                </div>

                                <div>
                                    <label
                                        className={
                                            'mb-2 block text-xs font-bold uppercase tracking-[0.24em] text-[color:var(--muted-foreground)]'
                                        }
                                    >
                                        Choose A Game
                                    </label>
                                    <Select
                                        value={selectedGame?.id ?? ''}
                                        onChange={(event) => setSelectedGameId(parseInt(event.currentTarget.value, 10))}
                                        disabled={!selectedNode || availableGames.length < 1}
                                    >
                                        {availableGames.map((game) => (
                                            <option key={game.id} value={game.id}>
                                                {game.displayName}
                                            </option>
                                        ))}
                                    </Select>
                                    <p className={'mt-2 text-xs text-[color:var(--muted-foreground)]'}>
                                        {selectedGame?.description ||
                                            'The selected nest controls which eggs can be ordered on this node.'}
                                    </p>
                                </div>
                            </div>

                            <div className={'mt-8'}>
                                <div className={'mb-4 flex flex-wrap items-center justify-between gap-3'}>
                                    <div>
                                        <h2 className={'text-xl font-black tracking-tight text-[#f8f6ef]'}>
                                            Plan Resources
                                        </h2>
                                        <p className={'mt-1 text-xs text-[color:var(--muted-foreground)]'}>
                                            RAM and storage are live node stock. If either one runs out, the node is
                                            treated as sold out.
                                        </p>
                                    </div>
                                    {selectedNode && selectedNode.showRemainingCapacity ? (
                                        <div className={'billing-chip'}>
                                            {selectedNode.availability.memoryRemainingGb} GB RAM Left /{' '}
                                            {selectedNode.availability.diskRemainingGb} GB Storage Left
                                        </div>
                                    ) : selectedNode ? (
                                        <div className={'billing-chip'}>Remaining stock hidden</div>
                                    ) : (
                                        <div className={'billing-chip'}>Waiting for node selection</div>
                                    )}
                                </div>

                                <div className={'grid gap-4 xl:grid-cols-3'}>
                                    <BillingResourceSlider
                                        label={'vCore'}
                                        value={cpuCores}
                                        min={selectedNode && selectedNode.limits.maxCpu > 0 ? 1 : 0}
                                        max={selectedNode?.limits.maxCpu ?? 0}
                                        unit={'vCore'}
                                        helper={'Selectable per order only. This does not reduce node stock.'}
                                        disabled={!selectedNode || selectedNode.limits.maxCpu < 1}
                                        onChange={setCpuCores}
                                    />
                                    <BillingResourceSlider
                                        label={'RAM'}
                                        value={memoryGb}
                                        min={selectedNode && selectedNode.limits.maxMemoryGb > 0 ? 1 : 0}
                                        max={selectedNode?.limits.maxMemoryGb ?? 0}
                                        unit={'GB'}
                                        helper={'Live stock based on the node setup and current usage.'}
                                        disabled={!selectedNode || selectedNode.limits.maxMemoryGb < 1}
                                        onChange={setMemoryGb}
                                    />
                                    <BillingResourceSlider
                                        label={'Storage'}
                                        value={diskGb}
                                        min={
                                            selectedNode &&
                                            selectedNode.limits.maxDiskGb >= (selectedNode.limits.diskStepGb ?? 10)
                                                ? selectedNode.limits.diskStepGb
                                                : 0
                                        }
                                        max={selectedNode?.limits.maxDiskGb ?? 0}
                                        step={selectedNode?.limits.diskStepGb ?? 10}
                                        unit={'GB'}
                                        helper={'Billed in 10 GB steps and tied to the node storage stock.'}
                                        disabled={
                                            !selectedNode ||
                                            selectedNode.limits.maxDiskGb < (selectedNode?.limits.diskStepGb ?? 10)
                                        }
                                        onChange={setDiskGb}
                                    />
                                </div>
                            </div>

                            <div className={'mt-8'}>
                                <div className={'mb-4'}>
                                    <h2 className={'text-xl font-black tracking-tight text-[#f8f6ef]'}>
                                        Startup Variables
                                    </h2>
                                    <p className={'mt-1 text-xs text-[color:var(--muted-foreground)]'}>
                                        Variables are loaded from the selected egg. Read-only items are shown for review
                                        and cannot be changed.
                                    </p>
                                </div>

                                {selectedGame && selectedGame.variables.length > 0 ? (
                                    <div className={'grid gap-4 xl:grid-cols-2'}>
                                        {selectedGame.variables.map((variable) => (
                                            <BillingVariableBox
                                                key={`${selectedGame.id}:${variable.envVariable}`}
                                                variable={variable}
                                                value={
                                                    variables[variable.envVariable] ??
                                                    variable.serverValue ??
                                                    variable.defaultValue ??
                                                    ''
                                                }
                                                onChange={(value) =>
                                                    setVariables((current) => ({
                                                        ...current,
                                                        [variable.envVariable]: value,
                                                    }))
                                                }
                                            />
                                        ))}
                                    </div>
                                ) : (
                                    <div className={'billing-empty-card text-sm'}>
                                        {selectedGame
                                            ? 'This egg does not expose any user-viewable startup variables.'
                                            : 'Choose a game to load its startup variables.'}
                                    </div>
                                )}
                            </div>

                            <div
                                className={
                                    'mt-8 flex flex-wrap items-center justify-between gap-4 border-t border-[color:var(--border)] pt-6'
                                }
                            >
                                <div className={'text-xs text-[color:var(--muted-foreground)]'}>
                                    Orders create an invoice in RM immediately. Provisioning starts only after the
                                    payment is verified successfully.
                                </div>
                                <button
                                    type={'submit'}
                                    disabled={
                                        submitting ||
                                        !selectedNode ||
                                        !selectedGame ||
                                        !selectedNode.availability.isAvailable
                                    }
                                    className={'billing-primary-btn min-w-[13rem] px-6 py-3 text-xs'}
                                >
                                    {submitting ? 'Creating Invoice...' : 'Create Invoice'}
                                </button>
                            </div>
                        </form>

                        <aside className={'space-y-6 xl:sticky xl:top-8'}>
                            <section className={'billing-panel p-6'}>
                                <div className={'flex items-start justify-between gap-4'}>
                                    <div>
                                        <p
                                            className={
                                                'text-[10px] font-bold uppercase tracking-[0.3em] text-[color:var(--muted-foreground)]'
                                            }
                                        >
                                            Live Preview
                                        </p>
                                        <h2 className={'mt-2 text-2xl font-black tracking-tight text-[#f8f6ef]'}>
                                            {selectedGame?.displayName || 'Choose A Plan'}
                                        </h2>
                                        <p className={'mt-2 text-xs leading-6 text-[color:var(--muted-foreground)]'}>
                                            {selectedNode?.displayName || 'Select a node first.'}
                                            {selectedGame ? ` • ${selectedGame.nestName}` : ''}
                                        </p>
                                    </div>
                                    <div
                                        className={
                                            'rounded-2xl border border-[rgba(var(--primary-rgb),0.42)] bg-[rgba(var(--primary-rgb),0.14)] px-4 py-3 text-right'
                                        }
                                    >
                                        <p
                                            className={
                                                'text-[10px] font-bold uppercase tracking-[0.24em] text-[color:var(--muted-foreground)]'
                                            }
                                        >
                                            Total
                                        </p>
                                        <p className={'mt-1 text-2xl font-black text-[color:var(--primary)]'}>
                                            {formatMoney(total)}
                                        </p>
                                    </div>
                                </div>

                                <div className={'mt-6 grid gap-3'}>
                                    <div className={'billing-soft-card'}>
                                        <div className={'flex items-center justify-between gap-3'}>
                                            <span
                                                className={
                                                    'text-xs uppercase tracking-[0.22em] text-[color:var(--muted-foreground)]'
                                                }
                                            >
                                                vCore
                                            </span>
                                            <strong className={'text-sm text-[#f8f6ef]'}>{cpuCores} vCore</strong>
                                        </div>
                                        <div className={'mt-2 text-sm text-[color:var(--muted-foreground)]'}>
                                            {formatMoney(cpuTotal)}
                                        </div>
                                    </div>
                                    <div className={'billing-soft-card'}>
                                        <div className={'flex items-center justify-between gap-3'}>
                                            <span
                                                className={
                                                    'text-xs uppercase tracking-[0.22em] text-[color:var(--muted-foreground)]'
                                                }
                                            >
                                                RAM
                                            </span>
                                            <strong className={'text-sm text-[#f8f6ef]'}>{memoryGb} GB</strong>
                                        </div>
                                        <div className={'mt-2 text-sm text-[color:var(--muted-foreground)]'}>
                                            {formatMoney(memoryTotal)}
                                        </div>
                                    </div>
                                    <div className={'billing-soft-card'}>
                                        <div className={'flex items-center justify-between gap-3'}>
                                            <span
                                                className={
                                                    'text-xs uppercase tracking-[0.22em] text-[color:var(--muted-foreground)]'
                                                }
                                            >
                                                Storage
                                            </span>
                                            <strong className={'text-sm text-[#f8f6ef]'}>{diskGb} GB</strong>
                                        </div>
                                        <div className={'mt-2 text-sm text-[color:var(--muted-foreground)]'}>
                                            {formatMoney(diskTotal)} • {diskUnits} x 10 GB block
                                        </div>
                                    </div>
                                </div>

                                <div className={'mt-6 billing-soft-card !rounded-2xl !p-5'}>
                                    <p
                                        className={
                                            'text-[10px] font-bold uppercase tracking-[0.28em] text-[color:var(--muted-foreground)]'
                                        }
                                    >
                                        Plan Defaults
                                    </p>
                                    <div className={'mt-4 grid gap-3 text-sm'}>
                                        <div className={'flex items-center justify-between gap-3'}>
                                            <span className={'text-[color:var(--muted-foreground)]'}>Allocations</span>
                                            <span className={'font-semibold text-[#f8f6ef]'}>
                                                {selectedNode?.defaults.allocationLimit ?? 0}
                                            </span>
                                        </div>
                                        <div className={'flex items-center justify-between gap-3'}>
                                            <span className={'text-[color:var(--muted-foreground)]'}>Databases</span>
                                            <span className={'font-semibold text-[#f8f6ef]'}>
                                                {selectedNode?.defaults.databaseLimit ?? 0}
                                            </span>
                                        </div>
                                        <div className={'flex items-center justify-between gap-3'}>
                                            <span className={'text-[color:var(--muted-foreground)]'}>Backups</span>
                                            <span className={'font-semibold text-[#f8f6ef]'}>
                                                {selectedNode?.defaults.backupLimit ?? 0}
                                            </span>
                                        </div>
                                        <div className={'flex items-center justify-between gap-3'}>
                                            <span className={'text-[color:var(--muted-foreground)]'}>Swap</span>
                                            <span className={'font-semibold text-[#f8f6ef]'}>
                                                {selectedNode?.defaults.swapMb ?? 0} MB
                                            </span>
                                        </div>
                                        <div className={'flex items-center justify-between gap-3'}>
                                            <span className={'text-[color:var(--muted-foreground)]'}>IO Weight</span>
                                            <span className={'font-semibold text-[#f8f6ef]'}>
                                                {selectedNode?.defaults.ioWeight ?? 0}
                                            </span>
                                        </div>
                                        <div className={'flex items-center justify-between gap-3'}>
                                            <span className={'text-[color:var(--muted-foreground)]'}>OOM Killer</span>
                                            <span className={'font-semibold text-[#f8f6ef]'}>
                                                {selectedNode?.defaults.oomDisabled ? 'Disabled' : 'Enabled'}
                                            </span>
                                        </div>
                                        <div className={'flex items-center justify-between gap-3'}>
                                            <span className={'text-[color:var(--muted-foreground)]'}>
                                                Start On Completion
                                            </span>
                                            <span className={'font-semibold text-[#f8f6ef]'}>
                                                {selectedNode?.defaults.startOnCompletion ? 'Yes' : 'No'}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <div className={'mt-6 billing-soft-card !rounded-2xl !p-5'}>
                                    <p
                                        className={
                                            'text-[10px] font-bold uppercase tracking-[0.28em] text-[color:var(--muted-foreground)]'
                                        }
                                    >
                                        Node Availability
                                    </p>
                                    {selectedNode && selectedNode.showRemainingCapacity ? (
                                        <div className={'mt-4 grid gap-3 text-sm'}>
                                            <div className={'flex items-center justify-between gap-3'}>
                                                <span className={'text-[color:var(--muted-foreground)]'}>
                                                    Max vCore / Order
                                                </span>
                                                <span className={'font-semibold text-[#f8f6ef]'}>
                                                    {selectedNode.availability.cpuRemaining}
                                                </span>
                                            </div>
                                            <div className={'flex items-center justify-between gap-3'}>
                                                <span className={'text-[color:var(--muted-foreground)]'}>
                                                    RAM Remaining
                                                </span>
                                                <span className={'font-semibold text-[#f8f6ef]'}>
                                                    {selectedNode.availability.memoryRemainingGb} GB
                                                </span>
                                            </div>
                                            <div className={'flex items-center justify-between gap-3'}>
                                                <span className={'text-[color:var(--muted-foreground)]'}>
                                                    Storage Remaining
                                                </span>
                                                <span className={'font-semibold text-[#f8f6ef]'}>
                                                    {selectedNode.availability.diskRemainingGb} GB
                                                </span>
                                            </div>
                                            <div className={'flex items-center justify-between gap-3'}>
                                                <span className={'text-[color:var(--muted-foreground)]'}>
                                                    Free Allocations
                                                </span>
                                                <span className={'font-semibold text-[#f8f6ef]'}>
                                                    {selectedNode.availability.freeAllocations}
                                                </span>
                                            </div>
                                        </div>
                                    ) : selectedNode ? (
                                        <p className={'mt-4 text-sm leading-7 text-[color:var(--muted-foreground)]'}>
                                            Remaining node stock is hidden by the admin, but billing limits are still
                                            enforced before the order is accepted.
                                        </p>
                                    ) : (
                                        <p className={'mt-4 text-sm leading-7 text-[color:var(--muted-foreground)]'}>
                                            Select a node to view the live billing preview for this plan.
                                        </p>
                                    )}

                                    {soldOutReason && (
                                        <div
                                            className={
                                                'mt-4 rounded-xl border border-amber-500/25 bg-amber-500/10 px-4 py-3 text-sm text-amber-100'
                                            }
                                        >
                                            {soldOutReason}
                                        </div>
                                    )}
                                </div>
                            </section>
                        </aside>
                    </div>
                )}

                <section className={'billing-panel mt-6 p-6 md:p-8'}>
                    <div className={'mb-5 flex flex-wrap items-center justify-between gap-4'}>
                        <div>
                            <h2 className={'text-2xl font-black tracking-tight text-[#f8f6ef]'}>
                                Active Subscriptions
                            </h2>
                            <p className={'mt-2 text-sm text-[color:var(--muted-foreground)]'}>
                                Renew a server for another month, or upgrade its plan. Downgrades are blocked.
                            </p>
                        </div>
                        {subscriptions && subscriptions.length > 0 ? (
                            <BillingPagination
                                currentPage={subscriptionsPage}
                                onPageChange={setSubscriptionsPage}
                                pageSize={ACTIVE_SUBSCRIPTIONS_PAGE_SIZE}
                                totalItems={subscriptions.length}
                            />
                        ) : subscriptionsLoading && subscriptions ? (
                            <Spinner size={Spinner.Size.SMALL} />
                        ) : null}
                    </div>

                    {!subscriptions && subscriptionsLoading ? (
                        <Spinner centered />
                    ) : subscriptions && subscriptions.length > 0 ? (
                        <div className={'grid gap-4'}>
                            {paginateItems(subscriptions, subscriptionsPage, ACTIVE_SUBSCRIPTIONS_PAGE_SIZE).map(
                                (subscription) => (
                                <BillingSubscriptionCard
                                    key={subscription.id}
                                    subscription={subscription}
                                    renewing={renewingSubscriptionId === subscription.id}
                                    upgrading={upgradingSubscriptionId === subscription.id}
                                    togglingAutoRenew={togglingSubscriptionId === subscription.id}
                                    onRenew={(current) => void onRenewSubscription(current.id)}
                                    onUpgrade={(current, payload) => void onUpgradeSubscription(current.id, payload)}
                                    onToggleAutoRenew={(current, enabled) => void onToggleAutoRenew(current.id, enabled)}
                                />
                                )
                            )}
                        </div>
                    ) : (
                        <div className={'billing-empty-card'}>No active billing subscriptions exist yet.</div>
                    )}
                </section>

                <section className={'billing-panel mt-6 p-6 md:p-8'}>
                    <div className={'mb-5 flex flex-wrap items-center justify-between gap-4'}>
                        <div>
                            <h2 className={'text-2xl font-black tracking-tight text-[#f8f6ef]'}>Invoices</h2>
                            <p className={'mt-2 text-sm text-[color:var(--muted-foreground)]'}>
                                Checkout, renewals, upgrades, and payment receipts now flow through invoices first.
                            </p>
                        </div>
                        {invoices && invoices.length > 0 ? (
                            <BillingPagination
                                currentPage={invoicesPage}
                                onPageChange={setInvoicesPage}
                                pageSize={INVOICES_PAGE_SIZE}
                                totalItems={invoices.length}
                            />
                        ) : invoicesLoading && invoices ? (
                            <Spinner size={Spinner.Size.SMALL} />
                        ) : null}
                    </div>

                    {!invoices && invoicesLoading ? (
                        <Spinner centered />
                    ) : invoices && invoices.length > 0 ? (
                        <div className={'grid gap-4 xl:grid-cols-2'}>
                            {paginateItems(invoices, invoicesPage, INVOICES_PAGE_SIZE).map((invoice: BillingInvoice) => (
                                <article key={invoice.id} className={'billing-order-card'}>
                                    {(() => {
                                        const latestPayment = invoice.payments[0] || null;
                                        const latestRefund = latestPayment?.refunds[0] || null;
                                        const canRetryPayment =
                                            !invoice.paidAt && ['open', 'draft', 'failed', 'processing'].includes(invoice.status);

                                        return (
                                            <>
                                    <div className={'flex flex-wrap items-start justify-between gap-3'}>
                                        <div>
                                            <p
                                                className={
                                                    'text-[10px] font-bold uppercase tracking-[0.26em] text-[color:var(--muted-foreground)]'
                                                }
                                            >
                                                {invoice.invoiceNumber}
                                            </p>
                                            <h3 className={'mt-2 text-xl font-black tracking-tight text-[#f8f6ef]'}>
                                                {getOrderStatusLabel(invoice.type)}
                                            </h3>
                                            <p className={'mt-2 text-xs text-[color:var(--muted-foreground)]'}>
                                                Issued {invoice.issuedAt ? invoice.issuedAt.toLocaleString() : 'Unknown'}
                                            </p>
                                        </div>
                                        <span
                                            className={`rounded-full px-3 py-1 text-[10px] font-bold uppercase tracking-[0.22em] ${getInvoiceStatusClasses(
                                                invoice.status
                                            )}`}
                                        >
                                            {getOrderStatusLabel(invoice.status)}
                                        </span>
                                    </div>

                                    <div className={'mt-5 grid gap-3 text-sm text-[color:var(--muted-foreground)]'}>
                                        <div className={'flex items-center justify-between gap-3'}>
                                            <span>Amount</span>
                                            <span className={'font-semibold text-[color:var(--primary)]'}>
                                                {formatMoney(invoice.grandTotal)}
                                            </span>
                                        </div>
                                        <div className={'flex items-center justify-between gap-3'}>
                                            <span>Due At</span>
                                            <span className={'font-semibold text-[#f8f6ef]'}>
                                                {invoice.dueAt ? invoice.dueAt.toLocaleString() : 'Unknown'}
                                            </span>
                                        </div>
                                        <div className={'flex items-center justify-between gap-3'}>
                                            <span>Paid At</span>
                                            <span className={'font-semibold text-[#f8f6ef]'}>
                                                {invoice.paidAt ? invoice.paidAt.toLocaleString() : 'Not paid yet'}
                                            </span>
                                        </div>
                                        {latestPayment && (
                                            <div className={'flex items-center justify-between gap-3'}>
                                                <span>Payment Method</span>
                                                <span className={'font-semibold text-[#f8f6ef]'}>
                                                    {latestPayment.providerPaymentMethod || latestPayment.provider || 'Recorded'}
                                                </span>
                                            </div>
                                        )}
                                        {latestRefund && (
                                            <div className={'flex items-center justify-between gap-3'}>
                                                <span>Latest Refund</span>
                                                <span className={'font-semibold text-amber-200'}>
                                                    {latestRefund.refundNumber} • {getOrderStatusLabel(latestRefund.status)}
                                                </span>
                                            </div>
                                        )}
                                    </div>
                                    <div className={'mt-5 flex flex-wrap items-center gap-3'}>
                                        {canRetryPayment && (
                                            <button
                                                type={'button'}
                                                onClick={() => void onRetryInvoicePayment(invoice.id)}
                                                disabled={retryingInvoiceId === invoice.id}
                                                className={'billing-primary-btn'}
                                            >
                                                {retryingInvoiceId === invoice.id ? 'Redirecting...' : 'Retry Payment'}
                                            </button>
                                        )}
                                        {latestPayment && rootAdmin && (
                                            <a href={`/admin/billing/payments/${latestPayment.id}`} className={'billing-secondary-btn'}>
                                                Open Refund Tools
                                            </a>
                                        )}
                                        {latestPayment && !rootAdmin && (
                                            <p className={'text-xs leading-6 text-[color:var(--muted-foreground)]'}>
                                                Refunds are reviewed from billing admin after payment verification.
                                            </p>
                                        )}
                                    </div>
                                            </>
                                        );
                                    })()}
                                </article>
                            ))}
                        </div>
                    ) : (
                        <div className={'billing-empty-card'}>No invoices have been created yet.</div>
                    )}
                </section>

                <section className={'billing-panel mt-6 p-6 md:p-8'}>
                    <div className={'mb-5 flex flex-wrap items-center justify-between gap-4'}>
                        <div>
                            <h2 className={'text-2xl font-black tracking-tight text-[#f8f6ef]'}>My Billing Orders</h2>
                            <p className={'mt-2 text-sm text-[color:var(--muted-foreground)]'}>
                                Provisioning intent and server deployment status after payment verification.
                            </p>
                        </div>
                        {orders && orders.length > 0 ? (
                            <BillingPagination
                                currentPage={ordersPage}
                                onPageChange={setOrdersPage}
                                pageSize={ORDERS_PAGE_SIZE}
                                totalItems={orders.length}
                            />
                        ) : ordersLoading && orders ? (
                            <Spinner size={Spinner.Size.SMALL} />
                        ) : null}
                    </div>

                    {!orders && ordersLoading ? (
                        <Spinner centered />
                    ) : orders && orders.length > 0 ? (
                        <div className={'grid gap-4 xl:grid-cols-2'}>
                            {paginateItems(orders, ordersPage, ORDERS_PAGE_SIZE).map((order) => (
                                <article key={order.id} className={'billing-order-card'}>
                                    <div className={'flex flex-wrap items-start justify-between gap-3'}>
                                        <div>
                                            <p
                                                className={
                                                    'text-[10px] font-bold uppercase tracking-[0.26em] text-[color:var(--muted-foreground)]'
                                                }
                                            >
                                                Order #{order.id}
                                            </p>
                                            <h3 className={'mt-2 text-xl font-black tracking-tight text-[#f8f6ef]'}>
                                                {order.serverName}
                                            </h3>
                                            <p className={'mt-2 text-xs text-[color:var(--muted-foreground)]'}>
                                                {order.nodeName} • {order.gameName}
                                            </p>
                                        </div>
                                        <span
                                            className={`rounded-full px-3 py-1 text-[10px] font-bold uppercase tracking-[0.22em] ${getOrderStatusClasses(
                                                order.status
                                            )}`}
                                        >
                                            {getOrderStatusLabel(order.status)}
                                        </span>
                                    </div>

                                    <div className={'mt-5 grid gap-3 text-sm text-[color:var(--muted-foreground)]'}>
                                        <div className={'flex items-center justify-between gap-3'}>
                                            <span>Resources</span>
                                            <span className={'font-semibold text-[#f8f6ef]'}>
                                                {order.cpuCores} vCore / {order.memoryGb} GB / {order.diskGb} GB
                                            </span>
                                        </div>
                                        <div className={'flex items-center justify-between gap-3'}>
                                            <span>Total</span>
                                            <span className={'font-semibold text-[color:var(--primary)]'}>
                                                {formatMoney(order.total)}
                                            </span>
                                        </div>
                                        <div className={'flex items-center justify-between gap-3'}>
                                            <span>Placed</span>
                                            <span className={'font-semibold text-[#f8f6ef]'}>
                                                {order.createdAt ? order.createdAt.toLocaleString() : 'Unknown'}
                                            </span>
                                        </div>
                                        {order.adminNotes && (
                                            <div
                                                className={
                                                    'rounded-xl border border-[color:var(--border)] bg-[color:var(--card)] px-4 py-3 text-xs leading-6 text-[color:var(--muted-foreground)]'
                                                }
                                            >
                                                {order.adminNotes}
                                            </div>
                                        )}
                                    </div>
                                </article>
                            ))}
                        </div>
                    ) : (
                        <div className={'billing-empty-card'}>No billing orders have been placed yet.</div>
                    )}
                </section>
            </div>
        </div>
    );
};
