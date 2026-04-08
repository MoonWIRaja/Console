export type ThemeMode = 'light' | 'dark';

export interface ThemePreset {
    id: string;
    label: string;
    light: Record<string, string>;
    dark: Record<string, string>;
}

export const DEFAULT_THEME_ID = 'burhan-core';
export const DEFAULT_THEME_MODE: ThemeMode = 'dark';
export const THEME_STORAGE_KEY = 'panel.theme.id';
export const THEME_MODE_STORAGE_KEY = 'panel.theme.mode';
export const THEME_MODE_SYNC_EVENT = 'panel.theme.mode.sync';

export const THEME_PRESETS: ThemePreset[] = [
    {
        id: DEFAULT_THEME_ID,
        label: 'Burhan Core',
        light: {
            background: 'rgb(245, 231, 198)',
            foreground: 'rgb(34, 34, 34)',
            card: 'rgb(245, 231, 198)',
            'card-foreground': 'rgb(34, 34, 34)',
            popover: 'rgb(245, 231, 198)',
            'popover-foreground': 'rgb(34, 34, 34)',
            primary: 'rgb(34, 34, 34)',
            'primary-foreground': 'rgb(245, 231, 198)',
            secondary: 'rgba(34, 34, 34, 0.06)',
            'secondary-foreground': 'rgb(34, 34, 34)',
            muted: 'rgba(34, 34, 34, 0.05)',
            'muted-foreground': 'rgba(34, 34, 34, 0.74)',
            accent: 'rgba(34, 34, 34, 0.09)',
            'accent-foreground': 'rgb(34, 34, 34)',
            destructive: 'rgb(34, 34, 34)',
            'destructive-foreground': 'rgb(245, 231, 198)',
            border: 'rgba(34, 34, 34, 0.18)',
            input: 'rgba(34, 34, 34, 0.08)',
            ring: 'rgb(34, 34, 34)',
            'font-sans': '"Geist Mono", ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace',
            'font-mono': '"Geist Mono", ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace',
            radius: '0.75rem',
        },
        dark: {
            background: 'rgb(34, 34, 34)',
            foreground: 'rgb(245, 231, 198)',
            card: 'rgb(34, 34, 34)',
            'card-foreground': 'rgb(245, 231, 198)',
            popover: 'rgb(34, 34, 34)',
            'popover-foreground': 'rgb(245, 231, 198)',
            primary: 'rgb(245, 231, 198)',
            'primary-foreground': 'rgb(34, 34, 34)',
            secondary: 'rgba(245, 231, 198, 0.07)',
            'secondary-foreground': 'rgb(245, 231, 198)',
            muted: 'rgba(245, 231, 198, 0.05)',
            'muted-foreground': 'rgba(245, 231, 198, 0.74)',
            accent: 'rgba(245, 231, 198, 0.1)',
            'accent-foreground': 'rgb(245, 231, 198)',
            destructive: 'rgb(245, 231, 198)',
            'destructive-foreground': 'rgb(34, 34, 34)',
            border: 'rgba(245, 231, 198, 0.18)',
            input: 'rgba(245, 231, 198, 0.08)',
            ring: 'rgb(245, 231, 198)',
            'font-sans': '"Geist Mono", ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace',
            'font-mono': '"Geist Mono", ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace',
            radius: '0.75rem',
        },
    },
];

const allVariableNames = Array.from(
    new Set(
        THEME_PRESETS.flatMap((preset) => [...Object.keys(preset.light), ...Object.keys(preset.dark)]).map(
            (key) => `--${key}`
        )
    )
);

export const normalizeThemeMode = (): ThemeMode => 'dark';

export const getThemePreset = (themeId?: string): ThemePreset =>
    THEME_PRESETS.find((theme) => theme.id === themeId) || THEME_PRESETS[0];

export const getStoredThemeId = (): string => {
    if (typeof window === 'undefined') {
        return DEFAULT_THEME_ID;
    }

    return window.localStorage.getItem(THEME_STORAGE_KEY) || DEFAULT_THEME_ID;
};

export const getStoredThemeMode = (): ThemeMode => {
    return DEFAULT_THEME_MODE;
};

const extractRgbChannels = (value?: string): string | null => {
    if (!value) return null;

    const normalized = value.trim();

    if (normalized.startsWith('#')) {
        const hex = normalized.slice(1);
        if (hex.length === 3) {
            const r = Number.parseInt(hex[0] + hex[0], 16);
            const g = Number.parseInt(hex[1] + hex[1], 16);
            const b = Number.parseInt(hex[2] + hex[2], 16);
            if (!Number.isNaN(r) && !Number.isNaN(g) && !Number.isNaN(b)) return `${r}, ${g}, ${b}`;
        }

        if (hex.length === 6) {
            const r = Number.parseInt(hex.slice(0, 2), 16);
            const g = Number.parseInt(hex.slice(2, 4), 16);
            const b = Number.parseInt(hex.slice(4, 6), 16);
            if (!Number.isNaN(r) && !Number.isNaN(g) && !Number.isNaN(b)) return `${r}, ${g}, ${b}`;
        }

        return null;
    }

    const rgbMatch = normalized.match(/^rgba?\(([^)]+)\)$/i);
    if (!rgbMatch) return null;

    const channels = rgbMatch[1]
        .split(',')
        .slice(0, 3)
        .map((part) => Number.parseFloat(part.trim().replace('%', '')))
        .filter((channel) => !Number.isNaN(channel));

    if (channels.length !== 3) return null;
    return channels.map((channel) => Math.max(0, Math.min(255, Math.round(channel)))).join(', ');
};

export const applyThemePreset = (themeId: string, _mode: ThemeMode) => {
    if (typeof document === 'undefined') return;

    const preset = getThemePreset(themeId);
    const root = document.documentElement;
    const tokens = preset.dark;

    root.dataset.theme = preset.id;
    root.classList.add('dark');

    for (const cssVariable of allVariableNames) {
        root.style.removeProperty(cssVariable);
    }

    Object.entries(tokens).forEach(([key, value]) => {
        root.style.setProperty(`--${key}`, value);
    });

    const primaryRgb = extractRgbChannels(tokens.primary) || '245, 231, 198';
    const backgroundRgb = extractRgbChannels(tokens.background) || '34, 34, 34';
    const cardRgb = extractRgbChannels(tokens.card) || backgroundRgb;

    root.style.setProperty('--primary-rgb', primaryRgb);
    root.style.setProperty('--background-rgb', backgroundRgb);
    root.style.setProperty('--card-rgb', cardRgb);
    root.style.setProperty('--primary-glow-soft', `rgba(${primaryRgb}, 0.18)`);
    root.style.setProperty('--primary-glow-medium', `rgba(${primaryRgb}, 0.28)`);
    root.style.setProperty('--primary-glow-strong', `rgba(${primaryRgb}, 0.4)`);
};

export const setThemeMode = (_mode: ThemeMode) => {
    const normalizedMode: ThemeMode = 'dark';

    if (typeof window !== 'undefined') {
        window.localStorage.setItem(THEME_STORAGE_KEY, DEFAULT_THEME_ID);
        window.localStorage.setItem(THEME_MODE_STORAGE_KEY, normalizedMode);
        window.dispatchEvent(new CustomEvent<ThemeMode>(THEME_MODE_SYNC_EVENT, { detail: normalizedMode }));
    }

    applyThemePreset(DEFAULT_THEME_ID, normalizedMode);
    return normalizedMode;
};

export const syncStoredTheme = () => {
    setThemeMode(DEFAULT_THEME_MODE);
    return DEFAULT_THEME_MODE;
};

export const toggleThemeMode = (): ThemeMode => setThemeMode(DEFAULT_THEME_MODE);
