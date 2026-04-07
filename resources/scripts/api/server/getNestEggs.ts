import http from '@/api/http';
import type { StartupEggOption } from '@/api/swr/getServerStartup';

export type NestEgg = StartupEggOption;

export default async (uuid: string, nestId: number): Promise<NestEgg[]> => {
    const { data } = await http.get(`/api/client/servers/${uuid}/startup/nests/${nestId}/eggs`);

    // Response is directly an array, not wrapped in data.data
    const eggArray = Array.isArray(data) ? data : data.data || [];

    return eggArray.map((item: any) => ({
        id: Number(item.id),
        name: item.name,
        description: item.description ?? null,
        dockerImages: item.docker_images || [],
    }));
};
