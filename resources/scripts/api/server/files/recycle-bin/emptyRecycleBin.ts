import http from '@/api/http';

export default (uuid: string): Promise<void> => {
    return new Promise((resolve, reject) => {
        http.post(`/api/client/servers/${uuid}/files/recycle-bin/empty`)
            .then(() => resolve())
            .catch(reject);
    });
};
