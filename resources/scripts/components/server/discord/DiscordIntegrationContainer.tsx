import React, { useEffect, useState } from 'react';
import FlashMessageRender from '@/components/FlashMessageRender';
import PageLoadingSkeleton from '@/components/elements/PageLoadingSkeleton';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import SpinnerOverlay from '@/components/elements/SpinnerOverlay';
import { InteractiveHoverButton } from '@/components/ui/interactive-hover-button';
import { ServerContext } from '@/state/server';
import useFlash, { useFlashKey } from '@/plugins/useFlash';
import { usePermissions } from '@/plugins/usePermissions';
import {
    DiscordIntegrationState,
    installServerDiscordAgent,
    resetServerDiscordAgent,
    syncServerDiscordAgent,
    updateServerDiscordIntegration,
    useServerDiscordIntegration,
} from '@/api/server/discord/getServerDiscordIntegration';

type Draft = Omit<DiscordIntegrationState, 'hasBotToken'> & {
    botToken: string;
};

const emptyDraft: Draft = {
    enabled: false,
    botToken: '',
    guildId: '',
    chatChannelId: '',
    consoleChannelId: '',
    adminChannelId: '',
    linkChannelId: '',
    chatBridgeEnabled: true,
    consoleBridgeEnabled: true,
    linkingEnabled: true,
    whitelistRequiresLink: false,
};

const statusTone = (value: string): string =>
    value === 'connected'
        ? 'border-[#22c55e] bg-[#dcfce7] text-[#14532d]'
        : value === 'needs_restart'
          ? 'border-[#f59e0b] bg-[#fef3c7] text-[#78350f]'
          : value === 'error'
            ? 'border-[#ef4444] bg-[#fee2e2] text-[#7f1d1d]'
            : 'border-[#2D4A3E] bg-[#F5EFD5] text-[#742220]';

const labelFor = (value?: string | null): string =>
    (value || 'not configured')
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (char) => char.toUpperCase());

const discordIdHelp: Record<string, string> = {
    guildId:
        'Enable Discord Developer Mode, right click your Discord server name, then Copy Server ID. Paste that number here.',
    chatChannelId:
        'Right click the channel used for normal game chat, then Copy Channel ID. Bot needs View Channel and Send Messages.',
    consoleChannelId:
        'Right click the private staff channel for console logs/commands, then Copy Channel ID. Keep this admin-only.',
    adminChannelId:
        'Right click the staff/admin alert channel, then Copy Channel ID. This is used for moderation and system notices.',
    linkChannelId:
        'Right click the channel where players will run or receive link verification, then Copy Channel ID.',
};

const FieldHelp = ({ text }: { text: string }) => (
    <span className='group/help relative inline-flex'>
        <span
            aria-label={text}
            tabIndex={0}
            className='inline-flex h-5 w-5 cursor-help items-center justify-center rounded-full border-2 border-[#2D4A3E] bg-[#F5EFD5] text-[11px] font-black leading-none text-[#742220] shadow-[2px_2px_0_0_#2D4A3E] outline-none transition hover:bg-[#FEF9E1] focus:bg-[#FEF9E1]'
        >
            !
        </span>
        <span className='pointer-events-none absolute left-1/2 top-7 z-50 w-[min(18rem,80vw)] -translate-x-1/2 rounded-[18px] border-2 border-[#2D4A3E] bg-[#FEF9E1] bg-[repeating-linear-gradient(0deg,transparent,transparent_4.5px,rgba(116,34,32,0.04)_4.5px,rgba(116,34,32,0.04)_5px),repeating-linear-gradient(60deg,transparent,transparent_4.5px,rgba(116,34,32,0.04)_4.5px,rgba(116,34,32,0.04)_5px),repeating-linear-gradient(120deg,transparent,transparent_4.5px,rgba(116,34,32,0.04)_4.5px,rgba(116,34,32,0.04)_5px)] px-4 py-3 text-left text-[11px] font-semibold normal-case leading-5 tracking-normal text-[#742220] opacity-0 shadow-[4px_4px_0_0_#2D4A3E] transition group-hover/help:opacity-100 group-focus-within/help:opacity-100'>
            {text}
        </span>
    </span>
);

const Field = ({
    label,
    value,
    placeholder,
    onChange,
    type = 'text',
    help,
    labelHelp,
}: {
    label: string;
    value: string | null;
    placeholder?: string;
    type?: string;
    help?: string;
    labelHelp?: string;
    onChange: (value: string) => void;
}) => (
    <label className='block'>
        <span className='inline-flex items-center gap-2 text-[11px] font-bold uppercase tracking-[0.18em] text-[#742220]'>
            {label}
            {labelHelp && <FieldHelp text={labelHelp} />}
        </span>
        <input
            type={type}
            value={value || ''}
            placeholder={placeholder}
            onChange={(event) => onChange(event.currentTarget.value)}
            className='mt-2 w-full rounded-[18px] border-2 border-[#2D4A3E] bg-[#F5EFD5] px-4 py-3 text-sm font-semibold text-[#742220] outline-none transition focus:bg-[#FEF9E1] focus:shadow-[4px_4px_0_0_#2D4A3E]'
        />
        {help && <span className='mt-2 block text-[11px] leading-5 text-[rgba(116,34,32,0.62)]'>{help}</span>}
    </label>
);

const ToggleRow = ({
    label,
    description,
    checked,
    onChange,
}: {
    label: string;
    description: string;
    checked: boolean;
    onChange: (value: boolean) => void;
}) => (
    <button
        type='button'
        onClick={() => onChange(!checked)}
        className='flex w-full items-center justify-between gap-4 rounded-[18px] border-2 border-[#2D4A3E] bg-[#F5EFD5] px-4 py-3 text-left transition hover:bg-[#FEF9E1]'
    >
        <span>
            <span className='block text-xs font-bold uppercase tracking-[0.16em] text-[#742220]'>{label}</span>
            <span className='mt-1 block text-[11px] leading-5 text-[rgba(116,34,32,0.62)]'>{description}</span>
        </span>
        <span
            className={[
                'inline-flex min-w-[4.75rem] items-center justify-center rounded-full border px-3 py-1 text-[10px] font-black uppercase tracking-[0.16em]',
                checked ? 'border-[#166534] bg-[#22c55e] text-[#021108]' : 'border-[#7f1d1d] bg-[#ef4444] text-[#190303]',
            ].join(' ')}
        >
            {checked ? 'On' : 'Off'}
        </span>
    </button>
);

const DiscordIntegrationContainer = () => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const [canManage] = usePermissions(['discord.manage']);
    const [busy, setBusy] = useState(false);
    const [draft, setDraft] = useState<Draft>(emptyDraft);
    const { addFlash } = useFlash();
    const { clearFlashes, clearAndAddHttpError } = useFlashKey('server:discord');
    const { data, error, mutate } = useServerDiscordIntegration();

    useEffect(() => {
        if (error) {
            clearAndAddHttpError(error);
        }
    }, [error]);

    useEffect(() => {
        if (!data) {
            return;
        }

        setDraft({
            enabled: data.integration.enabled,
            botToken: '',
            guildId: data.integration.guildId || '',
            chatChannelId: data.integration.chatChannelId || '',
            consoleChannelId: data.integration.consoleChannelId || '',
            adminChannelId: data.integration.adminChannelId || '',
            linkChannelId: data.integration.linkChannelId || '',
            chatBridgeEnabled: data.integration.chatBridgeEnabled,
            consoleBridgeEnabled: data.integration.consoleBridgeEnabled,
            linkingEnabled: data.integration.linkingEnabled,
            whitelistRequiresLink: data.integration.whitelistRequiresLink,
        });
    }, [data]);

    const run = (task: () => Promise<void>) => {
        clearFlashes();
        setBusy(true);
        task()
            .catch((requestError) => clearAndAddHttpError(requestError))
            .finally(() => setBusy(false));
    };

    const save = () =>
        run(async () => {
            const next = await updateServerDiscordIntegration(uuid, {
                enabled: draft.enabled,
                botToken: draft.botToken || undefined,
                guildId: draft.guildId || undefined,
                chatChannelId: draft.chatChannelId || undefined,
                consoleChannelId: draft.consoleChannelId || undefined,
                adminChannelId: draft.adminChannelId || undefined,
                linkChannelId: draft.linkChannelId || undefined,
                chatBridgeEnabled: draft.chatBridgeEnabled,
                consoleBridgeEnabled: draft.consoleBridgeEnabled,
                linkingEnabled: draft.linkingEnabled,
                whitelistRequiresLink: draft.whitelistRequiresLink,
            });
            await mutate(next, false);
            setDraft((current) => ({ ...current, botToken: '' }));
            addFlash({ key: 'server:discord', type: 'success', message: 'Discord integration settings saved.' });
        });

    const install = () =>
        run(async () => {
            const next = await installServerDiscordAgent(uuid);
            await mutate(next, false);
            addFlash({
                key: 'server:discord',
                type: 'success',
                message: 'Discord panel agent installed. Use Sync Now or wait for the scheduler to connect it.',
            });
        });

    const sync = () =>
        run(async () => {
            const next = await syncServerDiscordAgent(uuid);
            await mutate(next, false);
            addFlash({
                key: 'server:discord',
                type: next.agent.connectionStatus === 'connected' ? 'success' : 'warning',
                message:
                    next.agent.connectionStatus === 'connected'
                        ? 'Discord panel agent synced and connected.'
                        : next.agent.lastError || 'Discord panel agent sync did not connect yet.',
            });
        });

    const reset = () =>
        run(async () => {
            const next = await resetServerDiscordAgent(uuid);
            await mutate(next, false);
            addFlash({ key: 'server:discord', type: 'success', message: 'Discord agent state reset.' });
        });

    if (!data && !error) {
        return (
            <ServerContentBlock title='Discord' className='content-container-full px-4 py-4 xl:px-6'>
                <PageLoadingSkeleton showChrome={false} showSpinner={false} rows={7} className='min-h-[420px]' />
            </ServerContentBlock>
        );
    }

    if (!data && error) {
        return (
            <ServerContentBlock
                showFlashKey='server:discord'
                title='Discord'
                className='content-container-full px-4 py-4 xl:px-6'
            >
                <FlashMessageRender byKey='server:discord' />
                <section className='sc-card p-5'>
                    <p className='text-xs uppercase tracking-[0.28em] sc-muted'>Discord Agent</p>
                    <h2 className='mt-2 text-2xl font-black tracking-tight sc-text'>Integration failed to load</h2>
                    <p className='mt-2 max-w-3xl text-sm leading-6 sc-muted'>
                        The panel could not load Discord integration data. Try again after the server error is resolved.
                    </p>
                    <div className='mt-5 flex justify-end border-t-2 border-[#2D4A3E] pt-5'>
                        <InteractiveHoverButton
                            type='button'
                            text='Retry'
                            iconName='refresh'
                            onClick={() => mutate()}
                        />
                    </div>
                </section>
            </ServerContentBlock>
        );
    }

    return (
        <ServerContentBlock
            showFlashKey='server:discord'
            title='Discord'
            className='content-container-full px-4 py-4 xl:px-6'
        >
            <SpinnerOverlay visible={busy} />
            <FlashMessageRender byKey='server:discord' />

            <div className='grid gap-6 2xl:grid-cols-[1.05fr_0.95fr]'>
                <section className='sc-card p-5'>
                    <div className='mb-5 flex flex-wrap items-start justify-between gap-4'>
                        <div>
                            <p className='text-xs uppercase tracking-[0.28em] sc-muted'>Discord Agent</p>
                            <h2 className='mt-2 text-2xl font-black tracking-tight sc-text'>Integration Setup</h2>
                            <p className='mt-2 max-w-3xl text-sm leading-6 sc-muted'>
                                Auto-detects the game from startup, Docker image, variables, egg metadata, and later the
                                runtime agent fingerprint. When connected, Players uses agent snapshots instead of the
                                fallback provider.
                            </p>
                        </div>
                        <span className={['rounded-full border px-4 py-2 text-xs font-black uppercase tracking-[0.18em]', statusTone(data?.agent.connectionStatus || '')].join(' ')}>
                            {labelFor(data?.agent.connectionStatus)}
                        </span>
                    </div>

                    <div className='grid gap-4 md:grid-cols-3'>
                        <div className='sc-card-inner p-4'>
                            <p className='text-[10px] font-bold uppercase tracking-[0.2em] sc-muted'>Detected Game</p>
                            <p className='mt-2 text-lg font-black sc-text'>{labelFor(data?.agent.detectedGameType)}</p>
                            <p className='mt-1 text-xs sc-muted'>{data?.agent.detectionConfidence || 0}% confidence</p>
                        </div>
                        <div className='sc-card-inner p-4'>
                            <p className='text-[10px] font-bold uppercase tracking-[0.2em] sc-muted'>Adapter</p>
                            <p className='mt-2 text-lg font-black sc-text'>{labelFor(data?.agent.adapter)}</p>
                            <p className='mt-1 text-xs sc-muted'>Auto selected from fingerprint.</p>
                        </div>
                        <div className='sc-card-inner p-4'>
                            <p className='text-[10px] font-bold uppercase tracking-[0.2em] sc-muted'>Player Source</p>
                            <p className='mt-2 text-lg font-black sc-text'>{data?.playerSource.label}</p>
                            <p className='mt-1 text-xs sc-muted'>{data?.playerSource.message}</p>
                        </div>
                    </div>

                    <div className='mt-5 grid gap-3 sm:grid-cols-2'>
                        {(data?.agent.detectionSources || []).map((source) => (
                            <div key={source} className='rounded-[16px] border border-[#2D4A3E]/45 bg-[#F5EFD5] px-3 py-2 text-xs font-bold uppercase tracking-[0.14em] text-[#742220]'>
                                {labelFor(source)}
                            </div>
                        ))}
                    </div>

                    <div className='mt-6 flex flex-wrap gap-3'>
                        <InteractiveHoverButton
                            type='button'
                            text={data?.agent.installStatus === 'not_installed' ? 'Install Agent' : 'Reinstall Agent'}
                            iconName='download'
                            variant='success'
                            disabled={!canManage}
                            onClick={install}
                        />
                        <InteractiveHoverButton
                            type='button'
                            text='Sync Now'
                            iconName='sync'
                            disabled={!canManage || data?.agent.installStatus === 'not_installed'}
                            onClick={sync}
                        />
                        <InteractiveHoverButton
                            type='button'
                            text='Reset Agent'
                            iconName='restart_alt'
                            variant='danger'
                            disabled={!canManage || data?.agent.installStatus === 'not_installed'}
                            onClick={reset}
                        />
                    </div>

                    <p className='mt-4 rounded-[18px] border-2 border-[#2D4A3E] bg-[#F5EFD5] px-4 py-3 text-xs leading-6 text-[#742220]'>
                        The panel agent reads <code>/.burhan/.discord/discord-agent.json</code>, syncs through Wings and
                        Discord, then powers the chat bridge and player source automatically from the scheduler. For
                        faster testing, press <strong>Sync Now</strong>.
                    </p>
                </section>

                <section className='sc-card p-5'>
                    <div className='mb-5'>
                        <p className='text-xs uppercase tracking-[0.28em] sc-muted'>Bot & Channels</p>
                        <h2 className='mt-2 text-2xl font-black tracking-tight sc-text'>Discord Settings</h2>
                        <p className='mt-2 text-sm leading-6 sc-muted'>
                            Bot token is encrypted by the panel. Leave it blank to keep the existing stored token.
                        </p>
                    </div>

                    <div className='grid gap-4'>
                        <ToggleRow
                            label='Enable Integration'
                            description='Turns on Discord bridge configuration for this server.'
                            checked={draft.enabled}
                            onChange={(enabled) => setDraft((current) => ({ ...current, enabled }))}
                        />
                        <Field
                            label='Discord Bot Token'
                            type='password'
                            value={draft.botToken}
                            placeholder={data?.integration.hasBotToken ? 'Stored. Leave blank to keep current token.' : 'Paste bot token'}
                            onChange={(botToken) => setDraft((current) => ({ ...current, botToken }))}
                        />
                        <div className='grid gap-4 sm:grid-cols-2'>
                            <Field label='Guild ID' labelHelp={discordIdHelp.guildId} value={draft.guildId} onChange={(guildId) => setDraft((current) => ({ ...current, guildId }))} />
                            <Field label='Chat Channel ID' labelHelp={discordIdHelp.chatChannelId} value={draft.chatChannelId} onChange={(chatChannelId) => setDraft((current) => ({ ...current, chatChannelId }))} />
                            <Field label='Console Channel ID' labelHelp={discordIdHelp.consoleChannelId} value={draft.consoleChannelId} onChange={(consoleChannelId) => setDraft((current) => ({ ...current, consoleChannelId }))} />
                            <Field label='Admin Channel ID' labelHelp={discordIdHelp.adminChannelId} value={draft.adminChannelId} onChange={(adminChannelId) => setDraft((current) => ({ ...current, adminChannelId }))} />
                            <Field label='Link Channel ID' labelHelp={discordIdHelp.linkChannelId} value={draft.linkChannelId} onChange={(linkChannelId) => setDraft((current) => ({ ...current, linkChannelId }))} />
                        </div>

                        <ToggleRow label='Chat Bridge' description='Sync game chat with the Discord chat channel.' checked={draft.chatBridgeEnabled} onChange={(chatBridgeEnabled) => setDraft((current) => ({ ...current, chatBridgeEnabled }))} />
                        <ToggleRow label='Console Bridge' description='Allow admin console output and commands through configured Discord channels.' checked={draft.consoleBridgeEnabled} onChange={(consoleBridgeEnabled) => setDraft((current) => ({ ...current, consoleBridgeEnabled }))} />
                        <ToggleRow label='Player Linking' description='Enable /link flow between game players and Discord accounts.' checked={draft.linkingEnabled} onChange={(linkingEnabled) => setDraft((current) => ({ ...current, linkingEnabled }))} />
                        <ToggleRow label='Require Link For Whitelist' description='Only allow whitelisted access after a player links Discord.' checked={draft.whitelistRequiresLink} onChange={(whitelistRequiresLink) => setDraft((current) => ({ ...current, whitelistRequiresLink }))} />

                        <div className='flex justify-end border-t-2 border-[#2D4A3E] pt-5'>
                            <InteractiveHoverButton
                                type='button'
                                text='Save Settings'
                                iconName='save'
                                disabled={!canManage}
                                onClick={save}
                            />
                        </div>
                    </div>
                </section>
            </div>
        </ServerContentBlock>
    );
};

export default DiscordIntegrationContainer;
