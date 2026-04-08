import useSWR from 'swr';
import http from '@/api/http';
import { ServerContext } from '@/state/server';

export interface NetworkSubdomainTemplate {
    id: number;
    name: string;
    recordType: 'CNAME' | 'SRV';
    domain: {
        id: number;
        name: string;
    };
}

export interface NetworkSubdomain {
    id: number;
    hostnameLabel: string;
    fullDomain: string;
    recordType: 'CNAME' | 'SRV';
    resolvedTarget: string | null;
    createdAt: string | null;
    domain: {
        id: number;
        name: string;
    };
    record: {
        id: number;
        name: string;
        recordType: 'CNAME' | 'SRV';
    };
}

export interface NetworkSubdomainResponse {
    items: NetworkSubdomain[];
    templates: NetworkSubdomainTemplate[];
}

const rawDataToSubdomain = (data: any): NetworkSubdomain => ({
    id: data.id,
    hostnameLabel: data.hostname_label,
    fullDomain: data.full_domain,
    recordType: data.record_type,
    resolvedTarget: data.resolved_target,
    createdAt: data.created_at,
    domain: {
        id: data.domain.id,
        name: data.domain.name,
    },
    record: {
        id: data.record.id,
        name: data.record.name,
        recordType: data.record.record_type,
    },
});

const rawDataToTemplate = (data: any): NetworkSubdomainTemplate => ({
    id: data.id,
    name: data.name,
    recordType: data.record_type,
    domain: {
        id: data.domain.id,
        name: data.domain.name,
    },
});

export default (enabled = true) => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);

    return useSWR<NetworkSubdomainResponse>(
        enabled ? ['server:network:subdomains', uuid] : null,
        async () => {
            const { data } = await http.get(`/api/client/servers/${uuid}/network/subdomains`);

            return {
                items: (data.items || []).map(rawDataToSubdomain),
                templates: (data.templates || []).map(rawDataToTemplate),
            };
        },
        {
            revalidateOnFocus: false,
        }
    );
};
