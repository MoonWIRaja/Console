import http from '@/api/http';

export default (uuid: string, directory: string, files: string[]): Promise<void> => {
    return new Promise((resolve, reject) => {
        http.post(
            `/api/client/servers/${uuid}/files/recycle-bin/move`,
            { root: directory, files },
            {
                // Moving many files/folders can take longer than the default 20s timeout.
                timeout: 180000,
                timeoutErrorMessage: 'Move operation timed out. Try again with fewer files per action.',
            }
        )
            .then(() => resolve())
            .catch(reject);
    });
};
