import http from '@/api/http';

export type PlayerScope = 'all' | 'online' | 'operators' | 'admins' | 'staff' | 'banned';

export interface ServerPlayer {
    id: string;
    name: string;
    uuid: string;
    source_id: string;
    status: 'online' | 'offline' | string;
    ping: number;
    role: string;
    is_operator: boolean;
    is_admin: boolean;
    banned: boolean;
    country: string;
    avatar_url: string;
    last_seen_at: string;
    is_dummy: boolean;
}

export interface PlayerFilter {
    id: string;
    label: string;
    description: string;
}

export interface PlayerAction {
    id: string;
    label: string;
    description: string;
    tone?: 'primary' | 'neutral' | 'success' | 'warning' | 'danger';
    requires_input?: boolean;
    input_key?: string;
    input_label?: string;
    input_placeholder?: string;
}

export interface PlayerActionGroup {
    id: string;
    title: string;
    description?: string;
    actions: PlayerAction[];
}

export interface PlayersCapabilities {
    filters: PlayerFilter[];
    action_groups: PlayerActionGroup[];
    tabs: string[];
    notes: string[];
    integrations: Record<string, unknown>;
}

export interface PlayersGameMeta {
    type: string;
    label: string;
    is_dummy: boolean;
}

export interface PlayersCountSummary {
    total: number;
    online: number;
    operators: number;
    admins: number;
    staff: number;
    banned: number;
}

export interface PlayersListResponse {
    game: PlayersGameMeta;
    scope: PlayerScope;
    search: string;
    counts: PlayersCountSummary;
    capabilities: PlayersCapabilities;
    items: ServerPlayer[];
    is_dummy: boolean;
}

export interface PlayerProfileResponse {
    game: PlayersGameMeta;
    player: ServerPlayer & {
        action_groups: PlayerActionGroup[];
    };
    is_dummy: boolean;
}

export interface PlayerInventorySlot {
    slot: string;
    item_name: string;
    item_id: string;
    count: number;
    icon_url?: string;
}

export interface PlayerInventorySection {
    id: string;
    title: string;
    slots: PlayerInventorySlot[];
}

export interface PlayerInventoryResponse {
    game: PlayersGameMeta;
    player_id: string;
    available: boolean;
    message?: string;
    sections: PlayerInventorySection[];
    summary: { label: string; value: string }[];
    is_dummy: boolean;
}

export interface PlayerStatisticsCategory {
    id: string;
    title: string;
    entries: { label: string; value: string }[];
}

export interface PlayerStatisticsResponse {
    game: PlayersGameMeta;
    player_id: string;
    available: boolean;
    message?: string;
    categories: PlayerStatisticsCategory[];
    is_dummy: boolean;
}

export interface PlayerActionResponse {
    game: PlayersGameMeta;
    accepted: boolean;
    queued: boolean;
    message: string;
    action: string;
    action_label?: string;
    player_id: string;
    command_preview?: string;
    context?: Record<string, unknown>;
    is_dummy: boolean;
}

const buildPlayerPath = (uuid: string, playerId: string): string =>
    `/api/client/servers/${uuid}/players/${encodeURIComponent(playerId)}`;

export const getServerPlayers = async (
    uuid: string,
    params?: { scope?: PlayerScope; search?: string }
): Promise<PlayersListResponse> => {
    const { data } = await http.get(`/api/client/servers/${uuid}/players`, { params });

    return data;
};

export const getServerPlayersCapabilities = async (uuid: string): Promise<{
    game: PlayersGameMeta;
    counts: PlayersCountSummary;
    capabilities: PlayersCapabilities;
    is_dummy: boolean;
}> => {
    const { data } = await http.get(`/api/client/servers/${uuid}/players/capabilities`);

    return data;
};

export const getServerPlayerProfile = async (uuid: string, playerId: string): Promise<PlayerProfileResponse> => {
    const { data } = await http.get(buildPlayerPath(uuid, playerId));

    return data;
};

export const getServerPlayerInventory = async (uuid: string, playerId: string): Promise<PlayerInventoryResponse> => {
    const { data } = await http.get(`${buildPlayerPath(uuid, playerId)}/inventory`);

    return data;
};

export const getServerPlayerStatistics = async (uuid: string, playerId: string): Promise<PlayerStatisticsResponse> => {
    const { data } = await http.get(`${buildPlayerPath(uuid, playerId)}/statistics`);

    return data;
};

export const runServerPlayerAction = async (
    uuid: string,
    playerId: string,
    action: string,
    context?: Record<string, unknown>
): Promise<PlayerActionResponse> => {
    const { data } = await http.post(`${buildPlayerPath(uuid, playerId)}/actions`, {
        action,
        context: context || {},
    });

    return data;
};
