import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import TitledGreyBox from '@/components/elements/TitledGreyBox';
import tw from 'twin.macro';
import VariableBox from '@/components/server/startup/VariableBox';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import getServerStartup from '@/api/swr/getServerStartup';
import Spinner from '@/components/elements/Spinner';
import { ServerError } from '@/components/elements/ScreenBlock';
import { httpErrorToHuman } from '@/api/http';
import { ServerContext } from '@/state/server';
import { useDeepCompareEffect } from '@/plugins/useDeepCompareEffect';
import isEqual from 'react-fast-compare';
import Input from '@/components/elements/Input';
import InputSpinner from '@/components/elements/InputSpinner';
import useFlash from '@/plugins/useFlash';
import Select from '@/components/ui/select';
import { Textarea } from '@/components/elements/Input';
import { Dialog } from '@/components/elements/dialog';
import updateStartupCommand from '@/api/server/updateStartupCommand';
import resetStartupCommand from '@/api/server/resetStartupCommand';
import changeStartupEgg from '@/api/server/changeStartupEgg';
import { usePermissions } from '@/plugins/usePermissions';
import FlashMessageRender from '@/components/FlashMessageRender';
import { InteractiveHoverButton } from '@/components/ui/interactive-hover-button';

const normalizeCommand = (value: string): string =>
    value.replace(/\\\r?\n/g, ' ').replace(/\r?\n+/g, ' ').replace(/\s{2,}/g, ' ').trim();

const resolveCommandPreview = (command: string, variables: { envVariable: string; serverValue: string | null; defaultValue: string }[]) => {
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
    const [selectedEggId, setSelectedEggId] = useState(0);
    const [selectedDockerImage, setSelectedDockerImage] = useState('');
    const { clearFlashes, clearAndAddHttpError } = useFlash();
    const [canEditStartup] = usePermissions(['startup.update']);
    const lastSavedCommand = useRef('');
    const savingCommand = useRef<string | null>(null);
    const dockerSavingValue = useRef<string | null>(null);

    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const variables = ServerContext.useStoreState(
        ({ server }) => ({
            variables: server.data!.variables,
            invocation: server.data!.invocation,
            dockerImage: server.data!.dockerImage,
        }),
        isEqual
    );

    const { data, error, isValidating, mutate } = getServerStartup(uuid, {
        ...variables,
        dockerImages: { [variables.dockerImage]: variables.dockerImage },
    });

    const availableEggs = data?.eggs || [];
    const currentEggId = data?.currentEgg?.id || 0;
    const currentNestName = data?.nest?.name || '';

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
    const hasDockerOnlyChange =
        !!data &&
        !hasEggChange &&
        selectedDockerImage !== (data.currentDockerImage || variables.dockerImage || dockerOptionsForSelectedEgg[0]?.value || '');

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
        lastSavedCommand.current = data.rawStartupCommand || '';
        setStartupDraft(data.rawStartupCommand || '');
        setSelectedEggId(currentEggId);
        setSelectedDockerImage(data.currentDockerImage || variables.dockerImage || Object.values(data.dockerImages || {})[0] || '');
    }, [data?.rawStartupCommand, data?.currentDockerImage, currentEggId, variables.dockerImage]);

    const applyStartupProfileChange = useCallback(() => {
        if (!data || !canEditStartup) return;

        setLoading(true);
        clearFlashes('startup:image');
        changeStartupEgg(uuid, selectedEggId, selectedDockerImage)
            .then((response) => {
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
                setSelectedDockerImage(response.currentDockerImage || selectedDockerImage);
                setStartupDraft(response.rawStartupCommand || '');
            })
            .catch((error) => {
                console.error(error);
                clearAndAddHttpError({ key: 'startup:image', error });
            })
            .then(() => {
                dockerSavingValue.current = null;
                setLoading(false);
                setChangeEggOpen(false);
            });
    }, [uuid, selectedEggId, selectedDockerImage, data, canEditStartup]);

    useEffect(() => {
        if (!canEditStartup || !data || !hasDockerOnlyChange) return;
        if (loading) return;
        if (dockerSavingValue.current === selectedDockerImage) return;

        const timer = window.setTimeout(() => {
            dockerSavingValue.current = selectedDockerImage;
            applyStartupProfileChange();
        }, 500);

        return () => window.clearTimeout(timer);
    }, [selectedDockerImage, hasDockerOnlyChange, canEditStartup, data, loading, applyStartupProfileChange]);

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
        !error || (error && isValidating) ? (
            <Spinner centered size={Spinner.Size.LARGE} />
        ) : (
            <ServerError title={'Oops!'} message={httpErrorToHuman(error)} onRetry={() => mutate()} />
        )
    ) : (
        <ServerContentBlock
            title={'Startup Settings'}
            showFlashKey={'startup:image'}
            className={'content-container-full px-4 xl:px-6'}
        >
            <div css={tw`md:flex`}>
                <TitledGreyBox
                    title={
                        <div css={tw`flex items-center justify-between gap-3`}>
                            <p css={tw`text-sm font-bold uppercase tracking-wide text-neutral-100`}>Startup Command</p>
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

                    <div css={tw`space-y-3 px-1 py-2`}>
                        <Textarea
                            value={startupDraft}
                            onChange={(event) => setStartupDraft(event.currentTarget.value)}
                            rows={4}
                            readOnly={!canEditStartup}
                            css={tw`font-mono`}
                            style={{ backgroundColor: 'var(--card)' }}
                        />
                        {canEditStartup && commandSaving && (
                            <div css={tw`flex items-center justify-end gap-2 text-xs text-neutral-400`}>
                                <Spinner size={Spinner.Size.SMALL} />
                                <span>Auto-saving...</span>
                            </div>
                        )}
                        <div css={tw`rounded-lg border border-[color:var(--border)] bg-[color:var(--background)] p-3`}>
                            <p css={tw`mb-2 text-xs uppercase tracking-wide text-neutral-300`}>Preview</p>
                            <p css={tw`font-mono text-sm break-words text-[#f8f6ef]`}>{commandPreview.preview}</p>
                            {commandPreview.placeholders.length > 0 && (
                                <p css={tw`mt-2 text-xs text-neutral-400`}>
                                    Placeholders: {commandPreview.placeholders.join(', ')}
                                </p>
                            )}
                        </div>
                    </div>
                </TitledGreyBox>
                <TitledGreyBox
                    title={
                        <div css={tw`flex items-center justify-between gap-3`}>
                            <p css={tw`text-sm font-bold uppercase tracking-wide text-neutral-100`}>Server Setup</p>
                            {canEditStartup && (
                                <InteractiveHoverButton
                                    type={'button'}
                                    text={'Change'}
                                    variant={'success'}
                                    className={'h-8 min-w-0 px-3 text-xs'}
                                    disabled={!hasEggChange || loading}
                                    onClick={() => setChangeEggOpen(true)}
                                />
                            )}
                        </div>
                    }
                    css={tw`flex-1 lg:flex-none lg:w-1/3 mt-8 md:mt-0 md:ml-10`}
                >
                    <Dialog.Confirm
                        open={changeEggOpen}
                        title={'Confirm startup profile change'}
                        confirm={'Change Egg'}
                        onClose={() => setChangeEggOpen(false)}
                        onConfirmed={applyStartupProfileChange}
                    >
                        Changing Egg will replace current startup variables with the new Egg variables. Old variable
                        values will be removed. After changing Egg, you need to fill the new variables and then run
                        reinstall from Settings.
                    </Dialog.Confirm>

                    <InputSpinner visible={loading}>
                        <div css={tw`space-y-3`}>
                            <div>
                                <p css={tw`mb-2 text-xs uppercase tracking-wide text-neutral-300`}>Game Type</p>
                                <Input readOnly value={currentNestName} />
                            </div>
                            <div>
                                <p css={tw`mb-2 text-xs uppercase tracking-wide text-neutral-300`}>Server Type</p>
                                <Select
                                    disabled={!canEditStartup}
                                    onChange={(value) => {
                                        const nextEggId = Number(value);
                                        setSelectedEggId(nextEggId);
                                        const egg = availableEggs.find((item) => item.id === nextEggId);
                                        const nextDocker = egg?.dockerImages?.[0]?.value || '';
                                        if (nextDocker) {
                                            setSelectedDockerImage(nextDocker);
                                        }
                                    }}
                                    defaultValue={String(selectedEggId)}
                                    title={'Choose Server Type'}
                                    data={availableEggs.map((egg) => ({
                                        id: String(egg.id),
                                        label: egg.name,
                                        value: String(egg.id),
                                        description: egg.description || undefined,
                                    }))}
                                />
                            </div>
                            <div>
                                <p css={tw`mb-2 text-xs uppercase tracking-wide text-neutral-300`}>Docker Image</p>
                                <Select
                                    disabled={!canEditStartup || dockerOptionsForSelectedEgg.length < 1}
                                    onChange={(value) => setSelectedDockerImage(value)}
                                    defaultValue={selectedDockerImage}
                                    title={'Choose Docker Image'}
                                    data={dockerOptionsForSelectedEgg.map((image) => ({
                                        id: image.value,
                                        label: image.label || image.value,
                                        value: image.value,
                                        description: image.value,
                                    }))}
                                />
                            </div>
                        </div>
                    </InputSpinner>
                    <p css={tw`mt-3 text-xs text-neutral-300`}>
                        Game Type is managed by administrator. You can change Server Type and Docker Image here. If
                        Server Type is changed, update new variables and run reinstall from Settings.
                    </p>
                </TitledGreyBox>
            </div>
            <h3 css={tw`mt-8 mb-2 text-2xl`}>Variables</h3>
            <div css={tw`grid gap-8 md:grid-cols-2`}>
                {data.variables.map((variable) => (
                    <VariableBox key={variable.envVariable} variable={variable} />
                ))}
            </div>
        </ServerContentBlock>
    );
};

export default StartupContainer;
