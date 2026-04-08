import http from '@/api/http';

export const restoreServerBackup = async (uuid: string, backup: string, truncate?: boolean): Promise<void> => {
    await http.post(
        `/api/client/servers/${uuid}/backups/${backup}/restore`,
        {
            truncate,
        },
        {
            // Local restore can queue background work; allow slower API edge cases before surfacing timeout.
            timeout: 120000,
        }
    );
};
