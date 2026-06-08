import useSWR from 'swr';
import http from '@/api/http';
import { ServerContext } from '@/state/server';

export interface DiscordIntegrationState {
    enabled: boolean;
    hasBotToken: boolean;
    guildId: string | null;
    chatChannelId: string | null;
    consoleChannelId: string | null;
    adminChannelId: string | null;
    linkChannelId: string | null;
    chatBridgeEnabled: boolean;
    consoleBridgeEnabled: boolean;
    linkingEnabled: boolean;
    whitelistRequiresLink: boolean;
}

export interface DiscordAgentState {
    installStatus: 'not_installed' | 'installed' | 'needs_restart' | 'error' | string;
    connectionStatus: 'offline' | 'connected' | 'stale' | string;
    agentVersion: string | null;
    adapter: string | null;
    detectedGameType: string | null;
    detectionConfidence: number;
    detectionSources: string[];
    capabilities: Record<string, boolean>;
    lastError: string | null;
    installedAt: string | null;
    restartRequestedAt: string | null;
    lastSeenAt: string | null;
}

export interface DiscordPlayerSourceState {
    mode: 'agent' | 'panel_fallback' | string;
    label: string;
    message: string;
}

export interface ServerDiscordIntegrationResponse {
    integration: DiscordIntegrationState;
    agent: DiscordAgentState;
    playerSource: DiscordPlayerSourceState;
    server: {
        uuid: string;
        identifier: string;
        name: string;
    };
}

export interface UpdateServerDiscordIntegrationPayload {
    enabled?: boolean;
    botToken?: string;
    guildId?: string;
    chatChannelId?: string;
    consoleChannelId?: string;
    adminChannelId?: string;
    linkChannelId?: string;
    chatBridgeEnabled?: boolean;
    consoleBridgeEnabled?: boolean;
    linkingEnabled?: boolean;
    whitelistRequiresLink?: boolean;
}

const rawToResponse = (data: any): ServerDiscordIntegrationResponse => ({
    integration: {
        enabled: !!data.integration?.enabled,
        hasBotToken: !!data.integration?.has_bot_token,
        guildId: data.integration?.guild_id ?? null,
        chatChannelId: data.integration?.chat_channel_id ?? null,
        consoleChannelId: data.integration?.console_channel_id ?? null,
        adminChannelId: data.integration?.admin_channel_id ?? null,
        linkChannelId: data.integration?.link_channel_id ?? null,
        chatBridgeEnabled: !!data.integration?.chat_bridge_enabled,
        consoleBridgeEnabled: !!data.integration?.console_bridge_enabled,
        linkingEnabled: !!data.integration?.linking_enabled,
        whitelistRequiresLink: !!data.integration?.whitelist_requires_link,
    },
    agent: {
        installStatus: data.agent?.install_status ?? 'not_installed',
        connectionStatus: data.agent?.connection_status ?? 'offline',
        agentVersion: data.agent?.agent_version ?? null,
        adapter: data.agent?.adapter ?? null,
        detectedGameType: data.agent?.detected_game_type ?? null,
        detectionConfidence: Number(data.agent?.detection_confidence ?? 0),
        detectionSources: data.agent?.detection_sources ?? [],
        capabilities: data.agent?.capabilities ?? {},
        lastError: data.agent?.last_error ?? null,
        installedAt: data.agent?.installed_at ?? null,
        restartRequestedAt: data.agent?.restart_requested_at ?? null,
        lastSeenAt: data.agent?.last_seen_at ?? null,
    },
    playerSource: {
        mode: data.player_source?.mode ?? 'panel_fallback',
        label: data.player_source?.label ?? 'Using panel player provider fallback',
        message: data.player_source?.message ?? '',
    },
    server: {
        uuid: data.server?.uuid ?? '',
        identifier: data.server?.identifier ?? '',
        name: data.server?.name ?? '',
    },
});

const payloadToRaw = (payload: UpdateServerDiscordIntegrationPayload) => ({
    enabled: payload.enabled,
    bot_token: payload.botToken,
    guild_id: payload.guildId,
    chat_channel_id: payload.chatChannelId,
    console_channel_id: payload.consoleChannelId,
    admin_channel_id: payload.adminChannelId,
    link_channel_id: payload.linkChannelId,
    chat_bridge_enabled: payload.chatBridgeEnabled,
    console_bridge_enabled: payload.consoleBridgeEnabled,
    linking_enabled: payload.linkingEnabled,
    whitelist_requires_link: payload.whitelistRequiresLink,
});

export const useServerDiscordIntegration = (enabled = true) => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);

    return useSWR<ServerDiscordIntegrationResponse>(
        enabled ? ['server:discord', uuid] : null,
        async () => {
            const { data } = await http.get(`/api/client/servers/${uuid}/discord`);

            return rawToResponse(data);
        },
        { revalidateOnFocus: false }
    );
};

export const updateServerDiscordIntegration = async (
    uuid: string,
    payload: UpdateServerDiscordIntegrationPayload
): Promise<ServerDiscordIntegrationResponse> => {
    const { data } = await http.put(`/api/client/servers/${uuid}/discord`, payloadToRaw(payload));

    return rawToResponse(data);
};

export const installServerDiscordAgent = async (uuid: string): Promise<ServerDiscordIntegrationResponse> => {
    const { data } = await http.post(`/api/client/servers/${uuid}/discord/agent/install`);

    return rawToResponse(data);
};

export const syncServerDiscordAgent = async (uuid: string): Promise<ServerDiscordIntegrationResponse> => {
    const { data } = await http.post(`/api/client/servers/${uuid}/discord/agent/sync`);

    return rawToResponse(data);
};

export const resetServerDiscordAgent = async (uuid: string): Promise<ServerDiscordIntegrationResponse> => {
    const { data } = await http.post(`/api/client/servers/${uuid}/discord/agent/reset`);

    return rawToResponse(data);
};
