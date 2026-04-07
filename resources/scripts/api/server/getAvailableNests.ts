import http from '@/api/http';

export interface NestOption {
    id: number;
    name: string;
    description: string | null;
}

export default async (uuid: string): Promise<NestOption[]> => {
    const { data } = await http.get(`/api/client/servers/${uuid}/startup/nests`);

    // Response is directly an array, not wrapped in data.data
    const nestArray = Array.isArray(data) ? data : data.data || [];

    return nestArray.map((item: any) => ({
        id: Number(item.id),
        name: item.name,
        description: item.description ?? null,
    }));
};
