import React, { memo, useEffect, useMemo, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { ServerContext } from '@/state/server';
import isEqual from 'react-fast-compare';
import Spinner from '@/components/elements/Spinner';
import Features from '@feature/Features';
import Console from '@/components/server/console/Console';
import ConsoleBackground from '@/components/server/console/ConsoleBackground';
import PowerButtons from '@/components/server/console/PowerButtons';
import { Alert } from '@/components/elements/alert';
import { useStoreState, useStoreActions } from 'easy-peasy';
import { ApplicationStore } from '@/state';
import { bytesToString, ip, mbToBytes } from '@/lib/formatters';
import useWebsocketEvent from '@/plugins/useWebsocketEvent';
import { SocketEvent, SocketRequest } from '@/components/server/events';
import classNames from 'classnames';
import Avatar from '@/components/Avatar';
import { Dialog } from '@/components/elements/dialog';
import FlashMessageRender from '@/components/FlashMessageRender';
import Select, { TSelectData } from '@/components/ui/select';
import { httpErrorToHuman } from '@/api/http';
import useFlash from '@/plugins/useFlash';
import getServerSubdomains from '@/api/server/network/getServerSubdomains';
import {
    getServerPlayerInventory,
    getServerPlayerProfile,
    PlayerActionGroup,
    getServerPlayerStatistics,
    getServerPlayers,
    PlayerAction,
    PlayerInventorySection,
    PlayerInventorySlot,
    PlayerInventoryResponse,
    PlayerProfileResponse,
    PlayerScope,
    PlayersListResponse,
    PlayerStatisticsResponse,
    runServerPlayerAction,
    ServerPlayer,
} from '@/api/server/players';
import {
    BEDROCK_SYSTEM_LIST_EVENT,
    BedrockConsoleRoster,
    isBedrockListCommandEcho,
    normalizeConsoleLine,
    parseBedrockRosterHeader,
    parseBedrockRosterNames,
} from '@/components/server/console/bedrockRoster';

export type PowerAction = 'start' | 'stop' | 'restart' | 'kill';

type LiveStats = {
    memory: number;
    cpu: number;
    disk: number;
    rx: number;
    tx: number;
};

const clampPercent = (value: number): number => Math.max(0, Math.min(100, value));

const defaultPlayerFilters: TSelectData[] = [
    {
        id: 'all',
        label: 'All Players',
        value: 'all',
        description: 'All player records currently known to the panel.',
    },
    {
        id: 'online',
        label: 'Online Players',
        value: 'online',
        description: 'Only players currently connected.',
    },
    {
        id: 'operators',
        label: 'Operators',
        value: 'operators',
        description: 'Players with OP access.',
    },
    {
        id: 'banned',
        label: 'Banned',
        value: 'banned',
        description: 'Players currently banned.',
    },
];

const allowedPlayerScopes: PlayerScope[] = ['all', 'online', 'operators', 'banned'];

const scopeCount = (scope: PlayerScope, data?: PlayersListResponse | null): number => {
    if (!data) return 0;

    switch (scope) {
        case 'online':
            return data.counts.online;
        case 'operators':
            return data.counts.operators;
        case 'admins':
            return data.counts.admins;
        case 'staff':
            return data.counts.staff;
        case 'banned':
            return data.counts.banned;
        default:
            return data.counts.total;
    }
};

const capabilitiesState = (data?: PlayersListResponse | null): Record<string, unknown> => {
    const state = data?.capabilities?.state;

    return state && typeof state === 'object' ? state : {};
};

const capabilitiesIntegrations = (data?: PlayersListResponse | null): Record<string, any> => {
    const integrations = data?.capabilities?.integrations;

    return integrations && typeof integrations === 'object' ? integrations as Record<string, any> : {};
};

const buildPlayerProviderLabel = (data?: PlayersListResponse | null): string => {
    const agent = capabilitiesIntegrations(data).discord_agent;
    if (!agent) {
        return data?.game.label || 'Loading player provider...';
    }

    if (agent.connection_status === 'connected') {
        return 'Synced by Discord Agent';
    }

    if (agent.install_status === 'needs_restart') {
        return 'Agent installed. Restart server to activate.';
    }

    if (agent.enabled) {
        return 'Agent offline. Using panel fallback.';
    }

    return data?.game.label || 'Loading player provider...';
};

const stateBoolean = (value: unknown): boolean | undefined => (typeof value === 'boolean' ? value : undefined);

const stateNumber = (value: unknown): number | undefined =>
    typeof value === 'number' && Number.isFinite(value) ? value : undefined;

const buildMinecraftJavaEmptyMessage = (data: PlayersListResponse, scope: PlayerScope): string => {
    const state = capabilitiesState(data);
    const knownRecords = stateNumber(state.known_player_records) ?? data.counts.total;
    const onlineCount = stateNumber(state.online_count) ?? data.counts.online;
    const statusConnected = stateBoolean(state.status_connected);
    const queryEnabled = stateBoolean(state.query_enabled);
    const sampleAvailable = stateBoolean(state.sample_available);
    const onlineModeEnabled = stateBoolean(state.online_mode_enabled);
    const statusEnabled = stateBoolean(state.status_enabled);

    if (scope === 'online') {
        if (onlineModeEnabled === false) {
            return 'Live player lookup is disabled because online-mode=false in server.properties. Enable online-mode=true and restart the server.';
        }

        if (statusEnabled === false) {
            return knownRecords > 0
                ? 'The live online roster is disabled because enable-status=false in server.properties. Switch to All Players to browse saved player records.'
                : 'The live online roster is disabled because enable-status=false in server.properties. Enable it and restart the server.';
        }

        if (statusConnected === false) {
            return knownRecords > 0
                ? 'The panel could not reach the Minecraft status endpoint right now. Switch to All Players to browse saved records recovered from server files.'
                : 'The panel could not reach the Minecraft status endpoint right now, so no live roster could be recovered.';
        }

        if ((onlineCount ?? 0) === 0) {
            return knownRecords > 0
                ? 'No players are online right now. Switch to All Players, Operators, or Banned to browse saved player records.'
                : 'No players are online right now.';
        }

        if (queryEnabled === false && sampleAvailable === false) {
            return knownRecords > 0
                ? 'The server is online, but it is not exposing a trusted live roster right now. Switch to All Players to browse saved records.'
                : 'The server is online, but it is not exposing a trusted live roster right now. Enable query or let the panel recover identities from server files.';
        }

        return 'No live player entries matched the current view.';
    }

    if (scope === 'operators') {
        return knownRecords > 0
            ? 'No operators were found in the current player records.'
            : 'No saved player records were found yet. Once players join, the panel can recover trusted identities from server files.';
    }

    if (scope === 'banned') {
        return knownRecords > 0
            ? 'No banned players were found in the current player records.'
            : 'No saved player records were found yet. Once players join, the panel can recover trusted identities from server files.';
    }

    if (onlineModeEnabled === false) {
        return 'Trusted player records are unavailable because online-mode=false in server.properties. Enable online-mode=true and restart the server.';
    }

    return 'No saved player records were found yet. Once players join, the panel can recover trusted identities from server files such as usercache.json.';
};

const buildPlayersEmptyMessage = (
    data: PlayersListResponse | null,
    scope: PlayerScope,
    search: string
): string => {
    if (search) {
        return 'No players matched current filter.';
    }

    if (!data) {
        return 'Player data is still loading.';
    }

    if (data.game.type === 'minecraft_java') {
        return buildMinecraftJavaEmptyMessage(data, scope);
    }

    if (canUseBedrockConsoleRoster(data)) {
        return 'Waiting for the Bedrock live roster snapshot. The panel refreshes it automatically from the console list command when online-mode is enabled.';
    }

    return scope === 'all'
        ? 'No player records are available for this server yet.'
        : 'No players matched this filter yet.';
};

const isGenericPlayerName = (name?: string | null): boolean => {
    const normalized = (name || '').trim().toLowerCase();
    if (!normalized) return true;

    if (/^online\s+player\s*#?\d+$/i.test(normalized)) {
        return true;
    }

    return ['anonymous', 'anonymous player', 'unknown', 'unknown player'].includes(normalized);
};

const mergeStablePlayerNames = (
    next: PlayersListResponse,
    previous: PlayersListResponse | null
): PlayersListResponse => {
    if (!previous) return next;

    const previousById = new Map(previous.items.map((item) => [item.id, item]));
    const previousByUuid = new Map(previous.items.filter((item) => item.uuid).map((item) => [item.uuid, item]));

    const items = next.items.map((item) => {
        const prior = previousById.get(item.id) || (item.uuid ? previousByUuid.get(item.uuid) : undefined);
        if (!prior) return item;

        const nextName = (item.name || '').trim();
        const priorName = (prior.name || '').trim();
        const keepPriorName =
            (!nextName || isGenericPlayerName(nextName)) && !!priorName && !isGenericPlayerName(priorName);

        return {
            ...item,
            name: keepPriorName ? priorName : item.name,
            avatar_url: item.avatar_url || prior.avatar_url,
        };
    });

    return {
        ...next,
        items,
    };
};

const canUseBedrockConsoleRoster = (data?: PlayersListResponse | null): boolean =>
    data?.game.type === 'minecraft_bedrock' &&
    ((data.capabilities?.integrations?.bedrock_console_roster_fallback as boolean | undefined) === true);

const buildBedrockConsolePlayerId = (name: string): string => {
    const slug = name
        .trim()
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');

    return `bedrock:console:${slug || 'player'}`;
};

const isConsoleOnlyPlayer = (player?: ServerPlayer | null): boolean => player?.meta?.console_only === true;

const matchesPlayerSearch = (player: ServerPlayer, search: string): boolean => {
    const needle = search.trim().toLowerCase();
    if (!needle) {
        return true;
    }

    return [player.name, player.id, player.uuid, player.source_id]
        .join(' ')
        .toLowerCase()
        .includes(needle);
};

const withBedrockConsoleRoster = (
    data: PlayersListResponse | null,
    roster: BedrockConsoleRoster | null,
    scope: PlayerScope,
    search: string
): PlayersListResponse | null => {
    if (!data || !canUseBedrockConsoleRoster(data) || !roster) {
        return data;
    }

    const existingByName = new Map(
        (data.items || []).map((player) => [player.name.trim().toLowerCase(), player] as const)
    );
    const rosterKeys = new Set(roster.names.map((name) => name.trim().toLowerCase()));

    const mergedOnlineItems = [
        ...roster.names.map((name) => {
            const existing = existingByName.get(name.trim().toLowerCase());
            if (existing) {
                return existing;
            }

            return {
                id: buildBedrockConsolePlayerId(name),
                name,
                uuid: '',
                source_id: buildBedrockConsolePlayerId(name),
                status: 'online',
                ping: 0,
                role: 'player',
                is_operator: false,
                is_admin: false,
                banned: false,
                country: '',
                avatar_url: `https://mc-heads.net/avatar/${encodeURIComponent(name)}/64`,
                last_seen_at: new Date(roster.observedAt).toISOString(),
                is_dummy: false,
                meta: {
                    console_only: true,
                    identity_source: 'bedrock-console-list',
                },
            } as ServerPlayer;
        }),
        ...(data.items || []).filter((player) => !rosterKeys.has(player.name.trim().toLowerCase())),
    ];

    return {
        ...data,
        counts: {
            ...data.counts,
            total: Math.max(data.counts.total, mergedOnlineItems.length),
            online: Math.max(data.counts.online, mergedOnlineItems.length),
        },
        items:
            scope === 'online'
                ? mergedOnlineItems.filter((player) => matchesPlayerSearch(player, search))
                : data.items,
    };
};

const toneClass = (tone?: string): string => {
    switch (tone) {
        case 'success':
            return 'border-[#2D4A3E] bg-[#F5EFD5] text-[#2D4A3E] hover:border-[#2D4A3E] hover:bg-[rgba(45,74,62,0.12)]';
        case 'warning':
            return 'border-[#f59e0b] bg-[#F5EFD5] text-[#742220] hover:border-[#d97706] hover:bg-[rgba(245,158,11,0.12)]';
        case 'danger':
            return 'border-[#742220] bg-[#F5EFD5] text-[#742220] hover:border-[#5f1c1a] hover:bg-[rgba(116,34,32,0.12)]';
        case 'neutral':
            return 'border-[#2D4A3E] bg-[#F5EFD5] text-[#742220] hover:border-[#2D4A3E] hover:bg-[rgba(45,74,62,0.12)]';
        default:
            return 'border-[#2D4A3E] bg-[#F5EFD5] text-[#742220] hover:border-[#2D4A3E] hover:bg-[rgba(45,74,62,0.12)]';
    }
};

const tabLabel = (tab: string): string => {
    switch (tab) {
        case 'inventory':
            return 'Inventory';
        case 'statistics':
            return 'Statistics';
        default:
            return 'Overview';
    }
};

type ActionFieldType = 'text' | 'textarea' | 'number' | 'select';

type ActionField = {
    key: string;
    label: string;
    type: ActionFieldType;
    placeholder?: string;
    required?: boolean;
    helpText?: string;
    options?: TSelectData[];
};

const gamemodeOptions: TSelectData[] = [
    {
        id: 'survival',
        label: 'Survival',
        value: 'survival',
        description: 'Standard survival gameplay.',
        icon: <span className={'text-[10px] font-bold'}>S</span>,
    },
    {
        id: 'creative',
        label: 'Creative',
        value: 'creative',
        description: 'Unlimited resources and flight.',
        icon: <span className={'text-[10px] font-bold'}>C</span>,
    },
    {
        id: 'adventure',
        label: 'Adventure',
        value: 'adventure',
        description: 'Adventure mode restrictions.',
        icon: <span className={'text-[10px] font-bold'}>A</span>,
    },
    {
        id: 'spectator',
        label: 'Spectator',
        value: 'spectator',
        description: 'Free spectator camera mode.',
        icon: <span className={'text-[10px] font-bold'}>SP</span>,
    },
];

const effectOptions: TSelectData[] = [
    {
        id: 'speed',
        label: 'Speed',
        value: 'minecraft:speed',
        description: 'Increase movement speed.',
        icon: <span className={'text-[10px] font-bold'}>SP</span>,
    },
    {
        id: 'strength',
        label: 'Strength',
        value: 'minecraft:strength',
        description: 'Increase melee damage.',
        icon: <span className={'text-[10px] font-bold'}>ST</span>,
    },
    {
        id: 'regeneration',
        label: 'Regeneration',
        value: 'minecraft:regeneration',
        description: 'Regenerate health quickly.',
        icon: <span className={'text-[10px] font-bold'}>RG</span>,
    },
    {
        id: 'resistance',
        label: 'Resistance',
        value: 'minecraft:resistance',
        description: 'Reduce incoming damage.',
        icon: <span className={'text-[10px] font-bold'}>RS</span>,
    },
    {
        id: 'night_vision',
        label: 'Night Vision',
        value: 'minecraft:night_vision',
        description: 'Improve visibility in dark areas.',
        icon: <span className={'text-[10px] font-bold'}>NV</span>,
    },
];

const normalizeGamemodeValue = (value?: string | null): string => {
    const normalized = (value || '').trim().toLowerCase();
    if (['survival', 'creative', 'adventure', 'spectator'].includes(normalized)) {
        return normalized;
    }

    return 'survival';
};

const gamemodeLabelFromValue = (value?: string | null): string => {
    const normalized = normalizeGamemodeValue(value);

    switch (normalized) {
        case 'creative':
            return 'Creative';
        case 'adventure':
            return 'Adventure';
        case 'spectator':
            return 'Spectator';
        default:
            return 'Survival';
    }
};

const extractGamemodeFromStatistics = (stats?: PlayerStatisticsResponse | null): string => {
    const categories = stats?.categories || [];
    for (const category of categories) {
        for (const entry of category.entries || []) {
            if (entry.label.toLowerCase() === 'gamemode') {
                return entry.value || '-';
            }
        }
    }

    return '-';
};

const extractGamemodeFromProfile = (profile?: PlayerProfileResponse | null): string => {
    const gamemode = profile?.player.meta?.current_gamemode;

    return typeof gamemode === 'string' && gamemode.trim() !== '' ? gamemode : '-';
};

const formatPlayerPing = (ping?: number | null): string => {
    const value = Number(ping || 0);

    return value > 0 ? `${value}ms` : '-';
};

const remapBanActionGroup = (groups: PlayerActionGroup[], banned: boolean): PlayerActionGroup[] =>
    groups.map((group) => ({
        ...group,
        actions: group.actions.map((action) => {
            if (!['ban', 'unban'].includes(action.id)) {
                return action;
            }

            if (banned) {
                return {
                    id: 'unban',
                    label: 'Unban',
                    description: 'Remove ban and allow this player to reconnect.',
                    tone: 'success',
                };
            }

            return {
                id: 'ban',
                label: 'Ban',
                description: 'Ban player from server.',
                tone: 'danger',
                requires_input: true,
                input_key: 'reason',
                input_label: 'Reason',
                input_placeholder: 'Reason for ban',
            };
        }),
    }));

const actionFieldsFor = (action: PlayerAction): ActionField[] => {
    switch (action.id) {
        case 'message':
            return [
                {
                    key: 'text',
                    label: 'Message',
                    type: 'textarea',
                    placeholder: 'Type message to send...',
                    required: true,
                },
            ];
        case 'kick':
        case 'ban':
            return [
                {
                    key: 'reason',
                    label: 'Reason',
                    type: 'text',
                    placeholder: action.input_placeholder || 'Reason',
                    required: true,
                },
            ];
        case 'minecraft.gamemode':
            return [
                {
                    key: 'mode',
                    label: 'Select Gamemode',
                    type: 'select',
                    required: true,
                    options: gamemodeOptions,
                    helpText: 'Current gamemode is shown below if live playerdata is available.',
                },
            ];
        case 'minecraft.effect':
            return [
                {
                    key: 'effect',
                    label: 'Select Effect',
                    type: 'select',
                    required: true,
                    options: effectOptions,
                },
            ];
        case 'inventory.give':
            return [
                {
                    key: 'item',
                    label: 'Item ID',
                    type: 'text',
                    placeholder: 'minecraft:diamond',
                    required: true,
                },
                {
                    key: 'amount',
                    label: 'Amount',
                    type: 'number',
                    placeholder: '1',
                    required: true,
                },
            ];
        default:
            if (action.requires_input) {
                return [
                    {
                        key: action.input_key || 'value',
                        label: action.input_label || 'Input',
                        type: 'text',
                        placeholder: action.input_placeholder || '',
                        required: true,
                    },
                ];
            }

            return [];
    }
};

const PlayerAvatar = ({ player, size = 36 }: { player: ServerPlayer; size?: number }) => {
    const avatarSources = useMemo(() => {
        const normalizedUuid = (player.uuid || '').trim();
        const compactUuid = normalizedUuid.replace(/-/g, '');
        const normalizedName = (player.name || '').trim();
        const normalizedId = (player.id || '').trim();
        const compactId = normalizedId.replace(/-/g, '');
        const sources: string[] = [];

        if (player.avatar_url) {
            sources.push(player.avatar_url);
        }

        if (normalizedUuid) {
            if (compactUuid) {
                sources.push(`https://mc-heads.net/avatar/${encodeURIComponent(compactUuid)}/64`);
                sources.push(`https://minotar.net/avatar/${encodeURIComponent(compactUuid)}/64`);
            }
            if (compactUuid !== normalizedUuid) {
                sources.push(`https://mc-heads.net/avatar/${encodeURIComponent(normalizedUuid)}/64`);
                sources.push(`https://minotar.net/avatar/${encodeURIComponent(normalizedUuid)}/64`);
            }
        }

        if (normalizedName) {
            sources.push(`https://mc-heads.net/avatar/${encodeURIComponent(normalizedName)}/64`);
            sources.push(`https://minotar.net/avatar/${encodeURIComponent(normalizedName)}/64`);
        }

        if (normalizedId && normalizedId !== normalizedUuid && normalizedId !== normalizedName) {
            if (compactId) {
                sources.push(`https://mc-heads.net/avatar/${encodeURIComponent(compactId)}/64`);
                sources.push(`https://minotar.net/avatar/${encodeURIComponent(compactId)}/64`);
            }
            if (compactId !== normalizedId) {
                sources.push(`https://mc-heads.net/avatar/${encodeURIComponent(normalizedId)}/64`);
                sources.push(`https://minotar.net/avatar/${encodeURIComponent(normalizedId)}/64`);
            }
        }

        return Array.from(new Set(sources));
    }, [player.avatar_url, player.id, player.name, player.uuid]);

    const [sourceIndex, setSourceIndex] = useState(0);

    useEffect(() => {
        setSourceIndex(0);
    }, [avatarSources]);

    const activeSource = avatarSources[sourceIndex] || '';

    if (activeSource) {
        return (
            <img
                src={activeSource}
                alt={`${player.name} avatar`}
                className={'rounded-lg object-cover'}
                style={{ width: size, height: size }}
                onError={() => {
                    if (sourceIndex < avatarSources.length - 1) {
                        setSourceIndex((index) => index + 1);
                    } else {
                        setSourceIndex(avatarSources.length);
                    }
                }}
            />
        );
    }

    return (
        <div
            className={
                'flex items-center justify-center rounded-lg bg-[color:var(--accent)] text-xs font-bold text-[color:var(--foreground)]'
            }
            style={{ width: size, height: size }}
        >
            {player.name.charAt(0).toUpperCase()}
        </div>
    );
};

const itemIconSources = (slot: PlayerInventorySlot | null): string[] => {
    if (!slot) return [];

    const sources: string[] = [];
    if (slot.icon_url) {
        sources.push(slot.icon_url);
    }

    const rawItemId = (slot.item_id || '').trim();
    if (!rawItemId) return Array.from(new Set(sources));

    let namespace = 'minecraft';
    let itemPath = rawItemId;
    if (rawItemId.includes(':')) {
        const [ns, path] = rawItemId.split(':', 2);
        namespace = (ns || 'minecraft').toLowerCase();
        itemPath = path || '';
    }

    if (namespace === 'minecraft' && itemPath) {
        const encoded = encodeURIComponent(itemPath);
        sources.push(
            `https://raw.githubusercontent.com/InventivetalentDev/minecraft-assets/1.21.4/assets/minecraft/textures/item/${encoded}.png`,
            `https://raw.githubusercontent.com/InventivetalentDev/minecraft-assets/1.21.4/assets/minecraft/textures/block/${encoded}.png`,
            `https://raw.githubusercontent.com/InventivetalentDev/minecraft-assets/1.20.4/assets/minecraft/textures/item/${encoded}.png`,
            `https://raw.githubusercontent.com/InventivetalentDev/minecraft-assets/1.20.4/assets/minecraft/textures/block/${encoded}.png`
        );
    }

    return Array.from(new Set(sources));
};

const PlayerItemIcon = ({
    slot,
    className,
    emptyClassName,
}: {
    slot: PlayerInventorySlot | null;
    className?: string;
    emptyClassName?: string;
}) => {
    const sources = useMemo(() => itemIconSources(slot), [slot]);
    const [sourceIndex, setSourceIndex] = useState(0);

    useEffect(() => {
        setSourceIndex(0);
    }, [sources]);

    const activeSource = sources[sourceIndex] || '';
    if (activeSource) {
        return (
            <img
                src={activeSource}
                alt={slot?.item_name || 'Item'}
                className={classNames('object-contain', className)}
                style={{ imageRendering: 'pixelated' }}
                onError={() => {
                    if (sourceIndex < sources.length - 1) {
                        setSourceIndex((index) => index + 1);
                    } else {
                        setSourceIndex(sources.length);
                    }
                }}
            />
        );
    }

    return (
        <span className={classNames('material-icons-round text-sm text-[color:var(--text-subtle)]', emptyClassName)}>
            inventory_2
        </span>
    );
};

const parseSlotNumber = (slot: string): number | null => {
    const match = slot.match(/-?\d+/);
    if (!match) return null;

    const value = Number.parseInt(match[0], 10);
    return Number.isNaN(value) ? null : value;
};

const findSectionById = (sections: PlayerInventorySection[], ids: string[]): PlayerInventorySection | undefined => {
    const wanted = ids.map((id) => id.toLowerCase());

    return sections.find((section) => wanted.includes((section.id || '').toLowerCase()));
};

const findNamedSlot = (slots: PlayerInventorySlot[], names: string[]): PlayerInventorySlot | null => {
    const wanted = names.map((name) => name.toLowerCase());

    const found = slots.find((slot) => wanted.some((name) => (slot.slot || '').toLowerCase().includes(name)));

    return found || null;
};

const mapSlotsToGrid = (
    slots: PlayerInventorySlot[],
    size: number,
    resolvers: Array<(value: number) => number | null>
): Array<PlayerInventorySlot | null> => {
    const grid: Array<PlayerInventorySlot | null> = Array.from({ length: size }, () => null);

    slots.forEach((slot) => {
        const parsed = parseSlotNumber(slot.slot || '');

        let index: number | null = null;
        if (parsed !== null) {
            for (const resolver of resolvers) {
                const resolved = resolver(parsed);
                if (resolved !== null && resolved >= 0 && resolved < size) {
                    index = resolved;
                    break;
                }
            }
        }

        if (index === null) {
            const firstEmpty = grid.findIndex((entry) => entry === null);
            if (firstEmpty !== -1) {
                index = firstEmpty;
            }
        }

        if (index !== null && !grid[index]) {
            grid[index] = slot;
        }
    });

    return grid;
};

const ServerClock = () => {
    const [time, setTime] = useState(new Date());

    useEffect(() => {
        const timer = setInterval(() => setTime(new Date()), 1000);
        return () => clearInterval(timer);
    }, []);

    const formattedTime = time.toLocaleTimeString('en-US', {
        timeZone: 'UTC',
        hour12: false,
        hour: '2-digit',
        minute: '2-digit',
    });

    return (
        <span className={'sc-12e2ts-13 hQcRzU text-xs font-mono font-medium text-[color:var(--text-subtle)] bg-[color:var(--primary)]/10 px-2 py-1 rounded-md'}>
            UTC Time: {formattedTime}
        </span>
    );
};

const ServerConsoleContainer = () => {
    const name = ServerContext.useStoreState((state) => state.server.data!.name);
    const status = ServerContext.useStoreState((state) => state.status.value);
    const conflictStatus = ServerContext.useStoreState((state) => state.server.data!.status);
    const inConflictState = ServerContext.useStoreState((state) => state.server.inConflictState);
    const limits = ServerContext.useStoreState((state) => state.server.data!.limits);
    const node = ServerContext.useStoreState((state) => state.server.data!.node);
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const isInstalling = ServerContext.useStoreState((state) => state.server.isInstalling);
    const isTransferring = ServerContext.useStoreState((state) => state.server.data!.isTransferring);
    const eggFeatures = ServerContext.useStoreState((state) => state.server.data!.eggFeatures, isEqual);
    const isNodeUnderMaintenance = ServerContext.useStoreState((state) => state.server.data!.isNodeUnderMaintenance);
    const connected = ServerContext.useStoreState((state) => state.socket.connected);
    const instance = ServerContext.useStoreState((state) => state.socket.instance);
    const username = useStoreState((state: ApplicationStore) => state.user.data!.username);
    const email = useStoreState((state: ApplicationStore) => state.user.data!.email);
    const { addFlash, clearFlashes } = useFlash();
    const { data: subdomainsData } = getServerSubdomains();

    const allocation = ServerContext.useStoreState((state) => {
        const match = state.server.data!.allocations.find((item) => item.isDefault);
        return !match ? 'n/a' : `${match.alias || ip(match.ip)}:${match.port}`;
    });

    const [stats, setStats] = useState<LiveStats>({ memory: 0, cpu: 0, disk: 0, rx: 0, tx: 0 });
    const [networkRate, setNetworkRate] = useState<{ rx: number; tx: number }>({ rx: 0, tx: 0 });
    const previousNetwork = useRef<{ rx: number; tx: number }>({ rx: -1, tx: -1 });

    const [playerScope, setPlayerScope] = useState<PlayerScope>('online');
    const [playerSearch, setPlayerSearch] = useState('');
    const [debouncedPlayerSearch, setDebouncedPlayerSearch] = useState('');
    const [playersLoading, setPlayersLoading] = useState(false);
    const [playersError, setPlayersError] = useState<string | null>(null);
    const [playersData, setPlayersData] = useState<PlayersListResponse | null>(null);
    const [bedrockConsoleRoster, setBedrockConsoleRoster] = useState<BedrockConsoleRoster | null>(null);
    const pendingBedrockRoster = useRef<{ online: number; max: number } | null>(null);

    const [playerDialogOpen, setPlayerDialogOpen] = useState(false);
    const [playerDialogLoading, setPlayerDialogLoading] = useState(false);
    const [playerDialogError, setPlayerDialogError] = useState<string | null>(null);
    const [playerDialogTab, setPlayerDialogTab] = useState<'overview' | 'inventory' | 'statistics'>('overview');
    const [playerActionLoading, setPlayerActionLoading] = useState<string | null>(null);
    const [playerActionNotice, setPlayerActionNotice] = useState<string | null>(null);
    const [playerActionDialogOpen, setPlayerActionDialogOpen] = useState(false);
    const [playerActionTarget, setPlayerActionTarget] = useState<PlayerAction | null>(null);
    const [playerActionContext, setPlayerActionContext] = useState<Record<string, string>>({});
    const [playerActionFormError, setPlayerActionFormError] = useState<string | null>(null);
    const [playerGamemodeHint, setPlayerGamemodeHint] = useState<string | null>(null);
    const [playerActionGamemodeLabel, setPlayerActionGamemodeLabel] = useState<string>('-');
    const [selectedPlayer, setSelectedPlayer] = useState<PlayerProfileResponse | null>(null);
    const [selectedInventory, setSelectedInventory] = useState<PlayerInventoryResponse | null>(null);
    const [selectedStatistics, setSelectedStatistics] = useState<PlayerStatisticsResponse | null>(null);
    const [sideConsoleOpen, setSideConsoleOpen] = useState(false);

    useEffect(() => {
        document.title = `${name} | Console`;
    }, [name]);

    useEffect(() => {
        if (connected && instance) {
            instance.send(SocketRequest.SEND_STATS);
        }
    }, [connected, instance]);

    useEffect(() => {
        const timeout = window.setTimeout(() => setDebouncedPlayerSearch(playerSearch.trim()), 280);

        return () => window.clearTimeout(timeout);
    }, [playerSearch]);

    const effectivePlayersData = useMemo(
        () => withBedrockConsoleRoster(playersData, bedrockConsoleRoster, playerScope, debouncedPlayerSearch),
        [playersData, bedrockConsoleRoster, playerScope, debouncedPlayerSearch]
    );
    const playerProviderLabel = useMemo(() => buildPlayerProviderLabel(effectivePlayersData), [effectivePlayersData]);

    const playersUnavailableReason = (() => {
        if (conflictStatus === 'installing') {
            return 'Player data is unavailable while the server is installing or reinstalling.';
        }

        if (conflictStatus === 'install_failed' || conflictStatus === 'reinstall_failed') {
            return 'Player data is unavailable while the server is in a failed install state.';
        }

        if (conflictStatus === 'suspended') {
            return 'Player data is unavailable while the server is suspended.';
        }

        if (isTransferring) {
            return 'Player data is unavailable while the server is being transferred.';
        }

        if (isNodeUnderMaintenance) {
            return 'Player data is unavailable while the node is under maintenance.';
        }

        return null;
    })();

    useEffect(() => {
        if (inConflictState) {
            setPlayersLoading(false);
            setPlayersData(null);
            setBedrockConsoleRoster(null);
            pendingBedrockRoster.current = null;
            setPlayersError(playersUnavailableReason || 'Player data is temporarily unavailable.');

            return;
        }

        // Don't poll for players when the server is offline or stopping - avoids sending
        // unnecessary commands (like "list uuids") to a server that isn't running.
        if (status === 'offline' || status === 'stopping') {
            setPlayersLoading(false);
            setPlayersData(null);
            setBedrockConsoleRoster(null);
            pendingBedrockRoster.current = null;
            return;
        }

        let active = true;
        let inFlight = false;

        const requestPlayers = (showLoading: boolean) => {
            if (!active || inFlight) {
                return;
            }

            inFlight = true;
            if (showLoading) {
                setPlayersLoading(true);
            }
            setPlayersError(null);

            getServerPlayers(uuid, {
                scope: playerScope,
                search: debouncedPlayerSearch || undefined,
            })
                .then((response) => {
                    if (!active) return;
                    setPlayersData((previous) => mergeStablePlayerNames(response, previous));
                })
                .catch((error) => {
                    if (!active) return;
                    setPlayersError(httpErrorToHuman(error));
                })
                .finally(() => {
                    inFlight = false;
                    if (!active) return;
                    if (showLoading) {
                        setPlayersLoading(false);
                    }
                });
        };

        requestPlayers(true);
        const interval = window.setInterval(() => requestPlayers(false), 12000);

        return () => {
            active = false;
            window.clearInterval(interval);
        };
    }, [uuid, playerScope, debouncedPlayerSearch, inConflictState, playersUnavailableReason, status]);

    useEffect(() => {
        if (!connected || !instance || inConflictState || !canUseBedrockConsoleRoster(playersData)) {
            return;
        }

        const requestBedrockRoster = () => {
            window.dispatchEvent(new CustomEvent(BEDROCK_SYSTEM_LIST_EVENT, { detail: { serverId: uuid } }));
            instance.send('send command', 'list');
        };

        requestBedrockRoster();
        const interval = window.setInterval(() => requestBedrockRoster(), 15000);

        return () => window.clearInterval(interval);
    }, [connected, instance, inConflictState, playersData, uuid]);

    useWebsocketEvent(SocketEvent.CONSOLE_OUTPUT, (data) => {
        if (!canUseBedrockConsoleRoster(playersData)) {
            pendingBedrockRoster.current = null;
            return;
        }

        const normalized = normalizeConsoleLine(data);
        const header = parseBedrockRosterHeader(normalized);
        if (header) {
            pendingBedrockRoster.current =
                header.names.length === 0 && header.online > 0 ? { online: header.online, max: header.max } : null;

            setBedrockConsoleRoster({
                online: header.online,
                max: header.max,
                names: header.names.slice(0, header.online || header.names.length),
                observedAt: Date.now(),
            });
            return;
        }

        if (!pendingBedrockRoster.current || !normalized || isBedrockListCommandEcho(normalized)) {
            return;
        }

        const names = parseBedrockRosterNames(normalized);
        if (names.length === 0) {
            if (!/^\[.*\]/.test(normalized)) {
                pendingBedrockRoster.current = null;
            }

            return;
        }

        const pending = pendingBedrockRoster.current;
        pendingBedrockRoster.current = null;

        setBedrockConsoleRoster({
            online: pending.online,
            max: pending.max,
            names: names.slice(0, pending.online || names.length),
            observedAt: Date.now(),
        });
    });

    useWebsocketEvent(SocketEvent.STATS, (data) => {
        let parsed: any = {};
        try {
            parsed = JSON.parse(data);
        } catch (error) {
            return;
        }

        const currentRx = parsed.network?.rx_bytes || 0;
        const currentTx = parsed.network?.tx_bytes || 0;
        const rxPerSecond = previousNetwork.current.rx < 0 ? 0 : Math.max(0, currentRx - previousNetwork.current.rx);
        const txPerSecond = previousNetwork.current.tx < 0 ? 0 : Math.max(0, currentTx - previousNetwork.current.tx);

        previousNetwork.current = { rx: currentRx, tx: currentTx };
        setNetworkRate({ rx: rxPerSecond, tx: txPerSecond });
        setStats({
            memory: parsed.memory_bytes || 0,
            cpu: parsed.cpu_absolute || 0,
            disk: parsed.disk_bytes || 0,
            rx: currentRx,
            tx: currentTx,
        });
    });

    const memoryLimitBytes = useMemo(() => (limits.memory > 0 ? mbToBytes(limits.memory) : 0), [limits.memory]);
    const diskLimitBytes = useMemo(() => (limits.disk > 0 ? mbToBytes(limits.disk) : 0), [limits.disk]);

    const cpuPercent = limits.cpu > 0 ? clampPercent((stats.cpu / limits.cpu) * 100) : 0;
    const memoryPercent = memoryLimitBytes > 0 ? clampPercent((stats.memory / memoryLimitBytes) * 100) : 0;
    const diskPercent = diskLimitBytes > 0 ? clampPercent((stats.disk / diskLimitBytes) * 100) : 0;

    const statusBadgeClass = classNames(
        'rounded-lg border px-2 py-0.5 text-xs font-bold',
        status === 'running'
            ? 'border-green-700/60 bg-green-100 text-green-800'
            : status === 'offline' || status === null
                ? 'border-red-700/40 bg-red-100 text-red-800'
                : 'border-amber-700/40 bg-amber-100 text-amber-900'
    );

    const playerFilterOptions = useMemo<TSelectData[]>(() => {
        const source = (
            effectivePlayersData?.capabilities.filters ||
            defaultPlayerFilters.map((item) => ({
                id: item.id,
                label: item.label,
                description: item.description,
            }))
        ).filter((item) => allowedPlayerScopes.includes(item.id as PlayerScope));

        const normalized = source.length
            ? source
            : defaultPlayerFilters.map((item) => ({
                id: item.id,
                label: item.label,
                description: item.description,
            }));

        const withAll =
            normalized.some((item) => item.id === 'all')
                ? normalized
                : [
                    {
                        id: 'all',
                        label: 'All Players',
                        description: 'All player records currently known to the panel.',
                    },
                    ...normalized,
                ];

        return withAll.map((item) => {
            const scope = item.id as PlayerScope;
            const count = scopeCount(scope, effectivePlayersData);

            return {
                id: item.id,
                label: item.label,
                value: item.id,
                description: undefined,
                icon: (
                    <span
                        className={
                            'inline-flex items-center justify-center text-[11px] font-bold leading-none tracking-tight'
                        }
                    >
                        {count > 99 ? '99+' : count}
                    </span>
                ),
            };
        });
    }, [effectivePlayersData]);

    const playerTabs = useMemo(() => {
        const tabs = new Set<string>(['overview']);

        (effectivePlayersData?.capabilities.tabs || []).forEach((tab) => tabs.add(tab));

        if (selectedInventory?.available) tabs.add('inventory');
        if (selectedStatistics?.available) tabs.add('statistics');

        return Array.from(tabs).filter((tab) => ['overview', 'inventory', 'statistics'].includes(tab));
    }, [effectivePlayersData, selectedInventory, selectedStatistics]);

    useEffect(() => {
        if (!playerTabs.includes(playerDialogTab)) {
            setPlayerDialogTab('overview');
        }
    }, [playerTabs, playerDialogTab]);

    const activeGameType = (selectedPlayer?.game?.type || selectedInventory?.game?.type || '').toLowerCase();
    const useMinecraftInventoryLayout = activeGameType === 'minecraft_java' || activeGameType === 'minecraft_bedrock';

    const minecraftInventoryLayout = useMemo(() => {
        if (!selectedInventory?.available || !useMinecraftInventoryLayout) return null;

        const sections = selectedInventory.sections || [];
        const armorSection = findSectionById(sections, ['armor']);
        const offhandSection = findSectionById(sections, ['offhand']);
        const inventorySection =
            findSectionById(sections, ['inventory', 'main', 'main_inventory']) ||
            sections.find((section) => !['armor', 'offhand', 'hotbar'].includes((section.id || '').toLowerCase()));
        const hotbarSection = findSectionById(sections, ['hotbar']);

        const armorSlots = armorSection?.slots || [];
        const offhandFromArmor = findNamedSlot(armorSlots, ['offhand']);
        const offhandSlot = offhandFromArmor || offhandSection?.slots?.[0] || null;

        const armor = {
            helmet: findNamedSlot(armorSlots, ['helmet']) || armorSlots[0] || null,
            chestplate: findNamedSlot(armorSlots, ['chestplate']) || armorSlots[1] || null,
            leggings: findNamedSlot(armorSlots, ['leggings']) || armorSlots[2] || null,
            boots: findNamedSlot(armorSlots, ['boots']) || armorSlots[3] || null,
        };

        const mainSlots = mapSlotsToGrid(inventorySection?.slots || [], 27, [
            (value) => (value >= 10 && value <= 36 ? value - 10 : null),
            (value) => (value >= 9 && value <= 35 ? value - 9 : null),
            (value) => (value >= 1 && value <= 27 ? value - 1 : null),
            (value) => (value >= 0 && value <= 26 ? value : null),
        ]);

        const hotbarSlots = mapSlotsToGrid(hotbarSection?.slots || [], 9, [
            (value) => (value >= 1 && value <= 9 ? value - 1 : null),
            (value) => (value >= 0 && value <= 8 ? value : null),
            (value) => (value >= 36 && value <= 44 ? value - 36 : null),
        ]);

        return {
            armor,
            offhandSlot,
            mainSlots,
            hotbarSlots,
        };
    }, [selectedInventory, useMinecraftInventoryLayout]);

    const currentGamemodeFromStats = useMemo(
        () => extractGamemodeFromStatistics(selectedStatistics),
        [selectedStatistics]
    );
    const currentGamemodeFromProfile = extractGamemodeFromProfile(selectedPlayer);
    const currentGamemode =
        playerGamemodeHint || (currentGamemodeFromStats !== '-' ? currentGamemodeFromStats : currentGamemodeFromProfile);
    const displayedActionGamemode =
        playerActionGamemodeLabel === 'Unavailable' || playerActionGamemodeLabel === 'Loading from playerdata...'
            ? currentGamemode !== '-'
                ? currentGamemode
                : playerActionGamemodeLabel
            : playerActionGamemodeLabel || currentGamemode || '-';

    const actionDialogFields = useMemo(
        () => (playerActionTarget ? actionFieldsFor(playerActionTarget) : []),
        [playerActionTarget]
    );

    const renderMinecraftSlot = (
        slot: PlayerInventorySlot | null,
        options?: { indexLabel?: string; titlePrefix?: string }
    ) => (
        <div
            className={
                'relative flex h-12 w-12 items-center justify-center overflow-hidden rounded border border-[color:var(--border)] bg-[color:var(--card)]'
            }
            title={slot ? `${slot.item_name} (${slot.item_id})` : options?.titlePrefix || 'Empty Slot'}
        >
            <PlayerItemIcon
                slot={slot}
                className={'h-8 w-8'}
                emptyClassName={'text-[color:var(--text-subtle)] opacity-40'}
            />
            {options?.indexLabel && (
                <span className={'absolute left-1 top-0 text-[10px] font-semibold text-[color:var(--text-subtle)]'}>
                    {options.indexLabel}
                </span>
            )}
            {slot && slot.count > 1 && (
                <span
                    className={
                        'absolute bottom-0 right-1 text-[11px] font-bold text-[color:var(--foreground)] [text-shadow:0_1px_1px_rgba(0,0,0,0.9)]'
                    }
                >
                    {slot.count}
                </span>
            )}
        </div>
    );

    const loadPlayerDetailsData = async (playerId: string) => {
        const [profile, inventory, statistics] = await Promise.all([
            getServerPlayerProfile(uuid, playerId),
            getServerPlayerInventory(uuid, playerId),
            getServerPlayerStatistics(uuid, playerId),
        ]);

        return { profile, inventory, statistics };
    };

    const openPlayerDetails = async (playerId: string): Promise<void> => {
        setPlayerDialogOpen(true);
        setPlayerDialogLoading(true);
        setPlayerDialogError(null);
        setPlayerActionNotice(null);
        setPlayerActionLoading(null);
        setPlayerDialogTab('overview');
        setPlayerActionDialogOpen(false);
        setPlayerActionTarget(null);
        setPlayerActionFormError(null);
        setPlayerActionGamemodeLabel('-');
        setPlayerGamemodeHint(null);

        try {
            const { profile, inventory, statistics } = await loadPlayerDetailsData(playerId);
            setSelectedPlayer(profile);
            setSelectedInventory(inventory);
            setSelectedStatistics(statistics);
            const detectedMode = extractGamemodeFromStatistics(statistics);
            const profileMode = extractGamemodeFromProfile(profile);
            setPlayerGamemodeHint(detectedMode !== '-' ? detectedMode : profileMode !== '-' ? profileMode : null);
        } catch (error) {
            setSelectedPlayer(null);
            setSelectedInventory(null);
            setSelectedStatistics(null);
            setPlayerGamemodeHint(null);
            setPlayerDialogError(httpErrorToHuman(error));
        } finally {
            setPlayerDialogLoading(false);
        }
    };

    const closePlayerDialog = (): void => {
        setPlayerDialogOpen(false);
        setPlayerActionDialogOpen(false);
        setPlayerActionTarget(null);
        setPlayerActionFormError(null);
        setPlayerActionGamemodeLabel('-');
        setPlayerActionNotice(null);
        setPlayerActionLoading(null);
        setPlayerGamemodeHint(null);
    };

    const closePlayerActionDialog = (): void => {
        setPlayerActionDialogOpen(false);
        setPlayerActionTarget(null);
        setPlayerActionFormError(null);
    };

    const openPlayerActionDialog = (action: PlayerAction): void => {
        const initialContext: Record<string, string> = {};
        const defaultFields = actionFieldsFor(action);
        const initialGamemode = currentGamemode !== '-' ? currentGamemode : '-';

        defaultFields.forEach((field) => {
            if (field.type === 'select') {
                initialContext[field.key] = field.options?.[0]?.value || '';
            } else if (field.key === 'amount') {
                initialContext[field.key] = '1';
            } else {
                initialContext[field.key] = '';
            }
        });

        if (action.id === 'minecraft.gamemode') {
            initialContext.mode = normalizeGamemodeValue(currentGamemode);
            setPlayerActionGamemodeLabel(initialGamemode === '-' ? 'Loading from playerdata...' : initialGamemode);
        } else {
            setPlayerActionGamemodeLabel('-');
        }

        setPlayerActionContext(initialContext);
        setPlayerActionFormError(null);
        setPlayerActionTarget(action);
        setPlayerActionDialogOpen(true);

        if (action.id === 'minecraft.gamemode' && selectedPlayer?.player.id) {
            const playerId = selectedPlayer.player.id;

            Promise.allSettled([
                getServerPlayerProfile(uuid, playerId),
                getServerPlayerStatistics(uuid, playerId),
            ])
                .then(([profileResult, statisticsResult]) => {
                    const profile = profileResult.status === 'fulfilled' ? profileResult.value : selectedPlayer;
                    const statistics = statisticsResult.status === 'fulfilled' ? statisticsResult.value : selectedStatistics;
                    const detectedMode = extractGamemodeFromStatistics(statistics);
                    const profileMode = extractGamemodeFromProfile(profile);
                    const resolvedMode = detectedMode !== '-' ? detectedMode : profileMode;

                    if (profileResult.status === 'fulfilled') {
                        setSelectedPlayer(profile);
                    }
                    if (statisticsResult.status === 'fulfilled') {
                        setSelectedStatistics(statistics);
                    }
                    if (resolvedMode === '-') {
                        if (currentGamemode === '-') {
                            setPlayerActionGamemodeLabel('Unavailable');
                        }
                        return;
                    }

                    setPlayerGamemodeHint(resolvedMode);
                    setPlayerActionGamemodeLabel(resolvedMode);
                    setPlayerActionContext((current) => ({
                        ...current,
                        mode: normalizeGamemodeValue(resolvedMode),
                    }));
                })
                .catch(() => {
                    if (currentGamemode === '-') {
                        setPlayerActionGamemodeLabel('Unavailable');
                    }
                });
        }
    };

    const refreshSelectedPlayerDetails = async (): Promise<void> => {
        const activePlayerId = selectedPlayer?.player.id;
        if (!activePlayerId) return;

        try {
            const { profile, inventory, statistics } = await loadPlayerDetailsData(activePlayerId);
            setSelectedPlayer(profile);
            setSelectedInventory(inventory);
            setSelectedStatistics(statistics);
        } catch (error) {
            // Keep existing UI state if refresh fails; user already has previous successful snapshot.
        }
    };

    const runPlayerAction = async (): Promise<void> => {
        if (!selectedPlayer || !playerActionTarget) return;

        const context: Record<string, unknown> = {};
        const fields = actionFieldsFor(playerActionTarget);

        for (const field of fields) {
            const value = (playerActionContext[field.key] || '').trim();
            if (field.required && !value) {
                setPlayerActionFormError(`Please fill "${field.label}".`);
                return;
            }

            if (field.type === 'number') {
                const amount = Number.parseInt(value || '0', 10);
                if (Number.isNaN(amount) || amount <= 0) {
                    setPlayerActionFormError(`"${field.label}" must be a number greater than 0.`);
                    return;
                }
                context[field.key] = amount;
            } else if (value !== '') {
                context[field.key] = value;
            }
        }

        if (playerActionTarget.id === 'message' && typeof context.text !== 'string') {
            context.text = 'Hello from panel';
        }
        if (
            (playerActionTarget.id === 'kick' || playerActionTarget.id === 'ban') &&
            typeof context.reason !== 'string'
        ) {
            context.reason = 'Panel moderation';
        }
        if (playerActionTarget.id === 'inventory.give') {
            if (typeof context.item !== 'string') context.item = 'minecraft:diamond';
            if (typeof context.amount === 'undefined') context.amount = 1;
        }
        if (playerActionTarget.id === 'minecraft.gamemode') {
            context.mode = normalizeGamemodeValue(String(context.mode || currentGamemode));
        }

        setPlayerActionFormError(null);
        setPlayerActionLoading(playerActionTarget.id);
        setPlayerActionNotice(null);

        const actionId = playerActionTarget.id;
        const playerId = selectedPlayer.player.id;

        try {
            const response = await runServerPlayerAction(uuid, playerId, actionId, context);
            const command = response.command_preview ? `\nCommand: ${response.command_preview}` : '';
            setPlayerActionNotice(`${response.message}${command}`);
            if (actionId === 'minecraft.gamemode') {
                const nextGamemode = gamemodeLabelFromValue(String(context.mode || 'survival'));
                setPlayerGamemodeHint(nextGamemode);
                setPlayerActionGamemodeLabel(nextGamemode);
                setSelectedPlayer((current) => {
                    if (!current) return current;

                    return {
                        ...current,
                        player: {
                            ...current.player,
                            meta: {
                                ...(current.player.meta || {}),
                                current_gamemode: nextGamemode,
                            },
                        },
                    };
                });
                setSelectedStatistics((current) => {
                    if (!current) return current;

                    return {
                        ...current,
                        categories: current.categories.map((category) => ({
                            ...category,
                            entries: (category.entries || []).map((entry) =>
                                entry.label.toLowerCase() === 'gamemode'
                                    ? {
                                          ...entry,
                                          value: nextGamemode,
                                      }
                                    : entry
                            ),
                        })),
                    };
                });
            }
            if (actionId === 'ban' || actionId === 'unban') {
                const willBeBanned = actionId === 'ban';
                setSelectedPlayer((current) => {
                    if (!current) return current;

                    return {
                        ...current,
                        player: {
                            ...current.player,
                            banned: willBeBanned,
                            action_groups: remapBanActionGroup(current.player.action_groups, willBeBanned),
                        },
                    };
                });
            }
            closePlayerActionDialog();
            void refreshSelectedPlayerDetails();
        } catch (error) {
            setPlayerActionFormError(httpErrorToHuman(error));
        } finally {
            setPlayerActionLoading(null);
        }
    };

    return (
        <>
            {playerActionDialogOpen && createPortal(
                <div
                    className={'fixed inset-0 z-[9999] flex items-center justify-center px-3 py-5'}
                    onMouseDown={(event) => {
                        if (event.target === event.currentTarget) {
                            closePlayerActionDialog();
                        }
                    }}
                >
                    <div className={'absolute inset-0 bg-[#101711]/70 backdrop-blur-sm'} />
                    <div
                        className={
                            'relative z-[10000] max-h-[calc(100vh-8rem)] w-[92vw] max-w-[640px] overflow-y-auto rounded-[1.35rem] border-2 p-3 text-[#742220] shadow-none transition-all duration-150 sm:p-4 md:p-6'
                        }
                        style={{
                            borderColor: '#2D4A3E',
                            backgroundColor: '#FEF9E1',
                            backgroundImage:
                                'repeating-linear-gradient(0deg, transparent, transparent 4.5px, rgba(116, 34, 32, 0.04) 4.5px, rgba(116, 34, 32, 0.04) 5px), repeating-linear-gradient(60deg, transparent, transparent 4.5px, rgba(116, 34, 32, 0.04) 4.5px, rgba(116, 34, 32, 0.04) 5px), repeating-linear-gradient(120deg, transparent, transparent 4.5px, rgba(116, 34, 32, 0.04) 4.5px, rgba(116, 34, 32, 0.04) 5px)',
                            boxShadow: '#2D4A3E 4px 4px 0 0',
                        }}
                    >
                        <div className={'mb-5 flex items-start justify-between gap-4'}>
                            <div>
                                <h3
                                    className={
                                        'text-2xl font-bold tracking-wide text-[#742220]'
                                    }
                                >
                                    {playerActionTarget ? `Run: ${playerActionTarget.label}` : 'Run Action'}
                                </h3>
                                <p className={'mt-2 text-sm leading-6 text-[rgba(116,34,32,0.72)]'}>
                                    {playerActionTarget?.description ||
                                        'Configure action details and confirm execution.'}
                                </p>
                            </div>
                            <button
                                type={'button'}
                                onClick={closePlayerActionDialog}
                                className={
                                    'flex h-9 w-9 shrink-0 items-center justify-center rounded-[10px] border-2 border-[#2D4A3E] bg-[#F5EFD5] text-[#742220] transition-colors hover:bg-[rgba(45,74,62,0.12)] hover:text-[#2D4A3E] focus:outline-none focus:ring-2 focus:ring-[#2D4A3E]/40'
                                }
                                aria-label={'Close action dialog'}
                            >
                                <svg
                                    xmlns={'http://www.w3.org/2000/svg'}
                                    viewBox={'0 0 20 20'}
                                    fill={'currentColor'}
                                    aria-hidden={'true'}
                                    className={'h-5 w-5'}
                                >
                                    <path
                                        fillRule={'evenodd'}
                                        d={'M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z'}
                                        clipRule={'evenodd'}
                                    />
                                </svg>
                            </button>
                        </div>

                        <div className={'space-y-4'}>
                            {playerActionTarget?.id === 'minecraft.gamemode' && (
                                <div
                                    className={
                                        'rounded-[18px] border-2 border-[#2D4A3E] bg-[#F5EFD5] px-4 py-3 text-[#742220] shadow-[4px_4px_0_0_#2D4A3E]'
                                    }
                                >
                                    <p
                                        className={
                                            'text-[11px] font-bold uppercase tracking-wide text-[rgba(116,34,32,0.72)]'
                                        }
                                    >
                                        Current Gamemode
                                    </p>
                                    <p className={'mt-1 text-sm font-semibold text-[#742220]'}>
                                        {displayedActionGamemode}
                                    </p>
                                </div>
                            )}

                            {actionDialogFields.map((field) => (
                                <div key={field.key} className={'space-y-1.5'}>
                                    <label
                                        className={
                                            'block text-[11px] font-bold uppercase tracking-wide text-[#742220]'
                                        }
                                    >
                                        {field.label}
                                    </label>
                                    {field.type === 'select' ? (
                                        <Select
                                            key={`${field.key}-${playerActionContext[field.key] || field.options?.[0]?.value || ''}`}
                                            data={field.options || []}
                                            value={playerActionContext[field.key] || field.options?.[0]?.value}
                                            title={field.label}
                                            compact
                                            onChange={(value) =>
                                                setPlayerActionContext((current) => ({
                                                    ...current,
                                                    [field.key]: value,
                                                }))
                                            }
                                        />
                                    ) : field.type === 'textarea' ? (
                                        <textarea
                                            autoFocus
                                            className={
                                                'w-full rounded-[18px] border-2 border-[#2D4A3E] bg-[#F5EFD5] px-4 py-3 text-sm font-medium text-[#742220] outline-none transition-colors placeholder:text-[rgba(116,34,32,0.42)] focus:border-[#2D4A3E] focus:ring-2 focus:ring-[#2D4A3E]/25'
                                            }
                                            rows={4}
                                            placeholder={field.placeholder}
                                            value={playerActionContext[field.key] || ''}
                                            onChange={(event) => {
                                                const value = event.currentTarget.value;

                                                setPlayerActionContext((current) => ({
                                                    ...current,
                                                    [field.key]: value,
                                                }));
                                            }}
                                        />
                                    ) : (
                                        <input
                                            autoFocus
                                            className={
                                                'w-full rounded-[18px] border-2 border-[#2D4A3E] bg-[#F5EFD5] px-4 py-3 text-sm font-medium text-[#742220] outline-none transition-colors placeholder:text-[rgba(116,34,32,0.42)] focus:border-[#2D4A3E] focus:ring-2 focus:ring-[#2D4A3E]/25'
                                            }
                                            type={field.type === 'number' ? 'number' : 'text'}
                                            min={field.type === 'number' ? 1 : undefined}
                                            step={field.type === 'number' ? 1 : undefined}
                                            placeholder={field.placeholder}
                                            value={playerActionContext[field.key] || ''}
                                            onChange={(event) => {
                                                const value = event.currentTarget.value;

                                                setPlayerActionContext((current) => ({
                                                    ...current,
                                                    [field.key]: value,
                                                }));
                                            }}
                                        />
                                    )}
                                    {field.helpText && (
                                        <p className={'text-[11px] text-[rgba(116,34,32,0.72)]'}>
                                            {field.helpText}
                                        </p>
                                    )}
                                </div>
                            ))}

                            {actionDialogFields.length === 0 && (
                                <div
                                    className={
                                        'rounded-[18px] border-2 border-[#2D4A3E] bg-[#F5EFD5] px-4 py-3 text-xs font-medium text-[#742220] shadow-[4px_4px_0_0_#2D4A3E]'
                                    }
                                >
                                    This action does not require additional input.
                                </div>
                            )}

                            {playerActionFormError && (
                                <div
                                    className={
                                        'rounded-[18px] border-2 border-[#742220] bg-[rgba(116,34,32,0.1)] px-4 py-3 text-xs font-semibold text-[#742220]'
                                    }
                                >
                                    {playerActionFormError}
                                </div>
                            )}

                            <div className={'mt-6 flex flex-wrap items-center justify-end gap-2 border-t border-[#2D4A3E] pt-5'}>
                                <button
                                    type={'button'}
                                    className={
                                        'group relative inline-flex h-10 min-w-[8.5rem] cursor-pointer items-center justify-center overflow-hidden rounded-full border border-[#2D4A3E] bg-[#F5EFD5] px-6 text-xs font-semibold uppercase tracking-wide text-[#742220] transition-colors hover:bg-[rgba(45,74,62,0.12)] hover:text-[#2D4A3E] disabled:cursor-not-allowed disabled:opacity-60'
                                    }
                                    onClick={closePlayerActionDialog}
                                    disabled={!!playerActionLoading}
                                >
                                    Cancel
                                </button>
                                <button
                                    type={'button'}
                                    className={
                                        'group relative inline-flex h-10 min-w-[10.75rem] cursor-pointer items-center justify-center overflow-hidden rounded-full border border-[#2D4A3E] bg-[#2D4A3E] px-6 text-xs font-bold uppercase tracking-wide text-[#FEF9E1] transition-colors hover:bg-[#233b32] disabled:cursor-not-allowed disabled:opacity-60'
                                    }
                                    onClick={() => void runPlayerAction()}
                                    disabled={!!playerActionLoading}
                                >
                                    {playerActionLoading &&
                                        playerActionTarget &&
                                        playerActionLoading === playerActionTarget.id
                                        ? 'Processing...'
                                        : 'Run Action'}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                , document.body)}

            <Dialog
                open={playerDialogOpen}
                onClose={closePlayerDialog}
                preventExternalClose={playerActionDialogOpen}
                title={selectedPlayer?.player?.name ? `${selectedPlayer.player.name} Profile` : 'Player Profile'}
                panelClassName={'!w-[90vw] !max-w-[90vw] !h-[90vh] !border-2 !border-[#2D4A3E] !bg-[#FEF9E1] !text-[#742220] !shadow-[4px_4px_0_0_#2D4A3E]'}
                contentClassName={'!flex !h-full !max-h-[calc(90vh-7rem)] !flex-col overflow-hidden'}
            >
                <div className={'mt-4 flex min-h-0 flex-1 flex-col gap-4'}>
                    {playerDialogLoading && (
                        <div className={'flex justify-center py-8'}>
                            <Spinner size={'large'} centered />
                        </div>
                    )}

                    {!playerDialogLoading && playerDialogError && (
                        <div
                            className={
                                'rounded-lg border border-red-500/40 bg-red-500/10 px-3 py-2 text-xs text-red-200'
                            }
                        >
                            {playerDialogError}
                        </div>
                    )}

                    {!playerDialogLoading && !playerDialogError && selectedPlayer && (
                        <>
                            <div
                                className={
                                    'sc-card-inner rounded-[22px] border-2 border-[#2D4A3E] bg-[#F5EFD5] p-4 text-[#742220] shadow-[4px_4px_0_0_#2D4A3E]'
                                }
                            >
                                <div className={'flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between'}>
                                    <div className={'flex items-start gap-3'}>
                                        <div
                                            className={
                                                'flex h-[72px] w-[72px] items-center justify-center overflow-hidden rounded-xl border-2 border-[#2D4A3E] bg-[#EDE6D0]'
                                            }
                                        >
                                            <PlayerAvatar player={selectedPlayer.player} size={72} />
                                        </div>
                                        <div className={'min-w-0'}>
                                            <div className={'flex flex-wrap items-center gap-2'}>
                                                <h3
                                                    className={
                                                        'truncate text-xl font-bold uppercase tracking-wide text-[#742220]'
                                                    }
                                                >
                                                    {selectedPlayer.player.name}
                                                </h3>
                                                <span
                                                    className={classNames(
                                                        'rounded-md border px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide',
                                                        selectedPlayer.player.status === 'online'
                                                            ? 'border-[#2D4A3E] bg-[rgba(45,74,62,0.12)] text-[#2D4A3E]'
                                                            : 'border-[#742220]/40 bg-[rgba(116,34,32,0.08)] text-[#742220]'
                                                    )}
                                                >
                                                    {selectedPlayer.player.status}
                                                </span>
                                                {selectedPlayer.player.is_operator && (
                                                    <span
                                                        className={
                                                            'rounded-md border border-[#f59e0b] bg-[rgba(245,158,11,0.12)] px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-[#742220]'
                                                        }
                                                    >
                                                        Operator
                                                    </span>
                                                )}
                                                {selectedPlayer.player.is_admin && (
                                                    <span
                                                        className={
                                                            'rounded-md border border-[#2D4A3E] bg-[rgba(45,74,62,0.12)] px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-[#2D4A3E]'
                                                        }
                                                    >
                                                        Admin
                                                    </span>
                                                )}
                                                {selectedPlayer.player.banned && (
                                                    <span
                                                        className={
                                                            'rounded-md border border-[#742220] bg-[rgba(116,34,32,0.12)] px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-[#742220]'
                                                        }
                                                    >
                                                        Banned
                                                    </span>
                                                )}
                                            </div>
                                            <div
                                                className={
                                                    'mt-2 flex flex-wrap items-center gap-2 text-[11px] text-[rgba(116,34,32,0.72)]'
                                                }
                                            >
                                                <code
                                                    className={
                                                        'rounded border border-[#2D4A3E] bg-[#FEF9E1] px-2 py-1 text-[10px] text-[#742220]'
                                                    }
                                                >
                                                    {selectedPlayer.player.uuid || selectedPlayer.player.id}
                                                </code>
                                                <span>Ping: {formatPlayerPing(selectedPlayer.player.ping)}</span>
                                                {selectedPlayer.player.country ? (
                                                    <span>Country: {selectedPlayer.player.country}</span>
                                                ) : null}
                                            </div>
                                        </div>
                                    </div>
                                    <div className={'text-right text-[11px] text-[rgba(116,34,32,0.72)]'}>
                                        <p>{selectedPlayer.game.label}</p>
                                    </div>
                                </div>
                            </div>

                            <div className={'flex flex-wrap gap-2 border-b-2 border-[#2D4A3E] pb-2'}>
                                {playerTabs.map((tab) => (
                                    <button
                                        key={tab}
                                        type={'button'}
                                        onClick={() =>
                                            setPlayerDialogTab(tab as 'overview' | 'inventory' | 'statistics')
                                        }
                                        className={classNames(
                                            'rounded-lg px-3 py-1.5 text-xs font-bold uppercase tracking-wide transition-colors',
                                            playerDialogTab === tab
                                                ? 'border-2 border-[#2D4A3E] bg-[#2D4A3E] text-[#FEF9E1]'
                                                : 'border-2 border-[#2D4A3E] bg-[#F5EFD5] text-[#742220] hover:bg-[rgba(45,74,62,0.12)]'
                                        )}
                                    >
                                        {tabLabel(tab)}
                                    </button>
                                ))}
                            </div>

                            {playerActionNotice && (
                                <div
                                    className={
                                        'whitespace-pre-line rounded-lg border-2 border-[#2D4A3E] bg-[#F5EFD5] px-3 py-2 text-xs text-[#742220]'
                                    }
                                >
                                    {playerActionNotice}
                                </div>
                            )}

                            <div className={'min-h-0 flex-1 overflow-y-auto pr-1 pb-3'}>
                                {playerDialogTab === 'overview' && (
                                    <div className={'space-y-4'}>
                                        {selectedPlayer.player.action_groups.map((group) => (
                                            <section
                                                key={group.id}
                                                className={
                                                    'sc-card-inner rounded-[22px] border-2 border-[#2D4A3E] bg-[#F5EFD5] p-4 text-[#742220] shadow-[4px_4px_0_0_#2D4A3E]'
                                                }
                                            >
                                                <h4 className={'text-sm font-bold uppercase tracking-wide text-[#742220]'}>
                                                    {group.title}
                                                </h4>
                                                {group.description && (
                                                    <p className={'mt-1 text-[11px] text-[rgba(116,34,32,0.72)]'}>
                                                        {group.description}
                                                    </p>
                                                )}
                                                <div className={'mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2'}>
                                                    {group.actions.map((action) => (
                                                        <button
                                                            key={action.id}
                                                            type={'button'}
                                                            onClick={() => openPlayerActionDialog(action)}
                                                            disabled={!!playerActionLoading}
                                                            className={classNames(
                                                                'rounded-lg border px-3 py-2 text-left text-xs font-semibold transition-colors disabled:cursor-not-allowed disabled:opacity-50',
                                                                toneClass(action.tone)
                                                            )}
                                                        >
                                                            <span className={'block'}>{action.label}</span>
                                                            <span className={'mt-0.5 block text-[10px] opacity-80'}>
                                                                {playerActionLoading === action.id
                                                                    ? 'Processing...'
                                                                    : action.description}
                                                            </span>
                                                        </button>
                                                    ))}
                                                </div>
                                            </section>
                                        ))}

                                        {(effectivePlayersData?.capabilities.notes || []).length > 0 && (
                                            <section
                                                className={
                                                    'sc-card-inner rounded-[22px] border-2 border-[#2D4A3E] bg-[#F5EFD5] p-3 text-[#742220] shadow-[4px_4px_0_0_#2D4A3E]'
                                                }
                                            >
                                                {(effectivePlayersData?.capabilities.notes || []).map((note, idx) => (
                                                    <p
                                                        key={`${note}-${idx}`}
                                                        className={'text-[11px] text-[rgba(116,34,32,0.72)]'}
                                                    >
                                                        {note}
                                                    </p>
                                                ))}
                                            </section>
                                        )}
                                    </div>
                                )}

                                {playerDialogTab === 'inventory' && (
                                    <div className={'space-y-4'}>
                                        {!selectedInventory?.available && (selectedInventory?.summary || []).length > 0 && (
                                            <section
                                                className={
                                                    'sc-card-inner rounded-[22px] border-2 border-[#2D4A3E] bg-[#F5EFD5] p-4 text-[#742220] shadow-[4px_4px_0_0_#2D4A3E]'
                                                }
                                            >
                                                <h4
                                                    className={
                                                        'mb-3 text-sm font-bold uppercase tracking-wide text-[#742220]'
                                                    }
                                                >
                                                    Inventory Summary
                                                </h4>
                                                <div
                                                    className={
                                                        'grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3'
                                                    }
                                                >
                                                    {(selectedInventory?.summary || []).map((entry) => (
                                                        <div
                                                            key={`${entry.label}-${entry.value}`}
                                                            className={
                                                                'rounded-lg border border-[#2D4A3E] bg-[#FEF9E1] px-3 py-2 text-[#742220]'
                                                            }
                                                        >
                                                            <p
                                                                className={
                                                                    'text-[10px] uppercase tracking-wide text-[rgba(116,34,32,0.72)]'
                                                                }
                                                            >
                                                                {entry.label}
                                                            </p>
                                                            <p className={'mt-1 text-sm font-semibold text-[#742220]'}>
                                                                {entry.value}
                                                            </p>
                                                        </div>
                                                    ))}
                                                </div>
                                            </section>
                                        )}
                                        {!selectedInventory?.available && (
                                            <div
                                                className={
                                                    'sc-card-inner rounded-[22px] border-2 border-[#2D4A3E] bg-[#F5EFD5] px-4 py-3 text-xs text-[rgba(116,34,32,0.72)] shadow-[4px_4px_0_0_#2D4A3E]'
                                                }
                                            >
                                                {selectedInventory?.message ||
                                                    'Inventory information is unavailable for this game.'}
                                            </div>
                                        )}
                                        {selectedInventory?.available && (
                                            <>
                                                {(selectedInventory.summary || []).length > 0 && (
                                                    <section
                                                        className={
                                                            'sc-card-inner rounded-[22px] border-2 border-[#2D4A3E] bg-[#F5EFD5] p-4 text-[#742220] shadow-[4px_4px_0_0_#2D4A3E]'
                                                        }
                                                    >
                                                        <h4
                                                            className={
                                                                'mb-3 text-sm font-bold uppercase tracking-wide text-[#742220]'
                                                            }
                                                        >
                                                            Inventory Summary
                                                        </h4>
                                                        <div
                                                            className={
                                                                'grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3'
                                                            }
                                                        >
                                                            {(selectedInventory.summary || []).map((entry) => (
                                                                <div
                                                                    key={`${entry.label}-${entry.value}`}
                                                                    className={
                                                                        'rounded-lg border border-[#2D4A3E] bg-[#FEF9E1] px-3 py-2 text-[#742220]'
                                                                    }
                                                                >
                                                                    <p
                                                                        className={
                                                                            'text-[10px] uppercase tracking-wide text-[rgba(116,34,32,0.72)]'
                                                                        }
                                                                    >
                                                                        {entry.label}
                                                                    </p>
                                                                    <p
                                                                        className={
                                                                            'mt-1 text-sm font-semibold text-[#742220]'
                                                                        }
                                                                    >
                                                                        {entry.value}
                                                                    </p>
                                                                </div>
                                                            ))}
                                                        </div>
                                                    </section>
                                                )}

                                                {(selectedInventory.sections || []).length === 0 && (
                                                    <section
                                                        className={
                                                            'sc-card-inner rounded-[22px] border-2 border-[#2D4A3E] bg-[#F5EFD5] px-4 py-3 text-xs text-[rgba(116,34,32,0.72)] shadow-[4px_4px_0_0_#2D4A3E]'
                                                        }
                                                    >
                                                        No inventory sections were returned for this player.
                                                    </section>
                                                )}

                                                {useMinecraftInventoryLayout && minecraftInventoryLayout ? (
                                                    <section
                                                        className={
                                                            'sc-card-inner rounded-[22px] border-2 border-[#2D4A3E] bg-[#F5EFD5] p-4 text-[#742220] shadow-[4px_4px_0_0_#2D4A3E]'
                                                        }
                                                    >
                                                        <div
                                                            className={
                                                                'overflow-x-auto rounded-xl border-2 border-[#2D4A3E] bg-[#FEF9E1] p-4 text-[#742220]'
                                                            }
                                                        >
                                                            <div className={'min-w-[780px]'}>
                                                                <div className={'grid grid-cols-[auto_1fr] gap-6'}>
                                                                    <div className={'flex flex-col gap-3'}>
                                                                        <div>
                                                                            <span
                                                                                className={
                                                                                    'mb-1 flex items-center gap-1 text-xs font-semibold uppercase tracking-wide text-[rgba(116,34,32,0.72)]'
                                                                                }
                                                                            >
                                                                                <span
                                                                                    className={
                                                                                        'material-icons-round text-sm'
                                                                                    }
                                                                                >
                                                                                    shield
                                                                                </span>
                                                                                Armor
                                                                            </span>
                                                                            <div
                                                                                className={
                                                                                    'rounded-lg border border-[#2D4A3E] bg-[#FEF9E1] p-2'
                                                                                }
                                                                            >
                                                                                <div className={'flex flex-col gap-2'}>
                                                                                    {renderMinecraftSlot(
                                                                                        minecraftInventoryLayout.armor
                                                                                            .helmet,
                                                                                        { titlePrefix: 'Helmet' }
                                                                                    )}
                                                                                    {renderMinecraftSlot(
                                                                                        minecraftInventoryLayout.armor
                                                                                            .chestplate,
                                                                                        { titlePrefix: 'Chestplate' }
                                                                                    )}
                                                                                    {renderMinecraftSlot(
                                                                                        minecraftInventoryLayout.armor
                                                                                            .leggings,
                                                                                        { titlePrefix: 'Leggings' }
                                                                                    )}
                                                                                    {renderMinecraftSlot(
                                                                                        minecraftInventoryLayout.armor
                                                                                            .boots,
                                                                                        { titlePrefix: 'Boots' }
                                                                                    )}
                                                                                </div>
                                                                            </div>
                                                                        </div>

                                                                        <div>
                                                                            <span
                                                                                className={
                                                                                    'mb-1 block text-xs font-semibold uppercase tracking-wide text-[rgba(116,34,32,0.72)]'
                                                                                }
                                                                            >
                                                                                Offhand
                                                                            </span>
                                                                            <div
                                                                                className={
                                                                                    'rounded-lg border border-[#2D4A3E] bg-[#FEF9E1] p-2'
                                                                                }
                                                                            >
                                                                                {renderMinecraftSlot(
                                                                                    minecraftInventoryLayout.offhandSlot,
                                                                                    {
                                                                                        titlePrefix: 'Offhand',
                                                                                    }
                                                                                )}
                                                                            </div>
                                                                        </div>
                                                                    </div>

                                                                    <div>
                                                                        <span
                                                                            className={
                                                                                'mb-1 flex items-center gap-1 text-xs font-semibold uppercase tracking-wide text-[rgba(116,34,32,0.72)]'
                                                                            }
                                                                        >
                                                                            <span
                                                                                className={
                                                                                    'material-icons-round text-sm'
                                                                                }
                                                                            >
                                                                                grid_view
                                                                            </span>
                                                                            Main Inventory
                                                                        </span>
                                                                        <div
                                                                            className={
                                                                                'rounded-lg border border-[#2D4A3E] bg-[#FEF9E1] p-3'
                                                                            }
                                                                        >
                                                                            <div className={'grid grid-cols-9 gap-2'}>
                                                                                {minecraftInventoryLayout.mainSlots.map(
                                                                                    (slot, index) => (
                                                                                        <div key={`inventory-${index}`}>
                                                                                            {renderMinecraftSlot(slot, {
                                                                                                titlePrefix: `Inventory Slot ${index + 1
                                                                                                    }`,
                                                                                            })}
                                                                                        </div>
                                                                                    )
                                                                                )}
                                                                            </div>

                                                                            <div
                                                                                className={
                                                                                    'mt-4 border-t border-[#2D4A3E] pt-4'
                                                                                }
                                                                            >
                                                                                <span
                                                                                    className={
                                                                                        'mb-2 block text-xs font-semibold uppercase tracking-wide text-[rgba(116,34,32,0.72)]'
                                                                                    }
                                                                                >
                                                                                    Hotbar
                                                                                </span>
                                                                                <div
                                                                                    className={'grid grid-cols-9 gap-2'}
                                                                                >
                                                                                    {minecraftInventoryLayout.hotbarSlots.map(
                                                                                        (slot, index) => (
                                                                                            <div
                                                                                                key={`hotbar-${index}`}
                                                                                            >
                                                                                                {renderMinecraftSlot(
                                                                                                    slot,
                                                                                                    {
                                                                                                        titlePrefix: `Hotbar ${index + 1
                                                                                                            }`,
                                                                                                        indexLabel: `${index + 1
                                                                                                            }`,
                                                                                                    }
                                                                                                )}
                                                                                            </div>
                                                                                        )
                                                                                    )}
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </section>
                                                ) : (
                                                    (selectedInventory.sections || []).map((section) => (
                                                        <section
                                                            key={section.id}
                                                            className={
                                                                'sc-card-inner rounded-[22px] border-2 border-[#2D4A3E] bg-[#F5EFD5] p-4 text-[#742220] shadow-[4px_4px_0_0_#2D4A3E]'
                                                            }
                                                        >
                                                            <div
                                                                className={
                                                                    'mb-3 flex items-center justify-between gap-2'
                                                                }
                                                            >
                                                                <h4
                                                                    className={
                                                                        'text-sm font-bold uppercase tracking-wide text-[#742220]'
                                                                    }
                                                                >
                                                                    {section.title}
                                                                </h4>
                                                                <span
                                                                    className={
                                                                        'rounded-md border border-[#2D4A3E] bg-[#FEF9E1] px-2 py-0.5 text-[10px] font-semibold text-[#742220]'
                                                                    }
                                                                >
                                                                    {section.slots.length} slots
                                                                </span>
                                                            </div>

                                                            {section.slots.length === 0 ? (
                                                                <div
                                                                    className={
                                                                        'rounded-lg border border-[#2D4A3E] bg-[#FEF9E1] px-3 py-3 text-xs text-[rgba(116,34,32,0.72)]'
                                                                    }
                                                                >
                                                                    No item data in this section.
                                                                </div>
                                                            ) : (
                                                                <div
                                                                    className={
                                                                        'grid grid-cols-1 gap-2 sm:grid-cols-2 xl:grid-cols-3'
                                                                    }
                                                                >
                                                                    {section.slots.map((slot) => (
                                                                        <div
                                                                            key={`${section.id}-${slot.slot}`}
                                                                            className={
                                                                                'rounded-lg border border-[#2D4A3E] bg-[#FEF9E1] p-2 text-[#742220]'
                                                                            }
                                                                        >
                                                                            <div className={'flex items-center gap-2'}>
                                                                                <div
                                                                                    className={
                                                                                        'flex h-10 w-10 items-center justify-center overflow-hidden rounded-md border border-[#2D4A3E] bg-[#EDE6D0]'
                                                                                    }
                                                                                >
                                                                                    <PlayerItemIcon
                                                                                        slot={slot}
                                                                                        className={'h-7 w-7'}
                                                                                    />
                                                                                </div>
                                                                                <div className={'min-w-0 flex-1'}>
                                                                                    <p
                                                                                        className={
                                                                                            'truncate text-xs font-semibold text-[#742220]'
                                                                                        }
                                                                                    >
                                                                                        {slot.item_name}
                                                                                    </p>
                                                                                </div>
                                                                                <span
                                                                                    className={
                                                                                        'rounded-md border border-[#2D4A3E] bg-[rgba(45,74,62,0.12)] px-1.5 py-0.5 text-[10px] font-bold text-[#2D4A3E]'
                                                                                    }
                                                                                >
                                                                                    x{slot.count}
                                                                                </span>
                                                                            </div>
                                                                            <div
                                                                                className={
                                                                                    'mt-2 flex items-center justify-between gap-2 text-[10px] text-[rgba(116,34,32,0.72)]'
                                                                                }
                                                                            >
                                                                                <span>Slot {slot.slot}</span>
                                                                                <span className={'truncate text-right'}>
                                                                                    {slot.item_id}
                                                                                </span>
                                                                            </div>
                                                                        </div>
                                                                    ))}
                                                                </div>
                                                            )}
                                                        </section>
                                                    ))
                                                )}
                                            </>
                                        )}
                                    </div>
                                )}

                                {playerDialogTab === 'statistics' && (
                                    <div className={'space-y-4'}>
                                        {!selectedStatistics?.available && (
                                            <div
                                                className={
                                                    'sc-card-inner rounded-[22px] border-2 border-[#2D4A3E] bg-[#F5EFD5] p-3 text-xs text-[rgba(116,34,32,0.72)] shadow-[4px_4px_0_0_#2D4A3E]'
                                                }
                                            >
                                                {selectedStatistics?.message ||
                                                    'Statistics information is unavailable for this game.'}
                                            </div>
                                        )}
                                        {selectedStatistics?.available &&
                                            (selectedStatistics.categories || []).map((category) => (
                                                <section
                                                    key={category.id}
                                                    className={
                                                        'sc-card-inner rounded-[22px] border-2 border-[#2D4A3E] bg-[#F5EFD5] p-4 text-[#742220] shadow-[4px_4px_0_0_#2D4A3E]'
                                                    }
                                                >
                                                    <h4
                                                        className={
                                                            'mb-3 text-sm font-bold uppercase tracking-wide text-[#742220]'
                                                        }
                                                    >
                                                        {category.title}
                                                    </h4>
                                                    <div className={'space-y-2'}>
                                                        {category.entries.map((entry) => (
                                                            <div
                                                                key={`${category.id}-${entry.label}`}
                                                                className={
                                                                    'flex items-center justify-between rounded-lg border border-[#2D4A3E] bg-[#FEF9E1] px-3 py-2 text-xs text-[#742220]'
                                                                }
                                                            >
                                                                <span className={'text-[rgba(116,34,32,0.72)]'}>
                                                                    {entry.label}
                                                                </span>
                                                                <span
                                                                    className={
                                                                        'font-semibold text-[#742220]'
                                                                    }
                                                                >
                                                                    {entry.value}
                                                                </span>
                                                            </div>
                                                        ))}
                                                    </div>
                                                </section>
                                            ))}
                                    </div>
                                )}
                            </div>
                        </>
                    )}
                </div>
            </Dialog>

            <style>{`
                .server-console-shell {
                    position: relative;
                    display: flex;
                    height: 100%;
                    min-height: 0;
                    flex-direction: column;
                    overflow: hidden;
                    background: var(--background);
                    font-family: var(--font-sans, 'Geist Mono', ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace);
                }

                .server-console-shell::before {
                    content: '';
                    position: absolute;
                    inset: 0;
                    pointer-events: none;
                    z-index: 0;
                    background-image:
                        repeating-linear-gradient(0deg, transparent, transparent 4.5px, rgba(116, 34, 32, 0.045) 4.5px, rgba(116, 34, 32, 0.045) 5px),
                        repeating-linear-gradient(60deg, transparent, transparent 4.5px, rgba(116, 34, 32, 0.045) 4.5px, rgba(116, 34, 32, 0.045) 5px),
                        repeating-linear-gradient(120deg, transparent, transparent 4.5px, rgba(116, 34, 32, 0.045) 4.5px, rgba(116, 34, 32, 0.045) 5px);
                }

                .server-console-layout {
                    position: relative;
                    z-index: 10;
                    display: flex;
                    height: 100%;
                    min-height: 0;
                    width: 100%;
                    min-width: 0;
                    flex-direction: column;
                    overflow: hidden;
                }

                .server-console-main,
                .server-console-side {
                    min-height: 0;
                }

                @media (min-width: 1280px) {
                    .server-console-layout {
                        flex-direction: row;
                    }
                }

                .server-console-panel {
                    position: relative;
                    overflow: hidden;
                    border-radius: 22px;
                    border: 2px solid var(--primary);
                    background: var(--surface-elevated);
                    box-shadow: 4px 4px 0px 0px var(--primary);
                    transition: transform 0.2s ease, box-shadow 0.2s ease;
                }

                .server-console-panel::before {
                    content: '';
                    position: absolute;
                    inset: 0;
                    pointer-events: none;
                    z-index: 1;
                    background-image:
                        repeating-linear-gradient(0deg, transparent, transparent 4.5px, rgba(116, 34, 32, 0.045) 4.5px, rgba(116, 34, 32, 0.045) 5px),
                        repeating-linear-gradient(60deg, transparent, transparent 4.5px, rgba(116, 34, 32, 0.045) 4.5px, rgba(116, 34, 32, 0.045) 5px),
                        repeating-linear-gradient(120deg, transparent, transparent 4.5px, rgba(116, 34, 32, 0.045) 4.5px, rgba(116, 34, 32, 0.045) 5px);
                }

                .server-console-panel-head {
                    background: #F5EFD5;
                    border-bottom: 2px solid #E8E0C8;
                }

                .server-console-panel-body {
                    position: relative;
                    background: transparent;
                    border-radius: 0 0 18px 18px;
                    overflow: hidden;
                }

                .server-console-stat-card {
                    border-radius: 16px;
                    border: 1.5px solid #E8E0C8;
                    background: #F5EFD5;
                    padding: 1rem;
                    transition: border-color 0.2s ease, box-shadow 0.2s ease;
                }

                .server-console-stat-card:hover {
                    border-color: #2D4A3E;
                    box-shadow: 2px 2px 0px 0px #2D4A3E;
                }

                .server-console-progress-track {
                    height: 0.38rem;
                    width: 100%;
                    overflow: hidden;
                    border-radius: 999px;
                    background: #E8E0C8;
                }

                .server-console-progress-fill {
                    height: 100%;
                    border-radius: inherit;
                    transition: width 0.7s ease;
                    background: linear-gradient(90deg, #2D4A3E, #4A7A65);
                }

                .server-console-progress-fill-alert {
                    background: linear-gradient(90deg, #9B2335, #C0392B);
                }

                .server-console-inline-toggle {
                    position: absolute;
                    top: 50%;
                    right: 0;
                    z-index: 25;
                    display: none;
                    align-items: center;
                    justify-content: center;
                    width: 28px;
                    height: 84px;
                    transform: translateY(-50%);
                    border: 2px solid #2D4A3E;
                    border-right: 0;
                    border-radius: 12px 0 0 12px;
                    background: #FEF9E1;
                    color: #742220;
                    box-shadow: -4px 0 0px #2D4A3E;
                    transition: color 0.18s ease, background 0.18s ease;
                }

                .server-console-inline-toggle:hover {
                    background: #F5EFD5;
                    color: #2D4A3E;
                }

                @media (min-width: 1280px) {
                    .server-console-inline-toggle {
                        display: inline-flex;
                    }
                }
            `}</style>

            <div
                className={'server-console-shell w-full overflow-x-hidden'}
                style={{
                    fontFamily:
                        "var(--font-sans, 'Geist Mono', ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace)",
                    color: 'var(--foreground)',
                }}
            >
                <FlashMessageRender byKey={'console:copy'} className={'absolute right-4 top-4 z-[9998] w-auto max-w-sm'} />
                <div className={'server-console-layout'}>
                    {!sideConsoleOpen && (
                        <button
                            type={'button'}
                            title={'Open console'}
                            aria-label={'Open console'}
                            className={'server-console-inline-toggle'}
                            onClick={() => setSideConsoleOpen(true)}
                        >
                            <span className={'material-icons-round text-[18px]'}>chevron_left</span>
                        </button>
                    )}
                    <div
                        className={
                            'server-console-main flex min-h-0 w-full min-w-0 flex-1 flex-col gap-6 overflow-hidden p-4 md:p-6 xl:w-[77%] xl:flex-none'
                        }
                    >
                        {(isNodeUnderMaintenance || isInstalling || isTransferring) && (
                            <Alert type={'warning'} className={'mb-0'}>
                                {isNodeUnderMaintenance
                                    ? 'The node of this server is currently under maintenance and all actions are unavailable.'
                                    : isInstalling
                                        ? 'This server is currently running its installation process and most actions are unavailable.'
                                        : 'This server is currently being transferred to another node and all actions are unavailable.'}
                            </Alert>
                        )}
                        <div className={'server-console-panel flex min-h-0 min-w-0 flex-1 flex-col shadow-none'}>
                            <div
                                className={
                                    'server-console-panel-head flex items-center justify-between border-b border-[color:var(--border)] px-4 py-3'
                                }
                            >
                                <div className={'flex items-center gap-3'}>
                                    <span
                                        className={classNames('h-2.5 w-2.5 flex-shrink-0 rounded-full ring-2', {
                                            'animate-pulse bg-green-400 ring-green-500/30': status === 'running',
                                            'bg-red-500 ring-red-500/30': status === 'offline' || status === null,
                                            'animate-pulse bg-yellow-400 ring-yellow-500/30':
                                                status !== 'running' && status !== 'offline' && status !== null,
                                        })}
                                    />
                                    <h2 className={'text-sm font-bold uppercase tracking-widest text-[color:var(--foreground)]'}>
                                        Live Console
                                    </h2>
                                </div>
                                <ServerClock />
                            </div>
                            <div className={'server-console-panel-body min-w-0 flex-1 overflow-hidden'}>
                                <ConsoleBackground />
                                <div style={{ position: 'relative', zIndex: 4, height: '100%' }}>
                                    <Spinner.Suspense>
                                        <Console />
                                    </Spinner.Suspense>
                                </div>
                            </div>
                        </div>

                        <div className={'server-console-panel p-6'}>
                            <div className={'mb-5 flex items-center justify-between'}>
                                <h3 className={'text-sm font-bold uppercase tracking-widest'} style={{ color: '#742220' }}>
                                    Server Statistics
                                </h3>
                                <span className={'rounded-full border px-2.5 py-0.5 text-[9px] font-bold uppercase tracking-widest'} style={{ borderColor: '#2D4A3E', color: '#2D4A3E', background: '#F5EFD5' }}>
                                    Live
                                </span>
                            </div>
                            <div className={'grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4'}>
                                {/* CPU */}
                                <div className={'server-console-stat-card'}>
                                    <div className={'mb-3 flex items-center gap-2'}>
                                        <span className={'material-icons-round text-[18px]'} style={{ color: '#2D4A3E' }}>memory</span>
                                        <span className={'text-[10px] font-bold uppercase tracking-widest'} style={{ color: 'rgba(116,34,32,0.55)' }}>CPU Load</span>
                                    </div>
                                    <div className={'mb-3 tabular-nums'}>
                                        <span className={'text-3xl font-black'} style={{ color: '#742220' }}>{stats.cpu.toFixed(1)}</span>
                                        <span className={'ml-0.5 text-lg font-normal'} style={{ color: 'rgba(116,34,32,0.45)' }}>%</span>
                                    </div>
                                    <div className={'server-console-progress-track'}>
                                        <div
                                            className={cpuPercent >= 90 ? 'server-console-progress-fill server-console-progress-fill-alert' : 'server-console-progress-fill'}
                                            style={{ width: `${cpuPercent}%` }}
                                        />
                                    </div>
                                    {limits.cpu > 0 && (
                                        <p className={'mt-1.5 text-[10px]'} style={{ color: 'rgba(116,34,32,0.45)' }}>Limit: {limits.cpu}%</p>
                                    )}
                                </div>

                                {/* Memory */}
                                <div className={'server-console-stat-card'}>
                                    <div className={'mb-3 flex items-center gap-2'}>
                                        <span className={'material-icons-round text-[18px]'} style={{ color: '#2D4A3E' }}>developer_board</span>
                                        <span className={'text-[10px] font-bold uppercase tracking-widest'} style={{ color: 'rgba(116,34,32,0.55)' }}>Memory</span>
                                    </div>
                                    <div className={'mb-3 tabular-nums'}>
                                        <span className={'text-2xl font-black'} style={{ color: '#742220' }}>{bytesToString(stats.memory)}</span>
                                        <span className={'ml-1 text-xs font-normal'} style={{ color: 'rgba(116,34,32,0.45)' }}>
                                            / {memoryLimitBytes > 0 ? bytesToString(memoryLimitBytes) : '\u221E'}
                                        </span>
                                    </div>
                                    <div className={'server-console-progress-track'}>
                                        <div
                                            className={memoryPercent >= 90 ? 'server-console-progress-fill server-console-progress-fill-alert' : 'server-console-progress-fill'}
                                            style={{ width: `${memoryPercent}%` }}
                                        />
                                    </div>
                                </div>

                                {/* Disk */}
                                <div className={'server-console-stat-card'}>
                                    <div className={'mb-3 flex items-center gap-2'}>
                                        <span className={'material-icons-round text-[18px]'} style={{ color: '#2D4A3E' }}>storage</span>
                                        <span className={'text-[10px] font-bold uppercase tracking-widest'} style={{ color: 'rgba(116,34,32,0.55)' }}>Disk</span>
                                    </div>
                                    <div className={'mb-3 tabular-nums'}>
                                        <span className={'text-2xl font-black'} style={{ color: '#742220' }}>{bytesToString(stats.disk)}</span>
                                        <span className={'ml-1 text-xs font-normal'} style={{ color: 'rgba(116,34,32,0.45)' }}>
                                            / {diskLimitBytes > 0 ? bytesToString(diskLimitBytes) : '\u221E'}
                                        </span>
                                    </div>
                                    <div className={'server-console-progress-track'}>
                                        <div
                                            className={diskPercent >= 90 ? 'server-console-progress-fill server-console-progress-fill-alert' : 'server-console-progress-fill'}
                                            style={{ width: `${diskPercent}%` }}
                                        />
                                    </div>
                                </div>

                                {/* Network */}
                                <div className={'server-console-stat-card'}>
                                    <div className={'mb-3 flex items-center gap-2'}>
                                        <span className={'material-icons-round text-[18px]'} style={{ color: '#2D4A3E' }}>speed</span>
                                        <span className={'text-[10px] font-bold uppercase tracking-widest'} style={{ color: 'rgba(116,34,32,0.55)' }}>Network</span>
                                    </div>
                                    <div className={'flex flex-col gap-2'}>
                                        <div className={'flex items-center justify-between rounded-lg px-3 py-2'} style={{ background: '#F5EFD5', border: '1px solid #E8E0C8' }}>
                                            <div className={'flex items-center gap-1.5'}>
                                                <span className={'material-icons-round text-sm'} style={{ color: '#2D4A3E' }}>arrow_downward</span>
                                                <span className={'text-[10px] font-bold uppercase tracking-wide'} style={{ color: 'rgba(116,34,32,0.55)' }}>In</span>
                                            </div>
                                            <span className={'text-sm font-bold tabular-nums'} style={{ color: '#742220' }}>{bytesToString(networkRate.rx)}/s</span>
                                        </div>
                                        <div className={'flex items-center justify-between rounded-lg px-3 py-2'} style={{ background: '#F5EFD5', border: '1px solid #E8E0C8' }}>
                                            <div className={'flex items-center gap-1.5'}>
                                                <span className={'material-icons-round text-sm'} style={{ color: '#742220' }}>arrow_upward</span>
                                                <span className={'text-[10px] font-bold uppercase tracking-wide'} style={{ color: 'rgba(116,34,32,0.55)' }}>Out</span>
                                            </div>
                                            <span className={'text-sm font-bold tabular-nums'} style={{ color: '#742220' }}>{bytesToString(networkRate.tx)}/s</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <Features enabled={eggFeatures} />
                    </div>

                    {sideConsoleOpen ? (
                        <aside
                            className={
                                'server-console-side flex min-h-0 w-full min-w-0 flex-col overflow-hidden p-4 md:p-6 xl:w-[23%] xl:flex-none xl:pl-0'
                            }
                        >
                            <Console
                                variant={'sidebar'}
                                onRequestClose={() => setSideConsoleOpen(false)}
                                syncMode={'live-only'}
                                contributeToSharedTranscript={false}
                            />
                        </aside>
                    ) : (
                        <aside
                            className={
                                'server-console-side flex min-h-0 w-full min-w-0 flex-col gap-6 overflow-hidden p-4 md:p-6 xl:w-[23%] xl:flex-none xl:pl-0'
                            }
                        >
                            <div className={'server-console-panel flex items-center gap-3 p-4'}>
                                <div
                                    className={'relative flex h-11 w-11 flex-shrink-0 items-center justify-center overflow-hidden rounded-xl'}
                                    style={{ border: '2px solid #2D4A3E', background: '#F5EFD5' }}
                                >
                                    <Avatar.User size={44} />
                                </div>
                                <div className={'min-w-0 flex-1'}>
                                    <h3 className={'truncate text-sm font-bold'} style={{ color: '#742220' }}>{username}</h3>
                                    <p className={'truncate text-[10px]'} style={{ color: 'rgba(116,34,32,0.55)' }}>{email}</p>
                                </div>
                            </div>

                            <div className={'server-console-panel p-5'}>
                                <div className={'mb-4 flex items-center gap-2'}>
                                    <span className={'material-icons-round text-base'} style={{ color: '#2D4A3E' }}>dns</span>
                                    <h3 className={'text-sm font-bold uppercase tracking-widest'} style={{ color: '#742220' }}>
                                        Server Control
                                    </h3>
                                </div>
                                <div className={'mb-6 space-y-2.5 text-sm'}>
                                    <div className={'flex items-start justify-between gap-3'}>
                                        <span className={'text-xs font-medium'} style={{ color: 'rgba(116,34,32,0.55)' }}>IP:</span>
                                        <span
                                            className={'max-w-[70%] cursor-pointer break-all rounded-md px-2 py-0.5 text-right font-mono text-xs font-medium transition-colors'}
                                            style={{ border: '1px solid #E8E0C8', background: '#F5EFD5', color: '#742220' }}
                                            onClick={() => {
                                                navigator.clipboard.writeText(allocation).then(() => {
                                                    clearFlashes('console:copy');
                                                    addFlash({
                                                        key: 'console:copy',
                                                        type: 'success',
                                                        title: 'Copied!',
                                                        message: `Server IP "${allocation}" copied to clipboard.`,
                                                    });
                                                }).catch(() => undefined);
                                            }}
                                            title="Click to copy"
                                        >
                                            {allocation}
                                        </span>
                                    </div>
                                    {(subdomainsData?.items || []).map((subdomain) => (
                                        <div key={subdomain.id} className={'flex items-start justify-between gap-3'}>
                                            <span className={'text-xs font-medium'} style={{ color: 'rgba(116,34,32,0.55)' }}>Subdomain:</span>
                                            <span
                                                className={'max-w-[70%] cursor-pointer break-all rounded-md px-2 py-0.5 text-right font-mono text-xs font-medium transition-colors'}
                                                style={{ border: '1px solid #E8E0C8', background: '#F5EFD5', color: '#742220' }}
                                                onClick={() => {
                                                    navigator.clipboard.writeText(subdomain.fullDomain).then(() => {
                                                        clearFlashes('console:copy');
                                                        addFlash({
                                                            key: 'console:copy',
                                                            type: 'success',
                                                            title: 'Copied!',
                                                            message: `Subdomain "${subdomain.fullDomain}" copied to clipboard.`,
                                                        });
                                                    }).catch(() => undefined);
                                                }}
                                                title="Click to copy"
                                            >
                                                {subdomain.fullDomain}
                                            </span>
                                        </div>
                                    ))}
                                    <div className={'flex items-start justify-between gap-3'}>
                                        <span className={'text-xs font-medium'} style={{ color: 'rgba(116,34,32,0.55)' }}>Status:</span>
                                        <span className={statusBadgeClass}>{(status || 'offline').toUpperCase()}</span>
                                    </div>
                                    <div className={'flex items-start justify-between gap-3'}>
                                        <span className={'text-xs font-medium'} style={{ color: 'rgba(116,34,32,0.55)' }}>Node:</span>
                                        <code
                                            className={'max-w-[70%] break-all rounded-md px-2 py-1 text-right font-mono text-xs'}
                                            style={{ border: '1px solid #E8E0C8', background: '#F5EFD5', color: '#742220' }}
                                        >
                                            {node}
                                        </code>
                                    </div>
                                    <div className={'flex items-start justify-between gap-3'}>
                                        <span className={'text-xs font-medium'} style={{ color: 'rgba(116,34,32,0.55)' }}>Server ID:</span>
                                        <code
                                            title="Click to copy full ID"
                                            className={'max-w-[70%] cursor-pointer break-all rounded-md px-2 py-1 text-right font-mono text-xs transition-colors'}
                                            style={{ border: '1px solid #E8E0C8', background: '#F5EFD5', color: '#742220' }}
                                            onClick={() => {
                                                navigator.clipboard.writeText(uuid).then(() => {
                                                    clearFlashes('console:copy');
                                                    addFlash({
                                                        key: 'console:copy',
                                                        type: 'success',
                                                        title: 'Copied!',
                                                        message: `Server UUID copied to clipboard.`,
                                                    });
                                                }).catch(() => undefined);
                                            }}
                                        >
                                            {uuid.slice(0, 8)}
                                        </code>
                                    </div>
                                </div>
                                <PowerButtons className={'space-y-3'} variant={'glass'} />
                            </div>

                            <div className={'server-console-panel flex min-h-0 flex-1 flex-col p-5'}>
                                <div className={'mb-4 flex flex-col gap-3'}>
                                    <div className={'flex items-start justify-between gap-3'}>
                                        <div>
                                            <div className={'flex items-center gap-2'}>
                                                <span className={'material-icons-round text-base text-[color:var(--primary)]'}>group</span>
                                                <h3 className={'text-sm font-bold uppercase tracking-widest text-[#742220]'}>Players</h3>
                                            </div>
                                            <p className={'mt-0.5 text-[10px]'} style={{ color: 'rgba(116,34,32,0.55)' }}>
                                                {playerProviderLabel}
                                            </p>
                                        </div>
                                        <div className={'w-[172px] min-w-[172px]'}>
                                            <Select
                                                data={playerFilterOptions}
                                                defaultValue={playerScope}
                                                title={'Player Filter'}
                                                compact
                                                onChange={(value) => setPlayerScope(value as PlayerScope)}
                                            />
                                        </div>
                                    </div>
                                </div>

                                <div className={'relative mb-4'}>
                                    <input
                                        className={'w-full rounded-lg py-2 pl-3 pr-8 text-xs outline-none transition-colors'}
                                        style={{ border: '1.5px solid #E8E0C8', background: '#F5EFD5', color: '#742220' }}
                                        placeholder={'Filter by Name, UUID, or ID...'}
                                        type={'text'}
                                        value={playerSearch}
                                        onChange={(event) => setPlayerSearch(event.currentTarget.value)}
                                    />
                                    <span
                                        className={'material-icons-round pointer-events-none absolute right-2 top-2 text-sm'}
                                        style={{ color: 'rgba(116,34,32,0.45)' }}
                                    >
                                        search
                                    </span>
                                </div>

                                <div className={'space-y-3 overflow-y-auto pr-1'} style={{ maxHeight: 'calc(100% - 120px)' }}>
                                    {playersLoading && (
                                        <div className={'py-8'}>
                                            <Spinner size={'small'} centered />
                                            <p className={'mt-2 text-center text-xs text-[color:var(--text-subtle)]'}>
                                                Loading players...
                                            </p>
                                        </div>
                                    )}

                                    {!playersLoading && playersError && (
                                        <p
                                            className={'rounded-lg px-3 py-2 text-xs'}
                                            style={{ border: '1px solid rgba(155,35,53,0.4)', background: 'rgba(155,35,53,0.08)', color: '#9B2335' }}
                                        >
                                            {playersError}
                                        </p>
                                    )}

                                    {!playersLoading && !playersError && (effectivePlayersData?.items || []).length === 0 && (
                                        <p
                                            className={'rounded-lg px-3 py-2 text-xs'}
                                            style={{ border: '1px solid #E8E0C8', background: '#F5EFD5', color: 'rgba(116,34,32,0.55)' }}
                                        >
                                            {buildPlayersEmptyMessage(
                                                effectivePlayersData,
                                                playerScope,
                                                debouncedPlayerSearch
                                            )}
                                        </p>
                                    )}

                                    {!playersLoading &&
                                        !playersError &&
                                        (effectivePlayersData?.items || []).map((player) => (
                                            <div
                                                key={player.id}
                                                className={
                                                    isConsoleOnlyPlayer(player)
                                                        ? 'flex items-center justify-between rounded-lg border border-transparent p-2 opacity-95'
                                                        : 'flex cursor-pointer items-center justify-between rounded-lg border border-transparent p-2 transition-colors hover:bg-[#F5EFD5]'
                                                }
                                                style={isConsoleOnlyPlayer(player) ? {} : { borderColor: 'transparent' }}
                                                onMouseEnter={(e) => { if (!isConsoleOnlyPlayer(player)) (e.currentTarget as HTMLElement).style.borderColor = '#E8E0C8'; }}
                                                onMouseLeave={(e) => { if (!isConsoleOnlyPlayer(player)) (e.currentTarget as HTMLElement).style.borderColor = 'transparent'; }}
                                                role={isConsoleOnlyPlayer(player) ? undefined : 'button'}
                                                tabIndex={isConsoleOnlyPlayer(player) ? -1 : 0}
                                                onClick={() => {
                                                    if (isConsoleOnlyPlayer(player)) {
                                                        clearFlashes('players:console-only');
                                                        addFlash({
                                                            key: 'players:console-only',
                                                            type: 'info',
                                                            message:
                                                                'This Bedrock player was detected from the live console roster. Detailed actions unlock automatically once the backend also resolves the player identity.',
                                                        });
                                                        return;
                                                    }

                                                    void openPlayerDetails(player.id);
                                                }}
                                                onKeyDown={(event) => {
                                                    if (!isConsoleOnlyPlayer(player) && (event.key === 'Enter' || event.key === ' ')) {
                                                        event.preventDefault();
                                                        void openPlayerDetails(player.id);
                                                    }
                                                }}
                                            >
                                                <div className={'flex min-w-0 items-center gap-3'}>
                                                    <div
                                                        className={'flex h-9 w-9 items-center justify-center overflow-hidden rounded-lg'}
                                                        style={{ border: '1.5px solid #E8E0C8', background: '#F5EFD5' }}
                                                    >
                                                        <PlayerAvatar player={player} size={36} />
                                                    </div>
                                                    <div className={'min-w-0'}>
                                                        <div className={'flex flex-wrap items-center gap-1'}>
                                                            <p className={'truncate text-sm font-bold'} style={{ color: '#742220' }}>
                                                                {player.name}
                                                            </p>
                                                            {player.is_operator && (
                                                                <span
                                                                    className={'rounded px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide'}
                                                                    style={{ border: '1px solid #2D4A3E', background: 'rgba(45,74,62,0.12)', color: '#2D4A3E' }}
                                                                >
                                                                    OP
                                                                </span>
                                                            )}
                                                            {player.is_admin && (
                                                                <span
                                                                    className={'rounded px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide'}
                                                                    style={{ border: '1px solid rgba(116,34,32,0.4)', background: 'rgba(116,34,32,0.08)', color: '#742220' }}
                                                                >
                                                                    Admin
                                                                </span>
                                                            )}
                                                            {player.banned && (
                                                                <span
                                                                    className={'rounded px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide'}
                                                                    style={{ border: '1px solid rgba(155,35,53,0.4)', background: 'rgba(155,35,53,0.08)', color: '#9B2335' }}
                                                                >
                                                                    Banned
                                                                </span>
                                                            )}
                                                        </div>
                                                        <p className={'text-[10px]'} style={{ color: 'rgba(116,34,32,0.55)' }}>
                                                            {isConsoleOnlyPlayer(player)
                                                                ? 'Live roster snapshot'
                                                                : player.status === 'online'
                                                                ? `Ping: ${formatPlayerPing(player.ping)}`
                                                                : 'Offline'}
                                                        </p>
                                                    </div>
                                                </div>
                                                <div className={'flex gap-1'}>
                                                    {!isConsoleOnlyPlayer(player) && (
                                                        <button
                                                            className={'rounded p-1 transition-colors'}
                                                            style={{ color: 'rgba(116,34,32,0.45)' }}
                                                            type={'button'}
                                                            onClick={(event) => {
                                                                event.stopPropagation();
                                                                void openPlayerDetails(player.id);
                                                            }}
                                                            onMouseEnter={(e) => { (e.currentTarget as HTMLElement).style.color = '#2D4A3E'; (e.currentTarget as HTMLElement).style.background = 'rgba(45,74,62,0.1)'; }}
                                                            onMouseLeave={(e) => { (e.currentTarget as HTMLElement).style.color = 'rgba(116,34,32,0.45)'; (e.currentTarget as HTMLElement).style.background = ''; }}
                                                        >
                                                            <span className={'material-icons-round text-sm'}>settings</span>
                                                        </button>
                                                    )}
                                                </div>
                                            </div>
                                        ))}
                                </div>
                            </div>
                        </aside>
                    )}
                </div>
            </div>
        </>
    );
};

export default memo(ServerConsoleContainer, isEqual);
