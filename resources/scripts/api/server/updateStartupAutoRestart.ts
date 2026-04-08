import http from '@/api/http';

export interface StartupAutoRestartDefaults {
    enabled: boolean;
    delaySeconds: number;
    maxAttempts: number;
    windowMinutes: number;
}

export interface StartupAutoRestartResponse {
    enabled: boolean;
    defaults: StartupAutoRestartDefaults;
}

export default async (uuid: string, enabled: boolean): Promise<StartupAutoRestartResponse> => {
    const { data } = await http.put(`/api/client/servers/${uuid}/startup/auto-restart`, { enabled });

    return {
        enabled: !!data.enabled,
        defaults: {
            enabled: !!data.defaults?.enabled,
            delaySeconds: Number(data.defaults?.delay_seconds || 30),
            maxAttempts: Number(data.defaults?.max_attempts || 3),
            windowMinutes: Number(data.defaults?.window_minutes || 15),
        },
    };
};
