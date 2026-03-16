import crypto from 'node:crypto';
import process from 'node:process';
import {
    ActionRowBuilder,
    ButtonBuilder,
    ChannelType,
    Client,
    Events,
    GatewayIntentBits,
    ModalBuilder,
    Partials,
    StringSelectMenuBuilder,
    TextInputBuilder,
    TextInputStyle,
} from 'discord.js';

const env = {
    botToken: process.env.DISCORD_BOT_TOKEN?.trim() ?? '',
    panelInternalBaseUrl: (process.env.PANEL_INTERNAL_BASE_URL?.trim() ?? '').replace(/\/+$/, ''),
    sharedSecret: process.env.TICKET_BRIDGE_SHARED_SECRET?.trim() ?? '',
    relayWebhookId: process.env.DISCORD_RELAY_WEBHOOK_ID?.trim() ?? '',
    enableGuildMembersIntent: ['1', 'true', 'yes', 'on'].includes((process.env.DISCORD_ENABLE_GUILD_MEMBERS ?? '').trim().toLowerCase()),
    enableMessageContentIntent: ['1', 'true', 'yes', 'on'].includes((process.env.DISCORD_ENABLE_MESSAGE_CONTENT ?? '').trim().toLowerCase()),
    heartbeatSeconds: Math.max(Number(process.env.TICKET_BRIDGE_HEARTBEAT_SECONDS ?? 30) || 30, 10),
};

if (!env.botToken || !env.panelInternalBaseUrl || !env.sharedSecret) {
    console.error(
        '[ticket-bridge] Missing required environment. Set DISCORD_BOT_TOKEN, PANEL_INTERNAL_BASE_URL, and TICKET_BRIDGE_SHARED_SECRET.'
    );
    process.exit(1);
}

const client = new Client({
    intents: [
        GatewayIntentBits.Guilds,
        ...(env.enableGuildMembersIntent ? [GatewayIntentBits.GuildMembers] : []),
        GatewayIntentBits.GuildMessages,
        ...(env.enableMessageContentIntent ? [GatewayIntentBits.MessageContent] : []),
    ],
    partials: [Partials.Channel, Partials.Message],
});

let heartbeatTimer = null;

const signBody = (body) =>
    crypto.createHmac('sha256', env.sharedSecret).update(body).digest('hex');

const postInternal = async (path, payload, { expectJson = false } = {}) => {
    const body = JSON.stringify(payload);
    const response = await fetch(`${env.panelInternalBaseUrl}${path}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Tickets-Signature': signBody(body),
        },
        body,
    });

    if (!response.ok) {
        const text = await response.text();
        throw new Error(`Internal API ${path} failed with ${response.status}: ${text}`);
    }

    if (!expectJson) {
        return null;
    }

    return response.json();
};

const isThreadChannel = (channel) =>
    !!channel && [
        ChannelType.PublicThread,
        ChannelType.PrivateThread,
        ChannelType.AnnouncementThread,
    ].includes(channel.type);

const normalizeRoles = (member) => {
    if (!member?.roles) {
        return [];
    }

    if (Array.isArray(member.roles)) {
        return member.roles.map(String);
    }

    if (member.roles.cache) {
        return [...member.roles.cache.keys()].map(String);
    }

    return [];
};

const normalizeAttachments = (message) =>
    [...(message.attachments?.values?.() ?? [])].map((attachment) => ({
        id: String(attachment.id),
        filename: attachment.name ?? 'attachment',
        content_type: attachment.contentType ?? null,
        size: attachment.size ?? 0,
        url: attachment.url,
        proxy_url: attachment.proxyURL ?? null,
    }));

const normalizeThread = (channel) => ({
    id: String(channel?.id ?? ''),
    parent_id: channel?.parentId ? String(channel.parentId) : null,
    name: channel?.name ?? null,
    archived: !!channel?.archived,
    locked: !!channel?.locked,
    type: channel?.type ?? null,
});

const normalizeMessage = (message) => ({
    id: String(message.id),
    channel_id: String(message.channelId),
    content: message.content ?? '',
    edited_timestamp: message.editedAt ? message.editedAt.toISOString() : null,
    attachments: normalizeAttachments(message),
    author: {
        id: String(message.author?.id ?? ''),
        username: message.author?.username ?? null,
        global_name: message.author?.globalName ?? null,
        bot: !!message.author?.bot,
        webhook_id: message.webhookId ? String(message.webhookId) : null,
        roles: normalizeRoles(message.member),
    },
    member: {
        roles: normalizeRoles(message.member),
    },
});

const shouldIgnoreMessage = (message) => {
    if (!isThreadChannel(message.channel)) {
        return true;
    }

    if (message.author?.bot) {
        return true;
    }

    if (env.relayWebhookId && message.webhookId && String(message.webhookId) === env.relayWebhookId) {
        return true;
    }

    return false;
};

const normalizeInteraction = (interaction) => {
    const payload = {
        type: interaction.type,
        guild_id: interaction.guildId ? String(interaction.guildId) : null,
        channel_id: interaction.channelId ? String(interaction.channelId) : null,
        user: {
            id: String(interaction.user?.id ?? ''),
        },
        member: {
            user: {
                id: String(interaction.user?.id ?? ''),
            },
            roles: normalizeRoles(interaction.member),
        },
        data: {},
    };

    if ('customId' in interaction && interaction.customId) {
        payload.data.custom_id = interaction.customId;
    }

    if (interaction.isStringSelectMenu()) {
        payload.data.values = interaction.values.map(String);
    }

    if (interaction.isModalSubmit()) {
        payload.data.components = interaction.components.map((row) => ({
            type: 1,
            components: row.components.map((component) => ({
                type: component.type,
                custom_id: component.customId,
                value: component.value ?? '',
            })),
        }));
    }

    return payload;
};

const buildMessageComponents = (rows = []) =>
    rows
        .map((row) => {
            const actionRow = new ActionRowBuilder();

            for (const component of row.components ?? []) {
                if (component.type === 2) {
                    const button = new ButtonBuilder()
                        .setStyle(component.style)
                        .setLabel(component.label ?? 'Open');

                    if (component.style === 5 && component.url) {
                        button.setURL(component.url);
                    } else {
                        button.setCustomId(component.custom_id ?? `tickets:fallback:${crypto.randomUUID()}`);
                    }

                    if (component.emoji) {
                        button.setEmoji(component.emoji);
                    }

                    if (component.disabled) {
                        button.setDisabled(true);
                    }

                    actionRow.addComponents(button);
                }

                if (component.type === 3) {
                    const menu = new StringSelectMenuBuilder()
                        .setCustomId(component.custom_id ?? `tickets:select:${crypto.randomUUID()}`)
                        .setPlaceholder(component.placeholder ?? 'Select an item')
                        .setMinValues(component.min_values ?? 1)
                        .setMaxValues(component.max_values ?? 1)
                        .addOptions(
                            (component.options ?? []).map((option) => ({
                                label: option.label ?? 'Option',
                                value: option.value ?? option.label ?? crypto.randomUUID(),
                                description: option.description ?? undefined,
                                default: !!option.default,
                            }))
                        );

                    if (component.disabled) {
                        menu.setDisabled(true);
                    }

                    actionRow.addComponents(menu);
                }
            }

            return actionRow;
        })
        .filter((row) => row.components.length > 0);

const buildModal = (data) => {
    const modal = new ModalBuilder()
        .setCustomId(data.custom_id ?? `tickets:modal:${crypto.randomUUID()}`)
        .setTitle(data.title ?? 'Support Ticket');

    for (const row of data.components ?? []) {
        const textInputRow = new ActionRowBuilder();

        for (const component of row.components ?? []) {
            if (component.type !== 4) {
                continue;
            }

            const input = new TextInputBuilder()
                .setCustomId(component.custom_id ?? `tickets:field:${crypto.randomUUID()}`)
                .setLabel(component.label ?? 'Value')
                .setStyle(component.style === 2 ? TextInputStyle.Paragraph : TextInputStyle.Short)
                .setRequired(component.required !== false);

            if (typeof component.min_length === 'number') {
                input.setMinLength(component.min_length);
            }

            if (typeof component.max_length === 'number') {
                input.setMaxLength(component.max_length);
            }

            if (component.value) {
                input.setValue(component.value);
            }

            if (component.placeholder) {
                input.setPlaceholder(component.placeholder);
            }

            textInputRow.addComponents(input);
        }

        if (textInputRow.components.length > 0) {
            modal.addComponents(textInputRow);
        }
    }

    return modal;
};

const buildInteractionMessage = (data = {}, { includeEphemeral = false } = {}) => {
    const message = {};

    if (typeof data.content === 'string') {
        message.content = data.content;
    }

    if (Array.isArray(data.embeds)) {
        message.embeds = data.embeds;
    }

    if (Array.isArray(data.components)) {
        const components = buildMessageComponents(data.components);
        message.components = components.length > 0 ? components : [];
    }

    if (data.allowed_mentions) {
        message.allowedMentions = {
            parse: data.allowed_mentions.parse ?? [],
        };
    }

    if (includeEphemeral) {
        message.ephemeral = Boolean(data.flags & 64);
    }

    return message;
};

const applyInteractionActions = async (interaction, response) => {
    for (const action of response.actions ?? []) {
        if (!action || action.type !== 'delete_channel') {
            continue;
        }

        const channelId = String(action.channel_id ?? '');
        if (!channelId) {
            continue;
        }

        const channel = interaction.channel?.id === channelId
            ? interaction.channel
            : await interaction.client.channels.fetch(channelId).catch(() => null);

        if (!channel || typeof channel.delete !== 'function') {
            continue;
        }

        await channel.delete(action.reason ?? 'Ticket closed');
    }
};

const applyInteractionResponse = async (interaction, response) => {
    if (!response || typeof response !== 'object') {
        throw new Error('Panel returned an invalid interaction response.');
    }

    if (response.type === 9) {
        await interaction.showModal(buildModal(response.data ?? {}));
        return;
    }

    if (response.type === 4) {
        const data = response.data ?? {};
        const reply = buildInteractionMessage(data, { includeEphemeral: true });

        await interaction.reply(reply);
        await applyInteractionActions(interaction, response);
        return;
    }

    if (response.type === 6) {
        await interaction.deferUpdate();
        await applyInteractionActions(interaction, response);
        return;
    }

    if (response.type === 7) {
        const data = response.data ?? {};
        const message = buildInteractionMessage(data);

        await interaction.update(message);
        await applyInteractionActions(interaction, response);
        return;
    }

    await interaction.reply({
        content: 'Unsupported Discord interaction response.',
        ephemeral: true,
    });
};

const sendHeartbeat = async () => {
    try {
        await postInternal('/api/internal/tickets/discord/heartbeat', {
            status: client.isReady() ? 'ready' : 'connecting',
            shard: 0,
            gateway_ping_ms: client.ws.ping,
            uptime_seconds: Math.round(process.uptime()),
        });
    } catch (error) {
        console.error('[ticket-bridge] heartbeat failed:', error);
    }
};

const emitMessageEvent = async (eventType, rawMessage) => {
    const message = rawMessage.partial ? await rawMessage.fetch() : rawMessage;
    if (shouldIgnoreMessage(message)) {
        return;
    }

    await postInternal('/api/internal/tickets/discord/events', {
        event_type: eventType,
        thread: normalizeThread(message.channel),
        message: normalizeMessage(message),
    });
};

client.once(Events.ClientReady, async () => {
    console.log(`[ticket-bridge] logged in as ${client.user?.tag ?? 'unknown'}`);
    if (!env.enableGuildMembersIntent) {
        console.warn('[ticket-bridge] Guild Members intent is disabled. Ticket launcher and thread sync will still work, but role/member cache will stay minimal unless you enable the privileged Server Members Intent and restart the bridge.');
    }
    if (!env.enableMessageContentIntent) {
        console.warn('[ticket-bridge] Message Content intent is disabled. Launcher interactions and thread metadata will work, but Discord-to-panel message text sync requires enabling the privileged Message Content intent and restarting the bridge.');
    }
    await sendHeartbeat();
    heartbeatTimer = setInterval(sendHeartbeat, env.heartbeatSeconds * 1000);
});

client.on(Events.MessageCreate, async (message) => {
    try {
        await emitMessageEvent('MESSAGE_CREATE', message);
    } catch (error) {
        console.error('[ticket-bridge] MESSAGE_CREATE failed:', error);
    }
});

client.on(Events.MessageUpdate, async (_oldMessage, newMessage) => {
    try {
        await emitMessageEvent('MESSAGE_UPDATE', newMessage);
    } catch (error) {
        console.error('[ticket-bridge] MESSAGE_UPDATE failed:', error);
    }
});

client.on(Events.MessageDelete, async (message) => {
    try {
        if (!isThreadChannel(message.channel)) {
            return;
        }

        await postInternal('/api/internal/tickets/discord/events', {
            event_type: 'MESSAGE_DELETE',
            thread: normalizeThread(message.channel),
            message: normalizeMessage(message),
        });
    } catch (error) {
        console.error('[ticket-bridge] MESSAGE_DELETE failed:', error);
    }
});

client.on(Events.InteractionCreate, async (interaction) => {
    try {
        if (!interaction.isButton() && !interaction.isStringSelectMenu() && !interaction.isModalSubmit()) {
            return;
        }

        if (!String(interaction.customId ?? '').startsWith('tickets:')) {
            return;
        }

        const response = await postInternal(
            '/api/internal/tickets/discord/interactions',
            { payload: normalizeInteraction(interaction) },
            { expectJson: true }
        );

        await applyInteractionResponse(interaction, response);
    } catch (error) {
        console.error('[ticket-bridge] INTERACTION_CREATE failed:', error);

        if (!interaction.deferred && !interaction.replied) {
            try {
                await interaction.reply({
                    content: 'The support request could not be completed in Discord right now. Open the support center in the panel and try again.',
                    ephemeral: true,
                });
            } catch (replyError) {
                console.error('[ticket-bridge] interaction fallback reply failed:', replyError);
            }
        }
    }
});

client.on(Events.ThreadUpdate, async (_oldThread, newThread) => {
    try {
        await postInternal('/api/internal/tickets/discord/events', {
            event_type: 'THREAD_UPDATE',
            thread: normalizeThread(newThread),
        });
    } catch (error) {
        console.error('[ticket-bridge] THREAD_UPDATE failed:', error);
    }
});

client.on(Events.ThreadDelete, async (thread) => {
    try {
        await postInternal('/api/internal/tickets/discord/events', {
            event_type: 'THREAD_DELETE',
            thread: normalizeThread(thread),
        });
    } catch (error) {
        console.error('[ticket-bridge] THREAD_DELETE failed:', error);
    }
});

client.on(Events.Error, (error) => {
    console.error('[ticket-bridge] client error:', error);
});

const shutdown = async (signal) => {
    console.log(`[ticket-bridge] shutting down on ${signal}`);
    if (heartbeatTimer) {
        clearInterval(heartbeatTimer);
    }

    try {
        await client.destroy();
    } finally {
        process.exit(0);
    }
};

process.on('SIGINT', () => void shutdown('SIGINT'));
process.on('SIGTERM', () => void shutdown('SIGTERM'));

await client.login(env.botToken);
