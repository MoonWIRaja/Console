import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import TitledGreyBox from '@/components/elements/TitledGreyBox';
import tw from 'twin.macro';
import VariableBox from '@/components/server/startup/VariableBox';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import getServerStartup, { type StartupResponse } from '@/api/swr/getServerStartup';
import Spinner from '@/components/elements/Spinner';
import { ServerError } from '@/components/elements/ScreenBlock';
import { httpErrorToHuman } from '@/api/http';
import { ServerContext } from '@/state/server';
import { useDeepCompareEffect } from '@/plugins/useDeepCompareEffect';
import isEqual from 'react-fast-compare';
import InputSpinner from '@/components/elements/InputSpinner';
import useFlash from '@/plugins/useFlash';
import Select from '@/components/ui/select';
import { Textarea } from '@/components/elements/Input';
import { Dialog } from '@/components/elements/dialog';
import updateStartupCommand from '@/api/server/updateStartupCommand';
import resetStartupCommand from '@/api/server/resetStartupCommand';
import changeStartupEgg from '@/api/server/changeStartupEgg';
import getAvailableNests, { NestOption } from '@/api/server/getAvailableNests';
import getNestEggs, { NestEgg } from '@/api/server/getNestEggs';
import { usePermissions } from '@/plugins/usePermissions';
import FlashMessageRender from '@/components/FlashMessageRender';
import { InteractiveHoverButton } from '@/components/ui/interactive-hover-button';
import ToggleSwitch from '@/components/ui/toggle-switch';
import updateStartupAutoRestart from '@/api/server/updateStartupAutoRestart';
import PageLoadingSkeleton from '@/components/elements/PageLoadingSkeleton';

const normalizeCommand = (value: string): string =>
    value
        .replace(/\\\r?\n/g, ' ')
        .replace(/\r?\n+/g, ' ')
        .replace(/\s{2,}/g, ' ')
        .trim();

const resolveCommandPreview = (
    command: string,
    variables: { envVariable: string; serverValue: string | null; defaultValue: string }[]
) => {
    const values = variables.reduce<Record<string, string>>((carry, item) => {
        carry[item.envVariable] = item.serverValue ?? item.defaultValue ?? '';
        return carry;
    }, {});

    const placeholders = new Set<string>();

    const resolved = command.replace(/{{\s*([A-Z0-9_]+)\s*}}/gi, (_full, envName: string) => {
        const key = envName.toUpperCase();
        placeholders.add(key);

        return values[key] ?? `{{${key}}}`;
    });

    return {
        placeholders: Array.from(placeholders.values()),
        preview: normalizeCommand(resolved),
    };
};

const StartupContainer = () => {
    const [loading, setLoading] = useState(false);
    const [commandSaving, setCommandSaving] = useState(false);
    const [recoveryOpen, setRecoveryOpen] = useState(false);
    const [changeEggOpen, setChangeEggOpen] = useState(false);
    const [startupDraft, setStartupDraft] = useState('');
    const [availableNests, setAvailableNests] = useState<NestOption[]>([]);
    const [availableEggsByNest, setAvailableEggsByNest] = useState<Record<number, NestEgg[]>>({});
    const [selectedNestId, setSelectedNestId] = useState(0);
    const [selectedEggId, setSelectedEggId] = useState(0);
    const [selectedDockerImage, setSelectedDockerImage] = useState('');
    const [setupLoading, setSetupLoading] = useState(false);
    const [nestOptionsLoading, setNestOptionsLoading] = useState(false);
    const [nestEggsLoading, setNestEggsLoading] = useState(false);
    const [autoRestartOnCrash, setAutoRestartOnCrash] = useState(false);
    const [autoRestartSaving, setAutoRestartSaving] = useState(false);
    const { addFlash, clearFlashes, clearAndAddHttpError } = useFlash();
    const [canEditStartup] = usePermissions(['startup.update']);
    const lastSavedCommand = useRef('');
    const savingCommand = useRef<string | null>(null);
    const dockerSavingValue = useRef<string | null>(null);
    const hasLoadedNestOptions = useRef(false);

    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const variables = ServerContext.useStoreState(
        ({ server }) => ({
            variables: server.data!.variables,
            invocation: server.data!.invocation,
            dockerImage: server.data!.dockerImage,
        }),
        isEqual
    );

    const initialStartupData = useMemo<StartupResponse>(
        () => ({
            invocation: variables.invocation,
            currentDockerImage: variables.dockerImage,
            rawStartupCommand: variables.invocation,
            defaultStartupCommand: variables.invocation,
            nest: { id: 0, name: '' },
            currentEgg: { id: 0, name: '' },
            eggs: [],
            variables: variables.variables,
            dockerImages: variables.dockerImage ? { [variables.dockerImage]: variables.dockerImage } : {},
            autoRestartOnCrash: false,
            autoRestartDefaults: {
                enabled: false,
                delaySeconds: 30,
                maxAttempts: 3,
                windowMinutes: 15,
            },
        }),
        [variables.invocation, variables.dockerImage, variables.variables]
    );

    const { data, error, isValidating, mutate } = getServerStartup(uuid, initialStartupData);

    const currentNestId = data?.nest?.id || 0;
    const currentEggId = data?.currentEgg?.id || 0;
    const availableEggs = useMemo(
        () => availableEggsByNest[selectedNestId] || [],
        [availableEggsByNest, selectedNestId]
    );

    const setServerFromState = ServerContext.useStoreActions((actions) => actions.server.setServerFromState);
    const commandPreview = useMemo(
        () => resolveCommandPreview(startupDraft || data?.rawStartupCommand || '', data?.variables || []),
        [startupDraft, data?.rawStartupCommand, data?.variables]
    );
    const selectedEgg = useMemo(
        () => availableEggs.find((egg) => egg.id === selectedEggId) || null,
        [availableEggs, selectedEggId]
    );
    const dockerOptionsForSelectedEgg = useMemo(
        () =>
            selectedEgg?.dockerImages?.length
                ? selectedEgg.dockerImages
                : Object.entries(data?.dockerImages || {}).map(([label, value]) => ({ label, value })),
        [selectedEgg, data?.dockerImages]
    );
    const hasEggChange = !!data && selectedEggId !== currentEggId;
    const hasNestChange = !!data && selectedNestId !== currentNestId;
    const hasProfileChange = !!data && (hasNestChange || hasEggChange);
    const hasDockerOnlyChange =
        !!data &&
        !hasProfileChange &&
        selectedDockerImage !==
            (data.currentDockerImage || variables.dockerImage || dockerOptionsForSelectedEgg[0]?.value || '');

    useEffect(() => {
        // Since we're passing in initial data this will not trigger on mount automatically. We
        // want to always fetch fresh information from the API however when we're loading the startup
        // information.
        mutate();
    }, []);

    useDeepCompareEffect(() => {
        if (!data) return;

        setServerFromState((s) => ({
            ...s,
            invocation: data.invocation,
            variables: data.variables,
        }));
    }, [data]);

    useEffect(() => {
        if (!data) return;
        setAvailableNests((state) => {
            if (!currentNestId) {
                return state;
            }

            if (state.some((nest) => nest.id === currentNestId)) {
                return state;
            }

            return [
                ...state,
                {
                    id: currentNestId,
                    name: data.nest?.name || 'Unknown Nest',
                    description: null,
                },
            ];
        });
        setAvailableEggsByNest((state) => ({
            ...state,
            [currentNestId]: data.eggs || [],
        }));
        lastSavedCommand.current = data.rawStartupCommand || '';
        setStartupDraft(data.rawStartupCommand || '');
        setSelectedNestId(currentNestId);
        setSelectedEggId(currentEggId);
        setSelectedDockerImage(
            data.currentDockerImage || variables.dockerImage || Object.values(data.dockerImages || {})[0] || ''
        );
        setAutoRestartOnCrash(!!data.autoRestartOnCrash);
    }, [
        data?.rawStartupCommand,
        data?.currentDockerImage,
        currentEggId,
        currentNestId,
        variables.dockerImage,
        data?.eggs,
        data?.autoRestartOnCrash,
    ]);

    useEffect(() => {
        if (!data || hasLoadedNestOptions.current) return;

        hasLoadedNestOptions.current = true;
        setNestOptionsLoading(true);

        getAvailableNests(uuid)
            .then((nests) => {
                setAvailableNests(
                    nests.some((nest) => nest.id === currentNestId) || !currentNestId
                        ? nests
                        : [
                              ...nests,
                              {
                                  id: currentNestId,
                                  name: data.nest?.name || 'Unknown Nest',
                                  description: null,
                              },
                          ]
                );
            })
            .catch((error) => {
                console.error(error);
                clearAndAddHttpError({ key: 'startup:image', error });
                hasLoadedNestOptions.current = false;
            })
            .then(() => setNestOptionsLoading(false));
    }, [uuid, data?.nest?.name, currentNestId]);

    useEffect(() => {
        if (!selectedNestId || availableEggsByNest[selectedNestId]) {
            return;
        }

        setNestEggsLoading(true);
        clearFlashes('startup:image');

        getNestEggs(uuid, selectedNestId)
            .then((eggs) => {
                setAvailableEggsByNest((state) => ({
                    ...state,
                    [selectedNestId]: eggs,
                }));

                if (eggs.length < 1) {
                    addFlash({
                        key: 'startup:image',
                        type: 'error',
                        title: 'Error',
                        message: 'This game type does not have any server types that can be selected.',
                    });
                }
            })
            .catch((error) => {
                console.error(error);
                setAvailableEggsByNest((state) => ({
                    ...state,
                    [selectedNestId]: [],
                }));
                clearAndAddHttpError({ key: 'startup:image', error });
            })
            .then(() => setNestEggsLoading(false));
    }, [uuid, selectedNestId, availableEggsByNest]);

    useEffect(() => {
        if (!selectedNestId) {
            return;
        }

        if (availableEggs.length < 1) {
            if (selectedEggId !== 0) {
                setSelectedEggId(0);
            }

            if (selectedDockerImage !== '') {
                setSelectedDockerImage('');
            }

            return;
        }

        const nextEgg = availableEggs.find((egg) => egg.id === selectedEggId) || availableEggs[0];
        if (nextEgg.id !== selectedEggId) {
            setSelectedEggId(nextEgg.id);
        }

        const nextDockerImage =
            nextEgg.dockerImages.find((image) => image.value === selectedDockerImage)?.value ||
            nextEgg.dockerImages[0]?.value ||
            '';

        if (nextDockerImage !== selectedDockerImage) {
            setSelectedDockerImage(nextDockerImage);
        }
    }, [selectedNestId, availableEggs, selectedEggId, selectedDockerImage]);

    const applyStartupProfileChange = useCallback(() => {
        if (!data || !canEditStartup || !selectedNestId || !selectedEggId) return;

        setLoading(true);
        setSetupLoading(true);
        clearFlashes('startup:image');
        changeStartupEgg(uuid, selectedNestId, selectedEggId, selectedDockerImage)
            .then((response) => {
                setAvailableEggsByNest((state) => ({
                    ...state,
                    [response.nest.id]: response.eggs,
                }));
                mutate(
                    (current) =>
                        current
                            ? {
                                  ...current,
                                  invocation: response.invocation,
                                  currentDockerImage: response.currentDockerImage,
                                  rawStartupCommand: response.rawStartupCommand,
                                  defaultStartupCommand: response.defaultStartupCommand,
                                  dockerImages: response.dockerImages,
                                  nest: response.nest,
                                  currentEgg: response.currentEgg,
                                  eggs: response.eggs,
                                  variables: response.variables,
                                  autoRestartOnCrash: response.autoRestartOnCrash,
                                  autoRestartDefaults: response.autoRestartDefaults,
                              }
                            : current,
                    false
                );
                setServerFromState((state) => ({
                    ...state,
                    invocation: response.invocation,
                    dockerImage: response.currentDockerImage || selectedDockerImage,
                    variables: response.variables,
                }));
                setSelectedNestId(response.nest.id);
                setSelectedEggId(response.currentEgg.id);
                setSelectedDockerImage(response.currentDockerImage || selectedDockerImage);
                setStartupDraft(response.rawStartupCommand || '');
                setAutoRestartOnCrash(response.autoRestartOnCrash);
            })
            .catch((error) => {
                console.error(error);
                clearAndAddHttpError({ key: 'startup:image', error });
            })
            .then(() => {
                dockerSavingValue.current = null;
                setLoading(false);
                setSetupLoading(false);
                setChangeEggOpen(false);
            });
    }, [uuid, selectedNestId, selectedEggId, selectedDockerImage, data, canEditStartup]);

    const updateAutoRestart = (enabled: boolean) => {
        if (!canEditStartup || autoRestartSaving) return;

        const previous = autoRestartOnCrash;
        setAutoRestartOnCrash(enabled);
        setAutoRestartSaving(true);
        clearFlashes('startup:auto-restart');

        updateStartupAutoRestart(uuid, enabled)
            .then((response) => {
                setAutoRestartOnCrash(response.enabled);
                mutate(
                    (current) =>
                        current
                            ? {
                                  ...current,
                                  autoRestartOnCrash: response.enabled,
                                  autoRestartDefaults: response.defaults,
                              }
                            : current,
                    false
                );
            })
            .catch((error) => {
                console.error(error);
                setAutoRestartOnCrash(previous);
                clearAndAddHttpError({ key: 'startup:auto-restart', error });
            })
            .then(() => setAutoRestartSaving(false));
    };

    useEffect(() => {
        if (!canEditStartup || !data || !hasDockerOnlyChange) return;
        if (loading || setupLoading) return;
        if (dockerSavingValue.current === selectedDockerImage) return;

        const timer = window.setTimeout(() => {
            dockerSavingValue.current = selectedDockerImage;
            applyStartupProfileChange();
        }, 500);

        return () => window.clearTimeout(timer);
    }, [
        selectedDockerImage,
        hasDockerOnlyChange,
        canEditStartup,
        data,
        loading,
        setupLoading,
        applyStartupProfileChange,
    ]);

    const saveStartupCommand = (command: string) => {
        if (!canEditStartup || !data) return;

        const fixedCommand = normalizeCommand(command);
        if (!fixedCommand) return;

        savingCommand.current = fixedCommand;
        setCommandSaving(true);
        clearFlashes('startup:command');
        updateStartupCommand(uuid, fixedCommand)
            .then((response) => {
                const normalizedSaved = normalizeCommand(response.rawStartupCommand || fixedCommand);
                lastSavedCommand.current = normalizedSaved;
                setStartupDraft(normalizedSaved);
                mutate(
                    (current) =>
                        current
                            ? {
                                  ...current,
                                  invocation: response.invocation,
                                  rawStartupCommand: normalizedSaved,
                                  defaultStartupCommand: response.defaultStartupCommand,
                              }
                            : current,
                    false
                );
                setServerFromState((state) => ({ ...state, invocation: response.invocation }));
            })
            .catch((error) => {
                console.error(error);
                clearAndAddHttpError({ key: 'startup:command', error });
            })
            .then(() => {
                savingCommand.current = null;
                setCommandSaving(false);
            });
    };

    const recoverStartupCommand = () => {
        setCommandSaving(true);
        clearFlashes('startup:command');
        resetStartupCommand(uuid)
            .then((response) => {
                mutate(
                    (current) =>
                        current
                            ? {
                                  ...current,
                                  invocation: response.invocation,
                                  rawStartupCommand: response.rawStartupCommand,
                                  defaultStartupCommand: response.defaultStartupCommand,
                              }
                            : current,
                    false
                );
                setStartupDraft(response.rawStartupCommand);
                lastSavedCommand.current = response.rawStartupCommand;
                setServerFromState((state) => ({ ...state, invocation: response.invocation }));
            })
            .catch((error) => {
                console.error(error);
                clearAndAddHttpError({ key: 'startup:command', error });
            })
            .then(() => {
                setCommandSaving(false);
                setRecoveryOpen(false);
            });
    };

    useEffect(() => {
        if (!canEditStartup || !data) return;

        const current = startupDraft.trim();
        const persisted = (data.rawStartupCommand || '').trim();
        if (!current || current === persisted || current === lastSavedCommand.current) {
            return;
        }

        if (savingCommand.current === startupDraft) {
            return;
        }

        const timer = window.setTimeout(() => {
            saveStartupCommand(startupDraft);
        }, 700);

        return () => window.clearTimeout(timer);
    }, [startupDraft, canEditStartup, data?.rawStartupCommand, data?.variables]);

    return !data ? (
        <ServerContentBlock title={'Startup Settings'} className={'content-container-full px-4 py-4 xl:px-6'}>
            {!error || (error && isValidating) ? (
                <PageLoadingSkeleton showChrome={false} showSpinner={false} rows={8} className='min-h-[360px]' />
            ) : (
                <div css={tw`flex min-h-[360px] items-center justify-center`}>
                    <ServerError title={'Oops!'} message={httpErrorToHuman(error)} onRetry={() => mutate()} />
                </div>
            )}
        </ServerContentBlock>
    ) : (
        <ServerContentBlock
            title={'Startup Settings'}
            showFlashKey={'startup:image'}
            className={'content-container-full px-4 py-4 xl:px-6'}
        >
            <div
                css={tw`flex min-h-0 flex-1 flex-col overflow-y-auto overflow-x-hidden pr-1 pb-4`}
                style={{ WebkitOverflowScrolling: 'touch' }}
            >
                <div css={tw`xl:flex`}>
                    <div css={tw`flex-1`}>
                        <TitledGreyBox
                            title={
                                <div css={tw`flex items-center justify-between gap-3`}>
                                    <p
                                        css={tw`text-sm font-bold uppercase tracking-wide text-[color:var(--foreground)]`}
                                    >
                                        Startup Command
                                    </p>
                                    {canEditStartup && (
                                        <InteractiveHoverButton
                                            type={'button'}
                                            text={'Recovery'}
                                            variant={'warning'}
                                            className={'h-8 min-w-0 px-3 text-xs'}
                                            onClick={() => setRecoveryOpen(true)}
                                        />
                                    )}
                                </div>
                            }
                            css={tw`flex-1`}
                        >
                            <Dialog.Confirm
                                open={recoveryOpen}
                                title={'Recover startup command'}
                                confirm={'Recover'}
                                onClose={() => setRecoveryOpen(false)}
                                onConfirmed={recoverStartupCommand}
                            >
                                Reset startup command to the default service command?
                            </Dialog.Confirm>

                            <FlashMessageRender byKey={'startup:command'} css={tw`mb-3`} />

                            <div css={tw`space-y-6 px-1 py-2`}>
                                <Textarea
                                    value={startupDraft}
                                    onChange={(event: React.ChangeEvent<HTMLTextAreaElement>) =>
                                        setStartupDraft(event.currentTarget.value)
                                    }
                                    rows={4}
                                    readOnly={!canEditStartup}
                                    css={tw`font-mono`}
                                    style={{ backgroundColor: '#FEF9E1' }}
                                />
                                {canEditStartup && commandSaving && (
                                    <div
                                        css={tw`flex items-center justify-end gap-2 text-xs text-[color:var(--text-subtle)]`}
                                    >
                                        <Spinner size={Spinner.Size.SMALL} />
                                        <span>Auto-saving...</span>
                                    </div>
                                )}
                                <div className={'sc-card-inner p-3'}>
                                    <p className={'mb-2 text-xs uppercase tracking-wide sc-text'}>
                                        Preview
                                    </p>
                                    <p className={'font-mono text-sm break-words sc-text'}>
                                        {commandPreview.preview}
                                    </p>
                                    {commandPreview.placeholders.length > 0 && (
                                        <p css={tw`mt-2 text-xs text-[color:var(--text-subtle)]`}>
                                            Placeholders: {commandPreview.placeholders.join(', ')}
                                        </p>
                                    )}
                                </div>
                                <div css={tw`pt-5`} style={{ borderTop: '2px solid #2D4A3E' }}>
                                    <div css={tw`mb-3 flex items-center justify-between gap-3`}>
                                        <div css={tw`min-w-0`}>
                                            <p
                                                css={tw`text-sm font-bold uppercase tracking-wide text-[color:var(--foreground)]`}
                                            >
                                                Auto Restart
                                            </p>
                                            <p css={tw`mt-1 text-xs text-[color:var(--text-subtle)]`}>
                                                Restart automatically after an unexpected crash. Manual Stop or Kill
                                                from the panel will not trigger auto restart.
                                            </p>
                                        </div>
                                        {autoRestartSaving && <Spinner size={Spinner.Size.SMALL} />}
                                    </div>
                                    <FlashMessageRender byKey={'startup:auto-restart'} css={tw`mb-3`} />
                                    <div css={tw`space-y-4`}>
                                        <div css={tw`flex items-center justify-between gap-4`}>
                                            <div css={tw`min-w-0`}>
                                                <p css={tw`text-sm font-semibold text-[color:var(--foreground)]`}>
                                                    Auto Restart When Crashed
                                                </p>
                                                <p css={tw`mt-1 text-xs text-[color:var(--text-subtle)]`}>
                                                    Turn this on to automatically restart the server after an unexpected
                                                    crash.
                                                </p>
                                            </div>
                                            <ToggleSwitch
                                                id={`startup-auto-restart-${uuid}`}
                                                checked={autoRestartOnCrash}
                                                disabled={!canEditStartup || autoRestartSaving}
                                                onChange={updateAutoRestart}
                                                label={autoRestartOnCrash ? 'On' : 'Off'}
                                            />
                                        </div>
                                        <div className={'sc-card-inner p-3 text-xs sc-muted'}>
                                            Crash recovery policy: wait {data.autoRestartDefaults.delaySeconds} seconds
                                            before restart, allow up to {data.autoRestartDefaults.maxAttempts} tries
                                            every {data.autoRestartDefaults.windowMinutes} minutes.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </TitledGreyBox>
                    </div>
                    <TitledGreyBox
                        title={
                            <div css={tw`flex items-center justify-between gap-3`}>
                                <p css={tw`text-sm font-bold uppercase tracking-wide text-[color:var(--foreground)]`}>
                                    Server Setup
                                </p>
                                {canEditStartup && (
                                    <InteractiveHoverButton
                                        type={'button'}
                                        text={'Change'}
                                        variant={'success'}
                                        className={'h-8 min-w-0 px-3 text-xs'}
                                        disabled={
                                            !hasProfileChange || loading || setupLoading || availableEggs.length < 1
                                        }
                                        onClick={() => setChangeEggOpen(true)}
                                    />
                                )}
                            </div>
                        }
                        css={tw`flex-1 xl:flex-none xl:w-1/3 mt-8 xl:mt-0 xl:ml-10`}
                    >
                        <Dialog.Confirm
                            open={changeEggOpen}
                            title={'Confirm startup profile change'}
                            confirm={hasNestChange ? 'Delete Data and Change Game Type' : 'Change Setup'}
                            onClose={() => setChangeEggOpen(false)}
                            onConfirmed={applyStartupProfileChange}
                        >
                            {hasNestChange ? (
                                <div css={tw`space-y-3 text-sm leading-6`}>
                                    <p>
                                        Changing Game Type will permanently delete all existing server data before the
                                        new setup is applied.
                                    </p>
                                    <ul css={tw`list-disc space-y-1 pl-5`}>
                                        <li>All files inside the container will be deleted.</li>
                                        <li>All backups for this server will be deleted.</li>
                                        <li>Recycle Bin contents will be deleted.</li>
                                        <li>
                                            Current startup variables will be replaced with the new Game Type values.
                                        </li>
                                    </ul>
                                    <p>
                                        Continue only if you are sure. After changing, review the new variables and run
                                        reinstall from Settings.
                                    </p>
                                </div>
                            ) : (
                                <div css={tw`space-y-3 text-sm leading-6`}>
                                    <p>
                                        Changing Server Type will replace current startup variables with the new Server
                                        Type variables.
                                    </p>
                                    <p>
                                        Old variable values will be removed. After changing, fill the new variables and
                                        then run reinstall from Settings.
                                    </p>
                                </div>
                            )}
                        </Dialog.Confirm>

                        <InputSpinner visible={loading || setupLoading || nestOptionsLoading || nestEggsLoading}>
                            <div css={tw`space-y-3`}>
                                <div>
                                    <p css={tw`mb-2 text-xs uppercase tracking-wide text-[color:var(--foreground)]`}>
                                        Game Type
                                    </p>
                                    <Select
                                        key={`startup-nest-${selectedNestId}-${availableNests.length}`}
                                        disabled={!canEditStartup || nestOptionsLoading || availableNests.length < 1}
                                        onChange={(value) => setSelectedNestId(Number(value))}
                                        value={String(selectedNestId || currentNestId)}
                                        defaultValue={String(selectedNestId || currentNestId)}
                                        title={'Choose Game Type'}
                                        openDirection={'down'}
                                        data={availableNests.map((nest) => ({
                                            id: String(nest.id),
                                            label: nest.name,
                                            value: String(nest.id),
                                            description: nest.description || undefined,
                                        }))}
                                    />
                                </div>
                                <div>
                                    <p css={tw`mb-2 text-xs uppercase tracking-wide text-[color:var(--foreground)]`}>
                                        Server Type
                                    </p>
                                    <Select
                                        key={`startup-egg-${selectedNestId}-${selectedEggId}-${availableEggs.length}`}
                                        disabled={!canEditStartup || nestEggsLoading || availableEggs.length < 1}
                                        onChange={(value) => {
                                            const nextEggId = Number(value);
                                            setSelectedEggId(nextEggId);
                                            const egg = availableEggs.find((item) => item.id === nextEggId);
                                            const nextDocker = egg?.dockerImages?.[0]?.value || '';
                                            if (nextDocker) {
                                                setSelectedDockerImage(nextDocker);
                                            }
                                        }}
                                        value={String(selectedEggId)}
                                        defaultValue={String(selectedEggId)}
                                        title={'Choose Server Type'}
                                        openDirection={'down'}
                                        data={availableEggs.map((egg) => ({
                                            id: String(egg.id),
                                            label: egg.name,
                                            value: String(egg.id),
                                            description: egg.description || undefined,
                                        }))}
                                    />
                                </div>
                                <div>
                                    <p css={tw`mb-2 text-xs uppercase tracking-wide text-[color:var(--foreground)]`}>
                                        Docker Image
                                    </p>
                                    <Select
                                        key={`startup-docker-${selectedNestId}-${selectedEggId}-${selectedDockerImage}`}
                                        disabled={
                                            !canEditStartup ||
                                            nestEggsLoading ||
                                            dockerOptionsForSelectedEgg.length < 1 ||
                                            !selectedEggId
                                        }
                                        onChange={(value) => setSelectedDockerImage(value)}
                                        value={selectedDockerImage}
                                        defaultValue={selectedDockerImage}
                                        title={'Choose Docker Image'}
                                        openDirection={'down'}
                                        data={dockerOptionsForSelectedEgg.map((image) => ({
                                            id: image.value,
                                            label: image.label || image.value,
                                            value: image.value,
                                            description: image.value,
                                        }))}
                                    />
                                </div>
                                {selectedNestId > 0 && availableEggs.length < 1 && !nestEggsLoading && (
                                    <p css={tw`text-xs text-[#fca5a5]`}>
                                        This game type does not have any server types available right now.
                                    </p>
                                )}
                            </div>
                        </InputSpinner>
                        <p css={tw`mt-3 text-xs text-[color:var(--text-subtle)]`}>
                            Select Game Type first, then choose Server Type. Docker Image updates automatically when
                            needed. Changing Game Type deletes backups, recycle bin contents, and all container files
                            before applying the new setup. Changing Server Type in the same Game Type only resets
                            startup variables, so review the new values and run reinstall from Settings afterwards.
                        </p>
                    </TitledGreyBox>
                </div>
                <h3 css={tw`mt-8 mb-2 text-2xl text-[color:var(--foreground)]`}>Variables</h3>
                <div css={tw`grid gap-8 md:grid-cols-2`}>
                    {data.variables.map((variable) => (
                        <VariableBox key={variable.envVariable} variable={variable} />
                    ))}
                </div>
            </div>
        </ServerContentBlock>
    );
};

export default StartupContainer;
