import useSWR, { ConfigInterface } from 'swr';
import { AxiosError } from 'axios';
import http from '@/api/http';
import { useUserSWRKey } from '@/plugins/useSWRKey';

export interface LinkedOAuthAccount {
    displayName: string | null;
    email: string | null;
    avatar: string | null;
    linkedAt: Date | null;
    updatedAt: Date | null;
}

export interface OAuthProviderStatus {
    provider: 'google' | 'discord';
    label: string;
    enabled: boolean;
    configured: boolean;
    available: boolean;
    linked: boolean;
    linkUrl: string;
    account: LinkedOAuthAccount | null;
}

const useOAuthAccounts = (config?: ConfigInterface<OAuthProviderStatus[], AxiosError>) => {
    const key = useUserSWRKey(['account', 'oauth']);

    return useSWR<OAuthProviderStatus[]>(
        key,
        async () => {
            const { data } = await http.get('/api/client/account/oauth');

            return (data.data || []).map((item: any) => ({
                provider: item.provider,
                label: item.label,
                enabled: item.enabled,
                configured: item.configured,
                available: item.available,
                linked: item.linked,
                linkUrl: item.link_url,
                account: item.account
                    ? {
                          displayName: item.account.display_name ?? null,
                          email: item.account.email ?? null,
                          avatar: item.account.avatar ?? null,
                          linkedAt: item.account.linked_at ? new Date(item.account.linked_at) : null,
                          updatedAt: item.account.updated_at ? new Date(item.account.updated_at) : null,
                      }
                    : null,
            }));
        },
        { revalidateOnMount: true, revalidateOnFocus: false, ...(config || {}) }
    );
};

const unlinkOAuthAccount = async (provider: string): Promise<void> => {
    await http.delete(`/api/client/account/oauth/${provider}`);
};

export { useOAuthAccounts, unlinkOAuthAccount };
