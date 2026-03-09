import { useStoreState } from 'easy-peasy';
import { ApplicationStore } from '@/state';

const DEFAULT_SITE_NAME = 'Pterodactyl';
const DEFAULT_SITE_LOGO = '/assets/svgs/pterodactyl.svg';

export default () => {
    const settings = useStoreState((state: ApplicationStore) => state.settings.data);

    return {
        name: settings?.name || DEFAULT_SITE_NAME,
        logo: settings?.logo || DEFAULT_SITE_LOGO,
    };
};
