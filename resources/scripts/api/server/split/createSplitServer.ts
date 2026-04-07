import http from '@/api/http';

interface Values {
    name: string;
    cpu: number;
    memory: number;
    disk: number;
    swap: number;
    copy_subusers: boolean;
}

export default async (uuid: string, values: Values): Promise<void> => {
    await http.post(`/api/client/servers/${uuid}/split`, values);
};
