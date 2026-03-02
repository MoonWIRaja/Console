import http from '@/api/http';

export default (uuid: string, directory: string, files: string[]): Promise<void> => {
    return new Promise((resolve, reject) => {
        http.post(`/api/client/servers/${uuid}/files/recycle-bin/move`, { root: directory, files })
            .then(() => resolve())
            .catch(reject);
    });
};
