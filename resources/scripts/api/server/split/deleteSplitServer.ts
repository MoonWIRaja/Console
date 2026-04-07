import http from '@/api/http';

export default async (uuid: string, splitId: number, confirm: string): Promise<void> => {
    await http.delete(`/api/client/servers/${uuid}/split/${splitId}`, {
        data: { confirm },
    });
};
