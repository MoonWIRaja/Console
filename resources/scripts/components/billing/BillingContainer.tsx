import React, { useEffect, useRef, useState } from 'react';
import { useStoreState } from 'easy-peasy';
import { useLocation } from 'react-router-dom';
import { ApplicationStore } from '@/state';
import FlashMessageRender from '@/components/FlashMessageRender';
import Spinner from '@/components/elements/Spinner';
import Input from '@/components/elements/Input';
import Modal from '@/components/elements/Modal';
import Select from '@/components/elements/Select';
import useFlash, { useFlashKey } from '@/plugins/useFlash';
import {
    BillingInvoice,
    BillingNodeCatalog,
    BillingOrderActionResponse,
    BillingSubscriptionActionResponse,
    createBillingOrder,
    toggleBillingSubscriptionAutoRenew,
    useBillingCatalog,
    useBillingInvoices,
    useBillingOrders,
    useBillingProfile,
    useBillingSubscriptions,
    renewBillingSubscription,
    updateBillingProfile,
    upgradeBillingSubscription,
} from '@/api/account/billing';
import { useOAuthAccounts } from '@/api/account/oauth';
import BillingVariableBox from '@/components/billing/BillingVariableBox';
import BillingResourceSlider from '@/components/billing/BillingResourceSlider';
import BillingSubscriptionCard from '@/components/billing/BillingSubscriptionCard';
import { InteractiveHoverButton } from '@/components/ui/interactive-hover-button';
import { httpErrorToHuman } from '@/api/http';
import {
    emptyBillingProfile,
    getMissingBillingProfileLabels,
    isBillingProfileComplete,
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

type BillingFollowUpAction = {
    label: string;
    href: string;
    description: string;
    warning: string | null;
    eyebrow: string;
};

type BillingCheckoutDraft = {
    selectedNodeId: number | null;
    selectedNestId: number | null;
    selectedGameId: number | null;
    serverName: string;
    cpuCores: number;
    memoryGb: number;
    diskGb: number;
    variables: Record<string, string>;
};

type BillingPendingResumeAction =
    | { type: 'create' }
    | { type: 'renew'; subscriptionId: number }
    | { type: 'upgrade'; subscriptionId: number; payload: UpgradePayload };

type BillingCheckoutGateOptions = {
    persistDraft?: boolean;
    resumeAction?: BillingPendingResumeAction;
};

const BILLING_CHECKOUT_DRAFT_STORAGE_KEY = 'billing-checkout-draft';
const BILLING_PENDING_ACTION_STORAGE_KEY = 'billing-pending-action';
const BILLING_PENDING_ACTION_QUERY_KEY = 'billing_resume';
const MANUAL_BILLING_DISCORD_REQUIRED_ERROR =
    'Link your Discord account before checkout so the panel can open the required billing ticket.';

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

const withReturnTo = (url: string, returnTo: string): string => {
    try {
        const parsed = new URL(url, window.location.origin);
        parsed.searchParams.set('return_to', returnTo);

        return parsed.toString();
    } catch {
        return url;
    }
};

const readBillingCheckoutDraft = (): BillingCheckoutDraft | null => {
    if (typeof window === 'undefined') {
        return null;
    }

    try {
        const raw = window.sessionStorage.getItem(BILLING_CHECKOUT_DRAFT_STORAGE_KEY);
        if (!raw) {
            return null;
        }

        return JSON.parse(raw) as BillingCheckoutDraft;
    } catch {
        return null;
    }
};

const writeBillingCheckoutDraft = (draft: BillingCheckoutDraft | null): void => {
    if (typeof window === 'undefined') {
        return;
    }

    if (!draft) {
        window.sessionStorage.removeItem(BILLING_CHECKOUT_DRAFT_STORAGE_KEY);
        return;
    }

    window.sessionStorage.setItem(BILLING_CHECKOUT_DRAFT_STORAGE_KEY, JSON.stringify(draft));
};

const readBillingPendingAction = (): BillingPendingResumeAction | null => {
    if (typeof window === 'undefined') {
        return null;
    }

    try {
        const raw = window.sessionStorage.getItem(BILLING_PENDING_ACTION_STORAGE_KEY);
        if (!raw) {
            return null;
        }

        return JSON.parse(raw) as BillingPendingResumeAction;
    } catch {
        return null;
    }
};

const writeBillingPendingAction = (action: BillingPendingResumeAction | null): void => {
    if (typeof window === 'undefined') {
        return;
    }

    if (!action) {
        window.sessionStorage.removeItem(BILLING_PENDING_ACTION_STORAGE_KEY);
        return;
    }

    window.sessionStorage.setItem(BILLING_PENDING_ACTION_STORAGE_KEY, JSON.stringify(action));
};

const readBillingPendingActionFromSearch = (search: URLSearchParams): BillingPendingResumeAction | null => {
    const raw = search.get(BILLING_PENDING_ACTION_QUERY_KEY);
    if (!raw) {
        return null;
    }

    try {
        return JSON.parse(raw) as BillingPendingResumeAction;
    } catch {
        return null;
    }
};

const appendBillingPendingActionToReturnTo = (returnTo: string, action: BillingPendingResumeAction | null): string => {
    if (!action) {
        return returnTo;
    }

    try {
        const parsed = new URL(returnTo, window.location.origin);
        parsed.searchParams.set(BILLING_PENDING_ACTION_QUERY_KEY, JSON.stringify(action));

        return `${parsed.pathname}${parsed.search}${parsed.hash}`;
    } catch {
        return returnTo;
    }
};

const buildFollowUpAction = (
    response: BillingOrderActionResponse | BillingSubscriptionActionResponse,
    returnTo: string
): BillingFollowUpAction | null => {
    if (response.autoSettled) {
        return null;
    }

    if (response.ticket?.url) {
        return {
            label: response.ticketAutoCreated ? 'Open Payment Ticket' : 'View Payment Ticket',
            href: response.ticket.url,
            description:
                'Your support ticket for this invoice is ready. Open it to share payment proof, continue the conversation, and track manual approval updates.',
            warning: response.ticketWarning ?? null,
            eyebrow: response.ticketAutoCreated ? 'Ticket Auto Opened' : 'Payment Ticket Ready',
        };
    }

    if (response.ticketRequiresDiscordLink && response.linkDiscordUrl) {
        return {
            label: 'Link Discord & Open Ticket',
            href: withReturnTo(response.linkDiscordUrl, returnTo),
            description:
                'Link your Discord account first so the panel can open a private support ticket for this invoice and sync it to your Discord thread.',
            warning: response.ticketWarning ?? null,
            eyebrow: 'Discord Link Required',
        };
    }

    if (response.manualPaymentRequired) {
        return {
            label: 'Open Support Inbox',
            href: withReturnTo('/tickets', returnTo),
            description:
                'Continue this manual payment flow in your support inbox. That is where payment proof, staff replies, and approval updates are now tracked.',
            warning: response.ticketWarning ?? null,
            eyebrow: 'Manual Billing',
        };
    }

    return null;
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
                        <svg
                            xmlns={'http://www.w3.org/2000/svg'}
                            viewBox={'0 0 20 20'}
                            fill={'currentColor'}
                            className={'h-3 w-3'}
                        >
                            <path
                                fillRule={'evenodd'}
                                d={
                                    'M15.707 15.707a1 1 0 01-1.414 0l-5-5a1 1 0 010-1.414l5-5a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 010 1.414zm-6 0a1 1 0 01-1.414 0l-5-5a1 1 0 010-1.414l5-5a1 1 0 011.414 1.414L5.414 10l4.293 4.293a1 1 0 010 1.414z'
                                }
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
                        <svg
                            xmlns={'http://www.w3.org/2000/svg'}
                            viewBox={'0 0 20 20'}
                            fill={'currentColor'}
                            className={'h-3 w-3'}
                        >
                            <path
                                fillRule={'evenodd'}
                                d={
                                    'M10.293 15.707a1 1 0 010-1.414L14.586 10l-4.293-4.293a1 1 0 111.414-1.414l5 5a1 1 0 010 1.414l-5 5a1 1 0 01-1.414 0z'
                                }
                                clipRule={'evenodd'}
                            />
                            <path
                                fillRule={'evenodd'}
                                d={
                                    'M4.293 15.707a1 1 0 010-1.414L8.586 10 4.293 5.707a1 1 0 011.414-1.414l5 5a1 1 0 010 1.414l-5 5a1 1 0 01-1.414 0z'
                                }
                                clipRule={'evenodd'}
                            />
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    );
};

export default () => {
    const location = useLocation();
    const { addFlash } = useFlash();
    const { clearFlashes, clearAndAddHttpError, addError } = useFlashKey('billing');
    const {
        data: billingProfile,
        error: billingProfileError,
        isValidating: billingProfileLoading,
        mutate: mutateBillingProfile,
    } = useBillingProfile();
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
    const { data: providers } = useOAuthAccounts();
    const rootAdmin = useStoreState((state: ApplicationStore) => !!state.user.data?.rootAdmin);
    const discordProvider = (providers || []).find((provider) => provider.provider === 'discord') || null;

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
    const [subscriptionsPage, setSubscriptionsPage] = useState(1);
    const [invoicesPage, setInvoicesPage] = useState(1);
    const [ordersPage, setOrdersPage] = useState(1);
    const [followUpAction, setFollowUpAction] = useState<BillingFollowUpAction | null>(null);
    const [addressPromptVisible, setAddressPromptVisible] = useState(false);
    const [discordPromptVisible, setDiscordPromptVisible] = useState(false);
    const [billingForm, setBillingForm] = useState(emptyBillingProfile);
    const [billingSaving, setBillingSaving] = useState(false);
    const pendingCheckoutAction = useRef<(() => Promise<void>) | null>(null);
    const pendingCheckoutResumeAction = useRef<BillingPendingResumeAction | null>(null);
    const oauthResumeHandled = useRef(false);
    const restoredDraft = useRef(false);
    const restoredDraftVariables = useRef<Record<string, string> | null>(null);

    useEffect(() => {
        clearFlashes();
    }, [clearFlashes]);

    useEffect(() => {
        if (billingProfile) {
            setBillingForm(billingProfile);
        }
    }, [billingProfile]);

    useEffect(() => {
        if (restoredDraft.current || !catalog || catalog.length < 1) {
            return;
        }

        restoredDraft.current = true;

        const draft = readBillingCheckoutDraft();
        if (!draft) {
            return;
        }

        setSelectedNodeId(draft.selectedNodeId);
        setSelectedNestId(draft.selectedNestId);
        setSelectedGameId(draft.selectedGameId);
        setServerName(draft.serverName);
        setCpuCores(draft.cpuCores);
        setMemoryGb(draft.memoryGb);
        setDiskGb(draft.diskGb);
        restoredDraftVariables.current = draft.variables || null;
        setVariables(draft.variables || {});
        writeBillingCheckoutDraft(null);
    }, [catalog]);

    useEffect(() => {
        if (catalogError) {
            clearAndAddHttpError(catalogError);
        }
    }, [catalogError, clearAndAddHttpError]);

    useEffect(() => {
        if (billingProfileError) {
            clearAndAddHttpError(billingProfileError);
        }
    }, [billingProfileError, clearAndAddHttpError]);

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
    const billingProfileComplete = billingProfile?.isComplete ?? false;
    const billingProfileMissingLabels = billingProfile ? getMissingBillingProfileLabels(billingProfile) : '';
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

        if (restoredDraftVariables.current) {
            Object.entries(restoredDraftVariables.current).forEach(([envVariable, value]) => {
                if (Object.prototype.hasOwnProperty.call(nextVariables, envVariable)) {
                    nextVariables[envVariable] = value;
                }
            });

            restoredDraftVariables.current = null;
        }

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

    const onBillingFieldChange = <K extends keyof typeof billingForm>(key: K, value: (typeof billingForm)[K]) =>
        setBillingForm((current) => ({ ...current, [key]: value }));

    const writeCurrentCheckoutDraft = () => {
        writeBillingCheckoutDraft({
            selectedNodeId,
            selectedNestId,
            selectedGameId,
            serverName,
            cpuCores,
            memoryGb,
            diskGb,
            variables,
        });
    };

    const continuePendingCheckout = async () => {
        const action = pendingCheckoutAction.current;
        pendingCheckoutAction.current = null;

        if (action) {
            await action();
        }
    };

    const clearPendingCheckoutResume = () => {
        pendingCheckoutResumeAction.current = null;
        writeBillingPendingAction(null);
    };

    const requestCheckoutGate = async (action: () => Promise<void>, options?: BillingCheckoutGateOptions) => {
        if (billingProfileLoading && !billingProfile) {
            addError('Billing details are still loading. Please wait a moment and try again.', 'Billing');
            return;
        }

        if (!providers) {
            addError('Discord link status is still loading. Please wait a moment and try again.', 'Billing');
            return;
        }

        pendingCheckoutAction.current = action;
        pendingCheckoutResumeAction.current = options?.resumeAction ?? null;

        const nextProfile = normalizeBillingProfile(billingProfile || billingForm);
        if (!isBillingProfileComplete(nextProfile)) {
            setBillingForm(nextProfile);
            setAddressPromptVisible(true);
            return;
        }

        if (!discordProvider?.linked) {
            if (!discordProvider?.available || !discordProvider.linkUrl) {
                pendingCheckoutAction.current = null;
                addError('Discord linking is not available right now. Contact an administrator.', 'Billing');
                return;
            }

            if (options?.persistDraft) {
                writeCurrentCheckoutDraft();
            }

            setDiscordPromptVisible(true);
            return;
        }

        await continuePendingCheckout();
    };

    const handleManualBillingDiscordRequirement = (
        error: unknown,
        action: () => Promise<void>,
        options?: BillingCheckoutGateOptions
    ): boolean => {
        if (httpErrorToHuman(error) !== MANUAL_BILLING_DISCORD_REQUIRED_ERROR) {
            return false;
        }

        pendingCheckoutAction.current = action;
        pendingCheckoutResumeAction.current = options?.resumeAction ?? null;

        if (options?.persistDraft) {
            writeCurrentCheckoutDraft();
        }

        if (discordProvider?.available && discordProvider.linkUrl) {
            setDiscordPromptVisible(true);
            return true;
        }

        return false;
    };

    const saveBillingDetailsFromPrompt = async () => {
        setBillingSaving(true);
        clearAndAddHttpError();

        try {
            const updated = await updateBillingProfile(normalizeBillingProfile(billingForm));
            await mutateBillingProfile(updated, false);
            setBillingForm(updated);

            if (!isBillingProfileComplete(updated)) {
                addFlash({
                    key: 'billing',
                    type: 'warning',
                    title: 'More Billing Details Needed',
                    message: `Checkout is still blocked until these fields are completed: ${getMissingBillingProfileLabels(
                        updated
                    )}.`,
                });
                return;
            }

            addFlash({
                key: 'billing',
                type: 'success',
                title: 'Billing Details Saved',
                message: 'Your billing details were updated. Checkout can continue now.',
            });

            setAddressPromptVisible(false);

            if (!discordProvider?.linked) {
                setDiscordPromptVisible(true);
                return;
            }

            await continuePendingCheckout();
        } catch (error) {
            clearAndAddHttpError(error as Error);
        } finally {
            setBillingSaving(false);
        }
    };

    const handleLinkDiscordForCheckout = () => {
        if (!discordProvider?.linkUrl) {
            addError('Discord linking is not available right now. Contact an administrator.', 'Billing');
            return;
        }

        writeCurrentCheckoutDraft();

        if (pendingCheckoutResumeAction.current) {
            writeBillingPendingAction(pendingCheckoutResumeAction.current);
        }

        const returnTo = appendBillingPendingActionToReturnTo(
            `${location.pathname}${location.search}`,
            pendingCheckoutResumeAction.current
        );
        window.location.assign(withReturnTo(discordProvider.linkUrl, returnTo));
    };

    const performCreateInvoice = async () => {
        if (!selectedNode || !selectedGame) {
            return;
        }

        setSubmitting(true);
        setFollowUpAction(null);

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
            const returnTo = response.invoice?.id
                ? `/tickets?compose=payment&invoiceId=${response.invoice.id}`
                : '/tickets';
            const action = buildFollowUpAction(response, returnTo);
            setFollowUpAction(action);

            addFlash({
                key: 'billing',
                type: 'success',
                title: response.autoSettled ? 'Provisioning Started' : 'Invoice Created',
                message: response.autoSettled
                    ? `Invoice ${
                          response.invoice?.invoiceNumber ?? '#'
                      } required no payment and was settled automatically. Provisioning has started.`
                    : action
                    ? `Invoice ${
                          response.invoice?.invoiceNumber ?? '#'
                      } was created. Use the support ticket prompt below to continue this manual payment flow.`
                    : `Invoice ${
                          response.invoice?.invoiceNumber ?? '#'
                      } was created. Check your email and open your support inbox to continue manual payment confirmation.`,
            });

            if (response.ticketWarning) {
                addFlash({
                    key: 'billing',
                    type: 'warning',
                    title: 'Discord Sync Warning',
                    message: response.ticketWarning,
                });
            }

            void mutateCatalog();
            void mutateOrders();
            void mutateInvoices();
            void mutateBillingProfile();
            clearPendingCheckoutResume();
            writeBillingCheckoutDraft(null);
        } catch (error) {
            setFollowUpAction(null);

            if (
                handleManualBillingDiscordRequirement(error, performCreateInvoice, {
                    persistDraft: true,
                    resumeAction: { type: 'create' },
                })
            ) {
                return;
            }

            clearAndAddHttpError(error as Error);
        } finally {
            setSubmitting(false);
        }
    };

    const performRenewSubscription = async (subscriptionId: number) => {
        clearFlashes();
        setRenewingSubscriptionId(subscriptionId);
        setFollowUpAction(null);

        try {
            const response = await renewBillingSubscription(subscriptionId);
            const returnTo = response.invoice?.id
                ? `/tickets?compose=payment&invoiceId=${response.invoice.id}`
                : '/tickets';
            const action = buildFollowUpAction(response, returnTo);
            setFollowUpAction(action);
            addFlash({
                key: 'billing',
                type: 'success',
                title: response.autoSettled ? 'Renewal Applied' : 'Renewal Invoice Created',
                message: response.autoSettled
                    ? `${response.subscription.serverName} renewal required no payment and was applied immediately.`
                    : action
                    ? `${response.subscription.serverName} renewal invoice ${
                          response.invoice?.invoiceNumber ?? '#'
                      } was created. Continue the payment flow through the support ticket prompt below.`
                    : `${response.subscription.serverName} renewal invoice ${
                          response.invoice?.invoiceNumber ?? '#'
                      } was created. Check your email and open your support inbox to continue manual payment confirmation.`,
            });

            if (response.ticketWarning) {
                addFlash({
                    key: 'billing',
                    type: 'warning',
                    title: 'Discord Sync Warning',
                    message: response.ticketWarning,
                });
            }

            void mutateSubscriptions();
            void mutateInvoices();
            clearPendingCheckoutResume();
        } catch (error) {
            setFollowUpAction(null);

            if (
                handleManualBillingDiscordRequirement(error, () => performRenewSubscription(subscriptionId), {
                    resumeAction: { type: 'renew', subscriptionId },
                })
            ) {
                return;
            }

            clearAndAddHttpError(error as Error);
        } finally {
            setRenewingSubscriptionId(null);
        }
    };

    const performUpgradeSubscription = async (subscriptionId: number, payload: UpgradePayload) => {
        clearFlashes();
        setUpgradingSubscriptionId(subscriptionId);
        setFollowUpAction(null);
        const currentSubscription = subscriptions?.find((item) => item.id === subscriptionId) || null;

        try {
            const response = await upgradeBillingSubscription({
                id: subscriptionId,
                cpuCores: payload.cpuCores,
                memoryGb: payload.memoryGb,
                diskGb: payload.diskGb,
            });
            const additionalCharge = Number(
                Math.max(response.subscription.recurringTotal - (currentSubscription?.recurringTotal ?? 0), 0).toFixed(
                    2
                )
            );
            const returnTo = response.invoice?.id
                ? `/tickets?compose=payment&invoiceId=${response.invoice.id}`
                : '/tickets';
            const action = buildFollowUpAction(response, returnTo);
            setFollowUpAction(action);

            addFlash({
                key: 'billing',
                type: 'success',
                title: response.autoSettled ? 'Upgrade Applied' : 'Upgrade Invoice Created',
                message: response.autoSettled
                    ? `${response.subscription.serverName} upgrade required no payment and was applied immediately.`
                    : action
                    ? `${response.subscription.serverName} upgrade invoice ${
                          response.invoice?.invoiceNumber ?? '#'
                      } was created. Estimated prorated amount due now: ${formatMoney(
                          additionalCharge
                      )}. Continue the payment flow through the support ticket prompt below.`
                    : `${response.subscription.serverName} upgrade invoice ${
                          response.invoice?.invoiceNumber ?? '#'
                      } was created. Estimated prorated amount due now: ${formatMoney(
                          additionalCharge
                      )}. Check your email and open your support inbox to continue manual payment confirmation.`,
            });

            if (response.ticketWarning) {
                addFlash({
                    key: 'billing',
                    type: 'warning',
                    title: 'Discord Sync Warning',
                    message: response.ticketWarning,
                });
            }

            void mutateSubscriptions();
            void mutateCatalog();
            void mutateInvoices();
            clearPendingCheckoutResume();
        } catch (error) {
            setFollowUpAction(null);

            if (
                handleManualBillingDiscordRequirement(
                    error,
                    () => performUpgradeSubscription(subscriptionId, payload),
                    {
                        resumeAction: { type: 'upgrade', subscriptionId, payload },
                    }
                )
            ) {
                return;
            }

            clearAndAddHttpError(error as Error);
        } finally {
            setUpgradingSubscriptionId(null);
        }
    };

    const buildPendingCheckoutAction = (resumeAction: BillingPendingResumeAction): (() => Promise<void>) => {
        switch (resumeAction.type) {
            case 'create':
                return performCreateInvoice;
            case 'renew':
                return () => performRenewSubscription(resumeAction.subscriptionId);
            case 'upgrade':
                return () => performUpgradeSubscription(resumeAction.subscriptionId, resumeAction.payload);
        }
    };

    useEffect(() => {
        const search = new URLSearchParams(location.search);
        const oauthStatus = search.get('oauth_status');
        const pendingActionFromQuery = readBillingPendingActionFromSearch(search);

        if (!oauthStatus) {
            oauthResumeHandled.current = false;
            return;
        }

        if (oauthStatus !== 'linked') {
            if (!oauthResumeHandled.current) {
                oauthResumeHandled.current = true;
                addFlash({
                    key: 'billing',
                    type: 'warning',
                    title: 'Discord Link',
                    message: `Discord link flow ended with status: ${oauthStatus}.`,
                });
            }

            return;
        }

        if (
            oauthResumeHandled.current ||
            !providers ||
            !discordProvider?.linked ||
            (billingProfileLoading && !billingProfile)
        ) {
            return;
        }

        const pendingAction = pendingActionFromQuery || readBillingPendingAction();
        if (pendingAction?.type === 'create' && (!selectedNode || !selectedGame || !serverName.trim())) {
            return;
        }

        if (
            (pendingAction?.type === 'renew' || pendingAction?.type === 'upgrade') &&
            !subscriptions &&
            subscriptionsLoading
        ) {
            return;
        }

        oauthResumeHandled.current = true;

        if (!pendingAction) {
            addFlash({
                key: 'billing',
                type: 'success',
                title: 'Discord Linked',
                message: 'Your Discord account is linked. Your billing draft was restored if needed.',
            });
            return;
        }

        pendingCheckoutResumeAction.current = pendingAction;
        writeBillingPendingAction(null);
        if (pendingActionFromQuery && typeof window !== 'undefined') {
            search.delete(BILLING_PENDING_ACTION_QUERY_KEY);
            search.delete('oauth_status');
            search.delete('oauth_provider');
            const nextSearch = search.toString();
            window.history.replaceState({}, '', `${location.pathname}${nextSearch ? `?${nextSearch}` : ''}`);
        }

        addFlash({
            key: 'billing',
            type: 'success',
            title: 'Discord Linked',
            message: 'Your Discord account is linked. Continuing the billing checkout flow now.',
        });

        void requestCheckoutGate(buildPendingCheckoutAction(pendingAction), {
            persistDraft: pendingAction.type === 'create',
            resumeAction: pendingAction,
        });
    }, [
        location.search,
        addFlash,
        providers,
        discordProvider?.linked,
        billingProfileLoading,
        billingProfile,
        selectedNode,
        selectedGame,
        serverName,
        subscriptions,
        subscriptionsLoading,
        requestCheckoutGate,
        buildPendingCheckoutAction,
    ]);

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
                    ? `${subscription.serverName} will now use automatic renewal when gateway billing is enabled again.`
                    : `${subscription.serverName} will remain on manual renewal invoices only.`,
            });

            void mutateSubscriptions();
        } catch (error) {
            clearAndAddHttpError(error as Error);
        } finally {
            setTogglingSubscriptionId(null);
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

        await requestCheckoutGate(
            async () => {
                await performCreateInvoice();
            },
            { persistDraft: true, resumeAction: { type: 'create' } }
        );
    };

    const onRenewSubscription = async (subscriptionId: number) => {
        await requestCheckoutGate(
            async () => {
                await performRenewSubscription(subscriptionId);
            },
            {
                resumeAction: { type: 'renew', subscriptionId },
            }
        );
    };

    const onUpgradeSubscription = async (subscriptionId: number, payload: UpgradePayload) => {
        await requestCheckoutGate(
            async () => {
                await performUpgradeSubscription(subscriptionId, payload);
            },
            {
                resumeAction: { type: 'upgrade', subscriptionId, payload },
            }
        );
    };

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

                .billing-gate-modal {
                    border-radius: 24px;
                    border: 1px solid rgba(255, 255, 255, 0.08);
                    background:
                        linear-gradient(170deg, rgba(255, 255, 255, 0.045), rgba(255, 255, 255, 0.014) 55%),
                        rgba(4, 8, 14, 0.94);
                    padding: 1.25rem;
                    box-shadow:
                        inset 0 1px 0 rgba(255, 255, 255, 0.06),
                        0 26px 48px -28px rgba(0, 0, 0, 0.9);
                }

                .billing-gate-eyebrow {
                    font-size: 10px;
                    font-weight: 900;
                    letter-spacing: 0.28em;
                    text-transform: uppercase;
                    color: var(--primary);
                }

                .billing-gate-title {
                    margin-top: 10px;
                    font-size: clamp(1.4rem, 3vw, 1.9rem);
                    line-height: 1.05;
                    font-weight: 900;
                    color: #f8f6ef;
                }

                .billing-gate-copy {
                    margin-top: 10px;
                    font-size: 0.9rem;
                    line-height: 1.75;
                    color: rgba(174, 183, 194, 0.86);
                }

                .billing-gate-note {
                    margin-top: 16px;
                    border-radius: 16px;
                    border: 1px solid rgba(var(--primary-rgb), 0.2);
                    background: rgba(var(--primary-rgb), 0.08);
                    padding: 12px 14px;
                    font-size: 12px;
                    line-height: 1.7;
                    color: rgba(230, 252, 180, 0.9);
                }

                .billing-gate-grid {
                    display: grid;
                    gap: 14px;
                    margin-top: 22px;
                }

                .billing-gate-footer {
                    display: flex;
                    flex-wrap: wrap;
                    justify-content: space-between;
                    gap: 12px;
                    margin-top: 22px;
                    padding-top: 18px;
                    border-top: 1px solid rgba(255, 255, 255, 0.08);
                }

                .billing-gate-help {
                    max-width: 28rem;
                    font-size: 12px;
                    line-height: 1.7;
                    color: rgba(174, 183, 194, 0.76);
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

                    .billing-gate-footer {
                        flex-direction: column;
                    }
                }
            `}</style>
            <div className={'billing-wrap'}>
                <FlashMessageRender byKey={'billing'} />
                <div className={'billing-hero'}>
                    <div className={'billing-hero-pill-row'}>
                        <span className={'billing-hero-pill'}>Secure billing panel</span>
                        <span className={'billing-hero-route'}>Route /billing</span>
                    </div>
                    <h1 className={'billing-hero-title'}>Billing</h1>
                    <p className={'billing-hero-copy'}>
                        Build a server plan from the node stock that is currently available. New orders, renewals, and
                        upgrades now create manual billing invoices inside the panel first. After checkout, the panel
                        opens or prepares your private support ticket so payment proof, staff replies, and approval
                        updates stay in one place.
                    </p>
                    <div className={'mt-5 text-xs leading-6 text-[color:var(--muted-foreground)]'}>
                        Every invoice stays pending until billing staff confirms payment manually through your support
                        ticket workflow.
                    </div>
                </div>

                {followUpAction ? (
                    <section className={'billing-panel mb-6 p-6'}>
                        <div className={'flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between'}>
                            <div>
                                <p
                                    className={
                                        'text-xs font-black uppercase tracking-[0.28em] text-[color:var(--primary)]'
                                    }
                                >
                                    {followUpAction.eyebrow}
                                </p>
                                <h2 className={'mt-2 text-2xl font-black tracking-tight text-[#f8f6ef]'}>
                                    Continue In Your Support Ticket
                                </h2>
                                <p className={'mt-3 max-w-3xl text-sm leading-7 text-[color:var(--muted-foreground)]'}>
                                    {followUpAction.description}
                                </p>
                                {followUpAction.warning ? (
                                    <p className={'mt-3 text-sm leading-7 text-amber-200'}>
                                        Discord sync warning: {followUpAction.warning}
                                    </p>
                                ) : null}
                            </div>
                            <div className={'flex flex-wrap gap-3'}>
                                <a className={'billing-primary-btn'} href={followUpAction.href}>
                                    {followUpAction.label}
                                </a>
                                <a className={'billing-secondary-btn'} href={'/tickets'}>
                                    Ticket Inbox
                                </a>
                            </div>
                        </div>
                    </section>
                ) : null}

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
                                    Orders create a manual invoice in RM immediately. Provisioning starts only after
                                    billing admin marks the first invoice as paid.
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
                                    {submitting ? 'Creating Invoice...' : 'Checkout'}
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
                                Renewals and upgrades stay available here, but every invoice is handled manually while
                                payment gateway checkout is disabled.
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
                                        billingProfileReady={billingProfileComplete}
                                        billingProfileBlockReason={billingProfileMissingLabels || null}
                                        onRenew={(current) => void onRenewSubscription(current.id)}
                                        onUpgrade={(current, payload) =>
                                            void onUpgradeSubscription(current.id, payload)
                                        }
                                        onToggleAutoRenew={(current, enabled) =>
                                            void onToggleAutoRenew(current.id, enabled)
                                        }
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
                                New orders, renewals, upgrades, and payment receipts flow through invoices first. Manual
                                payment review is required before provisioning or renewal is applied.
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
                            {paginateItems(invoices, invoicesPage, INVOICES_PAGE_SIZE).map(
                                (invoice: BillingInvoice) => (
                                    <article key={invoice.id} className={'billing-order-card'}>
                                        {(() => {
                                            const latestPayment = invoice.payments[0] || null;
                                            const latestRefund = latestPayment?.refunds[0] || null;
                                            const canRetryPayment =
                                                invoice.provider !== 'manual' &&
                                                !invoice.paidAt &&
                                                ['open', 'draft', 'failed', 'processing'].includes(invoice.status);

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
                                                            <h3
                                                                className={
                                                                    'mt-2 text-xl font-black tracking-tight text-[#f8f6ef]'
                                                                }
                                                            >
                                                                {getOrderStatusLabel(invoice.type)}
                                                            </h3>
                                                            <p
                                                                className={
                                                                    'mt-2 text-xs text-[color:var(--muted-foreground)]'
                                                                }
                                                            >
                                                                Issued{' '}
                                                                {invoice.issuedAt
                                                                    ? invoice.issuedAt.toLocaleString()
                                                                    : 'Unknown'}
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

                                                    <div
                                                        className={
                                                            'mt-5 grid gap-3 text-sm text-[color:var(--muted-foreground)]'
                                                        }
                                                    >
                                                        <div className={'flex items-center justify-between gap-3'}>
                                                            <span>Amount</span>
                                                            <span
                                                                className={'font-semibold text-[color:var(--primary)]'}
                                                            >
                                                                {formatMoney(invoice.grandTotal)}
                                                            </span>
                                                        </div>
                                                        <div className={'flex items-center justify-between gap-3'}>
                                                            <span>Due At</span>
                                                            <span className={'font-semibold text-[#f8f6ef]'}>
                                                                {invoice.dueAt
                                                                    ? invoice.dueAt.toLocaleString()
                                                                    : 'Unknown'}
                                                            </span>
                                                        </div>
                                                        <div className={'flex items-center justify-between gap-3'}>
                                                            <span>Paid At</span>
                                                            <span className={'font-semibold text-[#f8f6ef]'}>
                                                                {invoice.paidAt
                                                                    ? invoice.paidAt.toLocaleString()
                                                                    : 'Not paid yet'}
                                                            </span>
                                                        </div>
                                                        {latestPayment && (
                                                            <div className={'flex items-center justify-between gap-3'}>
                                                                <span>Payment Method</span>
                                                                <span className={'font-semibold text-[#f8f6ef]'}>
                                                                    {latestPayment.paymentMethodBrand &&
                                                                    latestPayment.paymentMethodLast4
                                                                        ? `${latestPayment.paymentMethodBrand.toUpperCase()} •••• ${
                                                                              latestPayment.paymentMethodLast4
                                                                          }`
                                                                        : latestPayment.providerPaymentMethod ||
                                                                          latestPayment.provider ||
                                                                          'Recorded'}
                                                                </span>
                                                            </div>
                                                        )}
                                                        {invoice.providerStatus && (
                                                            <div className={'flex items-center justify-between gap-3'}>
                                                                <span>Gateway Status</span>
                                                                <span className={'font-semibold text-[#f8f6ef]'}>
                                                                    {getOrderStatusLabel(invoice.providerStatus)}
                                                                </span>
                                                            </div>
                                                        )}
                                                        {latestRefund && (
                                                            <div className={'flex items-center justify-between gap-3'}>
                                                                <span>Latest Refund</span>
                                                                <span className={'font-semibold text-amber-200'}>
                                                                    {latestRefund.refundNumber} •{' '}
                                                                    {getOrderStatusLabel(latestRefund.status)}
                                                                </span>
                                                            </div>
                                                        )}
                                                    </div>
                                                    <div className={'mt-5 flex flex-wrap items-center gap-3'}>
                                                        {!latestPayment && !invoice.paidAt && (
                                                            <p
                                                                className={
                                                                    'text-xs leading-6 text-[color:var(--muted-foreground)]'
                                                                }
                                                            >
                                                                Waiting for billing admin to confirm manual payment
                                                                before this invoice can be marked paid.
                                                            </p>
                                                        )}
                                                        {canRetryPayment && (
                                                            <span className={'text-xs leading-6 text-amber-200'}>
                                                                Online retry is unavailable while manual billing mode is
                                                                active.
                                                            </span>
                                                        )}
                                                        {invoice.hostedInvoiceUrl && (
                                                            <a
                                                                href={invoice.hostedInvoiceUrl}
                                                                className={'billing-secondary-btn'}
                                                            >
                                                                Open Hosted Invoice
                                                            </a>
                                                        )}
                                                        {invoice.invoicePdfUrl && (
                                                            <a
                                                                href={invoice.invoicePdfUrl}
                                                                className={'billing-ghost-btn'}
                                                                target={'_blank'}
                                                                rel={'noreferrer'}
                                                            >
                                                                Invoice PDF
                                                            </a>
                                                        )}
                                                        {latestPayment?.receiptPdfUrl && (
                                                            <a
                                                                href={latestPayment.receiptPdfUrl}
                                                                className={'billing-ghost-btn'}
                                                                target={'_blank'}
                                                                rel={'noreferrer'}
                                                            >
                                                                Receipt PDF
                                                            </a>
                                                        )}
                                                        {latestPayment && rootAdmin && (
                                                            <a
                                                                href={`/admin/billing/payments/${latestPayment.id}`}
                                                                className={'billing-secondary-btn'}
                                                            >
                                                                Open Refund Tools
                                                            </a>
                                                        )}
                                                        {latestPayment && !rootAdmin && (
                                                            <p
                                                                className={
                                                                    'text-xs leading-6 text-[color:var(--muted-foreground)]'
                                                                }
                                                            >
                                                                Refunds are reviewed by billing admin after payment
                                                                verification.
                                                            </p>
                                                        )}
                                                    </div>
                                                </>
                                            );
                                        })()}
                                    </article>
                                )
                            )}
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

                <Modal
                    visible={addressPromptVisible}
                    onDismissed={() => setAddressPromptVisible(false)}
                    showSpinnerOverlay={billingSaving}
                >
                    <form
                        className={'billing-gate-modal'}
                        onSubmit={(event) => {
                            event.preventDefault();
                            void saveBillingDetailsFromPrompt();
                        }}
                    >
                        <div className={'billing-gate-eyebrow'}>Checkout Guard</div>
                        <h2 className={'billing-gate-title'}>Complete Billing Address</h2>
                        <p className={'billing-gate-copy'}>
                            Checkout now pauses here instead of sending you to <code>/account</code>. Fill the billing
                            contact and address fields below, save once, and the current checkout flow will continue.
                        </p>

                        <div className={'billing-gate-note'}>
                            Autofill is enabled through browser address fields now. Google Places autocomplete is not
                            wired in this repo yet, so this modal is prepared for that later without making checkout
                            depend on an external API first.
                        </div>

                        {!isBillingProfileComplete(billingForm) && (
                            <div
                                className={
                                    'mt-4 rounded-2xl border border-amber-400/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-100'
                                }
                            >
                                Missing right now: {getMissingBillingProfileLabels(billingForm)}.
                            </div>
                        )}

                        <div className={'billing-gate-grid md:grid-cols-2'}>
                            <div>
                                <label
                                    className={
                                        'mb-2 block text-xs font-bold uppercase tracking-[0.22em] text-[color:var(--muted-foreground)]'
                                    }
                                >
                                    Legal Name
                                </label>
                                <Input
                                    autoComplete={'name'}
                                    value={billingForm.legalName}
                                    onChange={(event) => onBillingFieldChange('legalName', event.currentTarget.value)}
                                />
                            </div>
                            <div>
                                <label
                                    className={
                                        'mb-2 block text-xs font-bold uppercase tracking-[0.22em] text-[color:var(--muted-foreground)]'
                                    }
                                >
                                    Invoice Email
                                </label>
                                <Input
                                    autoComplete={'email'}
                                    type={'email'}
                                    value={billingForm.email}
                                    onChange={(event) => onBillingFieldChange('email', event.currentTarget.value)}
                                />
                            </div>
                            <div>
                                <label
                                    className={
                                        'mb-2 block text-xs font-bold uppercase tracking-[0.22em] text-[color:var(--muted-foreground)]'
                                    }
                                >
                                    Phone
                                </label>
                                <Input
                                    autoComplete={'tel'}
                                    inputMode={'tel'}
                                    value={billingForm.phone ?? ''}
                                    onChange={(event) => onBillingFieldChange('phone', event.currentTarget.value)}
                                />
                            </div>
                            <div>
                                <label
                                    className={
                                        'mb-2 block text-xs font-bold uppercase tracking-[0.22em] text-[color:var(--muted-foreground)]'
                                    }
                                >
                                    Company Name
                                </label>
                                <Input
                                    autoComplete={'organization'}
                                    value={billingForm.companyName ?? ''}
                                    onChange={(event) =>
                                        onBillingFieldChange('companyName', event.currentTarget.value || null)
                                    }
                                />
                            </div>
                            <div className={'md:col-span-2'}>
                                <label
                                    className={
                                        'mb-2 block text-xs font-bold uppercase tracking-[0.22em] text-[color:var(--muted-foreground)]'
                                    }
                                >
                                    Address Line 1
                                </label>
                                <Input
                                    autoComplete={'address-line1'}
                                    value={billingForm.addressLine1 ?? ''}
                                    onChange={(event) =>
                                        onBillingFieldChange('addressLine1', event.currentTarget.value)
                                    }
                                />
                            </div>
                            <div className={'md:col-span-2'}>
                                <label
                                    className={
                                        'mb-2 block text-xs font-bold uppercase tracking-[0.22em] text-[color:var(--muted-foreground)]'
                                    }
                                >
                                    Address Line 2
                                </label>
                                <Input
                                    autoComplete={'address-line2'}
                                    value={billingForm.addressLine2 ?? ''}
                                    onChange={(event) =>
                                        onBillingFieldChange('addressLine2', event.currentTarget.value || null)
                                    }
                                />
                            </div>
                            <div>
                                <label
                                    className={
                                        'mb-2 block text-xs font-bold uppercase tracking-[0.22em] text-[color:var(--muted-foreground)]'
                                    }
                                >
                                    City
                                </label>
                                <Input
                                    autoComplete={'address-level2'}
                                    value={billingForm.city ?? ''}
                                    onChange={(event) => onBillingFieldChange('city', event.currentTarget.value)}
                                />
                            </div>
                            <div>
                                <label
                                    className={
                                        'mb-2 block text-xs font-bold uppercase tracking-[0.22em] text-[color:var(--muted-foreground)]'
                                    }
                                >
                                    State
                                </label>
                                <Input
                                    autoComplete={'address-level1'}
                                    value={billingForm.state ?? ''}
                                    onChange={(event) => onBillingFieldChange('state', event.currentTarget.value)}
                                />
                            </div>
                            <div>
                                <label
                                    className={
                                        'mb-2 block text-xs font-bold uppercase tracking-[0.22em] text-[color:var(--muted-foreground)]'
                                    }
                                >
                                    Postcode
                                </label>
                                <Input
                                    autoComplete={'postal-code'}
                                    inputMode={'numeric'}
                                    value={billingForm.postcode ?? ''}
                                    onChange={(event) => onBillingFieldChange('postcode', event.currentTarget.value)}
                                />
                            </div>
                            <div>
                                <label
                                    className={
                                        'mb-2 block text-xs font-bold uppercase tracking-[0.22em] text-[color:var(--muted-foreground)]'
                                    }
                                >
                                    Country Code
                                </label>
                                <Input
                                    maxLength={2}
                                    placeholder={'MY'}
                                    value={billingForm.countryCode}
                                    onChange={(event) =>
                                        onBillingFieldChange('countryCode', event.currentTarget.value.toUpperCase())
                                    }
                                />
                            </div>
                        </div>

                        <div className={'billing-gate-footer'}>
                            <p className={'billing-gate-help'}>
                                These details are saved to your billing profile and printed on invoices or receipts. The
                                checkout flow resumes immediately after save if everything required is complete.
                            </p>
                            <div className={'flex flex-wrap gap-3'}>
                                <button
                                    className={'billing-secondary-btn'}
                                    onClick={() => setAddressPromptVisible(false)}
                                    type={'button'}
                                >
                                    Close
                                </button>
                                <InteractiveHoverButton
                                    className={'!w-auto !min-w-[13rem] normal-case tracking-normal'}
                                    disabled={billingSaving}
                                    text={billingSaving ? 'Saving...' : 'Save & Continue'}
                                    type={'submit'}
                                />
                            </div>
                        </div>
                    </form>
                </Modal>

                <Modal visible={discordPromptVisible} onDismissed={() => setDiscordPromptVisible(false)}>
                    <div className={'billing-gate-modal'}>
                        <div className={'billing-gate-eyebrow'}>Discord Required</div>
                        <h2 className={'billing-gate-title'}>Link Discord Before Checkout</h2>
                        <p className={'billing-gate-copy'}>
                            Billing checkout now hands off to your private support ticket flow after invoice creation.
                            That flow is tied to your Discord identity, so the panel needs your Discord account linked
                            first.
                        </p>

                        <div className={'billing-gate-note'}>
                            Your current order draft is kept in this browser session. After Discord returns you to{' '}
                            <code>/billing</code>, press Checkout again and continue from the same plan.
                        </div>

                        <div className={'billing-gate-footer'}>
                            <p className={'billing-gate-help'}>
                                If Discord linking fails or is cancelled, the current checkout request is not created
                                yet. Nothing will be billed until you come back and continue.
                            </p>
                            <div className={'flex flex-wrap gap-3'}>
                                <button
                                    className={'billing-secondary-btn'}
                                    onClick={() => setDiscordPromptVisible(false)}
                                    type={'button'}
                                >
                                    Close
                                </button>
                                <InteractiveHoverButton
                                    className={'!w-auto !min-w-[12rem] normal-case tracking-normal'}
                                    onClick={handleLinkDiscordForCheckout}
                                    text={'Link Discord'}
                                    type={'button'}
                                />
                            </div>
                        </div>
                    </div>
                </Modal>
            </div>
        </div>
    );
};
