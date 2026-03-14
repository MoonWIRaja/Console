import React, { memo, useEffect, useMemo, useRef, useState } from 'react';
import { ServerContext } from '@/state/server';
import isEqual from 'react-fast-compare';
import Spinner from '@/components/elements/Spinner';
import Features from '@feature/Features';
import Console from '@/components/server/console/Console';
import PowerButtons from '@/components/server/console/PowerButtons';
import { Alert } from '@/components/elements/alert';
import { useStoreState } from 'easy-peasy';
import { ApplicationStore } from '@/state';
import { bytesToString, ip, mbToBytes } from '@/lib/formatters';
import useWebsocketEvent from '@/plugins/useWebsocketEvent';
import { SocketEvent, SocketRequest } from '@/components/server/events';
import classNames from 'classnames';
import Avatar from '@/components/Avatar';
import { Dialog } from '@/components/elements/dialog';
import Select, { TSelectData } from '@/components/ui/select';
import { httpErrorToHuman } from '@/api/http';
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

const allowedPlayerScopes: PlayerScope[] = ['online', 'operators', 'banned'];

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
    const previousByUuid = new Map(
        previous.items
            .filter((item) => item.uuid)
            .map((item) => [item.uuid, item])
    );

    const items = next.items.map((item) => {
        const prior = previousById.get(item.id) || (item.uuid ? previousByUuid.get(item.uuid) : undefined);
        if (!prior) return item;

        const nextName = (item.name || '').trim();
        const priorName = (prior.name || '').trim();
        const keepPriorName =
            (!nextName || isGenericPlayerName(nextName)) &&
            !!priorName &&
            !isGenericPlayerName(priorName);

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

const toneClass = (tone?: string): string => {
    switch (tone) {
        case 'success':
            return 'border-green-500/40 bg-green-500/10 text-green-200 hover:border-green-400';
        case 'warning':
            return 'border-amber-500/40 bg-amber-500/10 text-amber-100 hover:border-amber-400';
        case 'danger':
            return 'border-red-500/40 bg-red-500/10 text-red-100 hover:border-red-400';
        case 'neutral':
            return 'border-[color:var(--border)] bg-[color:var(--background)] text-gray-100 hover:border-[color:var(--primary)]';
        default:
            return 'border-[color:var(--primary)]/35 bg-[color:var(--primary)]/10 text-[color:var(--foreground)] hover:border-[color:var(--primary)]';
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
        const normalizedName = (player.name || '').trim();
        const sources: string[] = [];

        if (player.avatar_url) {
            sources.push(player.avatar_url);
        }

        if (normalizedUuid) {
            sources.push(`https://mc-heads.net/avatar/${encodeURIComponent(normalizedUuid)}/64`);
            sources.push(`https://minotar.net/avatar/${encodeURIComponent(normalizedUuid)}/64`);
        }

        if (normalizedName) {
            sources.push(`https://mc-heads.net/avatar/${encodeURIComponent(normalizedName)}/64`);
            sources.push(`https://minotar.net/avatar/${encodeURIComponent(normalizedName)}/64`);
        }

        return Array.from(new Set(sources));
    }, [player.avatar_url, player.name, player.uuid]);

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
            className={'flex items-center justify-center rounded-lg bg-[color:var(--accent)] text-xs font-bold text-[color:var(--foreground)]'}
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
        <span className={classNames('material-icons-round text-sm text-gray-500', emptyClassName)}>
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

const findSectionById = (
    sections: PlayerInventorySection[],
    ids: string[]
): PlayerInventorySection | undefined => {
    const wanted = ids.map((id) => id.toLowerCase());

    return sections.find((section) => wanted.includes((section.id || '').toLowerCase()));
};

const findNamedSlot = (slots: PlayerInventorySlot[], names: string[]): PlayerInventorySlot | null => {
    const wanted = names.map((name) => name.toLowerCase());

    const found = slots.find((slot) =>
        wanted.some((name) => (slot.slot || '').toLowerCase().includes(name))
    );

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
    const [selectedPlayer, setSelectedPlayer] = useState<PlayerProfileResponse | null>(null);
    const [selectedInventory, setSelectedInventory] = useState<PlayerInventoryResponse | null>(null);
    const [selectedStatistics, setSelectedStatistics] = useState<PlayerStatisticsResponse | null>(null);

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
            setPlayersError(playersUnavailableReason || 'Player data is temporarily unavailable.');

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
    }, [uuid, playerScope, debouncedPlayerSearch, inConflictState, playersUnavailableReason]);

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
            ? 'border-[color:var(--primary)] bg-[color:var(--primary)]/10 text-[color:var(--primary)]'
            : status === 'offline' || status === null
            ? 'border-red-500/40 bg-red-500/10 text-red-400'
            : 'border-amber-500/40 bg-amber-500/10 text-amber-300'
    );

    const playerFilterOptions = useMemo<TSelectData[]>(() => {
        const source = (playersData?.capabilities.filters || defaultPlayerFilters.map((item) => ({
            id: item.id,
            label: item.label,
            description: item.description,
        }))).filter((item) => allowedPlayerScopes.includes(item.id as PlayerScope));

        const normalized = source.length
            ? source
            : defaultPlayerFilters.map((item) => ({
                  id: item.id,
                  label: item.label,
                  description: item.description,
              }));

        return normalized.map((item) => {
            const scope = item.id as PlayerScope;
            const count = scopeCount(scope, playersData);

            return {
                id: item.id,
                label: item.label,
                value: item.id,
                description: undefined,
                icon: (
                    <span className={'inline-flex items-center justify-center text-[11px] font-bold leading-none tracking-tight'}>
                        {count > 99 ? '99+' : count}
                    </span>
                ),
            };
        });
    }, [playersData]);

    const playerTabs = useMemo(() => {
        const tabs = new Set<string>(['overview']);

        (playersData?.capabilities.tabs || []).forEach((tab) => tabs.add(tab));

        if (selectedInventory?.available) tabs.add('inventory');
        if (selectedStatistics?.available) tabs.add('statistics');

        return Array.from(tabs).filter((tab) => ['overview', 'inventory', 'statistics'].includes(tab));
    }, [playersData, selectedInventory, selectedStatistics]);

    useEffect(() => {
        if (!playerTabs.includes(playerDialogTab)) {
            setPlayerDialogTab('overview');
        }
    }, [playerTabs, playerDialogTab]);

    const activeGameType = (selectedPlayer?.game?.type || selectedInventory?.game?.type || '').toLowerCase();
    const useMinecraftInventoryLayout =
        activeGameType === 'minecraft_java' || activeGameType === 'minecraft_bedrock';

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

    const currentGamemodeFromStats = useMemo(() => extractGamemodeFromStatistics(selectedStatistics), [selectedStatistics]);
    const currentGamemode = playerGamemodeHint || currentGamemodeFromStats;

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
            <PlayerItemIcon slot={slot} className={'h-8 w-8'} emptyClassName={'text-gray-600 opacity-40'} />
            {options?.indexLabel && (
                <span className={'absolute left-1 top-0 text-[10px] font-semibold text-gray-500'}>
                    {options.indexLabel}
                </span>
            )}
            {slot && slot.count > 1 && (
                <span
                    className={
                        'absolute bottom-0 right-1 text-[11px] font-bold text-white [text-shadow:0_1px_1px_rgba(0,0,0,0.9)]'
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
        setPlayerGamemodeHint(null);

        try {
            const { profile, inventory, statistics } = await loadPlayerDetailsData(playerId);
            setSelectedPlayer(profile);
            setSelectedInventory(inventory);
            setSelectedStatistics(statistics);
            const detectedMode = extractGamemodeFromStatistics(statistics);
            setPlayerGamemodeHint(detectedMode !== '-' ? detectedMode : null);
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
        }

        setPlayerActionContext(initialContext);
        setPlayerActionFormError(null);
        setPlayerActionTarget(action);
        setPlayerActionDialogOpen(true);
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
                setPlayerGamemodeHint(gamemodeLabelFromValue(String(context.mode || 'survival')));
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
            {playerActionDialogOpen && (
                <div
                    className={'fixed inset-0 z-[80] flex items-center justify-center px-3 py-5'}
                    onMouseDown={(event) => {
                        if (event.target === event.currentTarget) {
                            closePlayerActionDialog();
                        }
                    }}
                >
                    <div className={'absolute inset-0 bg-[color:var(--card)]/75'} />
                    <div className={'relative z-[81] w-[92vw] max-w-[640px] rounded-xl border border-[color:var(--border)] bg-[color:var(--background)] p-4 shadow-[0_24px_54px_rgba(0,0,0,0.45)]'}>
                        <div className={'mb-3 flex items-start justify-between gap-3'}>
                            <div>
                                <h3 className={'text-sm font-bold uppercase tracking-wide text-[color:var(--foreground)]'}>
                                    {playerActionTarget ? `Run: ${playerActionTarget.label}` : 'Run Action'}
                                </h3>
                                <p className={'mt-1 text-xs text-gray-400'}>
                                    {playerActionTarget?.description || 'Configure action details and confirm execution.'}
                                </p>
                            </div>
                            <button
                                type={'button'}
                                onClick={closePlayerActionDialog}
                                className={'rounded-md border border-[color:var(--border)] bg-[color:var(--card)] px-2 py-1 text-xs font-semibold text-gray-200 transition-colors hover:border-[color:var(--primary)]'}
                            >
                                Close
                            </button>
                        </div>

                        <div className={'max-h-[76vh] space-y-4 overflow-y-auto pr-1'}>
                            {playerActionTarget?.id === 'minecraft.gamemode' && (
                                <div className={'rounded-lg border border-[color:var(--border)] bg-[color:var(--card)] px-3 py-2'}>
                                    <p className={'text-[11px] uppercase tracking-wide text-gray-500'}>Current Gamemode</p>
                                    <p className={'mt-1 text-sm font-semibold text-[color:var(--foreground)]'}>
                                        {currentGamemode || '-'}
                                    </p>
                                </div>
                            )}

                            {actionDialogFields.map((field) => (
                                <div key={field.key} className={'space-y-1.5'}>
                                    <label className={'block text-[11px] font-bold uppercase tracking-wide text-gray-300'}>
                                        {field.label}
                                    </label>
                                    {field.type === 'select' ? (
                                        <Select
                                            data={field.options || []}
                                            defaultValue={playerActionContext[field.key] || field.options?.[0]?.value}
                                            title={field.label}
                                            compact
                                            onChange={(value) =>
                                                setPlayerActionContext((current) => ({ ...current, [field.key]: value }))
                                            }
                                        />
                                    ) : field.type === 'textarea' ? (
                                        <textarea
                                            autoFocus
                                            className={
                                                'w-full rounded-lg border border-[color:var(--border)] bg-[color:var(--card)] px-3 py-2 text-sm text-gray-100 outline-none transition-colors placeholder:text-gray-500 focus:border-[color:var(--primary)] focus:ring-1 focus:ring-[color:var(--primary)]'
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
                                                'w-full rounded-lg border border-[color:var(--border)] bg-[color:var(--card)] px-3 py-2 text-sm text-gray-100 outline-none transition-colors placeholder:text-gray-500 focus:border-[color:var(--primary)] focus:ring-1 focus:ring-[color:var(--primary)]'
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
                                    {field.helpText && <p className={'text-[11px] text-gray-500'}>{field.helpText}</p>}
                                </div>
                            ))}

                            {actionDialogFields.length === 0 && (
                                <div className={'rounded-lg border border-[color:var(--border)] bg-[color:var(--card)] px-3 py-2 text-xs text-gray-300'}>
                                    This action does not require additional input.
                                </div>
                            )}

                            {playerActionFormError && (
                                <div className={'rounded-lg border border-red-500/40 bg-red-500/10 px-3 py-2 text-xs text-red-200'}>
                                    {playerActionFormError}
                                </div>
                            )}

                            <div className={'flex items-center justify-end gap-2 pt-1'}>
                                <button
                                    type={'button'}
                                    className={
                                        'rounded-lg border border-[color:var(--border)] bg-[color:var(--card)] px-3 py-2 text-xs font-semibold text-gray-200 transition-colors hover:border-[color:var(--primary)]'
                                    }
                                    onClick={closePlayerActionDialog}
                                    disabled={!!playerActionLoading}
                                >
                                    Cancel
                                </button>
                                <button
                                    type={'button'}
                                    className={
                                        'rounded-lg border border-[color:var(--primary)] bg-[color:var(--primary)]/12 px-3 py-2 text-xs font-bold uppercase tracking-wide text-[color:var(--foreground)] transition-colors hover:bg-[color:var(--primary)]/20 disabled:cursor-not-allowed disabled:opacity-60'
                                    }
                                    onClick={() => void runPlayerAction()}
                                    disabled={!!playerActionLoading}
                                >
                                    {playerActionLoading && playerActionTarget && playerActionLoading === playerActionTarget.id
                                        ? 'Processing...'
                                        : 'Run Action'}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            <Dialog
                open={playerDialogOpen}
                onClose={closePlayerDialog}
                preventExternalClose={playerActionDialogOpen}
                title={selectedPlayer?.player?.name ? `${selectedPlayer.player.name} Profile` : 'Player Profile'}
                panelClassName={'!w-[90vw] !max-w-[90vw] !h-[90vh]'}
                contentClassName={'!flex !h-full !max-h-[calc(90vh-7rem)] !flex-col overflow-hidden'}
            >
                <div className={'mt-4 flex min-h-0 flex-1 flex-col gap-4'}>
                    {playerDialogLoading && (
                        <div className={'flex justify-center py-8'}>
                            <Spinner size={'large'} centered />
                        </div>
                    )}

                    {!playerDialogLoading && playerDialogError && (
                        <div className={'rounded-lg border border-red-500/40 bg-red-500/10 px-3 py-2 text-xs text-red-200'}>
                            {playerDialogError}
                        </div>
                    )}

                    {!playerDialogLoading && !playerDialogError && selectedPlayer && (
                        <>
                            <div className={'rounded-xl border border-[color:var(--border)] bg-[color:var(--background)] p-4'}>
                                <div className={'flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between'}>
                                    <div className={'flex items-start gap-3'}>
                                        <div className={'flex h-[72px] w-[72px] items-center justify-center overflow-hidden rounded-xl border border-[color:var(--border)] bg-[color:var(--card)]'}>
                                            <PlayerAvatar player={selectedPlayer.player} size={72} />
                                        </div>
                                        <div className={'min-w-0'}>
                                            <div className={'flex flex-wrap items-center gap-2'}>
                                                <h3 className={'truncate text-xl font-bold text-[color:var(--foreground)]'}>
                                                    {selectedPlayer.player.name}
                                                </h3>
                                                <span
                                                    className={classNames(
                                                        'rounded-md border px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide',
                                                        selectedPlayer.player.status === 'online'
                                                            ? 'border-green-500/40 bg-green-500/10 text-green-300'
                                                            : 'border-neutral-500/40 bg-neutral-500/10 text-neutral-300'
                                                    )}
                                                >
                                                    {selectedPlayer.player.status}
                                                </span>
                                                {selectedPlayer.player.is_operator && (
                                                    <span className={'rounded-md border border-amber-500/40 bg-amber-500/10 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-amber-200'}>
                                                        Operator
                                                    </span>
                                                )}
                                                {selectedPlayer.player.is_admin && (
                                                    <span className={'rounded-md border border-purple-500/40 bg-purple-500/10 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-purple-200'}>
                                                        Admin
                                                    </span>
                                                )}
                                                {selectedPlayer.player.banned && (
                                                    <span className={'rounded-md border border-red-500/40 bg-red-500/10 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-red-200'}>
                                                        Banned
                                                    </span>
                                                )}
                                            </div>
                                            <div className={'mt-2 flex flex-wrap items-center gap-2 text-[11px] text-gray-400'}>
                                                <code className={'rounded border border-[color:var(--border)] bg-[color:var(--card)] px-2 py-1 text-[10px] text-[color:var(--foreground)]'}>
                                                    {selectedPlayer.player.uuid || selectedPlayer.player.id}
                                                </code>
                                                <span>Ping: {selectedPlayer.player.ping}ms</span>
                                                {selectedPlayer.player.country ? <span>Country: {selectedPlayer.player.country}</span> : null}
                                            </div>
                                        </div>
                                    </div>
                                    <div className={'text-right text-[11px] text-gray-400'}>
                                        <p>{selectedPlayer.game.label}</p>
                                    </div>
                                </div>
                            </div>

                            <div className={'flex flex-wrap gap-2 border-b border-[color:var(--border)] pb-2'}>
                                {playerTabs.map((tab) => (
                                    <button
                                        key={tab}
                                        type={'button'}
                                        onClick={() => setPlayerDialogTab(tab as 'overview' | 'inventory' | 'statistics')}
                                        className={classNames(
                                            'rounded-lg px-3 py-1.5 text-xs font-bold uppercase tracking-wide transition-colors',
                                            playerDialogTab === tab
                                                ? 'border border-[color:var(--primary)] bg-[color:var(--primary)]/10 text-[color:var(--foreground)]'
                                                : 'border border-[color:var(--border)] bg-[color:var(--card)] text-gray-300 hover:border-[color:var(--primary)]'
                                        )}
                                    >
                                        {tabLabel(tab)}
                                    </button>
                                ))}
                            </div>

                            {playerActionNotice && (
                                <div className={'whitespace-pre-line rounded-lg border border-[color:var(--border)] bg-[color:var(--background)] px-3 py-2 text-xs text-gray-200'}>
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
                                                    'rounded-xl border border-[color:var(--border)] bg-[color:var(--background)] p-4'
                                                }
                                            >
                                                <h4 className={'text-sm font-bold text-[color:var(--foreground)]'}>
                                                    {group.title}
                                                </h4>
                                                {group.description && (
                                                    <p className={'mt-1 text-[11px] text-gray-400'}>
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

                                        {(playersData?.capabilities.notes || []).length > 0 && (
                                            <section
                                                className={
                                                    'rounded-xl border border-[color:var(--border)] bg-[color:var(--background)] p-3'
                                                }
                                            >
                                                {(playersData?.capabilities.notes || []).map((note, idx) => (
                                                    <p key={`${note}-${idx}`} className={'text-[11px] text-gray-400'}>
                                                        {note}
                                                    </p>
                                                ))}
                                            </section>
                                        )}
                                    </div>
                                )}

                                {playerDialogTab === 'inventory' && (
                                    <div className={'space-y-4'}>
                                        {!selectedInventory?.available && (
                                            <div
                                                className={
                                                    'rounded-xl border border-[color:var(--border)] bg-[color:var(--background)] px-4 py-3 text-xs text-gray-400'
                                                }
                                            >
                                                {selectedInventory?.message || 'Inventory information is unavailable for this game.'}
                                            </div>
                                        )}
                                        {selectedInventory?.available && (
                                            <>
                                                {(selectedInventory.summary || []).length > 0 && (
                                                    <section
                                                        className={
                                                            'rounded-xl border border-[color:var(--border)] bg-[color:var(--background)] p-4'
                                                        }
                                                    >
                                                        <h4 className={'mb-3 text-sm font-bold text-[color:var(--foreground)]'}>
                                                            Inventory Summary
                                                        </h4>
                                                        <div className={'grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3'}>
                                                            {(selectedInventory.summary || []).map((entry) => (
                                                                <div
                                                                    key={`${entry.label}-${entry.value}`}
                                                                    className={
                                                                        'rounded-lg border border-[color:var(--border)] bg-[color:var(--card)] px-3 py-2'
                                                                    }
                                                                >
                                                                    <p className={'text-[10px] uppercase tracking-wide text-gray-500'}>
                                                                        {entry.label}
                                                                    </p>
                                                                    <p className={'mt-1 text-sm font-semibold text-gray-100'}>
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
                                                            'rounded-xl border border-[color:var(--border)] bg-[color:var(--background)] px-4 py-3 text-xs text-gray-500'
                                                        }
                                                    >
                                                        No inventory sections were returned for this player.
                                                    </section>
                                                )}

                                                {useMinecraftInventoryLayout && minecraftInventoryLayout ? (
                                                    <section
                                                        className={
                                                            'rounded-xl border border-[color:var(--border)] bg-[color:var(--background)] p-4'
                                                        }
                                                    >
                                                        <div
                                                            className={
                                                                'overflow-x-auto rounded-xl border border-[color:var(--border)] bg-[color:var(--card)] p-4'
                                                            }
                                                        >
                                                            <div className={'min-w-[780px]'}>
                                                                <div className={'grid grid-cols-[auto_1fr] gap-6'}>
                                                                    <div className={'flex flex-col gap-3'}>
                                                                        <div>
                                                                            <span
                                                                                className={
                                                                                    'mb-1 flex items-center gap-1 text-xs font-semibold uppercase tracking-wide text-gray-400'
                                                                                }
                                                                            >
                                                                                <span className={'material-icons-round text-sm'}>
                                                                                    shield
                                                                                </span>
                                                                                Armor
                                                                            </span>
                                                                            <div
                                                                                className={
                                                                                    'rounded-lg border border-[color:var(--border)] bg-[color:var(--background)] p-2'
                                                                                }
                                                                            >
                                                                                <div className={'flex flex-col gap-2'}>
                                                                                    {renderMinecraftSlot(
                                                                                        minecraftInventoryLayout.armor.helmet,
                                                                                        { titlePrefix: 'Helmet' }
                                                                                    )}
                                                                                    {renderMinecraftSlot(
                                                                                        minecraftInventoryLayout.armor.chestplate,
                                                                                        { titlePrefix: 'Chestplate' }
                                                                                    )}
                                                                                    {renderMinecraftSlot(
                                                                                        minecraftInventoryLayout.armor.leggings,
                                                                                        { titlePrefix: 'Leggings' }
                                                                                    )}
                                                                                    {renderMinecraftSlot(
                                                                                        minecraftInventoryLayout.armor.boots,
                                                                                        { titlePrefix: 'Boots' }
                                                                                    )}
                                                                                </div>
                                                                            </div>
                                                                        </div>

                                                                        <div>
                                                                            <span
                                                                                className={
                                                                                    'mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-400'
                                                                                }
                                                                            >
                                                                                Offhand
                                                                            </span>
                                                                            <div
                                                                                className={
                                                                                    'rounded-lg border border-[color:var(--border)] bg-[color:var(--background)] p-2'
                                                                                }
                                                                            >
                                                                                {renderMinecraftSlot(minecraftInventoryLayout.offhandSlot, {
                                                                                    titlePrefix: 'Offhand',
                                                                                })}
                                                                            </div>
                                                                        </div>
                                                                    </div>

                                                                    <div>
                                                                        <span
                                                                            className={
                                                                                'mb-1 flex items-center gap-1 text-xs font-semibold uppercase tracking-wide text-gray-400'
                                                                            }
                                                                        >
                                                                            <span className={'material-icons-round text-sm'}>
                                                                                grid_view
                                                                            </span>
                                                                            Main Inventory
                                                                        </span>
                                                                        <div
                                                                            className={
                                                                                'rounded-lg border border-[color:var(--border)] bg-[color:var(--background)] p-3'
                                                                            }
                                                                        >
                                                                            <div className={'grid grid-cols-9 gap-2'}>
                                                                                {minecraftInventoryLayout.mainSlots.map((slot, index) => (
                                                                                    <div key={`inventory-${index}`}>
                                                                                        {renderMinecraftSlot(slot, {
                                                                                            titlePrefix: `Inventory Slot ${index + 1}`,
                                                                                        })}
                                                                                    </div>
                                                                                ))}
                                                                            </div>

                                                                            <div className={'mt-4 border-t border-[color:var(--border)] pt-4'}>
                                                                                <span
                                                                                    className={
                                                                                        'mb-2 block text-xs font-semibold uppercase tracking-wide text-gray-400'
                                                                                    }
                                                                                >
                                                                                    Hotbar
                                                                                </span>
                                                                                <div className={'grid grid-cols-9 gap-2'}>
                                                                                    {minecraftInventoryLayout.hotbarSlots.map(
                                                                                        (slot, index) => (
                                                                                            <div key={`hotbar-${index}`}>
                                                                                                {renderMinecraftSlot(slot, {
                                                                                                    titlePrefix: `Hotbar ${index + 1}`,
                                                                                                    indexLabel: `${index + 1}`,
                                                                                                })}
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
                                                                'rounded-xl border border-[color:var(--border)] bg-[color:var(--background)] p-4'
                                                            }
                                                        >
                                                            <div className={'mb-3 flex items-center justify-between gap-2'}>
                                                                <h4 className={'text-sm font-bold text-[color:var(--foreground)]'}>
                                                                    {section.title}
                                                                </h4>
                                                                <span
                                                                    className={
                                                                        'rounded-md border border-[color:var(--border)] bg-[color:var(--card)] px-2 py-0.5 text-[10px] font-semibold text-gray-300'
                                                                    }
                                                                >
                                                                    {section.slots.length} slots
                                                                </span>
                                                            </div>

                                                            {section.slots.length === 0 ? (
                                                                <div
                                                                    className={
                                                                        'rounded-lg border border-[color:var(--border)] bg-[color:var(--card)] px-3 py-3 text-xs text-gray-500'
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
                                                                                'rounded-lg border border-[color:var(--border)] bg-[color:var(--card)] p-2'
                                                                            }
                                                                        >
                                                                            <div className={'flex items-center gap-2'}>
                                                                                <div
                                                                                    className={
                                                                                        'flex h-10 w-10 items-center justify-center overflow-hidden rounded-md border border-[color:var(--border)] bg-[color:var(--background)]'
                                                                                    }
                                                                                >
                                                                                    <PlayerItemIcon slot={slot} className={'h-7 w-7'} />
                                                                                </div>
                                                                                <div className={'min-w-0 flex-1'}>
                                                                                    <p
                                                                                        className={
                                                                                            'truncate text-xs font-semibold text-gray-100'
                                                                                        }
                                                                                    >
                                                                                        {slot.item_name}
                                                                                    </p>
                                                                                </div>
                                                                                <span
                                                                                    className={
                                                                                        'rounded-md border border-[color:var(--primary)]/30 bg-[color:var(--primary)]/10 px-1.5 py-0.5 text-[10px] font-bold text-[color:var(--foreground)]'
                                                                                    }
                                                                                >
                                                                                    x{slot.count}
                                                                                </span>
                                                                            </div>
                                                                            <div
                                                                                className={
                                                                                    'mt-2 flex items-center justify-between gap-2 text-[10px] text-gray-500'
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
                                                    'rounded-lg border border-[color:var(--border)] bg-[color:var(--background)] p-3 text-xs text-gray-400'
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
                                                        'rounded-xl border border-[color:var(--border)] bg-[color:var(--background)] p-4'
                                                    }
                                                >
                                                    <h4 className={'mb-3 text-sm font-bold text-[color:var(--foreground)]'}>
                                                        {category.title}
                                                    </h4>
                                                    <div className={'space-y-2'}>
                                                        {category.entries.map((entry) => (
                                                            <div
                                                                key={`${category.id}-${entry.label}`}
                                                                className={
                                                                    'flex items-center justify-between rounded-lg border border-[color:var(--border)] bg-[color:var(--card)] px-3 py-2 text-xs'
                                                                }
                                                            >
                                                                <span className={'text-gray-400'}>{entry.label}</span>
                                                                <span className={'font-semibold text-gray-100'}>
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
                    background: transparent;
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

                .server-console-shell::before {
                    content: '';
                    position: absolute;
                    inset: 0;
                    pointer-events: none;
                    background:
                        radial-gradient(500px 180px at 8% 0%, rgba(var(--primary-rgb), 0.18), transparent 68%),
                        radial-gradient(460px 190px at 92% 0%, rgba(102, 141, 255, 0.18), transparent 70%),
                        linear-gradient(
                            180deg,
                            rgba(255, 255, 255, 0.015) 0%,
                            rgba(255, 255, 255, 0.005) 22%,
                            transparent 60%
                        );
                    opacity: 0.9;
                }

                .server-console-panel {
                    border-radius: 1rem;
                    border: 1px solid rgba(255, 255, 255, 0.08);
                    background:
                        linear-gradient(160deg, rgba(255, 255, 255, 0.04), rgba(255, 255, 255, 0.01) 44%),
                        rgba(5, 8, 14, 0.82);
                    box-shadow:
                        inset 0 1px 0 rgba(255, 255, 255, 0.08),
                        0 26px 40px -34px rgba(0, 0, 0, 0.9),
                        0 0 36px rgba(var(--primary-rgb), 0.08);
                }

                .server-console-panel-head {
                    background:
                        linear-gradient(180deg, rgba(255, 255, 255, 0.03), rgba(255, 255, 255, 0.01)),
                        rgba(5, 8, 14, 0.62);
                }
            `}</style>

            <div
                className={'server-console-shell w-full overflow-x-hidden text-gray-100'}
                style={{
                    fontFamily:
                        "'Space Mono', 'IBM Plex Mono', ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace",
                }}
            >
                <div className={'server-console-layout'}>
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
                        <div
                            className={
                                'server-console-panel flex min-h-0 min-w-0 flex-1 flex-col shadow-none'
                            }
                        >
                            <div
                                className={
                                    'server-console-panel-head flex items-center justify-between border-b border-[color:var(--border)] px-4 py-3'
                                }
                            >
                                <h2 className={'flex items-center text-sm font-bold uppercase tracking-wide text-[#f8f6ef]'}>
                                    <span
                                        className={classNames('mr-2 h-2 w-2 rounded-full', {
                                            'animate-pulse bg-green-500': status === 'running',
                                            'bg-red-500': status === 'offline' || status === null,
                                            'animate-pulse bg-yellow-500':
                                                status !== 'running' && status !== 'offline' && status !== null,
                                        })}
                                    />
                                    Live Console
                                </h2>
                            </div>
                            <div className={'min-w-0 flex-1 overflow-hidden p-4'}>
                                <Spinner.Suspense>
                                    <Console />
                                </Spinner.Suspense>
                            </div>
                        </div>

                        <div
                            className={
                                'server-console-panel p-6 shadow-[0_0_0_1px_rgba(var(--primary-rgb),0.06)]'
                            }
                        >
                            <h3 className={'mb-6 text-lg font-bold uppercase tracking-wide text-[#f8f6ef]'}>
                                Server Statistics
                            </h3>
                            <div className={'grid grid-cols-1 gap-8 md:grid-cols-2 lg:grid-cols-4'}>
                                <div className={'space-y-2'}>
                                    <div className={'flex items-end justify-between'}>
                                        <span className={'text-sm font-medium text-gray-400'}>CPU Usage</span>
                                    </div>
                                    <div className={'text-3xl font-black text-[#f8f6ef]'}>{stats.cpu.toFixed(1)}%</div>
                                    <div className={'h-2 w-full rounded-full bg-white/10'}>
                                        <div
                                            className={'h-2 rounded-none bg-blue-600 transition-all duration-500'}
                                            style={{ width: `${cpuPercent}%` }}
                                        />
                                    </div>
                                </div>

                                <div className={'space-y-2'}>
                                    <div className={'flex items-end justify-between'}>
                                        <span className={'text-sm font-medium text-gray-400'}>Memory Usage</span>
                                    </div>
                                    <div className={'text-2xl font-black text-[#f8f6ef]'}>
                                        {bytesToString(stats.memory)}
                                        <span className={'text-lg font-normal text-gray-500'}>
                                            {' '}
                                            / {memoryLimitBytes > 0 ? bytesToString(memoryLimitBytes) : '\u221E'}
                                        </span>
                                    </div>
                                    <div className={'h-2 w-full rounded-full bg-white/10'}>
                                        <div
                                            className={'h-2 rounded-none bg-purple-600 transition-all duration-500'}
                                            style={{ width: `${memoryPercent}%` }}
                                        />
                                    </div>
                                </div>

                                <div className={'space-y-2'}>
                                    <div className={'flex items-end justify-between'}>
                                        <span className={'text-sm font-medium text-gray-400'}>Disk Usage</span>
                                    </div>
                                    <div className={'text-2xl font-black text-[#f8f6ef]'}>
                                        {bytesToString(stats.disk)}
                                        <span className={'text-lg font-normal text-gray-500'}>
                                            {' '}
                                            / {diskLimitBytes > 0 ? bytesToString(diskLimitBytes) : '\u221E'}
                                        </span>
                                    </div>
                                    <div className={'h-2 w-full rounded-full bg-white/10'}>
                                        <div
                                            className={'h-2 rounded-none bg-pink-600 transition-all duration-500'}
                                            style={{ width: `${diskPercent}%` }}
                                        />
                                    </div>
                                </div>

                                <div className={'space-y-2'}>
                                    <div className={'flex items-end justify-between'}>
                                        <span className={'text-sm font-medium text-gray-400'}>Network</span>
                                    </div>
                                    <div className={'flex flex-col space-y-1'}>
                                        <div className={'flex items-center text-sm font-bold text-gray-200'}>
                                            <span className={'material-icons-round mr-1 text-base text-green-500'}>
                                                arrow_downward
                                            </span>
                                            {bytesToString(networkRate.rx)}/s
                                        </div>
                                        <div className={'flex items-center text-sm font-bold text-gray-200'}>
                                            <span className={'material-icons-round mr-1 text-base text-blue-500'}>
                                                arrow_upward
                                            </span>
                                            {bytesToString(networkRate.tx)}/s
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <Features enabled={eggFeatures} />
                    </div>

                    <aside
                        className={
                            'server-console-side flex min-h-0 w-full min-w-0 flex-col gap-6 overflow-hidden p-4 md:p-6 xl:w-[23%] xl:flex-none xl:pl-0'
                        }
                    >
                        <div
                            className={
                                'server-console-panel flex items-center p-4 shadow-[0_0_0_1px_rgba(var(--primary-rgb),0.05)]'
                            }
                        >
                            <div
                                className={
                                    'mr-3 flex h-10 w-10 items-center justify-center overflow-hidden rounded-lg border border-[color:var(--primary)] bg-[color:var(--card)] shadow-[0_0_12px_rgba(var(--primary-rgb), 0.25)]'
                                }
                            >
                                <Avatar.User size={40} />
                            </div>
                            <div className={'min-w-0'}>
                                <h3 className={'truncate font-bold text-[#f8f6ef]'}>{username}</h3>
                                <p className={'truncate text-xs text-gray-400'}>{email}</p>
                            </div>
                        </div>

                        <div
                            className={
                                'server-console-panel p-5 shadow-[0_0_0_1px_rgba(var(--primary-rgb),0.05)]'
                            }
                        >
                            <h3 className={'mb-4 text-lg font-bold uppercase tracking-wide text-[#f8f6ef]'}>
                                Server Control
                            </h3>
                            <div className={'mb-6 space-y-3 text-sm'}>
                                <div className={'flex items-start justify-between gap-3'}>
                                    <span className={'text-gray-400'}>IP:</span>
                                    <span
                                        className={
                                            'max-w-[70%] break-all rounded-md border border-[color:var(--border)] bg-[color:var(--card)] px-2 py-0.5 text-right font-mono text-xs font-medium text-gray-200'
                                        }
                                    >
                                        {allocation}
                                    </span>
                                </div>
                                <div className={'flex items-start justify-between gap-3'}>
                                    <span className={'text-gray-400'}>Status:</span>
                                    <span className={statusBadgeClass}>{(status || 'offline').toUpperCase()}</span>
                                </div>
                                <div className={'flex items-start justify-between gap-3'}>
                                    <span className={'text-gray-400'}>Node:</span>
                                    <code
                                        className={
                                            'max-w-[70%] break-all rounded-md border border-[color:var(--border)] bg-[color:var(--card)] px-2 py-1 text-right font-mono text-xs text-gray-200'
                                        }
                                    >
                                        {node}
                                    </code>
                                </div>
                                <div className={'flex items-start justify-between gap-3'}>
                                    <span className={'text-gray-400'}>Server ID:</span>
                                    <code
                                        title={uuid}
                                        className={
                                            'max-w-[70%] break-all rounded-md border border-[color:var(--border)] bg-[color:var(--card)] px-2 py-1 text-right font-mono text-xs text-gray-200'
                                        }
                                    >
                                        {uuid.slice(0, 8)}
                                    </code>
                                </div>
                            </div>
                            <PowerButtons className={'space-y-3'} variant={'glass'} />
                        </div>

                        <div
                            className={
                                'server-console-panel flex min-h-0 flex-1 flex-col p-5 shadow-[0_0_0_1px_rgba(var(--primary-rgb),0.05)]'
                            }
                        >
                            <div className={'mb-4 flex flex-col gap-3'}>
                                <div className={'flex items-start justify-between gap-3'}>
                                    <div>
                                        <h3 className={'text-lg font-bold text-[#f8f6ef]'}>Players</h3>
                                        <p className={'text-[11px] text-gray-400'}>
                                            {playersData?.game.label || 'Loading player provider...'}
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
                                    className={
                                        'w-full rounded-lg border border-gray-800 bg-[color:var(--card)] py-2 pl-3 pr-8 text-xs text-white outline-none focus:border-[color:var(--primary)] focus:ring-1 focus:ring-[color:var(--primary)]'
                                    }
                                    placeholder={'Filter by Name, UUID, or ID...'}
                                    type={'text'}
                                    value={playerSearch}
                                    onChange={(event) => setPlayerSearch(event.currentTarget.value)}
                                />
                                <span
                                    className={'material-icons-round pointer-events-none absolute right-2 top-2 text-sm text-gray-500'}
                                >
                                    search
                                </span>
                            </div>

                            <div className={'space-y-3 overflow-hidden pr-1'}>
                                {playersLoading && (
                                    <div className={'py-8'}>
                                        <Spinner size={'small'} centered />
                                        <p className={'mt-2 text-center text-xs text-gray-500'}>Loading players...</p>
                                    </div>
                                )}

                                {!playersLoading && playersError && (
                                    <p className={'rounded-lg border border-red-500/40 bg-red-500/10 px-3 py-2 text-xs text-red-200'}>
                                        {playersError}
                                    </p>
                                )}

                                {!playersLoading && !playersError && (playersData?.items || []).length === 0 && (
                                    <p className={'rounded-lg border border-[color:var(--border)] bg-[color:var(--background)] px-3 py-2 text-xs text-gray-400'}>
                                        {debouncedPlayerSearch
                                            ? 'No players matched current filter.'
                                            : 'No live player data available for this server yet.'}
                                    </p>
                                )}

                                {!playersLoading &&
                                    !playersError &&
                                    (playersData?.items || []).map((player) => (
                                        <div
                                            key={player.id}
                                            className={
                                                'flex cursor-pointer items-center justify-between rounded-lg border border-transparent p-2 transition-colors hover:border-[color:var(--border)] hover:bg-[color:var(--card)]/40'
                                            }
                                            role={'button'}
                                            tabIndex={0}
                                            onClick={() => void openPlayerDetails(player.id)}
                                            onKeyDown={(event) => {
                                                if (event.key === 'Enter' || event.key === ' ') {
                                                    event.preventDefault();
                                                    void openPlayerDetails(player.id);
                                                }
                                            }}
                                        >
                                            <div className={'flex min-w-0 items-center gap-3'}>
                                                <div className={'flex h-9 w-9 items-center justify-center overflow-hidden rounded-lg border border-[color:var(--border)] bg-[color:var(--background)]'}>
                                                    <PlayerAvatar player={player} size={36} />
                                                </div>
                                                <div className={'min-w-0'}>
                                                    <div className={'flex flex-wrap items-center gap-1'}>
                                                        <p className={'truncate text-sm font-bold text-gray-100'}>
                                                            {player.name}
                                                        </p>
                                                        {player.is_operator && (
                                                            <span className={'rounded border border-amber-500/40 bg-amber-500/10 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide text-amber-200'}>
                                                                OP
                                                            </span>
                                                        )}
                                                        {player.is_admin && (
                                                            <span className={'rounded border border-purple-500/40 bg-purple-500/10 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide text-purple-200'}>
                                                                Admin
                                                            </span>
                                                        )}
                                                        {player.banned && (
                                                            <span className={'rounded border border-red-500/40 bg-red-500/10 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide text-red-200'}>
                                                                Banned
                                                            </span>
                                                        )}
                                                    </div>
                                                    <p className={'text-[10px] text-gray-500'}>
                                                        {player.status === 'online'
                                                            ? `Ping: ${player.ping}ms`
                                                            : 'Offline'}
                                                    </p>
                                                </div>
                                            </div>
                                            <div className={'flex gap-1'}>
                                                <button
                                                    className={
                                                        'rounded p-1 text-gray-500 hover:bg-[color:var(--primary)]/10 hover:text-[color:var(--primary)]'
                                                    }
                                                    type={'button'}
                                                    onClick={(event) => {
                                                        event.stopPropagation();
                                                        void openPlayerDetails(player.id);
                                                    }}
                                                >
                                                    <span className={'material-icons-round text-sm'}>settings</span>
                                                </button>
                                            </div>
                                        </div>
                                    ))}
                            </div>
                        </div>
                    </aside>
                </div>
            </div>
        </>
    );
};

export default memo(ServerConsoleContainer, isEqual);
