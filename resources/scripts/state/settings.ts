import { action, Action } from 'easy-peasy';

export interface SiteSettings {
    name: string;
    logo: string;
    locale: string;
    captcha: {
        enabled: boolean;
        provider: 'turnstile';
        siteKey: string;
    };
    oauth: {
        google: {
            label: string;
            enabled: boolean;
        };
        discord: {
            label: string;
            enabled: boolean;
        };
    };
}

export interface SettingsStore {
    data?: SiteSettings;
    setSettings: Action<SettingsStore, SiteSettings>;
}

const settings: SettingsStore = {
    data: undefined,

    setSettings: action((state, payload) => {
        state.data = payload;
    }),
};

export default settings;
