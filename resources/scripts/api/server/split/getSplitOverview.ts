import { ServerContext } from '@/state/server';
import useSWR from 'swr';
import http from '@/api/http';
import { SplitOverview } from '@/api/server/split/types';

export default () => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);

    return useSWR<SplitOverview>(
        ['server:split', uuid],
        async () => {
            const { data } = await http.get(`/api/client/servers/${uuid}/split`);

            return data.data;
        },
        { revalidateOnFocus: false }
    );
};
