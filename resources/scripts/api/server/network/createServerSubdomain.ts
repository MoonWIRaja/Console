import http from '@/api/http';

export default async (uuid: string, recordId: number, hostname: string): Promise<void> => {
    await http.post(`/api/client/servers/${uuid}/network/subdomains`, {
        record_id: recordId,
        hostname,
    });
};
