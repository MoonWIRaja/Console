import useSWR, { ConfigInterface } from 'swr';
import { AxiosError } from 'axios';
import http from '@/api/http';
import { useUserSWRKey } from '@/plugins/useSWRKey';

export interface TicketAttachment {
    id: number;
    name: string;
    mimeType: string | null;
    sizeBytes: number;
    downloadUrl: string;
    sourceUrl: string | null;
}

export interface TicketMessage {
    id: number;
    authorType: string;
    authorUserId: number | null;
    authorDisplayName: string | null;
    authorAvatarUrl: string | null;
    origin: string;
    body: string | null;
    discordMessageId: string | null;
    discordSyncStatus: string;
    discordSyncError: string | null;
    editedAt: Date | null;
    deletedAt: Date | null;
    createdAt: Date | null;
    attachments: TicketAttachment[];
}

export interface TicketSummary {
    id: number;
    ticketNumber: string;
    category: string;
    source: string;
    status: string;
    subject: string;
    assignedAdmin: { id: number; username: string } | null;
    billingOrderId: number | null;
    billingInvoiceId: number | null;
    billingPaymentId: number | null;
    billingSubscriptionId: number | null;
    invoiceNumber: string | null;
    paymentNumber: string | null;
    serverName: string | null;
    discordThreadId: string | null;
    discordThreadUrl: string | null;
    discordSyncStatus: string;
    discordLastError: string | null;
    lastUserMessageAt: Date | null;
    lastAdminMessageAt: Date | null;
    resolvedAt: Date | null;
    closedAt: Date | null;
    createdAt: Date | null;
    updatedAt: Date | null;
    unread: boolean;
    url: string;
}

export interface TicketDetail extends TicketSummary {
    user: { id: number | null; username: string | null; email: string | null };
    messages: TicketMessage[];
    meta: Record<string, unknown>;
}

export interface TicketEligibilityItem {
    type: string;
    invoiceId?: number;
    invoiceNumber?: string | null;
    orderId?: number | null;
    subscriptionId?: number | null;
    paymentId?: number;
    paymentNumber?: string | null;
    serverId?: number;
    serverLabel?: string | null;
    serverUuidShort?: string | null;
    subject: string;
    status: string;
    amount: number;
    currency: string;
    serverName: string | null;
    existingTicketId: number | null;
    existingTicketNumber: string | null;
}

export const mapTicketAttachment = (item: any): TicketAttachment => ({
    id: item.id,
    name: item.name,
    mimeType: item.mime_type ?? null,
    sizeBytes: item.size_bytes ?? 0,
    downloadUrl: item.download_url,
    sourceUrl: item.source_url ?? null,
});

export const mapTicketMessage = (item: any): TicketMessage => ({
    id: item.id,
    authorType: item.author_type,
    authorUserId: item.author_user_id ?? null,
    authorDisplayName: item.author_display_name ?? null,
    authorAvatarUrl: item.author_avatar_url ?? null,
    origin: item.origin,
    body: item.body ?? null,
    discordMessageId: item.discord_message_id ?? null,
    discordSyncStatus: item.discord_sync_status,
    discordSyncError: item.discord_sync_error ?? null,
    editedAt: item.edited_at ? new Date(item.edited_at) : null,
    deletedAt: item.deleted_at ? new Date(item.deleted_at) : null,
    createdAt: item.created_at ? new Date(item.created_at) : null,
    attachments: (item.attachments || []).map(mapTicketAttachment),
});

export const mapTicketSummary = (item: any): TicketSummary => ({
    id: item.id,
    ticketNumber: item.ticket_number,
    category: item.category,
    source: item.source,
    status: item.status,
    subject: item.subject,
    assignedAdmin: item.assigned_admin ? { id: item.assigned_admin.id, username: item.assigned_admin.username } : null,
    billingOrderId: item.billing_order_id ?? null,
    billingInvoiceId: item.billing_invoice_id ?? null,
    billingPaymentId: item.billing_payment_id ?? null,
    billingSubscriptionId: item.billing_subscription_id ?? null,
    invoiceNumber: item.invoice_number ?? null,
    paymentNumber: item.payment_number ?? null,
    serverName: item.server_name ?? null,
    discordThreadId: item.discord_thread_id ?? null,
    discordThreadUrl: item.discord_thread_url ?? null,
    discordSyncStatus: item.discord_sync_status,
    discordLastError: item.discord_last_error ?? null,
    lastUserMessageAt: item.last_user_message_at ? new Date(item.last_user_message_at) : null,
    lastAdminMessageAt: item.last_admin_message_at ? new Date(item.last_admin_message_at) : null,
    resolvedAt: item.resolved_at ? new Date(item.resolved_at) : null,
    closedAt: item.closed_at ? new Date(item.closed_at) : null,
    createdAt: item.created_at ? new Date(item.created_at) : null,
    updatedAt: item.updated_at ? new Date(item.updated_at) : null,
    unread: !!item.unread,
    url: item.url,
});

export const mapTicketDetail = (item: any): TicketDetail => ({
    ...mapTicketSummary(item),
    user: {
        id: item.user?.id ?? null,
        username: item.user?.username ?? null,
        email: item.user?.email ?? null,
    },
    messages: (item.messages || []).map(mapTicketMessage),
    meta: item.meta || {},
});

export const useTickets = (config?: ConfigInterface<TicketSummary[], AxiosError>) => {
    const key = useUserSWRKey(['tickets']);

    return useSWR<TicketSummary[]>(
        key,
        async () => {
            const { data } = await http.get('/api/client/account/tickets');
            return (data.data || []).map(mapTicketSummary);
        },
        { revalidateOnMount: true, revalidateOnFocus: false, ...(config || {}) }
    );
};

export const useTicket = (ticketId?: number | null, config?: ConfigInterface<TicketDetail, AxiosError>) => {
    const key = ticketId ? useUserSWRKey(['ticket', ticketId]) : null;

    return useSWR<TicketDetail>(
        key,
        async () => {
            const { data } = await http.get(`/api/client/account/tickets/${ticketId}`);
            return mapTicketDetail(data.data);
        },
        { revalidateOnMount: true, revalidateOnFocus: false, ...(config || {}) }
    );
};

export const fetchTicketEligibles = async (
    category: 'payment' | 'refund' | 'support'
): Promise<TicketEligibilityItem[]> => {
    const { data } = await http.get('/api/client/account/tickets/eligibles', { params: { category } });

    return (data.data || []).map((item: any) => ({
        type: item.type,
        invoiceId: item.invoice_id ?? undefined,
        invoiceNumber: item.invoice_number ?? null,
        orderId: item.order_id ?? null,
        subscriptionId: item.subscription_id ?? null,
        paymentId: item.payment_id ?? undefined,
        paymentNumber: item.payment_number ?? null,
        serverId: item.server_id ?? undefined,
        serverLabel: item.server_label ?? null,
        serverUuidShort: item.server_uuid_short ?? null,
        subject: item.subject,
        status: item.status ?? 'available',
        amount: Number(item.amount ?? 0),
        currency: item.currency ?? 'MYR',
        serverName: item.server_name ?? null,
        existingTicketId: item.existing_ticket_id ?? null,
        existingTicketNumber: item.existing_ticket_number ?? null,
    }));
};

export const createTicket = async (
    payload: Record<string, unknown>,
    attachments: File[] = []
): Promise<TicketDetail> => {
    const requestPayload =
        attachments.length > 0
            ? (() => {
                  const form = new FormData();
                  Object.entries(payload).forEach(([key, value]) => {
                      if (value === undefined || value === null || value === '') {
                          return;
                      }

                      form.append(key, String(value));
                  });

                  attachments.forEach((file) => form.append('attachments[]', file));

                  return form;
              })()
            : payload;

    const { data } = await http.post(
        '/api/client/account/tickets',
        requestPayload,
        attachments.length > 0 ? { headers: { 'Content-Type': 'multipart/form-data' } } : undefined
    );
    return mapTicketDetail(data.data);
};

export const postTicketMessage = async (
    ticketId: number,
    body: string,
    attachments: File[] = []
): Promise<TicketMessage> => {
    const form = new FormData();
    if (body.trim() !== '') {
        form.append('body', body);
    }

    attachments.forEach((file) => form.append('attachments[]', file));

    const { data } = await http.post(`/api/client/account/tickets/${ticketId}/messages`, form, {
        headers: { 'Content-Type': 'multipart/form-data' },
    });

    return mapTicketMessage(data.data);
};

export const markTicketRead = async (ticketId: number): Promise<TicketDetail> => {
    const { data } = await http.post(`/api/client/account/tickets/${ticketId}/read`);
    return mapTicketDetail(data.data);
};

export const reopenTicket = async (ticketId: number): Promise<TicketDetail> => {
    const { data } = await http.post(`/api/client/account/tickets/${ticketId}/reopen`);
    return mapTicketDetail(data.data);
};
