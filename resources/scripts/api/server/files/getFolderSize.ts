import http from '@/api/http';

/**
 * Returns the total disk usage in bytes for a given server directory.
 *
 * This calls the Pterodactyl panel backend which runs `du -sb` directly on
 * the host volume filesystem — a single, fast, accurate call that does NOT
 * trigger rate limits (unlike recursive loadDirectory walking).
 */
const getFolderSize = async (uuid: string, directory: string): Promise<number> => {
    try {
        const { data } = await http.get(`/api/client/servers/${uuid}/files/size`, {
            params: { directory },
        });
        return Number(data.size ?? 0);
    } catch {
        return 0;
    }
};

export default getFolderSize;
