import http from '@/api/http';

interface StartupCommandResponse {
    invocation: string;
    rawStartupCommand: string;
    defaultStartupCommand: string;
}

export default async (uuid: string, startup: string): Promise<StartupCommandResponse> => {
    const { data } = await http.put(`/api/client/servers/${uuid}/startup/command`, { startup });

    return {
        invocation: data.meta.startup_command,
        rawStartupCommand: data.meta.raw_startup_command || '',
        defaultStartupCommand: data.meta.default_startup_command || '',
    };
};

