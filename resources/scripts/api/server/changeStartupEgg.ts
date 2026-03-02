import http from '@/api/http';
import { ServerEggVariable } from '@/api/server/types';
import { rawDataToServerEggVariable } from '@/api/transformers';

interface StartupResponse {
    invocation: string;
    currentDockerImage: string;
    rawStartupCommand: string;
    defaultStartupCommand: string;
    dockerImages: Record<string, string>;
    nest: { id: number; name: string };
    currentEgg: { id: number; name: string };
    eggs: { id: number; name: string; description?: string | null; dockerImages: { label: string; value: string }[] }[];
    variables: ServerEggVariable[];
}

export default async (uuid: string, eggId: number, dockerImage?: string): Promise<StartupResponse> => {
    const payload: Record<string, unknown> = { egg_id: eggId };
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
            description: egg.description,
            dockerImages: egg.docker_images || [],
        })),
        variables: ((data.data || []) as any[]).map(rawDataToServerEggVariable),
    };
};
