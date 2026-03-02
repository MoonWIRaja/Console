import http from '@/api/http';

interface StartupCommandResponse {
    invocation: string;
    rawStartupCommand: string;
    defaultStartupCommand: string;
}

export default async (uuid: string): Promise<StartupCommandResponse> => {
    const { data } = await http.post(`/api/client/servers/${uuid}/startup/command/reset`);

    return {
        invocation: data.meta.startup_command,
        rawStartupCommand: data.meta.raw_startup_command || '',
        defaultStartupCommand: data.meta.default_startup_command || '',
    };
};

