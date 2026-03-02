import http from '@/api/http';

export default async (): Promise<void> => {
    await http.delete('/api/client/account/avatar');
};
