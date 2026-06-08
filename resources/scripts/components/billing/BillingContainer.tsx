import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useLocation } from 'react-router-dom';
import FlashMessageRender from '@/components/FlashMessageRender';
import Spinner from '@/components/elements/Spinner';
import Input from '@/components/elements/Input';
import Modal from '@/components/elements/Modal';
import useFlash, { useFlashKey } from '@/plugins/useFlash';
import {
    BillingCheckout,
    BillingGame,
    BillingGameVariable,
    BillingInvoice,
    BillingNodeCatalog,
    BillingOrder,
    BillingOrderActionResponse,
    BillingProfile,
    BillingSubscriptionActionResponse,
    createBillingOrder,
    retryBillingInvoicePayment,
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

type BillingSection = 'plan' | 'subscriptions' | 'invoices' | 'receipts' | 'orders';
type PlanWizardStep = 'node' | 'nest' | 'game' | 'name' | 'resources' | 'variables' | 'billing' | 'summary';

type BillingCheckoutGateOptions = {
    persistDraft?: boolean;
    resumeAction?: BillingPendingResumeAction;
};

const BILLING_CHECKOUT_DRAFT_STORAGE_KEY = 'billing-checkout-draft';
const BILLING_PENDING_ACTION_STORAGE_KEY = 'billing-pending-action';
const BILLING_PENDING_ACTION_QUERY_KEY = 'billing_resume';
const MANUAL_BILLING_DISCORD_REQUIRED_ERROR =
    'Link your Discord account before checkout so the panel can open the required billing ticket.';
const BILLING_SECTIONS: BillingSection[] = ['plan', 'subscriptions', 'invoices', 'receipts', 'orders'];
const PLAN_WIZARD_STEP_ORDER: PlanWizardStep[] = [
    'node',
    'nest',
    'game',
    'name',
    'resources',
    'variables',
    'billing',
    'summary',
];
const PLAN_WIZARD_STEP_CONTENT: Record<
    PlanWizardStep,
    {
        eyebrow: string;
        title: string;
        copy: string;
        continueLabel?: string;
    }
> = {
    node: {
        eyebrow: 'Step 1',
        title: 'Choose A Node',
        copy: 'Pick the billing node with stock that matches the region and capacity you want to deploy on.',
    },
    nest: {
        eyebrow: 'Step 2',
        title: 'Choose A Game',
        copy: 'Select the game family first so only compatible server types appear in the next step.',
    },
    game: {
        eyebrow: 'Step 3',
        title: 'Choose Server Type',
        copy: 'Pick the exact egg or server type that should be provisioned after payment approval.',
    },
    name: {
        eyebrow: 'Step 4',
        title: 'Name Your Server',
        copy: 'This is the server name staff and the panel will use once the order is provisioned.',
    },
    resources: {
        eyebrow: 'Step 5',
        title: 'Select Resources',
        copy: 'Adjust CPU, RAM, and storage based on live node stock before you continue to startup configuration.',
    },
    variables: {
        eyebrow: 'Step 6',
        title: 'Review Startup Variables',
        copy: 'Check the egg variables now so the order is ready for provisioning without manual fixes later.',
    },
    billing: {
        eyebrow: 'Step 7',
        title: 'Confirm Billing Details',
        copy: 'Fill or update the billing address that will be printed on invoices, receipts, and payment records.',
        continueLabel: 'Save & Continue',
    },
    summary: {
        eyebrow: 'Step 8',
        title: 'Review Summary',
        copy: 'Confirm the full plan, billing address, and pricing summary before the invoice is created and checkout continues through the active billing gateway.',
        continueLabel: 'Checkout',
    },
};

const beginHostedCheckout = (checkout: BillingCheckout | null | undefined): boolean => {
    if (!checkout?.url || typeof window === 'undefined' || typeof document === 'undefined') {
        return false;
    }

    const method = (checkout.method || 'GET').toUpperCase();
    const payload = Object.entries(checkout.payload || {});

    if (method === 'GET' && payload.length === 0) {
        window.location.assign(checkout.url);
        return true;
    }

    const form = document.createElement('form');
    form.method = method === 'POST' ? 'POST' : 'GET';
    form.action = checkout.url;
    form.style.display = 'none';

    payload.forEach(([key, value]) => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = key;
        input.value = value || '';
        form.appendChild(input);
    });

    document.body.appendChild(form);
    form.submit();

    return true;
};

const isBillingSection = (value: string | null): value is BillingSection =>
    !!value && BILLING_SECTIONS.includes(value as BillingSection);

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
            label: response.ticketAutoCreated ? 'Open Support Ticket' : 'View Support Ticket',
            href: response.ticket.url,
            description:
                'Your support ticket for this invoice is ready. Open it to share payment proof, continue the conversation, and track manual approval updates.',
            warning: response.ticketWarning ?? null,
            eyebrow: response.ticketAutoCreated ? 'Ticket Auto Opened' : 'Support Ticket Ready',
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
            href: withReturnTo('/tickets?view=chat', returnTo),
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

const ACTIVE_SUBSCRIPTIONS_PAGE_SIZE = 5;
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
    const billingSearch = useMemo(() => new URLSearchParams(location.search), [location.search]);
    const currentSection: BillingSection = isBillingSection(billingSearch.get('section'))
        ? (billingSearch.get('section') as BillingSection)
        : 'plan';
    const isPlanSection = currentSection === 'plan';
    const isListSection = !isPlanSection;
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
    const [retryingInvoiceId, setRetryingInvoiceId] = useState<number | null>(null);
    const [togglingSubscriptionId, setTogglingSubscriptionId] = useState<number | null>(null);
    const [subscriptionsPage, setSubscriptionsPage] = useState(1);
    const [invoicesPage, setInvoicesPage] = useState(1);
    const [receiptsPage, setReceiptsPage] = useState(1);
    const [ordersPage, setOrdersPage] = useState(1);
    const [followUpAction, setFollowUpAction] = useState<BillingFollowUpAction | null>(null);
    const [addressPromptVisible, setAddressPromptVisible] = useState(false);
    const [discordPromptVisible, setDiscordPromptVisible] = useState(false);
    const [billingForm, setBillingForm] = useState(emptyBillingProfile);
    const [billingSaving, setBillingSaving] = useState(false);
    const [planStep, setPlanStep] = useState<PlanWizardStep>('node');
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

    const isReceiptInvoice = useCallback(
        (invoice: BillingInvoice) =>
            !!invoice.paidAt ||
            invoice.payments.some((payment) => !!payment.receiptPdfUrl || payment.refunds.length > 0),
        []
    );

    const sectionInvoices = useMemo(
        () => (invoices || []).filter((invoice) => !isReceiptInvoice(invoice)),
        [invoices, isReceiptInvoice]
    );

    useEffect(() => {
        setInvoicesPage((current) => clampPage(current, sectionInvoices.length, INVOICES_PAGE_SIZE));
    }, [sectionInvoices.length]);

    const receiptInvoices = useMemo(
        () => (invoices || []).filter((invoice) => isReceiptInvoice(invoice)),
        [invoices, isReceiptInvoice]
    );

    useEffect(() => {
        setReceiptsPage((current) => clampPage(current, receiptInvoices.length, INVOICES_PAGE_SIZE));
    }, [receiptInvoices.length]);

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
    const normalizedBillingForm = useMemo(() => normalizeBillingProfile(billingForm), [billingForm]);
    const currentBillingMissingLabels = getMissingBillingProfileLabels(normalizedBillingForm);
    const currentBillingComplete = isBillingProfileComplete(normalizedBillingForm);
    const nestOptions = getNestOptions(selectedNode);
    const availableGames = (selectedNode?.games || []).filter(
        (game) => !selectedNestId || game.nestId === selectedNestId
    );
    const selectedGame = availableGames.find((game) => game.id === selectedGameId) || null;
    const currentPlanStepIndex = PLAN_WIZARD_STEP_ORDER.indexOf(planStep);
    const currentPlanStepContent = PLAN_WIZARD_STEP_CONTENT[planStep];
    const previousPlanStep = currentPlanStepIndex > 0 ? PLAN_WIZARD_STEP_ORDER[currentPlanStepIndex - 1] : null;
    const nextPlanStep =
        currentPlanStepIndex < PLAN_WIZARD_STEP_ORDER.length - 1
            ? PLAN_WIZARD_STEP_ORDER[currentPlanStepIndex + 1]
            : null;

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
        setServerName((current) => (current.trim().length > 0 ? current : 'My Server'));
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

    const getGamesForNest = (nestId: number): BillingGame[] =>
        (selectedNode?.games || []).filter((game) => game.nestId === nestId);

    const onBillingFieldChange = <K extends keyof typeof billingForm>(key: K, value: typeof billingForm[K]) =>
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

    const continueHostedCheckout = (checkout: BillingCheckout | null | undefined): boolean => {
        if (!beginHostedCheckout(checkout)) {
            return false;
        }

        clearPendingCheckoutResume();
        writeBillingCheckoutDraft(null);

        return true;
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

    const persistBillingDetails = async (
        profile: BillingProfile = normalizedBillingForm,
        options?: { successMessage?: string }
    ): Promise<BillingProfile | null> => {
        setBillingSaving(true);
        clearAndAddHttpError();

        try {
            const updated = await updateBillingProfile(profile);
            await mutateBillingProfile(updated, false);
            setBillingForm(updated);

            if (options?.successMessage) {
                addFlash({
                    key: 'billing',
                    type: 'success',
                    title: 'Billing Details Saved',
                    message: options.successMessage,
                });
            }

            return updated;
        } catch (error) {
            clearAndAddHttpError(error as Error);
            return null;
        } finally {
            setBillingSaving(false);
        }
    };

    const saveBillingDetailsFromPrompt = async () => {
        const updated = await persistBillingDetails(normalizedBillingForm);
        if (!updated) {
            return;
        }

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

            if (!response.autoSettled && !response.manualPaymentRequired && response.checkout?.url) {
                if (continueHostedCheckout(response.checkout)) {
                    void mutateCatalog();
                    void mutateOrders();
                    void mutateInvoices();
                    void mutateBillingProfile();
                    return;
                }
            }

            const returnTo = '/tickets?view=tickets';
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

            if (!response.autoSettled && !response.manualPaymentRequired && response.checkout?.url) {
                if (continueHostedCheckout(response.checkout)) {
                    void mutateSubscriptions();
                    void mutateInvoices();
                    return;
                }
            }

            const returnTo = '/tickets?view=tickets';
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

            if (!response.autoSettled && !response.manualPaymentRequired && response.checkout?.url) {
                if (continueHostedCheckout(response.checkout)) {
                    void mutateSubscriptions();
                    void mutateCatalog();
                    void mutateInvoices();
                    return;
                }
            }

            const returnTo = '/tickets?view=tickets';
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

    const performRetryInvoicePayment = async (invoiceId: number) => {
        clearFlashes();
        setRetryingInvoiceId(invoiceId);

        try {
            const checkout = await retryBillingInvoicePayment(invoiceId);
            if (checkout?.url) {
                beginHostedCheckout(checkout);
            }
        } catch (error) {
            clearAndAddHttpError(error as Error);
        } finally {
            setRetryingInvoiceId(null);
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

    const validatePlanStep = (step: PlanWizardStep): boolean => {
        clearFlashes();

        switch (step) {
            case 'node':
                if (!selectedNode) {
                    addError('Choose a billing node first.', 'Billing');
                    return false;
                }

                if (!selectedNode.availability.isAvailable) {
                    addError(soldOutReason || 'This billing node is not available right now.', 'Billing');
                    return false;
                }

                return true;
            case 'nest':
                if (!selectedNode) {
                    addError('Choose a billing node first.', 'Billing');
                    return false;
                }

                if (nestOptions.length < 1 || !selectedNestId) {
                    addError('Choose a nest before continuing.', 'Billing');
                    return false;
                }

                return true;
            case 'game':
                if (!selectedGame) {
                    addError('Choose a server type before continuing.', 'Billing');
                    return false;
                }

                return true;
            case 'name':
                if (!serverName.trim()) {
                    addError('Enter a server name before continuing.', 'Billing');
                    return false;
                }

                return true;
            case 'resources':
                if (!selectedNode) {
                    addError('Choose a billing node first.', 'Billing');
                    return false;
                }

                if (!selectedNode.availability.isAvailable) {
                    addError(soldOutReason || 'This billing node is not available right now.', 'Billing');
                    return false;
                }

                return true;
            case 'variables':
                if (!selectedGame) {
                    addError('Choose a server type before reviewing startup variables.', 'Billing');
                    return false;
                }

                return true;
            case 'billing':
                if (!currentBillingComplete) {
                    addError(
                        `Complete your billing details before continuing: ${currentBillingMissingLabels}.`,
                        'Billing'
                    );
                    return false;
                }

                return true;
            case 'summary':
                return true;
        }
    };

    const resetPlanWizard = () => {
        clearFlashes();
        writeBillingCheckoutDraft(null);
        setFollowUpAction(null);
        setPlanStep('node');
        setServerName('');
        setCpuCores(1);
        setMemoryGb(1);
        setDiskGb(10);
        setVariables({});
        setBillingForm(normalizeBillingProfile(billingProfile || emptyBillingProfile));

        if (!catalog || catalog.length < 1) {
            setSelectedNodeId(null);
            setSelectedNestId(null);
            setSelectedGameId(null);
            return;
        }

        const fallback = catalog.find((node) => node.availability.isAvailable) || catalog[0];
        setSelectedNodeId(fallback.id);
        setSelectedNestId(null);
        setSelectedGameId(null);
    };

    const goToPreviousPlanStep = () => {
        if (currentPlanStepIndex <= 0) {
            return;
        }

        setPlanStep(PLAN_WIZARD_STEP_ORDER[currentPlanStepIndex - 1]);
    };

    const goToNextPlanStep = async () => {
        if (planStep === 'summary') {
            return;
        }

        if (!validatePlanStep(planStep)) {
            return;
        }

        if (planStep === 'billing') {
            const updated = await persistBillingDetails(normalizedBillingForm);
            if (!updated) {
                return;
            }

            if (!isBillingProfileComplete(updated)) {
                addFlash({
                    key: 'billing',
                    type: 'warning',
                    title: 'More Billing Details Needed',
                    message: `Complete these fields before continuing: ${getMissingBillingProfileLabels(updated)}.`,
                });
                return;
            }
        }

        const nextStep = PLAN_WIZARD_STEP_ORDER[currentPlanStepIndex + 1];
        if (nextStep) {
            setPlanStep(nextStep);
        }
    };

    const submitPlanCheckout = async () => {
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

        if (!currentBillingComplete) {
            setPlanStep('billing');
            addError(`Complete your billing details before checkout: ${currentBillingMissingLabels}.`, 'Billing');
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

    const renderPlanWizardActions = ({
        continueLabel,
        onContinue,
        continueDisabled = false,
    }: {
        continueLabel?: string;
        onContinue: () => void | Promise<void>;
        continueDisabled?: boolean;
    }) => (
        <div className={'billing-wizard-actions'}>
            <div className={'flex flex-wrap gap-3'}>
                {currentPlanStepIndex > 0 ? (
                    <button type={'button'} className={'billing-secondary-btn'} onClick={goToPreviousPlanStep}>
                        Back
                    </button>
                ) : null}
                <button type={'button'} className={'billing-ghost-btn'} onClick={resetPlanWizard}>
                    Cancel
                </button>
            </div>
            <button
                type={'button'}
                className={'billing-primary-btn'}
                disabled={continueDisabled}
                onClick={() => void onContinue()}
            >
                {continueLabel || currentPlanStepContent.continueLabel || 'Continue'}
            </button>
        </div>
    );

    const renderPlanStepNavigator = () => {
        const previousStepContent = previousPlanStep ? PLAN_WIZARD_STEP_CONTENT[previousPlanStep] : null;
        const nextStepContent = nextPlanStep ? PLAN_WIZARD_STEP_CONTENT[nextPlanStep] : null;
        const progressWidth = `${(((currentPlanStepIndex + 1) / PLAN_WIZARD_STEP_ORDER.length) * 100).toFixed(2)}%`;

        return (
            <div className={'billing-wizard-nav-grid grid gap-3 lg:grid-cols-[minmax(0,1fr)_240px_minmax(0,1fr)]'}>
                <button
                    type={'button'}
                    disabled={!previousStepContent}
                    onClick={goToPreviousPlanStep}
                    className={
                        'billing-wizard-prev-btn flex min-w-0 items-center gap-3 rounded-[12px] border-2 border-[#2D4A3E] bg-[#FEF9E1] px-4 py-4 text-left transition-all duration-150 shadow-[4px_4px_0px_0px_#2D4A3E] hover:shadow-[6px_6px_0px_0px_#2D4A3E] hover:-translate-x-0.5 hover:-translate-y-0.5 disabled:cursor-not-allowed disabled:opacity-45 disabled:hover:shadow-[4px_4px_0px_0px_#2D4A3E] disabled:hover:translate-x-0 disabled:hover:translate-y-0'
                    }
                >
                    <span
                        className={
                            'inline-flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full border-2 border-[#2D4A3E] bg-[#F5EFD5] text-[#742220]'
                        }
                    >
                        <svg
                            xmlns={'http://www.w3.org/2000/svg'}
                            viewBox={'0 0 16 16'}
                            fill={'currentColor'}
                            className={'h-4 w-4'}
                        >
                            <path
                                fillRule={'evenodd'}
                                clipRule={'evenodd'}
                                d={
                                    'M10.5 14.0607L9.96966 13.5303L5.14644 8.7071C4.75592 8.31658 4.75592 7.68341 5.14644 7.29289L9.96966 2.46966L10.5 1.93933L11.5607 2.99999L11.0303 3.53032L6.56065 7.99999L11.0303 12.4697L11.5607 13L10.5 14.0607Z'
                                }
                            />
                        </svg>
                    </span>
                    <div className={'min-w-0'}>
                        <p
                            className={
                                'text-[10px] font-bold uppercase tracking-[0.24em] text-[color:var(--muted-foreground)]'
                            }
                        >
                            Previous
                        </p>
                        <p className={'mt-2 truncate text-sm font-black text-[#742220]'}>
                            {previousStepContent?.title || 'Start Here'}
                        </p>
                        <p
                            className={
                                'mt-1 text-[11px] uppercase tracking-[0.18em] text-[color:var(--muted-foreground)]'
                            }
                        >
                            {previousStepContent?.eyebrow || 'Wizard Entry'}
                        </p>
                    </div>
                </button>

                <div
                    className={
                        'billing-wizard-current-card rounded-[12px] border-2 border-[#742220] bg-[#FEF9E1] px-4 py-4 text-center shadow-[4px_4px_0px_0px_#742220]'
                    }
                >
                    <p className={'text-[10px] font-bold uppercase tracking-[0.3em] text-[color:var(--primary)]'}>
                        {currentPlanStepContent.eyebrow} of {PLAN_WIZARD_STEP_ORDER.length}
                    </p>
                    <p className={'mt-2 text-base font-black tracking-tight text-[#742220]'}>
                        {currentPlanStepContent.title}
                    </p>
                    <div className={'mt-4 h-2 overflow-hidden rounded-full bg-[#EDE6D0]'}>
                        <div
                            className={'h-full rounded-full bg-[#742220] transition-all duration-300'}
                            style={{ width: progressWidth }}
                        />
                    </div>
                </div>

                {nextStepContent ? (
                    <button
                        type={'button'}
                        onClick={() => void goToNextPlanStep()}
                        className={
                            'billing-wizard-next-btn flex min-w-0 items-center justify-between gap-3 rounded-[12px] border-2 border-[#2D4A3E] bg-[#FEF9E1] px-4 py-4 text-left transition-all duration-150 shadow-[4px_4px_0px_0px_#2D4A3E] hover:shadow-[6px_6px_0px_0px_#2D4A3E] hover:-translate-x-0.5 hover:-translate-y-0.5'
                        }
                    >
                        <div className={'min-w-0'}>
                            <p
                                className={
                                    'text-[10px] font-bold uppercase tracking-[0.24em] text-[color:var(--muted-foreground)]'
                                }
                            >
                                Next
                            </p>
                            <p className={'mt-2 truncate text-sm font-black text-[#742220]'}>{nextStepContent.title}</p>
                            <p
                                className={
                                    'mt-1 text-[11px] uppercase tracking-[0.18em] text-[color:var(--muted-foreground)]'
                                }
                            >
                                {nextStepContent.eyebrow}
                            </p>
                        </div>
                        <span
                            className={
                                'inline-flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full border-2 border-[#2D4A3E] bg-[#F5EFD5] text-[#742220]'
                            }
                        >
                            <svg
                                xmlns={'http://www.w3.org/2000/svg'}
                                viewBox={'0 0 16 16'}
                                fill={'currentColor'}
                                className={'h-4 w-4'}
                            >
                                <path
                                    fillRule={'evenodd'}
                                    clipRule={'evenodd'}
                                    d={
                                        'M5.50001 1.93933L6.03034 2.46966L10.8536 7.29288C11.2441 7.68341 11.2441 8.31657 10.8536 8.7071L6.03034 13.5303L5.50001 14.0607L4.43935 13L4.96968 12.4697L9.43935 7.99999L4.96968 3.53032L4.43935 2.99999L5.50001 1.93933Z'
                                    }
                                />
                            </svg>
                        </span>
                    </button>
                ) : (
                    <div
                        className={
                            'billing-wizard-next-btn flex min-w-0 items-center justify-between gap-3 rounded-[12px] border-2 border-[#2D4A3E] bg-[#FEF9E1] px-4 py-4 text-left shadow-[4px_4px_0px_0px_#2D4A3E]'
                        }
                    >
                        <div className={'min-w-0'}>
                            <p
                                className={
                                    'text-[10px] font-bold uppercase tracking-[0.24em] text-[color:var(--muted-foreground)]'
                                }
                            >
                                Final Step
                            </p>
                            <p className={'mt-2 truncate text-sm font-black text-[#742220]'}>Ready To Checkout</p>
                            <p
                                className={
                                    'mt-1 text-[11px] uppercase tracking-[0.18em] text-[color:var(--muted-foreground)]'
                                }
                            >
                                Create Invoice
                            </p>
                        </div>
                        <span
                            className={
                                'inline-flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full border-2 border-[#2D4A3E] bg-[#F5EFD5] text-[#742220]'
                            }
                        >
                            <svg
                                xmlns={'http://www.w3.org/2000/svg'}
                                viewBox={'0 0 20 20'}
                                fill={'currentColor'}
                                className={'h-4 w-4'}
                            >
                                <path
                                    fillRule={'evenodd'}
                                    d={
                                        'M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z'
                                    }
                                    clipRule={'evenodd'}
                                />
                            </svg>
                        </span>
                    </div>
                )}
            </div>
        );
    };

    const renderPlanStepContent = () => {
        switch (planStep) {
            case 'node':
                return (
                    <>
                        <div className={'billing-choice-grid'}>
                            {(catalog || []).map((node) => {
                                const isSelected = selectedNode?.id === node.id;
                                const isAvailable = node.availability.isAvailable;

                                return (
                                    <button
                                        key={node.id}
                                        type={'button'}
                                        className={`billing-choice-card ${
                                            isSelected ? 'billing-choice-card-selected' : ''
                                        } ${!isAvailable ? 'billing-choice-card-disabled' : ''}`}
                                        disabled={!isAvailable}
                                        onClick={() => setSelectedNodeId(node.id)}
                                    >
                                        <div className={'flex items-start justify-between gap-3'}>
                                            <div>
                                                <p className={'billing-choice-title'}>{node.displayName}</p>
                                                <p className={'billing-choice-copy'}>
                                                    {node.description || 'Node stock is available for manual billing.'}
                                                </p>
                                            </div>
                                            <span
                                                className={`rounded-full px-3 py-1 text-[10px] font-bold uppercase tracking-[0.22em] ${
                                                    isAvailable
                                                        ? 'billing-status billing-status-active'
                                                        : 'billing-status billing-status-rejected'
                                                }`}
                                            >
                                                {isAvailable ? 'Available' : 'Sold Out'}
                                            </span>
                                        </div>
                                        <div className={'billing-choice-meta-grid'}>
                                            <div>
                                                <span>Eggs</span>
                                                <strong>{node.games.length}</strong>
                                            </div>
                                            <div>
                                                <span>Free Ports</span>
                                                <strong>{node.availability.freeAllocations}</strong>
                                            </div>
                                            <div>
                                                <span>RAM Left</span>
                                                <strong>
                                                    {node.showRemainingCapacity
                                                        ? `${node.availability.memoryRemainingGb} GB`
                                                        : 'Hidden'}
                                                </strong>
                                            </div>
                                            <div>
                                                <span>Storage Left</span>
                                                <strong>
                                                    {node.showRemainingCapacity
                                                        ? `${node.availability.diskRemainingGb} GB`
                                                        : 'Hidden'}
                                                </strong>
                                            </div>
                                        </div>
                                    </button>
                                );
                            })}
                        </div>
                    </>
                );
            case 'nest':
                return (
                    <>
                        <div className={'billing-choice-grid'}>
                            {nestOptions.map((nest) => {
                                const gamesForNest = getGamesForNest(nest.id);
                                const isSelected = selectedNestId === nest.id;

                                return (
                                    <button
                                        key={nest.id}
                                        type={'button'}
                                        className={`billing-choice-card ${
                                            isSelected ? 'billing-choice-card-selected' : ''
                                        }`}
                                        onClick={() => {
                                            setSelectedNestId(nest.id);
                                            setSelectedGameId(null);
                                        }}
                                    >
                                        <div className={'flex items-start justify-between gap-3'}>
                                            <div>
                                                <p className={'billing-choice-title'}>{nest.name}</p>
                                                <p className={'billing-choice-copy'}>
                                                    {gamesForNest.length} server types are available in this nest.
                                                </p>
                                            </div>
                                            <span className={'billing-chip !px-3 !py-1'}>
                                                {gamesForNest.length} Eggs
                                            </span>
                                        </div>
                                    </button>
                                );
                            })}
                        </div>
                        {nestOptions.length < 1 ? (
                            <div className={'billing-empty-card'}>
                                This node does not have any nests available for billing yet.
                            </div>
                        ) : null}
                    </>
                );
            case 'game':
                return (
                    <>
                        <div className={'billing-choice-grid'}>
                            {availableGames.map((game) => {
                                const isSelected = selectedGame?.id === game.id;

                                return (
                                    <button
                                        key={game.id}
                                        type={'button'}
                                        className={`billing-choice-card ${
                                            isSelected ? 'billing-choice-card-selected' : ''
                                        }`}
                                        onClick={() => setSelectedGameId(game.id)}
                                    >
                                        <div className={'flex items-start justify-between gap-3'}>
                                            <div>
                                                <p className={'billing-choice-title'}>{game.displayName}</p>
                                                <p className={'billing-choice-copy'}>
                                                    {game.description ||
                                                        'No description is available for this egg yet.'}
                                                </p>
                                            </div>
                                            <span className={'billing-chip !px-3 !py-1'}>{game.eggName}</span>
                                        </div>
                                    </button>
                                );
                            })}
                        </div>
                        {availableGames.length < 1 ? (
                            <div className={'billing-empty-card'}>
                                There are no server types available in this nest on the selected node.
                            </div>
                        ) : null}
                    </>
                );
            case 'name':
                return (
                    <>
                        <div className={'billing-soft-card !rounded-[20px] !p-5'}>
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
                                placeholder={'My Server'}
                                maxLength={191}
                            />
                            <p className={'mt-3 text-sm leading-7 text-[color:var(--muted-foreground)]'}>
                                Use a clear name so billing staff can identify this order quickly once the invoice is
                                paid and the server is provisioned.
                            </p>
                        </div>
                    </>
                );
            case 'resources':
                return (
                    <>
                        <div className={'mb-4 flex flex-wrap items-center justify-between gap-3'}>
                            <div>
                                <h2 className={'text-xl font-black tracking-tight text-[#742220]'}>Plan Resources</h2>
                                <p className={'mt-1 text-xs text-[color:var(--muted-foreground)]'}>
                                    RAM and storage are tied to live billing stock on the selected node.
                                </p>
                            </div>
                            {selectedNode && selectedNode.showRemainingCapacity ? (
                                <div className={'billing-chip'}>
                                    {selectedNode.availability.memoryRemainingGb} GB RAM Left /{' '}
                                    {selectedNode.availability.diskRemainingGb} GB Storage Left
                                </div>
                            ) : selectedNode ? (
                                <div className={'billing-chip'}>Remaining stock hidden</div>
                            ) : null}
                        </div>

                        <div className={'grid gap-4'}>
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

                        {soldOutReason ? (
                            <div
                                className={
                                    'mt-5 rounded-xl border border-amber-500/25 bg-amber-500/10 px-4 py-3 text-sm text-amber-800'
                                }
                            >
                                {soldOutReason}
                            </div>
                        ) : null}
                    </>
                );
            case 'variables':
                return (
                    <>
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
                                    ? 'This server type does not expose any startup variables that need review.'
                                    : 'Choose a server type first to load startup variables.'}
                            </div>
                        )}
                    </>
                );
            case 'billing':
                return (
                    <>
                        {currentBillingComplete ? (
                            <div
                                style={{
                                    marginBottom: '1.25rem',
                                    borderRadius: '16px',
                                    border: '2px solid #2D4A3E',
                                    background: 'rgba(45, 74, 62, 0.10)',
                                    padding: '10px 16px',
                                    fontSize: '13px',
                                    color: '#2D4A3E',
                                    fontWeight: 600,
                                    boxShadow: '2px 2px 0px 0px #2D4A3E',
                                }}
                            >
                                Billing details are complete. You can still edit anything below before moving to the
                                summary step.
                            </div>
                        ) : (
                            <div
                                style={{
                                    marginBottom: '1.25rem',
                                    borderRadius: '16px',
                                    border: '2px solid rgba(116, 34, 32, 0.40)',
                                    background: 'rgba(116, 34, 32, 0.07)',
                                    padding: '10px 16px',
                                    fontSize: '13px',
                                    color: '#742220',
                                    fontWeight: 600,
                                    boxShadow: '2px 2px 0px 0px rgba(116, 34, 32, 0.30)',
                                }}
                            >
                                Missing right now: {currentBillingMissingLabels}.
                            </div>
                        )}

                        <div className={'grid gap-4 md:grid-cols-2'}>
                            <div>
                                <label
                                    className={
                                        'mb-2 block text-xs font-bold uppercase tracking-[0.22em] text-[color:var(--muted-foreground)]'
                                    }
                                >
                                    Legal Name <span style={{ color: '#742220' }}>*</span>
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
                                    Invoice Email <span style={{ color: '#742220' }}>*</span>
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
                                    Phone <span style={{ color: '#742220' }}>*</span>
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
                                    Address Line 1 <span style={{ color: '#742220' }}>*</span>
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
                                    City <span style={{ color: '#742220' }}>*</span>
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
                                    State <span style={{ color: '#742220' }}>*</span>
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
                                    Postcode <span style={{ color: '#742220' }}>*</span>
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
                                    Country Code <span style={{ color: '#742220' }}>*</span>
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
                            <label
                                className={
                                    'md:col-span-2 flex items-center gap-3 rounded-xl border border-[#C8BCA0] bg-[#F5EFD5] px-4 py-3 text-sm text-[rgba(116,34,32,0.70)]'
                                }
                            >
                                <input
                                    type={'checkbox'}
                                    checked={billingForm.isBusiness}
                                    onChange={(event) =>
                                        onBillingFieldChange('isBusiness', event.currentTarget.checked)
                                    }
                                />
                                Business billing entity
                            </label>
                        </div>
                    </>
                );
            case 'summary':
                return (
                    <>
                        <div className={'grid gap-4 xl:grid-cols-2'}>
                            <div className={'billing-soft-card !rounded-[20px] !p-5'}>
                                <p
                                    className={
                                        'text-[10px] font-bold uppercase tracking-[0.28em] text-[color:var(--muted-foreground)]'
                                    }
                                >
                                    Deployment
                                </p>
                                <div className={'billing-summary-grid'}>
                                    <div className={'billing-summary-row'}>
                                        <span>Node</span>
                                        <strong>{selectedNode?.displayName || '-'}</strong>
                                    </div>
                                    <div className={'billing-summary-row'}>
                                        <span>Nest</span>
                                        <strong>{selectedGame?.nestName || '-'}</strong>
                                    </div>
                                    <div className={'billing-summary-row'}>
                                        <span>Server Type</span>
                                        <strong>{selectedGame?.displayName || '-'}</strong>
                                    </div>
                                    <div className={'billing-summary-row'}>
                                        <span>Server Name</span>
                                        <strong>{serverName || '-'}</strong>
                                    </div>
                                </div>
                            </div>
                            <div className={'billing-soft-card !rounded-[20px] !p-5'}>
                                <p
                                    className={
                                        'text-[10px] font-bold uppercase tracking-[0.28em] text-[color:var(--muted-foreground)]'
                                    }
                                >
                                    Resources
                                </p>
                                <div className={'billing-summary-grid'}>
                                    <div className={'billing-summary-row'}>
                                        <span>vCore</span>
                                        <strong>{cpuCores}</strong>
                                    </div>
                                    <div className={'billing-summary-row'}>
                                        <span>RAM</span>
                                        <strong>{memoryGb} GB</strong>
                                    </div>
                                    <div className={'billing-summary-row'}>
                                        <span>Storage</span>
                                        <strong>{diskGb} GB</strong>
                                    </div>
                                    <div className={'billing-summary-row'}>
                                        <span>Total</span>
                                        <strong className={'text-[color:var(--primary)]'}>{formatMoney(total)}</strong>
                                    </div>
                                </div>
                            </div>
                            <div className={'billing-soft-card !rounded-[20px] !p-5 xl:col-span-2'}>
                                <p
                                    className={
                                        'text-[10px] font-bold uppercase tracking-[0.28em] text-[color:var(--muted-foreground)]'
                                    }
                                >
                                    Billing Details
                                </p>
                                <div className={'billing-summary-grid mt-4'}>
                                    <div className={'billing-summary-row'}>
                                        <span>Legal Name</span>
                                        <strong>{normalizedBillingForm.legalName || '-'}</strong>
                                    </div>
                                    <div className={'billing-summary-row'}>
                                        <span>Email</span>
                                        <strong>{normalizedBillingForm.email || '-'}</strong>
                                    </div>
                                    <div className={'billing-summary-row'}>
                                        <span>Phone</span>
                                        <strong>{normalizedBillingForm.phone || '-'}</strong>
                                    </div>
                                    <div className={'billing-summary-row'}>
                                        <span>Address</span>
                                        <strong>
                                            {[
                                                normalizedBillingForm.addressLine1,
                                                normalizedBillingForm.addressLine2,
                                                normalizedBillingForm.city,
                                                normalizedBillingForm.state,
                                                normalizedBillingForm.postcode,
                                                normalizedBillingForm.countryCode,
                                            ]
                                                .filter(Boolean)
                                                .join(', ') || '-'}
                                        </strong>
                                    </div>
                                </div>
                            </div>
                            <div className={'billing-soft-card !rounded-[20px] !p-5 xl:col-span-2'}>
                                <p
                                    className={
                                        'text-[10px] font-bold uppercase tracking-[0.28em] text-[color:var(--muted-foreground)]'
                                    }
                                >
                                    Startup Variables
                                </p>
                                {selectedGame && selectedGame.variables.length > 0 ? (
                                    <div className={'mt-4 grid gap-3 md:grid-cols-2'}>
                                        {selectedGame.variables.map((variable: BillingGameVariable) => (
                                            <div
                                                key={variable.envVariable}
                                                className={'billing-summary-row !items-start'}
                                            >
                                                <span>{variable.name}</span>
                                                <strong className={'text-right'}>
                                                    {variables[variable.envVariable] ||
                                                        variable.serverValue ||
                                                        variable.defaultValue ||
                                                        '-'}
                                                </strong>
                                            </div>
                                        ))}
                                    </div>
                                ) : (
                                    <p className={'mt-4 text-sm text-[color:var(--muted-foreground)]'}>
                                        No startup variables need to be reviewed for this server type.
                                    </p>
                                )}
                            </div>
                        </div>
                        <div
                            className={
                                'mt-6 rounded-xl border border-[#C8BCA0] bg-[#F5EFD5] px-4 py-3 text-sm text-[color:var(--muted-foreground)]'
                            }
                        >
                            Checkout creates a manual invoice immediately. Provisioning starts only after billing staff
                            marks the invoice as paid.
                        </div>
                    </>
                );
        }
    };

    const renderPlanFooterActions = () => {
        if (planStep === 'billing') {
            return renderPlanWizardActions({
                continueLabel: 'Save & Continue',
                continueDisabled: billingSaving,
                onContinue: goToNextPlanStep,
            });
        }

        if (planStep === 'summary') {
            return renderPlanWizardActions({
                continueLabel: submitting ? 'Creating Invoice...' : 'Checkout',
                continueDisabled: submitting,
                onContinue: submitPlanCheckout,
            });
        }

        return renderPlanWizardActions({ onContinue: goToNextPlanStep });
    };

    const renderPlanSidebar = () => (
        <aside className={'w-full min-w-0 space-y-0'}>
            <section className={'billing-panel p-4 xl:p-3.5'}>
                <div className={'flex items-start justify-between gap-3'}>
                    <div>
                        <p
                            className={
                                'text-[10px] font-bold uppercase tracking-[0.3em] text-[color:var(--muted-foreground)]'
                            }
                        >
                            Live Preview
                        </p>
                        <h2 className={'mt-1 text-lg font-black tracking-tight text-[#742220] xl:text-[1.05rem]'}>
                            {serverName.trim() || selectedGame?.displayName || 'Plan Preview'}
                        </h2>
                        <p className={'mt-1 text-[10px] leading-5 text-[color:var(--muted-foreground)]'}>
                            {selectedNode?.displayName || 'No node selected'} {'•'}{' '}
                            {selectedGame?.nestName || 'No software family selected'}
                        </p>
                    </div>
                    <div
                        className={
                            'rounded-xl border-2 border-[#742220] bg-[#FEF9E1] px-3 py-2 text-right shadow-[2px_2px_0px_0px_#742220]'
                        }
                    >
                        <p
                            className={
                                'text-[10px] font-bold uppercase tracking-[0.24em] text-[color:var(--muted-foreground)]'
                            }
                        >
                            Total
                        </p>
                        <p className={'mt-0.5 text-lg font-black text-[color:var(--primary)] xl:text-[1.1rem]'}>
                            {formatMoney(total)}
                        </p>
                    </div>
                </div>

                <div className={'mt-3 grid gap-2'}>
                    <div className={'billing-soft-card !rounded-[16px] !p-3'}>
                        <p
                            className={
                                'text-[10px] font-bold uppercase tracking-[0.24em] text-[color:var(--muted-foreground)]'
                            }
                        >
                            vCore
                        </p>
                        <div className={'mt-1.5 flex items-center justify-between gap-3'}>
                            <strong className={'text-sm text-[#742220]'}>{cpuCores} vCore</strong>
                        </div>
                        <div className={'mt-1 text-[13px] text-[color:var(--muted-foreground)]'}>
                            {formatMoney(cpuTotal)}
                        </div>
                    </div>
                    <div className={'billing-soft-card !rounded-[16px] !p-3'}>
                        <p
                            className={
                                'text-[10px] font-bold uppercase tracking-[0.24em] text-[color:var(--muted-foreground)]'
                            }
                        >
                            RAM
                        </p>
                        <div className={'mt-1.5 flex items-center justify-between gap-3'}>
                            <strong className={'text-sm text-[#742220]'}>{memoryGb} GB</strong>
                        </div>
                        <div className={'mt-1 text-[13px] text-[color:var(--muted-foreground)]'}>
                            {formatMoney(memoryTotal)}
                        </div>
                    </div>
                    <div className={'billing-soft-card !rounded-[16px] !p-3'}>
                        <p
                            className={
                                'text-[10px] font-bold uppercase tracking-[0.24em] text-[color:var(--muted-foreground)]'
                            }
                        >
                            Storage
                        </p>
                        <div className={'mt-1.5 flex items-center justify-between gap-3'}>
                            <strong className={'text-sm text-[#742220]'}>{diskGb} GB</strong>
                        </div>
                        <div className={'mt-1 text-[13px] text-[color:var(--muted-foreground)]'}>
                            {formatMoney(diskTotal)} {'•'} {diskUnits} x 10 GB block
                        </div>
                    </div>

                    <div className={'mt-3 billing-soft-card !rounded-2xl !p-3.5'}>
                        <p
                            className={
                                'text-[10px] font-bold uppercase tracking-[0.28em] text-[color:var(--muted-foreground)]'
                            }
                        >
                            Plan Defaults
                        </p>
                        <div className={'mt-2.5 grid gap-2 text-[12px]'}>
                            <div className={'flex items-center justify-between gap-3'}>
                                <span className={'text-[color:var(--muted-foreground)]'}>Allocations</span>
                                <span className={'font-semibold text-[#742220]'}>
                                    {selectedNode?.defaults.allocationLimit ?? 0}
                                </span>
                            </div>
                            <div className={'flex items-center justify-between gap-3'}>
                                <span className={'text-[color:var(--muted-foreground)]'}>Databases</span>
                                <span className={'font-semibold text-[#742220]'}>
                                    {selectedNode?.defaults.databaseLimit ?? 0}
                                </span>
                            </div>
                            <div className={'flex items-center justify-between gap-3'}>
                                <span className={'text-[color:var(--muted-foreground)]'}>Backups</span>
                                <span className={'font-semibold text-[#742220]'}>
                                    {selectedNode?.defaults.backupLimit ?? 0}
                                </span>
                            </div>
                            <div className={'flex items-center justify-between gap-3'}>
                                <span className={'text-[color:var(--muted-foreground)]'}>Split Limit</span>
                                <span className={'font-semibold text-[#742220]'}>
                                    {selectedNode?.defaults.splitLimit ?? 0}
                                </span>
                            </div>
                            <div className={'flex items-center justify-between gap-3'}>
                                <span className={'text-[color:var(--muted-foreground)]'}>Swap</span>
                                <span className={'font-semibold text-[#742220]'}>
                                    {selectedNode ? `${selectedNode.defaults.swapMb} MB` : '0 MB'}
                                </span>
                            </div>
                            <div className={'flex items-center justify-between gap-3'}>
                                <span className={'text-[color:var(--muted-foreground)]'}>IO Weight</span>
                                <span className={'font-semibold text-[#742220]'}>
                                    {selectedNode?.defaults.ioWeight ?? 0}
                                </span>
                            </div>
                            <div className={'flex items-center justify-between gap-3'}>
                                <span className={'text-[color:var(--muted-foreground)]'}>OOM Killer</span>
                                <span className={'font-semibold text-[#742220]'}>
                                    {selectedNode?.defaults.oomDisabled ? 'Disabled' : 'Enabled'}
                                </span>
                            </div>
                            <div className={'flex items-center justify-between gap-3'}>
                                <span className={'text-[color:var(--muted-foreground)]'}>Start On Completion</span>
                                <span className={'font-semibold text-[#742220]'}>
                                    {selectedNode?.defaults.startOnCompletion ? 'Yes' : 'No'}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div className={'mt-3 billing-soft-card !rounded-2xl !p-3.5'}>
                        <div className={'flex items-start justify-between gap-3'}>
                            <p
                                className={
                                    'text-[10px] font-bold uppercase tracking-[0.28em] text-[color:var(--muted-foreground)]'
                                }
                            >
                                Node Availability
                            </p>
                            <span
                                className={`rounded-full px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-[0.22em] ${
                                    selectedNode?.availability.isAvailable
                                        ? 'billing-status billing-status-active'
                                        : 'billing-status billing-status-rejected'
                                }`}
                            >
                                {selectedNode?.availability.isAvailable ? 'Available' : 'Sold Out'}
                            </span>
                        </div>
                        <div className={'mt-2.5 grid gap-2 text-[12px]'}>
                            <div className={'flex items-center justify-between gap-3'}>
                                <span className={'text-[color:var(--muted-foreground)]'}>Max vCore / Order</span>
                                <span className={'font-semibold text-[#742220]'}>
                                    {selectedNode?.limits.maxCpu ?? 0}
                                </span>
                            </div>
                            <div className={'flex items-center justify-between gap-3'}>
                                <span className={'text-[color:var(--muted-foreground)]'}>RAM Remaining</span>
                                <span className={'font-semibold text-[#742220]'}>
                                    {selectedNode?.showRemainingCapacity
                                        ? `${selectedNode.availability.memoryRemainingGb} GB`
                                        : 'Hidden'}
                                </span>
                            </div>
                            <div className={'flex items-center justify-between gap-3'}>
                                <span className={'text-[color:var(--muted-foreground)]'}>Storage Remaining</span>
                                <span className={'font-semibold text-[#742220]'}>
                                    {selectedNode?.showRemainingCapacity
                                        ? `${selectedNode.availability.diskRemainingGb} GB`
                                        : 'Hidden'}
                                </span>
                            </div>
                            <div className={'flex items-center justify-between gap-3'}>
                                <span className={'text-[color:var(--muted-foreground)]'}>Free Allocations</span>
                                <span className={'font-semibold text-[#742220]'}>
                                    {selectedNode?.availability.freeAllocations ?? 0}
                                </span>
                            </div>
                        </div>
                        {!selectedNode?.showRemainingCapacity && selectedNode ? (
                            <p className={'mt-2.5 text-[10px] leading-5 text-[color:var(--muted-foreground)]'}>
                                This billing node hides live RAM and storage remaining. Stock is still enforced during
                                checkout.
                            </p>
                        ) : null}
                    </div>
                </div>

                {soldOutReason ? (
                    <div
                        className={
                            'mt-2.5 rounded-xl border border-amber-500/25 bg-amber-500/10 px-3 py-2.5 text-[12px] text-amber-800'
                        }
                    >
                        {soldOutReason}
                    </div>
                ) : null}
            </section>
        </aside>
    );

    const renderInvoiceCard = (invoice: BillingInvoice, receiptMode = false) => {
        const latestPayment = invoice.payments[0] || null;
        const latestRefund = latestPayment?.refunds[0] || null;
        const canRetryPayment =
            invoice.provider !== 'manual' &&
            !invoice.paidAt &&
            ['open', 'draft', 'failed', 'processing'].includes(invoice.status);

        return (
            <article key={invoice.id} className={'billing-subscription-card'}>
                <div className={'flex flex-wrap items-start justify-between gap-4'}>
                    <div>
                        <div className={'flex flex-wrap items-center gap-3'}>
                            <h3 className={'billing-subscription-title'}>{invoice.invoiceNumber}</h3>
                            <span
                                className={`rounded-full px-3 py-1 text-[10px] font-bold uppercase tracking-[0.22em] ${getInvoiceStatusClasses(
                                    invoice.status
                                )}`}
                            >
                                {getOrderStatusLabel(invoice.status)}
                            </span>
                        </div>
                        <p className={'mt-2 text-xs text-[color:var(--muted-foreground)]'}>
                            {receiptMode ? 'Payment Receipt' : getOrderStatusLabel(invoice.type)} {'•'} Issued{' '}
                            {invoice.issuedAt ? invoice.issuedAt.toLocaleString() : 'Unknown'}
                        </p>
                    </div>
                    <div className={'text-right'}>
                        <p
                            className={
                                'text-[10px] font-bold uppercase tracking-[0.26em] text-[color:var(--muted-foreground)]'
                            }
                        >
                            {receiptMode ? 'Receipt Total' : 'Invoice Total'}
                        </p>
                        <p className={'mt-2 text-2xl font-black text-[color:var(--primary)]'}>
                            {formatMoney(invoice.grandTotal)}
                        </p>
                    </div>
                </div>

                <div className={'mt-5 grid gap-3 text-sm text-[color:var(--muted-foreground)] lg:grid-cols-2'}>
                    <div className={'flex items-center justify-between gap-3'}>
                        <span>Due At</span>
                        <span className={'font-semibold text-[#742220]'}>
                            {invoice.dueAt ? invoice.dueAt.toLocaleString() : 'Unknown'}
                        </span>
                    </div>
                    <div className={'flex items-center justify-between gap-3'}>
                        <span>Paid At</span>
                        <span className={'font-semibold text-[#742220]'}>
                            {invoice.paidAt ? invoice.paidAt.toLocaleString() : 'Not paid yet'}
                        </span>
                    </div>
                    <div className={'flex items-center justify-between gap-3'}>
                        <span>Amount</span>
                        <span className={'font-semibold text-[color:var(--primary)]'}>
                            {formatMoney(invoice.grandTotal)}
                        </span>
                    </div>
                    <div className={'flex items-center justify-between gap-3'}>
                        <span>Provider</span>
                        <span className={'font-semibold text-[#742220]'}>
                            {invoice.provider ? invoice.provider.toUpperCase() : 'Manual'}
                        </span>
                    </div>
                    {receiptMode && latestPayment && (
                        <div className={'flex items-center justify-between gap-3'}>
                            <span>Payment Method</span>
                            <span className={'font-semibold text-[#742220]'}>
                                {latestPayment.paymentMethodBrand && latestPayment.paymentMethodLast4
                                    ? `${latestPayment.paymentMethodBrand.toUpperCase()} •••• ${
                                          latestPayment.paymentMethodLast4
                                      }`
                                    : latestPayment.providerPaymentMethod || latestPayment.provider || 'Recorded'}
                            </span>
                        </div>
                    )}
                    {invoice.providerStatus && (
                        <div className={'flex items-center justify-between gap-3'}>
                            <span>Gateway Status</span>
                            <span className={'font-semibold text-[#742220]'}>
                                {getOrderStatusLabel(invoice.providerStatus)}
                            </span>
                        </div>
                    )}
                    {receiptMode && latestRefund && (
                        <div className={'flex items-center justify-between gap-3 lg:col-span-2'}>
                            <span>Latest Refund</span>
                            <span className={'font-semibold text-amber-200'}>
                                {latestRefund.refundNumber} • {getOrderStatusLabel(latestRefund.status)}
                            </span>
                        </div>
                    )}
                </div>

                {!receiptMode && !latestPayment && !invoice.paidAt && invoice.provider === 'manual' && (
                    <div
                        className={
                            'mt-4 rounded-xl border border-amber-500/25 bg-amber-500/10 px-4 py-3 text-sm leading-6 text-amber-800'
                        }
                    >
                        Waiting for billing admin to confirm manual payment before this invoice can be marked paid.
                    </div>
                )}

                <div className={'mt-5 flex flex-wrap items-center gap-3'}>
                    {!receiptMode && canRetryPayment && (
                        <button
                            className={'billing-primary-btn'}
                            disabled={retryingInvoiceId === invoice.id}
                            onClick={() => void performRetryInvoicePayment(invoice.id)}
                        >
                            {retryingInvoiceId === invoice.id ? 'Redirecting...' : 'Pay Now'}
                        </button>
                    )}
                    {invoice.hostedInvoiceUrl && !receiptMode && (
                        <a href={invoice.hostedInvoiceUrl} className={'billing-secondary-btn'}>
                            Open Hosted Invoice
                        </a>
                    )}
                    {invoice.invoicePdfUrl && !receiptMode && (
                        <a
                            href={invoice.invoicePdfUrl}
                            className={'billing-ghost-btn'}
                            target={'_blank'}
                            rel={'noreferrer'}
                        >
                            Invoice PDF
                        </a>
                    )}
                    {receiptMode && latestPayment?.receiptPdfUrl && (
                        <a
                            href={latestPayment.receiptPdfUrl}
                            className={'billing-secondary-btn'}
                            target={'_blank'}
                            rel={'noreferrer'}
                        >
                            Receipt PDF
                        </a>
                    )}
                    {receiptMode && !latestPayment?.receiptPdfUrl && (
                        <p className={'text-xs leading-6 text-[color:var(--muted-foreground)]'}>
                            Receipt PDF is not available yet, but this invoice has recorded payment activity.
                        </p>
                    )}
                </div>
            </article>
        );
    };

    const renderBillingOrderCard = (order: BillingOrder) => (
        <article key={order.id} className={'billing-subscription-card'}>
            <div className={'flex flex-wrap items-start justify-between gap-4'}>
                <div>
                    <div className={'flex flex-wrap items-center gap-3'}>
                        <h3 className={'billing-subscription-title'}>{order.serverName}</h3>
                        <span
                            className={`rounded-full px-3 py-1 text-[10px] font-bold uppercase tracking-[0.22em] ${getOrderStatusClasses(
                                order.status
                            )}`}
                        >
                            {getOrderStatusLabel(order.status)}
                        </span>
                    </div>
                    <p className={'mt-2 text-xs text-[color:var(--muted-foreground)]'}>
                        Order #{order.id} {'•'} {order.nodeName} {'•'} {order.gameName}
                    </p>
                </div>
                <div className={'text-right'}>
                    <p
                        className={
                            'text-[10px] font-bold uppercase tracking-[0.26em] text-[color:var(--muted-foreground)]'
                        }
                    >
                        Order Total
                    </p>
                    <p className={'mt-2 text-2xl font-black text-[color:var(--primary)]'}>{formatMoney(order.total)}</p>
                </div>
            </div>

            <div className={'mt-5 grid gap-3 text-sm text-[color:var(--muted-foreground)] lg:grid-cols-2'}>
                <div className={'flex items-center justify-between gap-3'}>
                    <span>Resources</span>
                    <span className={'font-semibold text-[#742220]'}>
                        {order.cpuCores} vCore / {order.memoryGb} GB / {order.diskGb} GB
                    </span>
                </div>
                <div className={'flex items-center justify-between gap-3'}>
                    <span>Placed</span>
                    <span className={'font-semibold text-[#742220]'}>
                        {order.createdAt ? order.createdAt.toLocaleString() : 'Unknown'}
                    </span>
                </div>
                <div className={'flex items-center justify-between gap-3'}>
                    <span>Payment Verified</span>
                    <span className={'font-semibold text-[#742220]'}>
                        {order.paymentVerifiedAt ? order.paymentVerifiedAt.toLocaleString() : 'Pending'}
                    </span>
                </div>
                <div className={'flex items-center justify-between gap-3'}>
                    <span>Provisioned</span>
                    <span className={'font-semibold text-[#742220]'}>
                        {order.provisionedAt ? order.provisionedAt.toLocaleString() : 'Not provisioned yet'}
                    </span>
                </div>
            </div>

            {order.adminNotes && (
                <div
                    className={
                        'mt-4 rounded-xl border border-[color:var(--border)] bg-[color:var(--card)] px-4 py-3 text-sm leading-6 text-[color:var(--muted-foreground)]'
                    }
                >
                    {order.adminNotes}
                </div>
            )}

            {order.provisionFailureMessage && (
                <div
                    className={
                        'mt-4 rounded-xl border border-red-500/25 bg-red-500/10 px-4 py-3 text-sm leading-6 text-red-100'
                    }
                >
                    {order.provisionFailureCode ? `${order.provisionFailureCode}: ` : ''}
                    {order.provisionFailureMessage}
                </div>
            )}
        </article>
    );

    const renderInvoiceSection = (
        title: string,
        copy: string,
        items: BillingInvoice[],
        currentPage: number,
        onPageChange: (page: number) => void,
        loading: boolean,
        emptyMessage: string,
        receiptMode = false
    ) => (
        <section className={'billing-panel billing-section-shell p-6 md:p-8'}>
            <div className={'mb-5 flex flex-wrap items-center justify-between gap-4'}>
                <div>
                    <h2 className={'text-2xl font-black tracking-tight text-[#742220]'}>{title}</h2>
                    <p className={'mt-2 text-sm text-[color:var(--muted-foreground)]'}>{copy}</p>
                </div>
                {items.length > 0 ? (
                    <BillingPagination
                        currentPage={currentPage}
                        onPageChange={onPageChange}
                        pageSize={INVOICES_PAGE_SIZE}
                        totalItems={items.length}
                    />
                ) : loading && items ? (
                    <Spinner size={Spinner.Size.SMALL} />
                ) : null}
            </div>

            <div className={'billing-section-scroll'}>
                {!invoices && loading ? (
                    <Spinner centered />
                ) : items.length > 0 ? (
                    <div className={'grid gap-4'}>
                        {paginateItems(items, currentPage, INVOICES_PAGE_SIZE).map((invoice) =>
                            renderInvoiceCard(invoice, receiptMode)
                        )}
                    </div>
                ) : (
                    <div className={'billing-empty-card'}>{emptyMessage}</div>
                )}
            </div>
        </section>
    );

    if (currentSection === 'plan' && !catalog && catalogLoading) {
        return (
            <div className={'min-h-screen bg-[#D6D2C7] px-6 py-8 text-[#742220] md:px-10'}>
                <Spinner centered size={Spinner.Size.LARGE} />
            </div>
        );
    }

    return (
        <div
            className={`billing-shell h-full min-h-0 px-4 pb-8 pt-6 text-white md:px-8 md:pt-8 ${
                isPlanSection ? 'billing-shell-plan' : ''
            } ${isListSection ? 'billing-shell-list' : ''}`}
        >
            <style>{`
                .billing-shell {
                    position: relative;
                    height: 100%;
                    min-height: 0;
                    overflow-y: auto;
                    overflow-x: hidden;
                    -webkit-overflow-scrolling: touch;
                    display: flex;
                    flex-direction: column;
                    background: #D6D2C7;
                    font-family: var(--font-sans, 'Inter', sans-serif);
                    --surface-elevated: #FEF9E1;
                    --surface-border: #C8BCA0;
                    --surface-subtle: #F5EFD5;
                    --surface-subtle-strong: #EDE6D0;
                }

                .billing-shell::before {
                    content: '';
                    position: fixed;
                    inset: 0;
                    pointer-events: none;
                    z-index: 9999;
                    background-image:
                        repeating-linear-gradient(0deg, transparent, transparent 4.5px, rgba(116, 34, 32, 0.05) 4.5px, rgba(116, 34, 32, 0.05) 5px),
                        repeating-linear-gradient(60deg, transparent, transparent 4.5px, rgba(116, 34, 32, 0.05) 4.5px, rgba(116, 34, 32, 0.05) 5px),
                        repeating-linear-gradient(120deg, transparent, transparent 4.5px, rgba(116, 34, 32, 0.05) 4.5px, rgba(116, 34, 32, 0.05) 5px);
                }

                .billing-shell::after {
                    display: none;
                }

                .billing-wrap {
                    position: relative;
                    z-index: 2;
                    width: 100%;
                    min-height: 0;
                }

                .billing-wrap-plan {
                    display: flex;
                    flex: 1 1 0%;
                    flex-direction: column;
                    min-height: 0;
                    overflow: hidden;
                }

                .billing-wrap-list {
                    display: flex;
                    flex: 1 1 0%;
                    flex-direction: column;
                    min-height: 0;
                    overflow: hidden;
                }

                .billing-plan-layout {
                    display: grid;
                    min-height: 0;
                    gap: 1.5rem;
                    height: 100%;
                }

                .billing-plan-main {
                    min-width: 0;
                    display: flex;
                    flex-direction: column;
                    min-height: 0;
                }

                .billing-plan-main-body {
                    flex: 1 1 0%;
                    min-height: 0;
                    display: flex;
                    flex-direction: column;
                }

                .billing-plan-aside {
                    min-width: 0;
                    min-height: 0;
                    align-self: stretch;
                }

                .billing-plan-aside > aside {
                    height: 100%;
                }

                .billing-plan-aside .billing-panel {
                    height: 100%;
                }

                .billing-section-shell {
                    display: flex;
                    flex: 1 1 0%;
                    flex-direction: column;
                    min-height: 0;
                    overflow: hidden;
                }

                .billing-section-scroll {
                    flex: 1 1 0%;
                    min-height: 0;
                    overflow-x: hidden;
                    overflow-y: auto;
                    scrollbar-width: none;
                    -ms-overflow-style: none;
                }

                .billing-section-scroll::-webkit-scrollbar {
                    display: none;
                    width: 0;
                    height: 0;
                }

                .billing-choice-grid {
                    display: grid;
                    gap: 1rem;
                }

                .billing-choice-card {
                    width: 100%;
                    border-radius: 12px;
                    border: 2px solid #2D4A3E;
                    background: #FEF9E1;
                    padding: 1.15rem;
                    text-align: left;
                    box-shadow: 4px 4px 0px 0px #2D4A3E;
                    transition: transform 0.15s ease, box-shadow 0.15s ease;
                }

                .billing-choice-card:hover:not(:disabled) {
                    transform: translate(-2px, -2px);
                    box-shadow: 6px 6px 0px 0px #2D4A3E;
                }

                .billing-choice-card-selected {
                    border-color: #742220;
                    box-shadow: 4px 4px 0px 0px #742220;
                }

                .billing-choice-card-disabled {
                    cursor: not-allowed;
                    opacity: 0.52;
                }

                .billing-choice-title {
                    font-size: 1rem;
                    font-weight: 900;
                    color: #742220;
                }

                .billing-choice-copy {
                    margin-top: 0.45rem;
                    font-size: 0.86rem;
                    line-height: 1.7;
                    color: rgba(116, 34, 32, 0.55);
                }

                .billing-choice-meta-grid {
                    display: grid;
                    gap: 0.75rem;
                    margin-top: 1rem;
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                }

                .billing-choice-meta-grid > div {
                    border-radius: 8px;
                    border: 1px solid #C8BCA0;
                    background: #F5EFD5;
                    padding: 0.75rem;
                }

                .billing-choice-meta-grid span {
                    display: block;
                    font-size: 0.62rem;
                    font-weight: 800;
                    letter-spacing: 0.16em;
                    text-transform: uppercase;
                    color: rgba(116, 34, 32, 0.50);
                }

                .billing-choice-meta-grid strong {
                    display: block;
                    margin-top: 0.35rem;
                    font-size: 0.92rem;
                    color: #742220;
                }

                .billing-plan-footer {
                    flex-shrink: 0;
                    margin-top: 0;
                    padding-top: 0.25rem;
                }

                .billing-wizard-actions {
                    display: flex;
                    flex-wrap: wrap;
                    align-items: center;
                    justify-content: space-between;
                    gap: 0.9rem;
                    margin-top: 0;
                    padding-top: 1.25rem;
                    border-top: 1px solid #C8BCA0;
                }

                .billing-step-row {
                    display: flex;
                    align-items: flex-start;
                    gap: 0.85rem;
                    border-radius: 8px;
                    border: 1px solid #C8BCA0;
                    background: #F5EFD5;
                    padding: 0.85rem 0.9rem;
                }

                .billing-step-row-active {
                    border-color: #742220;
                    background: rgba(116, 34, 32, 0.06);
                }

                .billing-step-row-complete {
                    box-shadow: inset 0 0 0 1px rgba(116, 34, 32, 0.08);
                }

                .billing-step-index {
                    display: inline-flex;
                    height: 2rem;
                    width: 2rem;
                    flex-shrink: 0;
                    align-items: center;
                    justify-content: center;
                    border-radius: 999px;
                    border: 2px solid #2D4A3E;
                    background: #F5EFD5;
                    font-size: 0.75rem;
                    font-weight: 900;
                    color: #742220;
                }

                .billing-step-label {
                    font-size: 0.9rem;
                    font-weight: 800;
                    color: #742220;
                }

                .billing-step-copy {
                    margin-top: 0.25rem;
                    font-size: 0.72rem;
                    letter-spacing: 0.08em;
                    text-transform: uppercase;
                    color: rgba(116, 34, 32, 0.50);
                }

                .billing-summary-grid {
                    display: grid;
                    gap: 0.75rem;
                }

                .billing-summary-row {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 1rem;
                    border-radius: 8px;
                    border: 1px solid #C8BCA0;
                    background: #F5EFD5;
                    padding: 0.85rem 0.95rem;
                    color: rgba(116, 34, 32, 0.55);
                }

                .billing-summary-row strong {
                    color: #742220;
                }

                .billing-panel {
                    border-radius: 12px;
                    border: 2px solid #2D4A3E;
                    background: #FEF9E1;
                    box-shadow: 4px 4px 0px 0px #2D4A3E;
                }

                .billing-status {
                    border-radius: 999px;
                    border-width: 1px;
                }

                .billing-status-active {
                    border-color: rgba(16, 185, 129, 0.45);
                    background: rgba(16, 185, 129, 0.15);
                    color: #166534;
                }

                .billing-status-provisioning {
                    border-color: rgba(56, 189, 248, 0.45);
                    background: rgba(56, 189, 248, 0.14);
                    color: #0369a1;
                }

                .billing-status-suspended {
                    border-color: rgba(245, 158, 11, 0.45);
                    background: rgba(245, 158, 11, 0.14);
                    color: #92400e;
                }

                .billing-status-rejected {
                    border-color: rgba(239, 68, 68, 0.45);
                    background: rgba(239, 68, 68, 0.14);
                    color: #991b1b;
                }

                .billing-status-deleted {
                    border-color: rgba(115, 115, 115, 0.45);
                    background: rgba(115, 115, 115, 0.14);
                    color: #525252;
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
                    border-color: #5a1a18;
                    border-width: 2px;
                    background: #742220;
                    color: #FEF9E1;
                    box-shadow: 3px 3px 0px 0px #5a1a18;
                }

                .billing-primary-btn:hover:not(:disabled) {
                    transform: translate(-1px, -1px);
                    filter: none;
                    box-shadow: 5px 5px 0px 0px #5a1a18;
                }

                .billing-secondary-btn {
                    border-color: #2D4A3E;
                    border-width: 2px;
                    background: #F5EFD5;
                    color: #2D4A3E;
                }

                .billing-secondary-btn:hover:not(:disabled) {
                    border-color: #2D4A3E;
                    background: #E5F0EB;
                    color: #1F3A30;
                }

                .billing-ghost-btn {
                    border-color: #C8BCA0;
                    border-width: 2px;
                    background: transparent;
                    color: rgba(116, 34, 32, 0.60);
                }

                .billing-ghost-btn:hover:not(:disabled) {
                    border-color: #2D4A3E;
                    color: #742220;
                    background: rgba(45, 74, 62, 0.08);
                }

                .billing-primary-btn:disabled,
                .billing-secondary-btn:disabled,
                .billing-ghost-btn:disabled {
                    cursor: not-allowed;
                    opacity: 0.5;
                }

                .billing-slider-card {
                    border-radius: 8px;
                    border: 2px solid #2D4A3E;
                    background: #FEF9E1;
                    padding: 16px;
                    box-shadow: 3px 3px 0px 0px #2D4A3E;
                }

                .billing-slider-value {
                    display: inline-flex;
                    min-height: 46px;
                    width: 92px;
                    align-items: center;
                    justify-content: center;
                    border-radius: 999px;
                    border: 2px solid #2D4A3E;
                    background: #F5EFD5;
                    padding: 0 10px;
                    font-size: 0.9rem;
                    font-weight: 900;
                    color: #742220;
                    text-align: center;
                    appearance: textfield;
                    outline: none;
                    box-shadow: none;
                }

                .billing-slider-value::-webkit-outer-spin-button,
                .billing-slider-value::-webkit-inner-spin-button {
                    margin: 0;
                    -webkit-appearance: none;
                }

                .billing-slider-input::-webkit-slider-runnable-track {
                    height: 8px;
                    border-radius: 999px;
                    background: linear-gradient(
                        90deg,
                        #742220 0%,
                        #742220 var(--track-pct, 0%),
                        #C8BCA0 var(--track-pct, 0%),
                        #C8BCA0 100%
                    );
                }

                .billing-slider-input::-moz-range-track {
                    height: 8px;
                    border-radius: 999px;
                    background: linear-gradient(
                        90deg,
                        #742220 0%,
                        #742220 var(--track-pct, 0%),
                        #C8BCA0 var(--track-pct, 0%),
                        #C8BCA0 100%
                    );
                }

                .billing-slider-input::-webkit-slider-thumb {
                    -webkit-appearance: none;
                    appearance: none;
                    width: 20px;
                    height: 20px;
                    margin-top: -6px;
                    border-radius: 999px;
                    background: #742220;
                    border: 2px solid #5a1a18;
                    box-shadow: 2px 2px 0px 0px #5a1a18;
                    cursor: pointer;
                    transition: transform 0.15s ease, box-shadow 0.15s ease;
                }

                .billing-slider-input::-webkit-slider-thumb:hover {
                    transform: scale(1.15);
                    box-shadow: 3px 3px 0px 0px #5a1a18;
                }

                .billing-slider-input::-moz-range-thumb {
                    width: 20px;
                    height: 20px;
                    border-radius: 999px;
                    background: #742220;
                    border: 2px solid #5a1a18;
                    box-shadow: 2px 2px 0px 0px #5a1a18;
                    cursor: pointer;
                }

                .billing-subscription-card {
                    border-radius: 12px;
                    border: 2px solid #2D4A3E;
                    background: #FEF9E1;
                    padding: 1.25rem;
                    box-shadow: 4px 4px 0px 0px #2D4A3E;
                }

                .billing-subscription-title {
                    font-size: 1.45rem;
                    font-weight: 900;
                    letter-spacing: 0.01em;
                    color: #742220;
                }

                .billing-upgrade-panel {
                    margin-top: 1.25rem;
                    border-radius: 8px;
                    border: 1px solid #C8BCA0;
                    background: #F5EFD5;
                    padding: 1.25rem;
                }

                .billing-upgrade-summary {
                    margin-top: 1.25rem;
                    border-radius: 8px;
                    border: 1px solid #C8BCA0;
                    background: #F5EFD5;
                    padding: 1rem;
                }

                .billing-variable-card {
                    border-radius: 8px;
                    border: 2px solid #2D4A3E;
                    background: #FEF9E1;
                    overflow: hidden;
                    box-shadow: 3px 3px 0px 0px #2D4A3E;
                }

                .billing-variable-head {
                    border-bottom: 1px solid #C8BCA0;
                    background: #F5EFD5;
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
                    color: #742220;
                }

                .billing-variable-badge {
                    border-radius: 999px;
                    border: 1px solid #C8BCA0;
                    background: #EDE6D0;
                    padding: 0.14rem 0.52rem;
                    font-size: 0.6rem;
                    font-weight: 800;
                    letter-spacing: 0.07em;
                }

                .billing-variable-body {
                    padding: 0.85rem;
                    color: #742220;
                }

                .billing-chip {
                    border-radius: 999px;
                    border: 1px solid #C8BCA0;
                    background: #EDE6D0;
                    padding: 0.5rem 0.9rem;
                    font-size: 11px;
                    font-weight: 800;
                    letter-spacing: 0.18em;
                    text-transform: uppercase;
                    color: rgba(116, 34, 32, 0.60);
                }

                .billing-soft-card {
                    border-radius: 8px;
                    border: 1px solid #C8BCA0;
                    background: #F5EFD5;
                    padding: 0.95rem;
                }

                .billing-empty-card {
                    border-radius: 16px;
                    border: 1px dashed rgba(116, 34, 32, 0.22);
                    background: var(--surface-subtle);
                    padding: 1.5rem 1rem;
                    text-align: center;
                    font-size: 0.88rem;
                    color: rgba(116, 34, 32, 0.55);
                }

                .billing-order-card {
                    border-radius: 12px;
                    border: 2px solid #2D4A3E;
                    background: #FEF9E1;
                    padding: 1.1rem;
                    box-shadow: 4px 4px 0px 0px #2D4A3E;
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
                    color: rgba(116, 34, 32, 0.50);
                }

                .billing-pagination-value {
                    font-weight: 700;
                    color: #742220;
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
                    border-radius: 8px;
                    border: 2px solid #C8BCA0;
                    background: #EDE6D0;
                    color: rgba(116, 34, 32, 0.70);
                    font-size: 0.82rem;
                    font-weight: 800;
                    transition: all 0.15s ease;
                }

                .billing-page-btn:hover:not(:disabled) {
                    border-color: #2D4A3E;
                    background: #F5EFD5;
                    color: #742220;
                }

                .billing-page-btn:disabled {
                    cursor: not-allowed;
                    opacity: 0.45;
                }

                .billing-page-btn-active {
                    border-color: #5a1a18;
                    background: #742220;
                    color: #FEF9E1;
                    box-shadow: 2px 2px 0px 0px #5a1a18;
                }

                .billing-gate-modal {
                    border-radius: 12px;
                    border: 2px solid #2D4A3E;
                    background: #FEF9E1;
                    padding: 1.25rem;
                    box-shadow: 4px 4px 0px 0px #2D4A3E;
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
                    color: #742220;
                }

                .billing-gate-copy {
                    margin-top: 10px;
                    font-size: 0.9rem;
                    line-height: 1.75;
                    color: rgba(116, 34, 32, 0.55);
                }

                .billing-gate-note {
                    margin-top: 16px;
                    border-radius: 16px;
                    border: 1px solid rgba(116, 34, 32, 0.2);
                    background: rgba(116, 34, 32, 0.06);
                    padding: 12px 14px;
                    font-size: 12px;
                    line-height: 1.7;
                    color: rgba(116, 34, 32, 0.70);
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
                    border-top: 1px solid #C8BCA0;
                }

                .billing-gate-help {
                    max-width: 28rem;
                    font-size: 12px;
                    line-height: 1.7;
                    color: rgba(116, 34, 32, 0.50);
                }

                .billing-shell select.billing-field,
                .billing-shell input.billing-field {
                    border: 2px solid #C8BCA0 !important;
                    background: #FEF9E1 !important;
                    color: #742220 !important;
                    border-radius: 12px !important;
                    box-shadow: 2px 2px 0px 0px #C8BCA0 !important;
                    padding: 0.6rem 0.85rem !important;
                    font-size: 0.875rem !important;
                    outline: none !important;
                    width: 100% !important;
                    appearance: none !important;
                    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23742220' d='M6 8L1 3h10z'/%3E%3C/svg%3E") !important;
                    background-repeat: no-repeat !important;
                    background-position: right 0.85rem center !important;
                    background-size: 10px !important;
                    padding-right: 2.5rem !important;
                }

                .billing-shell select.billing-field:focus,
                .billing-shell input.billing-field:focus {
                    border-color: #742220 !important;
                    box-shadow: 2px 2px 0px 0px #742220 !important;
                }

                .billing-shell select.billing-field option {
                    background: #FEF9E1;
                    color: #742220;
                }

                .billing-shell input:not([type='checkbox']):not([type='radio']) {
                    border: 2px solid #C8BCA0 !important;
                    background: #FEF9E1 !important;
                    color: #742220 !important;
                    border-radius: 12px !important;
                    box-shadow: 2px 2px 0px 0px #C8BCA0 !important;
                }

                .billing-shell input:not([type='checkbox']):not([type='radio']):hover:not(:disabled) {
                    border-color: #B0A488 !important;
                }

                .billing-shell input:not([type='checkbox']):not([type='radio']):focus {
                    border-color: #742220 !important;
                    box-shadow: 2px 2px 0px 0px #742220 !important;
                    outline: none !important;
                }

                .billing-shell input:not([type='checkbox']):not([type='radio'])::placeholder {
                    color: rgba(116, 34, 32, 0.35) !important;
                }

                @media (min-width: 1280px) {
                    .billing-shell-list {
                        overflow: hidden;
                    }

                    .billing-shell-plan {
                        overflow: hidden;
                        padding-top: 1rem;
                        padding-bottom: 1rem;
                    }

                    .billing-plan-layout {
                        grid-template-columns: minmax(0, 1fr) 320px;
                        align-items: stretch;
                        flex: 1 1 0%;
                        gap: 1rem;
                    }

                    .billing-plan-main {
                        overflow: hidden;
                    }

                    .billing-plan-main-body {
                        overflow-x: hidden;
                        overflow-y: auto;
                        scrollbar-width: none;
                        -ms-overflow-style: none;
                    }

                    .billing-plan-main-body::-webkit-scrollbar {
                        display: none;
                        width: 0;
                        height: 0;
                    }

                    .billing-plan-aside {
                        overflow: hidden;
                    }
                }

                @media (min-width: 1536px) {
                    .billing-plan-layout {
                        grid-template-columns: minmax(0, 1fr) 330px;
                    }
                }

                @media (max-width: 1279px) {
                    .billing-shell {
                        height: auto;
                        overflow: visible;
                        min-height: unset;
                    }

                    .billing-ghost-btn {
                        margin-left: auto;
                        margin-right: auto;
                    }

                    .billing-shell-plan,
                    .billing-shell-list,
                    .billing-wrap-plan,
                    .billing-wrap-list {
                        overflow: visible;
                        height: auto;
                        min-height: unset;
                        flex: unset;
                    }

                    .billing-plan-layout {
                        grid-template-columns: minmax(0, 1fr);
                        height: auto;
                        min-height: unset;
                    }

                    .billing-plan-main {
                        overflow: visible;
                        height: auto;
                        min-height: unset;
                    }

                    .billing-plan-main-body {
                        overflow: visible;
                        flex: unset;
                        min-height: unset;
                    }

                    .billing-plan-aside {
                        overflow: visible;
                        height: auto;
                        min-height: unset;
                        align-self: unset;
                    }

                    .billing-plan-aside > aside,
                    .billing-plan-aside .billing-panel {
                        height: auto;
                    }

                    .billing-section-shell {
                        overflow: visible;
                        min-height: unset;
                    }

                    .billing-section-scroll {
                        overflow: visible;
                        flex: unset;
                        min-height: unset;
                    }

                    .billing-wizard-nav-grid {
                        grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr) !important;
                        gap: 6px !important;
                    }

                    /* Compact prev/next buttons */
                    .billing-wizard-prev-btn,
                    .billing-wizard-next-btn {
                        padding: 8px 10px !important;
                        gap: 6px !important;
                        border-radius: 8px !important;
                        box-shadow: 2px 2px 0px 0px #2D4A3E !important;
                    }

                    /* Icon circles - smaller */
                    .billing-wizard-prev-btn > span,
                    .billing-wizard-next-btn > span {
                        width: 26px !important;
                        height: 26px !important;
                        min-width: 26px !important;
                    }

                    /* Hide eyebrow labels (Previous / Next) */
                    .billing-wizard-prev-btn div > p:first-child,
                    .billing-wizard-next-btn div > p:first-child {
                        display: none !important;
                    }

                    /* Hide step eyebrow (Step 2, etc) */
                    .billing-wizard-prev-btn div > p:last-child,
                    .billing-wizard-next-btn div > p:last-child {
                        display: none !important;
                    }

                    /* Title - smaller, no top margin */
                    .billing-wizard-prev-btn div > p,
                    .billing-wizard-next-btn div > p {
                        font-size: 10px !important;
                        margin-top: 0 !important;
                    }

                    /* Center card compact */
                    .billing-wizard-current-card {
                        padding: 8px 10px !important;
                        border-radius: 8px !important;
                        box-shadow: 2px 2px 0px 0px #742220 !important;
                    }

                    .billing-wizard-current-card > p:first-child {
                        font-size: 7px !important;
                        letter-spacing: 0.2em !important;
                    }

                    .billing-wizard-current-card > p:nth-child(2) {
                        font-size: 10px !important;
                        margin-top: 4px !important;
                    }

                    .billing-wizard-current-card > div {
                        margin-top: 6px !important;
                        height: 3px !important;
                    }
                }

                @media (max-width: 640px) {
                    .billing-choice-meta-grid {
                        grid-template-columns: minmax(0, 1fr);
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

                    .billing-wizard-actions {
                        flex-direction: column;
                        align-items: stretch;
                    }
                }
            `}</style>
            <div
                className={`billing-wrap ${isPlanSection ? 'billing-wrap-plan' : ''} ${
                    isListSection ? 'billing-wrap-list' : ''
                }`}
            >
                <FlashMessageRender byKey={'billing'} />
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
                                <h2 className={'mt-2 text-2xl font-black tracking-tight text-[#742220]'}>
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
                                <a className={'billing-secondary-btn'} href={'/tickets?view=chat'}>
                                    Ticket Inbox
                                </a>
                            </div>
                        </div>
                    </section>
                ) : null}

                {currentSection === 'plan' &&
                    (!catalog || catalog.length < 1 ? (
                        <section className={'billing-panel p-8'}>
                            <h2 className={'text-2xl font-black tracking-tight text-[#742220]'}>No Billing Nodes</h2>
                            <p className={'mt-4 text-sm leading-7 text-[color:var(--muted-foreground)]'}>
                                Billing has not been enabled on any node yet. Ask an administrator to finish the node
                                setup first.
                            </p>
                        </section>
                    ) : (
                        <div className={'billing-plan-layout'}>
                            <section className={'billing-panel billing-plan-main h-full min-w-0 p-6 md:p-8'}>
                                <div className={'mb-6 space-y-5'}>
                                    {renderPlanStepNavigator()}
                                    <div>
                                        <p
                                            className={
                                                'text-[10px] font-bold uppercase tracking-[0.3em] text-[color:var(--primary)]'
                                            }
                                        >
                                            {currentPlanStepContent.eyebrow}
                                        </p>
                                        <h2 className={'mt-3 text-3xl font-black tracking-tight text-[#742220]'}>
                                            {currentPlanStepContent.title}
                                        </h2>
                                        <p
                                            className={
                                                'mt-3 max-w-3xl text-sm leading-7 text-[color:var(--muted-foreground)]'
                                            }
                                        >
                                            {currentPlanStepContent.copy}
                                        </p>
                                    </div>
                                </div>
                                <div className={'billing-plan-main-body'}>{renderPlanStepContent()}</div>
                                <div className={'billing-plan-footer'}>{renderPlanFooterActions()}</div>
                            </section>
                            <div className={'billing-plan-aside'}>{renderPlanSidebar()}</div>
                        </div>
                    ))}

                {currentSection === 'subscriptions' && (
                    <section className={'billing-panel billing-section-shell p-6 md:p-8'}>
                        <div className={'mb-5 flex flex-wrap items-center justify-between gap-4'}>
                            <div>
                                <h2 className={'text-2xl font-black tracking-tight text-[#742220]'}>
                                    Active Subscriptions
                                </h2>
                                <p className={'mt-2 text-sm text-[color:var(--muted-foreground)]'}>
                                    Renewals and upgrades stay available here, but every invoice is handled manually
                                    while payment gateway checkout is disabled.
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

                        <div className={'billing-section-scroll'}>
                            {!subscriptions && subscriptionsLoading ? (
                                <Spinner centered />
                            ) : subscriptions && subscriptions.length > 0 ? (
                                <div className={'grid gap-4'}>
                                    {paginateItems(
                                        subscriptions,
                                        subscriptionsPage,
                                        ACTIVE_SUBSCRIPTIONS_PAGE_SIZE
                                    ).map((subscription) => (
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
                                    ))}
                                </div>
                            ) : (
                                <div className={'billing-empty-card'}>No active billing subscriptions exist yet.</div>
                            )}
                        </div>
                    </section>
                )}

                {currentSection === 'invoices' &&
                    renderInvoiceSection(
                        'Invoices',
                        'Pending and active invoice work stays here until payment is confirmed. Paid billing proof moves into the receipts section.',
                        sectionInvoices,
                        invoicesPage,
                        setInvoicesPage,
                        invoicesLoading,
                        'No invoices have been created yet.'
                    )}

                {currentSection === 'receipts' &&
                    renderInvoiceSection(
                        'Receipts',
                        'Completed or paid invoices with receipt activity are collected here so billing proof stays separate from pending invoice work.',
                        receiptInvoices,
                        receiptsPage,
                        setReceiptsPage,
                        invoicesLoading,
                        'No receipts are available yet.',
                        true
                    )}

                {currentSection === 'orders' && (
                    <section className={'billing-panel billing-section-shell p-6 md:p-8'}>
                        <div className={'mb-5 flex flex-wrap items-center justify-between gap-4'}>
                            <div>
                                <h2 className={'text-2xl font-black tracking-tight text-[#742220]'}>
                                    My Billing Orders
                                </h2>
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

                        <div className={'billing-section-scroll'}>
                            {!orders && ordersLoading ? (
                                <Spinner centered />
                            ) : orders && orders.length > 0 ? (
                                <div className={'grid gap-4'}>
                                    {paginateItems(orders, ordersPage, ORDERS_PAGE_SIZE).map((order) =>
                                        renderBillingOrderCard(order)
                                    )}
                                </div>
                            ) : (
                                <div className={'billing-empty-card'}>No billing orders have been placed yet.</div>
                            )}
                        </div>
                    </section>
                )}

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
                                    'mt-4 rounded-2xl border border-amber-400/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-800'
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
