import useSWR, { ConfigInterface } from 'swr';
import http, { FractalResponseList } from '@/api/http';
import { rawDataToServerEggVariable } from '@/api/transformers';
import { ServerEggVariable } from '@/api/server/types';

export interface StartupEggOption {
    id: number;
    name: string;
    description: string | null;
    dockerImages: { label: string; value: string }[];
}

export interface StartupResponse {
    invocation: string;
    currentDockerImage: string;
    rawStartupCommand: string;
    defaultStartupCommand: string;
    nest: {
        id: number;
        name: string;
    };
    currentEgg: {
        id: number;
        name: string;
    };
    eggs: StartupEggOption[];
    variables: ServerEggVariable[];
    dockerImages: Record<string, string>;
}

export default (uuid: string, initialData?: StartupResponse | null, config?: ConfigInterface<StartupResponse>) =>
    useSWR(
        [uuid, '/startup'],
        async (): Promise<StartupResponse> => {
            const { data } = await http.get(`/api/client/servers/${uuid}/startup`);

            const variables = ((data as FractalResponseList).data || []).map(rawDataToServerEggVariable);

            return {
                variables,
                invocation: data.meta.startup_command,
                currentDockerImage: data.meta.current_docker_image || '',
                rawStartupCommand: data.meta.raw_startup_command || '',
                defaultStartupCommand: data.meta.default_startup_command || '',
                nest: data.meta.nest || { id: 0, name: '' },
                currentEgg: data.meta.current_egg || { id: 0, name: '' },
                eggs: (data.meta.eggs || []).map((egg: any) => ({
                    id: Number(egg.id),
                    name: egg.name,
                    description: egg.description ?? null,
                    dockerImages: egg.docker_images || [],
                })),
                dockerImages: data.meta.docker_images || {},
            };
        },
        { initialData: initialData || undefined, errorRetryCount: 3, ...(config || {}) }
    );
