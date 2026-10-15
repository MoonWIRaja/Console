import http from '@/api/http';

export type AiMode = 'ask' | 'agent';

export interface AiModelOption {
    id: number;
    name: string;
    provider: string;
    default: boolean;
}

export interface AiConfig {
    enabled: boolean;
    name: string;
    models: AiModelOption[];
}

export interface AiAction {
    uuid: string;
    tool: string;
    status: 'pending' | 'executed' | 'failed' | 'rejected';
    result: string | null;
    server: string | null;
    arguments: Record<string, any>;
    original: string | null;
}

export interface AiMessage {
    id: number;
    role: 'user' | 'assistant';
    content: string;
    created_at: string | null;
    model: string | null;
    tools: string[];
    actions: AiAction[];
    attachments?: AiAttachment[];
}

export interface AiAttachment {
    name: string;
    size: number;
    download_url: string | null;
    error?: string | null;
}

export interface AiConversationSummary {
    uuid: string;
    title: string;
    server: { id: string; name: string } | null;
    updated_at: string | null;
}

export interface AiConversation extends AiConversationSummary {
    messages: AiMessage[];
    continuing?: boolean;
}

const LONG = { timeout: 300000 };

export const getAiConfig = (): Promise<AiConfig> => http.get('/api/client/ai/config').then(({ data }) => data);

export const getAiConversations = (): Promise<AiConversationSummary[]> =>
    http.get('/api/client/ai/conversations').then(({ data }) => data.data);

export const createAiConversation = (server?: string | null): Promise<AiConversation> =>
    http.post('/api/client/ai/conversations', { server: server || null }).then(({ data }) => data);

export const getAiConversation = (uuid: string): Promise<AiConversation> =>
    http.get(`/api/client/ai/conversations/${uuid}`).then(({ data }) => data);

export const deleteAiConversation = (uuid: string): Promise<void> =>
    http.delete(`/api/client/ai/conversations/${uuid}`).then(() => undefined);

export interface FailedAttachment {
    name: string;
    size: number;
    error: string;
}

export const sendAiMessage = (
    uuid: string,
    content: string,
    mode: AiMode,
    model: number | null,
    files: File[] = [],
    cloudFiles: string[] = [],
    failedFiles: FailedAttachment[] = []
): Promise<AiConversation> => {
    if (files.length === 0) {
        return http
            .post(
                `/api/client/ai/conversations/${uuid}/messages`,
                { content, mode, model, cloud_files: cloudFiles, failed_files: failedFiles },
                LONG
            )
            .then(({ data }) => data);
    }

    const form = new FormData();
    form.append('content', content);
    form.append('mode', mode);
    if (model) form.append('model', String(model));
    files.forEach((file) => form.append('files[]', file));
    cloudFiles.forEach((id) => form.append('cloud_files[]', id));
    failedFiles.forEach((f, i) => {
        form.append(`failed_files[${i}][name]`, f.name);
        form.append(`failed_files[${i}][size]`, String(f.size));
        form.append(`failed_files[${i}][error]`, f.error);
    });

    return http
        .post(`/api/client/ai/conversations/${uuid}/messages`, form, {
            ...LONG,
            headers: { 'Content-Type': 'multipart/form-data' },
        })
        .then(({ data }) => data);
};

export const editAiMessage = (
    uuid: string,
    messageId: number,
    content: string,
    mode: AiMode,
    model: number | null
): Promise<AiConversation> =>
    http
        .post(`/api/client/ai/conversations/${uuid}/messages/${messageId}/edit`, { content, mode, model }, LONG)
        .then(({ data }) => data);

export const continueAiTask = (uuid: string, mode: AiMode, model: number | null): Promise<AiConversation> =>
    http.post(`/api/client/ai/conversations/${uuid}/continue`, { mode, model }, LONG).then(({ data }) => data);

export const resolveAiAction = (
    uuid: string,
    decision: 'approve' | 'reject',
    mode: AiMode,
    model: number | null
): Promise<AiConversation> =>
    http.post(`/api/client/ai/actions/${uuid}`, { decision, mode, model }, LONG).then(({ data }) => data);
