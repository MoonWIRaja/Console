import useSWR, { ConfigInterface } from 'swr';
import { AxiosError } from 'axios';
import http from '@/api/http';
import { useUserSWRKey } from '@/plugins/useSWRKey';

export interface DiscordCommunityStatus {
    enabled: boolean;
    configured: boolean;
    oauthReady: boolean;
    available: boolean;
    linked: boolean;
    requiresRelink: boolean;
    inviteUrl: string | null;
    member: boolean;
    roleAssigned: boolean;
}

const useDiscordCommunityStatus = (config?: ConfigInterface<DiscordCommunityStatus, AxiosError>) => {
    const key = useUserSWRKey(['account', 'discord-community']);

    return useSWR<DiscordCommunityStatus>(
        key,
        async () => {
            const { data } = await http.get('/api/client/account/discord/community');
            const item = data.data || {};

            return {
                enabled: !!item.enabled,
                configured: !!item.configured,
                oauthReady: !!item.oauth_ready,
                available: !!item.available,
                linked: !!item.linked,
                requiresRelink: !!item.requires_relink,
                inviteUrl: item.invite_url ?? null,
                member: !!item.member,
                roleAssigned: !!item.role_assigned,
            };
        },
        { revalidateOnMount: true, revalidateOnFocus: false, ...(config || {}) }
    );
};

export interface DiscordCommunityJoinResponse {
    success: boolean;
    error: string | null;
    redirectUrl: string | null;
    member: boolean;
    roleAssigned: boolean;
}

const joinDiscordCommunity = async (): Promise<DiscordCommunityJoinResponse> => {
    const { data } = await http.post('/api/client/account/discord/community/join');
    const item = data.data || {};

    return {
        success: item.success !== false,
        error: item.error ?? null,
        redirectUrl: item.redirect_url ?? null,
        member: !!item.member,
        roleAssigned: !!item.role_assigned,
    };
};

export { useDiscordCommunityStatus, joinDiscordCommunity };
