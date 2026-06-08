import http from '@/api/http';

interface Values {
    name: string;
    cpu: number;
    memory: number;
    disk: number;
    swap: number;
}

export default async (uuid: string, splitId: number, values: Values): Promise<void> => {
    await http.patch(`/api/client/servers/${uuid}/split/${splitId}`, values);
};
