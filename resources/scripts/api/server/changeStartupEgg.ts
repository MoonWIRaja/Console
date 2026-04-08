import http from '@/api/http';
import { rawDataToServerEggVariable } from '@/api/transformers';
import type { StartupEggOption, StartupResponse } from '@/api/swr/getServerStartup';

export type StartupProfileEgg = StartupEggOption;

export default async (uuid: string, nestId: number, eggId: number, dockerImage?: string): Promise<StartupResponse> => {
    const payload: Record<string, unknown> = { nest_id: nestId, egg_id: eggId };
    if (dockerImage) payload.docker_image = dockerImage;

    const { data } = await http.put(`/api/client/servers/${uuid}/startup/egg`, payload);

    return {
        invocation: data.meta.startup_command,
        currentDockerImage: data.meta.current_docker_image || '',
        rawStartupCommand: data.meta.raw_startup_command || '',
        defaultStartupCommand: data.meta.default_startup_command || '',
        dockerImages: data.meta.docker_images || {},
        nest: data.meta.nest || { id: 0, name: '' },
        currentEgg: data.meta.current_egg || { id: 0, name: '' },
        eggs: (data.meta.eggs || []).map((egg: any) => ({
            id: Number(egg.id),
            name: egg.name,
            description: egg.description ?? null,
            dockerImages: egg.docker_images || [],
        })),
        variables: ((data.data || []) as any[]).map(rawDataToServerEggVariable),
        autoRestartOnCrash: !!data.meta.auto_restart_on_crash,
        autoRestartDefaults: {
            enabled: !!data.meta.auto_restart_defaults?.enabled,
            delaySeconds: Number(data.meta.auto_restart_defaults?.delay_seconds || 30),
            maxAttempts: Number(data.meta.auto_restart_defaults?.max_attempts || 3),
            windowMinutes: Number(data.meta.auto_restart_defaults?.window_minutes || 15),
        },
    };
};
