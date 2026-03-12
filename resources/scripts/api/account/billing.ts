import useSWR, { ConfigInterface } from 'swr';
import { AxiosError } from 'axios';
import http from '@/api/http';
import { useUserSWRKey } from '@/plugins/useSWRKey';

export interface BillingGame {
    id: number;
    eggId: number;
    nestId: number;
    nestName: string;
    displayName: string;
    description: string | null;
    eggName: string;
    variables: BillingGameVariable[];
}

export interface BillingGameVariable {
    name: string;
    description: string;
    envVariable: string;
    defaultValue: string;
    serverValue: string | null;
    isEditable: boolean;
    rules: string[];
}

export interface BillingNodeCatalog {
    id: number;
    nodeId: number;
    displayName: string;
    description: string | null;
    showRemainingCapacity: boolean;
    defaults: {
        allocationLimit: number;
        databaseLimit: number;
        backupLimit: number;
        swapMb: number;
        ioWeight: number;
        oomDisabled: boolean;
        startOnCompletion: boolean;
    };
    pricing: {
        perVcore: number;
        perGbRam: number;
        per10gbDisk: number;
    };
    availability: {
        freeAllocations: number;
        cpuRemaining: number;
        memoryRemainingGb: number;
        diskRemainingGb: number;
        isAvailable: boolean;
    };
    limits: {
        maxCpu: number;
        maxMemoryGb: number;
        maxDiskGb: number;
        diskStepGb: number;
    };
    games: BillingGame[];
}

export interface BillingOrder {
    id: number;
    status: string;
    orderType?: string;
    serverName: string;
    nodeName: string;
    gameName: string;
    cpuCores: number;
    memoryGb: number;
    diskGb: number;
    total: number;
    serverId: number | null;
    adminNotes: string | null;
    createdAt: Date | null;
    approvedAt: Date | null;
    provisionedAt: Date | null;
    paymentVerifiedAt?: Date | null;
    provisionAttemptedAt?: Date | null;
    provisionFailureCode?: string | null;
    provisionFailureMessage?: string | null;
}

export interface BillingSubscription {
    id: number;
    status: string;
    serverId: number | null;
    serverIdentifier: string | null;
    serverName: string;
    nodeName: string;
    gameName: string;
    nestName: string | null;
    cpuCores: number;
    memoryGb: number;
    diskGb: number;
    renewalPeriodMonths: number;
    recurringTotal: number;
    renewsAt: Date | null;
    renewalReminderSentAt: Date | null;
    renewedAt: Date | null;
    upgradedAt: Date | null;
    suspendedAt: Date | null;
    renewAvailableAt: Date | null;
    deletionScheduledAt: Date | null;
    deletedAt: Date | null;
    canRenew: boolean;
    canUpgrade: boolean;
    pricing: {
        perVcore: number;
        perGbRam: number;
        per10gbDisk: number;
    };
    limits: {
        maxCpu: number;
        maxMemoryGb: number;
        maxDiskGb: number;
        diskStepGb: number;
        freeAllocations: number;
    };
    autoRenew?: boolean;
    autoRenewAvailable?: boolean;
    autoRenewUnavailableReason?: string | null;
    gatewayProvider?: string | null;
    graceSuspendAt?: Date | null;
    graceDeleteAt?: Date | null;
}

export interface BillingRefundSummary {
    id: number;
    refundNumber: string;
    amount: number;
    status: string;
    requestedAt: Date | null;
    completedAt: Date | null;
}

export interface BillingPaymentSummary {
    id: number;
    paymentNumber: string;
    provider: string;
    providerTransactionId: string | null;
    providerPaymentMethod: string | null;
    providerStatus: string | null;
    amount: number;
    currency: string;
    status: string;
    paidAt: Date | null;
    refunds: BillingRefundSummary[];
}

export interface BillingProfile {
    legalName: string;
    companyName: string | null;
    email: string;
    phone: string | null;
    addressLine1: string | null;
    addressLine2: string | null;
    city: string | null;
    state: string | null;
    postcode: string | null;
    countryCode: string;
    taxId: string | null;
    isBusiness: boolean;
}

export interface BillingInvoice {
    id: number;
    invoiceNumber: string;
    status: string;
    type: string;
    currency: string;
    subtotal: number;
    taxTotal: number;
    grandTotal: number;
    issuedAt: Date | null;
    dueAt: Date | null;
    paidAt: Date | null;
    payments: BillingPaymentSummary[];
}

export interface BillingCheckout {
    provider: string;
    method: string;
    url: string;
    payload: Record<string, string>;
}

export interface BillingOrderActionResponse {
    order: BillingOrder;
    invoice: BillingInvoice | null;
    checkout: BillingCheckout | null;
    checkoutError: string | null;
    autoSettled: boolean;
}

export interface BillingSubscriptionActionResponse {
    subscription: BillingSubscription;
    invoice: BillingInvoice | null;
    checkout: BillingCheckout | null;
    checkoutError: string | null;
    autoSettled: boolean;
}

export interface CreateBillingOrderPayload {
    billingNodeConfigId: number;
    billingGameProfileId: number;
    serverName: string;
    cpuCores: number;
    memoryGb: number;
    diskGb: number;
    variables: Record<string, string>;
}

export interface UpgradeBillingSubscriptionPayload {
    id: number;
    cpuCores: number;
    memoryGb: number;
    diskGb: number;
}

const mapOrder = (item: any): BillingOrder => ({
    id: item.id,
    status: item.status,
    orderType: item.order_type ?? undefined,
    serverName: item.server_name,
    nodeName: item.node_name,
    gameName: item.game_name,
    cpuCores: item.cpu_cores,
    memoryGb: item.memory_gb,
    diskGb: item.disk_gb,
    total: item.total,
    serverId: item.server_id ?? null,
    adminNotes: item.admin_notes ?? null,
    createdAt: item.created_at ? new Date(item.created_at) : null,
    approvedAt: item.approved_at ? new Date(item.approved_at) : null,
    provisionedAt: item.provisioned_at ? new Date(item.provisioned_at) : null,
    paymentVerifiedAt: item.payment_verified_at ? new Date(item.payment_verified_at) : null,
    provisionAttemptedAt: item.provision_attempted_at ? new Date(item.provision_attempted_at) : null,
    provisionFailureCode: item.provision_failure_code ?? null,
    provisionFailureMessage: item.provision_failure_message ?? null,
});

const mapSubscription = (item: any): BillingSubscription => ({
    id: item.id,
    status: item.status,
    serverId: item.server_id ?? null,
    serverIdentifier: item.server_identifier ?? null,
    serverName: item.server_name,
    nodeName: item.node_name,
    gameName: item.game_name,
    nestName: item.nest_name ?? null,
    cpuCores: item.cpu_cores,
    memoryGb: item.memory_gb,
    diskGb: item.disk_gb,
    renewalPeriodMonths: item.renewal_period_months,
    recurringTotal: item.recurring_total,
    renewsAt: item.renews_at ? new Date(item.renews_at) : null,
    renewalReminderSentAt: item.renewal_reminder_sent_at ? new Date(item.renewal_reminder_sent_at) : null,
    renewedAt: item.renewed_at ? new Date(item.renewed_at) : null,
    upgradedAt: item.upgraded_at ? new Date(item.upgraded_at) : null,
    suspendedAt: item.suspended_at ? new Date(item.suspended_at) : null,
    renewAvailableAt: item.renew_available_at ? new Date(item.renew_available_at) : null,
    deletionScheduledAt: item.deletion_scheduled_at ? new Date(item.deletion_scheduled_at) : null,
    deletedAt: item.deleted_at ? new Date(item.deleted_at) : null,
    canRenew: item.can_renew,
    canUpgrade: item.can_upgrade,
    autoRenew: item.auto_renew ?? false,
    autoRenewAvailable: item.auto_renew_available ?? false,
    autoRenewUnavailableReason: item.auto_renew_unavailable_reason ?? null,
    gatewayProvider: item.gateway_provider ?? null,
    graceSuspendAt: item.grace_suspend_at ? new Date(item.grace_suspend_at) : null,
    graceDeleteAt: item.grace_delete_at ? new Date(item.grace_delete_at) : null,
    pricing: {
        perVcore: item.pricing.per_vcore,
        perGbRam: item.pricing.per_gb_ram,
        per10gbDisk: item.pricing.per_10gb_disk,
    },
    limits: {
        maxCpu: item.limits.max_cpu,
        maxMemoryGb: item.limits.max_memory_gb,
        maxDiskGb: item.limits.max_disk_gb,
        diskStepGb: item.limits.disk_step_gb,
        freeAllocations: item.limits.free_allocations,
    },
});

const mapInvoice = (item: any): BillingInvoice => ({
    id: item.id,
    invoiceNumber: item.invoice_number,
    status: item.status,
    type: item.type,
    currency: item.currency,
    subtotal: item.subtotal,
    taxTotal: item.tax_total,
    grandTotal: item.grand_total,
    issuedAt: item.issued_at ? new Date(item.issued_at) : null,
    dueAt: item.due_at ? new Date(item.due_at) : null,
    paidAt: item.paid_at ? new Date(item.paid_at) : null,
    payments: (item.payments || []).map((payment: any) => ({
        id: payment.id,
        paymentNumber: payment.payment_number,
        provider: payment.provider,
        providerTransactionId: payment.provider_transaction_id ?? null,
        providerPaymentMethod: payment.provider_payment_method ?? null,
        providerStatus: payment.provider_status ?? null,
        amount: payment.amount,
        currency: payment.currency,
        status: payment.status,
        paidAt: payment.paid_at ? new Date(payment.paid_at) : null,
        refunds: (payment.refunds || []).map((refund: any) => ({
            id: refund.id,
            refundNumber: refund.refund_number,
            amount: refund.amount,
            status: refund.status,
            requestedAt: refund.requested_at ? new Date(refund.requested_at) : null,
            completedAt: refund.completed_at ? new Date(refund.completed_at) : null,
        })),
    })),
});

const mapProfile = (item: any): BillingProfile => ({
    legalName: item.legal_name ?? '',
    companyName: item.company_name ?? null,
    email: item.email ?? '',
    phone: item.phone ?? null,
    addressLine1: item.address_line_1 ?? null,
    addressLine2: item.address_line_2 ?? null,
    city: item.city ?? null,
    state: item.state ?? null,
    postcode: item.postcode ?? null,
    countryCode: item.country_code ?? 'MY',
    taxId: item.tax_id ?? null,
    isBusiness: item.is_business ?? false,
});

const mapCheckout = (item: any): BillingCheckout | null =>
    item
        ? {
              provider: item.provider,
              method: item.method,
              url: item.url,
              payload: item.payload || {},
          }
        : null;

const mapOrderActionResponse = (item: any): BillingOrderActionResponse => ({
    order: mapOrder(item),
    invoice: item.invoice ? mapInvoice(item.invoice) : null,
    checkout: item.checkout ? mapCheckout(item.checkout.checkout ?? item.checkout) : null,
    checkoutError: item.checkout_error ?? null,
    autoSettled: item.auto_settled ?? false,
});

const mapSubscriptionActionResponse = (item: any): BillingSubscriptionActionResponse => ({
    subscription: mapSubscription(item),
    invoice: item.invoice ? mapInvoice(item.invoice) : null,
    checkout: item.checkout ? mapCheckout(item.checkout.checkout ?? item.checkout) : null,
    checkoutError: item.checkout_error ?? null,
    autoSettled: item.auto_settled ?? false,
});

const useBillingCatalog = (config?: ConfigInterface<BillingNodeCatalog[], AxiosError>) => {
    const key = useUserSWRKey(['billing', 'catalog']);

    return useSWR<BillingNodeCatalog[]>(
        key,
        async () => {
            const { data } = await http.get('/api/client/account/billing/catalog');

            return (data.data || []).map((item: any) => ({
                id: item.id,
                nodeId: item.node_id,
                displayName: item.display_name,
                description: item.description ?? null,
                showRemainingCapacity: item.show_remaining_capacity,
                defaults: {
                    allocationLimit: item.defaults.allocation_limit,
                    databaseLimit: item.defaults.database_limit,
                    backupLimit: item.defaults.backup_limit,
                    swapMb: item.defaults.swap_mb,
                    ioWeight: item.defaults.io_weight,
                    oomDisabled: item.defaults.oom_disabled,
                    startOnCompletion: item.defaults.start_on_completion,
                },
                pricing: {
                    perVcore: item.pricing.per_vcore,
                    perGbRam: item.pricing.per_gb_ram,
                    per10gbDisk: item.pricing.per_10gb_disk,
                },
                availability: {
                    freeAllocations: item.availability.free_allocations,
                    cpuRemaining: item.availability.cpu_remaining,
                    memoryRemainingGb: item.availability.memory_remaining_gb,
                    diskRemainingGb: item.availability.disk_remaining_gb,
                    isAvailable: item.availability.is_available,
                },
                limits: {
                    maxCpu: item.limits.max_cpu,
                    maxMemoryGb: item.limits.max_memory_gb,
                    maxDiskGb: item.limits.max_disk_gb,
                    diskStepGb: item.limits.disk_step_gb,
                },
                games: (item.games || []).map((game: any) => ({
                    id: game.id,
                    eggId: game.egg_id,
                    nestId: game.nest_id,
                    nestName: game.nest_name,
                    displayName: game.display_name,
                    description: game.description ?? null,
                    eggName: game.egg_name,
                    variables: (game.variables || []).map((variable: any) => ({
                        name: variable.name,
                        description: variable.description ?? '',
                        envVariable: variable.env_variable,
                        defaultValue: variable.default_value ?? '',
                        serverValue: variable.server_value ?? null,
                        isEditable: variable.is_editable,
                        rules: (variable.rules || '').split('|'),
                    })),
                })),
            }));
        },
        { revalidateOnMount: true, revalidateOnFocus: false, ...(config || {}) }
    );
};

const useBillingProfile = (config?: ConfigInterface<BillingProfile, AxiosError>) => {
    const key = useUserSWRKey(['billing', 'profile']);

    return useSWR<BillingProfile>(
        key,
        async () => {
            const { data } = await http.get('/api/client/account/billing/profile');

            return mapProfile(data.data || {});
        },
        { revalidateOnMount: true, revalidateOnFocus: false, ...(config || {}) }
    );
};

const useBillingOrders = (config?: ConfigInterface<BillingOrder[], AxiosError>) => {
    const key = useUserSWRKey(['billing', 'orders']);

    return useSWR<BillingOrder[]>(
        key,
        async () => {
            const { data } = await http.get('/api/client/account/billing/orders');

            return (data.data || []).map(mapOrder);
        },
        { revalidateOnMount: true, revalidateOnFocus: false, ...(config || {}) }
    );
};

const useBillingSubscriptions = (config?: ConfigInterface<BillingSubscription[], AxiosError>) => {
    const key = useUserSWRKey(['billing', 'subscriptions']);

    return useSWR<BillingSubscription[]>(
        key,
        async () => {
            const { data } = await http.get('/api/client/account/billing/subscriptions');

            return (data.data || [])
                .map(mapSubscription)
                .filter(
                    (subscription: BillingSubscription) =>
                        subscription.serverId !== null && subscription.status !== 'deleted'
                );
        },
        { revalidateOnMount: true, revalidateOnFocus: false, ...(config || {}) }
    );
};

const useBillingInvoices = (config?: ConfigInterface<BillingInvoice[], AxiosError>) => {
    const key = useUserSWRKey(['billing', 'invoices']);

    return useSWR<BillingInvoice[]>(
        key,
        async () => {
            const { data } = await http.get('/api/client/account/billing/invoices');

            return (data.data || []).map(mapInvoice);
        },
        { revalidateOnMount: true, revalidateOnFocus: false, ...(config || {}) }
    );
};

const createBillingOrder = async (payload: CreateBillingOrderPayload): Promise<BillingOrderActionResponse> => {
    const { data } = await http.post('/api/client/account/billing/orders', {
        billing_node_config_id: payload.billingNodeConfigId,
        billing_game_profile_id: payload.billingGameProfileId,
        server_name: payload.serverName,
        cpu_cores: payload.cpuCores,
        memory_gb: payload.memoryGb,
        disk_gb: payload.diskGb,
        variables: payload.variables,
    });

    return mapOrderActionResponse(data.data);
};

const renewBillingSubscription = async (id: number): Promise<BillingSubscriptionActionResponse> => {
    const { data } = await http.post(`/api/client/account/billing/subscriptions/${id}/renew`);

    return mapSubscriptionActionResponse(data.data);
};

const upgradeBillingSubscription = async (
    payload: UpgradeBillingSubscriptionPayload
): Promise<BillingSubscriptionActionResponse> => {
    const { data } = await http.post(`/api/client/account/billing/subscriptions/${payload.id}/upgrade`, {
        cpu_cores: payload.cpuCores,
        memory_gb: payload.memoryGb,
        disk_gb: payload.diskGb,
    });

    return mapSubscriptionActionResponse(data.data);
};

const updateBillingProfile = async (payload: BillingProfile): Promise<BillingProfile> => {
    const { data } = await http.put('/api/client/account/billing/profile', {
        legal_name: payload.legalName,
        company_name: payload.companyName,
        email: payload.email,
        phone: payload.phone,
        address_line_1: payload.addressLine1,
        address_line_2: payload.addressLine2,
        city: payload.city,
        state: payload.state,
        postcode: payload.postcode,
        country_code: payload.countryCode,
        tax_id: payload.taxId,
        is_business: payload.isBusiness,
    });

    return mapProfile(data.data || {});
};

const retryBillingInvoicePayment = async (id: number): Promise<BillingCheckout> => {
    const { data } = await http.post(`/api/client/account/billing/invoices/${id}/retry-payment`);

    return mapCheckout(data.data)!;
};

const toggleBillingSubscriptionAutoRenew = async (id: number, autoRenew: boolean): Promise<BillingSubscription> => {
    const { data } = await http.patch(`/api/client/account/billing/subscriptions/${id}/auto-renew`, {
        auto_renew: autoRenew,
    });

    return mapSubscription(data.data);
};

export {
    useBillingProfile,
    useBillingCatalog,
    useBillingOrders,
    useBillingSubscriptions,
    useBillingInvoices,
    updateBillingProfile,
    createBillingOrder,
    renewBillingSubscription,
    upgradeBillingSubscription,
    retryBillingInvoicePayment,
    toggleBillingSubscriptionAutoRenew,
};
