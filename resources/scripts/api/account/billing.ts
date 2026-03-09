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

const mapOrder = (item: any): BillingOrder => ({
    id: item.id,
    status: item.status,
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

const createBillingOrder = async (payload: CreateBillingOrderPayload): Promise<BillingOrder> => {
    const { data } = await http.post('/api/client/account/billing/orders', {
        billing_node_config_id: payload.billingNodeConfigId,
        billing_game_profile_id: payload.billingGameProfileId,
        server_name: payload.serverName,
        cpu_cores: payload.cpuCores,
        memory_gb: payload.memoryGb,
        disk_gb: payload.diskGb,
        variables: payload.variables,
    });

    return mapOrder(data.data);
};

export { useBillingCatalog, useBillingOrders, createBillingOrder };
