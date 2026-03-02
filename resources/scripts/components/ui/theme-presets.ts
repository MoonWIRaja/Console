export type ThemeMode = 'light' | 'dark';

export interface ThemePreset {
    id: string;
    label: string;
    light: Record<string, string>;
    dark: Record<string, string>;
}

export const THEME_PRESETS: ThemePreset[] = [
    {
        id: 'cyberpunk',
        label: 'Cyberpunk',
        light: {
            background: 'rgb(0, 0, 0)',
            foreground: 'rgb(0, 255, 65)',
            card: 'rgb(5, 5, 5)',
            'card-foreground': 'rgb(0, 255, 65)',
            popover: 'rgb(0, 0, 0)',
            'popover-foreground': 'rgb(0, 255, 65)',
            primary: 'rgb(0, 255, 65)',
            'primary-foreground': 'rgb(0, 0, 0)',
            secondary: 'rgb(0, 59, 0)',
            'secondary-foreground': 'rgb(0, 255, 65)',
            muted: 'rgb(0, 26, 0)',
            'muted-foreground': 'rgb(0, 143, 17)',
            accent: 'rgb(0, 255, 65)',
            'accent-foreground': 'rgb(0, 0, 0)',
            destructive: 'rgb(255, 0, 0)',
            'destructive-foreground': 'rgb(255, 255, 255)',
            border: 'rgb(0, 59, 0)',
            input: 'rgb(0, 0, 0)',
            ring: 'rgb(0, 255, 65)',
            'font-sans': '"VT323", "Courier New", monospace',
            'font-mono': '"VT323", monospace',
            radius: '0rem',
        },
        dark: {
            background: 'rgb(0, 0, 0)',
            foreground: 'rgb(0, 255, 65)',
            card: 'rgb(5, 5, 5)',
            'card-foreground': 'rgb(0, 255, 65)',
            popover: 'rgb(0, 0, 0)',
            'popover-foreground': 'rgb(0, 255, 65)',
            primary: 'rgb(0, 255, 65)',
            'primary-foreground': 'rgb(0, 0, 0)',
            secondary: 'rgb(0, 59, 0)',
            'secondary-foreground': 'rgb(0, 255, 65)',
            muted: 'rgb(0, 26, 0)',
            'muted-foreground': 'rgb(0, 143, 17)',
            accent: 'rgb(0, 255, 65)',
            'accent-foreground': 'rgb(0, 0, 0)',
            destructive: 'rgb(255, 0, 0)',
            'destructive-foreground': 'rgb(255, 255, 255)',
            border: 'rgb(0, 59, 0)',
            input: 'rgb(0, 0, 0)',
            ring: 'rgb(0, 255, 65)',
            'font-sans': '"VT323", "Courier New", monospace',
            'font-mono': '"VT323", monospace',
            radius: '0rem',
        },
    },
    {
        id: 'earthy',
        label: 'Earthy',
        light: {
            background: 'rgb(250, 249, 245)',
            foreground: 'rgb(61, 57, 41)',
            card: 'rgb(245, 244, 239)',
            'card-foreground': 'rgb(20, 20, 19)',
            popover: 'rgb(255, 255, 255)',
            'popover-foreground': 'rgb(40, 38, 27)',
            primary: 'rgb(201, 100, 66)',
            'primary-foreground': 'rgb(255, 255, 255)',
            secondary: 'rgb(233, 230, 220)',
            'secondary-foreground': 'rgb(83, 81, 70)',
            muted: 'rgb(237, 233, 222)',
            'muted-foreground': 'rgb(110, 109, 104)',
            accent: 'rgb(233, 230, 220)',
            'accent-foreground': 'rgb(40, 38, 27)',
            destructive: 'rgb(20, 20, 19)',
            'destructive-foreground': 'rgb(255, 255, 255)',
            border: 'rgb(218, 217, 212)',
            input: 'rgb(180, 178, 167)',
            ring: 'rgb(201, 100, 66)',
            'font-sans': 'Outfit, sans-serif',
            'font-mono': '"Geist Mono", ui-monospace, monospace',
            radius: '1rem',
        },
        dark: {
            background: 'rgb(38, 38, 36)',
            foreground: 'rgb(241, 241, 239)',
            card: 'rgb(44, 44, 43)',
            'card-foreground': 'rgb(250, 249, 245)',
            popover: 'rgb(48, 48, 46)',
            'popover-foreground': 'rgb(229, 229, 226)',
            primary: 'rgb(217, 119, 87)',
            'primary-foreground': 'rgb(20, 20, 19)',
            secondary: 'rgb(250, 249, 245)',
            'secondary-foreground': 'rgb(48, 48, 46)',
            muted: 'rgb(27, 27, 25)',
            'muted-foreground': 'rgb(183, 181, 169)',
            accent: 'rgb(26, 25, 21)',
            'accent-foreground': 'rgb(245, 244, 238)',
            destructive: 'rgb(239, 68, 68)',
            'destructive-foreground': 'rgb(255, 255, 255)',
            border: 'rgb(62, 62, 56)',
            input: 'rgb(82, 81, 74)',
            ring: 'rgb(217, 119, 87)',
            'font-sans': 'Outfit, sans-serif',
            'font-mono': '"Geist Mono", ui-monospace, monospace',
            radius: '1rem',
        },
    },
    {
        id: 'amber-mono',
        label: 'Amber Mono',
        light: {
            background: 'rgb(250, 250, 250)',
            foreground: 'rgb(23, 23, 23)',
            card: 'rgb(245, 245, 245)',
            'card-foreground': 'rgb(23, 23, 23)',
            popover: 'rgb(245, 245, 245)',
            'popover-foreground': 'rgb(23, 23, 23)',
            primary: 'rgb(225, 113, 0)',
            'primary-foreground': 'rgb(250, 250, 249)',
            secondary: 'rgb(250, 250, 249)',
            'secondary-foreground': 'rgb(41, 37, 36)',
            muted: 'rgb(231, 229, 228)',
            'muted-foreground': 'rgb(28, 25, 23)',
            accent: 'rgb(250, 250, 249)',
            'accent-foreground': 'rgb(68, 64, 59)',
            destructive: 'rgb(231, 0, 11)',
            'destructive-foreground': 'rgb(250, 250, 249)',
            border: 'rgb(214, 211, 209)',
            input: 'rgb(166, 160, 155)',
            ring: 'rgb(225, 113, 0)',
            'font-sans': '"Geist Mono", monospace',
            'font-mono': '"Geist Mono", monospace',
            radius: '0rem',
        },
        dark: {
            background: 'rgb(10, 10, 10)',
            foreground: 'rgb(245, 245, 245)',
            card: 'rgb(23, 23, 23)',
            'card-foreground': 'rgb(245, 245, 244)',
            popover: 'rgb(23, 23, 23)',
            'popover-foreground': 'rgb(245, 245, 244)',
            primary: 'rgb(225, 113, 0)',
            'primary-foreground': 'rgb(245, 245, 244)',
            secondary: 'rgb(41, 37, 36)',
            'secondary-foreground': 'rgb(245, 245, 244)',
            muted: 'rgb(28, 25, 23)',
            'muted-foreground': 'rgb(166, 160, 155)',
            accent: 'rgb(68, 64, 59)',
            'accent-foreground': 'rgb(245, 245, 244)',
            destructive: 'rgb(193, 0, 7)',
            'destructive-foreground': 'rgb(245, 245, 244)',
            border: 'rgb(68, 64, 59)',
            input: 'rgb(41, 37, 36)',
            ring: 'rgb(151, 60, 0)',
            'font-sans': '"Geist Mono", monospace',
            'font-mono': '"Geist Mono", monospace',
            radius: '0rem',
        },
    },
    {
        id: 'limes',
        label: 'Limes',
        light: {
            background: 'rgb(252, 252, 252)',
            foreground: 'rgb(0, 0, 0)',
            card: 'rgb(252, 252, 252)',
            'card-foreground': 'rgb(0, 0, 0)',
            popover: 'rgb(252, 252, 252)',
            'popover-foreground': 'rgb(0, 0, 0)',
            primary: 'rgb(0, 0, 0)',
            'primary-foreground': 'rgb(255, 255, 255)',
            secondary: 'rgb(235, 235, 235)',
            'secondary-foreground': 'rgb(0, 0, 0)',
            muted: 'rgb(245, 245, 245)',
            'muted-foreground': 'rgb(82, 82, 82)',
            accent: 'rgb(235, 235, 235)',
            'accent-foreground': 'rgb(0, 0, 0)',
            destructive: 'rgb(229, 75, 79)',
            'destructive-foreground': 'rgb(255, 255, 255)',
            border: 'rgb(228, 228, 228)',
            input: 'rgb(235, 235, 235)',
            ring: 'rgb(0, 0, 0)',
            'font-sans': 'Geist, sans-serif',
            'font-mono': '"Geist Mono", monospace',
            radius: '0.5rem',
        },
        dark: {
            background: 'rgb(0, 0, 0)',
            foreground: 'rgb(255, 255, 255)',
            card: 'rgb(0, 0, 0)',
            'card-foreground': 'rgb(255, 255, 255)',
            popover: 'rgb(0, 0, 0)',
            'popover-foreground': 'rgb(255, 255, 255)',
            primary: 'rgb(94, 165, 0)',
            'primary-foreground': 'rgb(0, 0, 0)',
            secondary: 'rgb(34, 34, 34)',
            'secondary-foreground': 'rgb(255, 255, 255)',
            muted: 'rgb(29, 29, 29)',
            'muted-foreground': 'rgb(164, 164, 164)',
            accent: 'rgb(51, 51, 51)',
            'accent-foreground': 'rgb(245, 73, 0)',
            destructive: 'rgb(255, 91, 91)',
            'destructive-foreground': 'rgb(0, 0, 0)',
            border: 'rgb(36, 36, 36)',
            input: 'rgb(51, 51, 51)',
            ring: 'rgb(164, 164, 164)',
            'font-sans': 'Geist, sans-serif',
            'font-mono': '"Geist Mono", monospace',
            radius: '0.5rem',
        },
    },
    {
        id: 'domia',
        label: 'Domia',
        light: {
            background: 'rgb(255, 255, 255)',
            foreground: 'rgb(15, 20, 25)',
            card: 'rgb(247, 248, 248)',
            'card-foreground': 'rgb(15, 20, 25)',
            popover: 'rgb(255, 255, 255)',
            'popover-foreground': 'rgb(15, 20, 25)',
            primary: 'rgb(186, 0, 189)',
            'primary-foreground': 'rgb(255, 255, 255)',
            secondary: 'rgb(15, 20, 25)',
            'secondary-foreground': 'rgb(255, 255, 255)',
            muted: 'rgb(229, 229, 230)',
            'muted-foreground': 'rgb(15, 20, 25)',
            accent: 'rgb(227, 236, 246)',
            'accent-foreground': 'rgb(186, 0, 189)',
            destructive: 'rgb(244, 33, 46)',
            'destructive-foreground': 'rgb(255, 255, 255)',
            border: 'rgb(225, 234, 239)',
            input: 'rgb(247, 249, 250)',
            ring: 'rgb(186, 0, 189)',
            'font-sans': '"Open Sans", sans-serif',
            'font-mono': 'Menlo, monospace',
            radius: '1.3rem',
        },
        dark: {
            background: 'rgb(0, 0, 0)',
            foreground: 'rgb(231, 233, 234)',
            card: 'rgb(23, 24, 28)',
            'card-foreground': 'rgb(217, 217, 217)',
            popover: 'rgb(0, 0, 0)',
            'popover-foreground': 'rgb(231, 233, 234)',
            primary: 'rgb(186, 0, 189)',
            'primary-foreground': 'rgb(255, 255, 255)',
            secondary: 'rgb(240, 243, 244)',
            'secondary-foreground': 'rgb(15, 20, 25)',
            muted: 'rgb(24, 24, 24)',
            'muted-foreground': 'rgb(114, 118, 122)',
            accent: 'rgb(6, 22, 34)',
            'accent-foreground': 'rgb(186, 0, 189)',
            destructive: 'rgb(244, 33, 46)',
            'destructive-foreground': 'rgb(255, 255, 255)',
            border: 'rgb(36, 38, 40)',
            input: 'rgb(43, 21, 60)',
            ring: 'rgb(186, 0, 189)',
            'font-sans': '"Open Sans", sans-serif',
            'font-mono': 'Menlo, monospace',
            radius: '1.3rem',
        },
    },
    {
        id: 'flat-pink',
        label: 'Flat Pink',
        light: {
            background: 'rgb(252, 252, 252)',
            foreground: 'rgb(0, 0, 0)',
            card: 'rgb(252, 252, 252)',
            'card-foreground': 'rgb(0, 0, 0)',
            popover: 'rgb(252, 252, 252)',
            'popover-foreground': 'rgb(0, 0, 0)',
            primary: 'rgb(0, 0, 0)',
            'primary-foreground': 'rgb(255, 255, 255)',
            secondary: 'rgb(235, 235, 235)',
            'secondary-foreground': 'rgb(0, 0, 0)',
            muted: 'rgb(245, 245, 245)',
            'muted-foreground': 'rgb(82, 82, 82)',
            accent: 'rgb(235, 235, 235)',
            'accent-foreground': 'rgb(0, 0, 0)',
            destructive: 'rgb(75, 79, 229)',
            'destructive-foreground': 'rgb(255, 255, 255)',
            border: 'rgb(228, 228, 228)',
            input: 'rgb(235, 235, 235)',
            ring: 'rgb(0, 0, 0)',
            'font-sans': 'Geist, sans-serif',
            'font-mono': '"Geist Mono", monospace',
            radius: '0.5rem',
        },
        dark: {
            background: 'rgb(0, 0, 0)',
            foreground: 'rgb(255, 255, 255)',
            card: 'rgb(0, 0, 0)',
            'card-foreground': 'rgb(255, 255, 255)',
            popover: 'rgb(0, 0, 0)',
            'popover-foreground': 'rgb(255, 255, 255)',
            primary: 'rgb(124, 0, 81)',
            'primary-foreground': 'rgb(0, 0, 0)',
            secondary: 'rgb(34, 34, 34)',
            'secondary-foreground': 'rgb(255, 255, 255)',
            muted: 'rgb(29, 29, 29)',
            'muted-foreground': 'rgb(164, 164, 164)',
            accent: 'rgb(51, 51, 51)',
            'accent-foreground': 'rgb(82, 0, 206)',
            destructive: 'rgb(91, 91, 255)',
            'destructive-foreground': 'rgb(0, 0, 0)',
            border: 'rgb(36, 36, 36)',
            input: 'rgb(51, 51, 51)',
            ring: 'rgb(164, 164, 164)',
            'font-sans': 'Geist, sans-serif',
            'font-mono': '"Geist Mono", monospace',
            radius: '0.5rem',
        },
    },
    {
        id: 'terminal-muted',
        label: 'Terminal Muted',
        light: {
            background: 'rgb(240, 244, 242)',
            foreground: 'rgb(31, 46, 36)',
            card: 'rgb(232, 238, 234)',
            'card-foreground': 'rgb(20, 31, 24)',
            popover: 'rgb(232, 238, 234)',
            'popover-foreground': 'rgb(20, 31, 24)',
            primary: 'rgb(54, 125, 80)',
            'primary-foreground': 'rgb(249, 250, 250)',
            secondary: 'rgb(161, 112, 69)',
            'secondary-foreground': 'rgb(250, 250, 249)',
            muted: 'rgb(221, 227, 224)',
            'muted-foreground': 'rgb(76, 103, 86)',
            accent: 'rgb(213, 221, 216)',
            'accent-foreground': 'rgb(31, 46, 36)',
            destructive: 'rgb(161, 69, 69)',
            'destructive-foreground': 'rgb(250, 250, 250)',
            border: 'rgb(199, 209, 203)',
            input: 'rgb(227, 232, 229)',
            ring: 'rgb(54, 125, 80)',
            'font-sans': "ui-monospace, 'JetBrains Mono', 'Fira Code', 'Menlo', monospace",
            'font-mono': "ui-monospace, 'JetBrains Mono', 'Fira Code', 'Menlo', monospace",
            radius: '0rem',
        },
        dark: {
            background: 'rgb(9, 12, 10)',
            foreground: 'rgb(169, 214, 185)',
            card: 'rgb(13, 18, 15)',
            'card-foreground': 'rgb(203, 230, 213)',
            popover: 'rgb(13, 18, 15)',
            'popover-foreground': 'rgb(203, 230, 213)',
            primary: 'rgb(69, 161, 103)',
            'primary-foreground': 'rgb(11, 15, 12)',
            secondary: 'rgb(172, 111, 57)',
            'secondary-foreground': 'rgb(15, 13, 11)',
            muted: 'rgb(28, 34, 30)',
            'muted-foreground': 'rgb(117, 163, 134)',
            accent: 'rgb(34, 42, 37)',
            'accent-foreground': 'rgb(203, 230, 213)',
            destructive: 'rgb(172, 57, 57)',
            'destructive-foreground': 'rgb(250, 250, 250)',
            border: 'rgb(33, 44, 37)',
            input: 'rgb(22, 29, 24)',
            ring: 'rgb(69, 161, 103)',
            'font-sans': "ui-monospace, 'JetBrains Mono', 'Fira Code', 'Menlo', monospace",
            'font-mono': "ui-monospace, 'JetBrains Mono', 'Fira Code', 'Menlo', monospace",
            radius: '0rem',
        },
    },
    {
        id: 'light-green',
        label: 'Light Green',
        light: {
            background: 'rgb(251, 252, 248)',
            foreground: 'rgb(15, 23, 42)',
            card: 'rgb(255, 255, 255)',
            'card-foreground': 'rgb(15, 23, 42)',
            popover: 'rgb(255, 255, 255)',
            'popover-foreground': 'rgb(15, 23, 42)',
            primary: 'rgb(175, 243, 62)',
            'primary-foreground': 'rgb(0, 0, 0)',
            secondary: 'rgb(51, 65, 85)',
            'secondary-foreground': 'rgb(248, 250, 252)',
            muted: 'rgb(241, 245, 249)',
            'muted-foreground': 'rgb(100, 116, 139)',
            accent: 'rgb(240, 253, 244)',
            'accent-foreground': 'rgb(22, 101, 52)',
            destructive: 'rgb(239, 68, 68)',
            'destructive-foreground': 'rgb(255, 255, 255)',
            border: 'rgb(226, 232, 240)',
            input: 'rgb(226, 232, 240)',
            ring: 'rgb(175, 243, 62)',
            'font-sans': 'Inter, system-ui, sans-serif',
            'font-mono': '"JetBrains Mono", monospace',
            radius: '1rem',
        },
        dark: {
            background: 'rgb(2, 6, 23)',
            foreground: 'rgb(248, 250, 252)',
            card: 'rgb(15, 23, 42)',
            'card-foreground': 'rgb(248, 250, 252)',
            popover: 'rgb(15, 23, 42)',
            'popover-foreground': 'rgb(248, 250, 252)',
            primary: 'rgb(175, 243, 62)',
            'primary-foreground': 'rgb(0, 0, 0)',
            secondary: 'rgb(30, 41, 59)',
            'secondary-foreground': 'rgb(248, 250, 252)',
            muted: 'rgb(30, 41, 59)',
            'muted-foreground': 'rgb(148, 163, 184)',
            accent: 'rgb(20, 83, 45)',
            'accent-foreground': 'rgb(175, 243, 62)',
            destructive: 'rgb(153, 27, 27)',
            'destructive-foreground': 'rgb(255, 255, 255)',
            border: 'rgb(30, 41, 59)',
            input: 'rgb(30, 41, 59)',
            ring: 'rgb(175, 243, 62)',
            'font-sans': 'Inter, system-ui, sans-serif',
            'font-mono': '"JetBrains Mono", monospace',
            radius: '1rem',
        },
    },
];

export const DEFAULT_THEME_ID = 'cyberpunk';
export const DEFAULT_THEME_MODE: ThemeMode = 'dark';

const allVariableNames = Array.from(
    new Set(
        THEME_PRESETS.flatMap((preset) => [...Object.keys(preset.light), ...Object.keys(preset.dark)]).map(
            (key) => `--${key}`
        )
    )
);

export const getThemePreset = (themeId: string): ThemePreset =>
    THEME_PRESETS.find((theme) => theme.id === themeId) || THEME_PRESETS[0];

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
        .filter((value) => !Number.isNaN(value));

    if (channels.length !== 3) return null;
    return channels.map((value) => Math.max(0, Math.min(255, Math.round(value)))).join(', ');
};

export const applyThemePreset = (themeId: string, mode: ThemeMode) => {
    if (typeof document === 'undefined') return;

    const preset = getThemePreset(themeId);
    const root = document.documentElement;
    const tokens = mode === 'dark' ? preset.dark : preset.light;

    root.dataset.theme = preset.id;
    root.classList.toggle('dark', mode === 'dark');

    for (const cssVariable of allVariableNames) {
        root.style.removeProperty(cssVariable);
    }

    Object.entries(tokens).forEach(([key, value]) => {
        root.style.setProperty(`--${key}`, value);
    });

    const primaryRgb = extractRgbChannels(tokens.primary) || '163, 255, 18';
    const backgroundRgb = extractRgbChannels(tokens.background) || '12, 12, 12';
    const cardRgb = extractRgbChannels(tokens.card) || backgroundRgb;

    root.style.setProperty('--primary-rgb', primaryRgb);
    root.style.setProperty('--background-rgb', backgroundRgb);
    root.style.setProperty('--card-rgb', cardRgb);
    root.style.setProperty('--primary-glow-soft', `rgba(${primaryRgb}, 0.22)`);
    root.style.setProperty('--primary-glow-medium', `rgba(${primaryRgb}, 0.35)`);
    root.style.setProperty('--primary-glow-strong', `rgba(${primaryRgb}, 0.55)`);
};
