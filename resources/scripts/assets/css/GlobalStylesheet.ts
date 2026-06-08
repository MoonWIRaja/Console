import tw from 'twin.macro';
import { createGlobalStyle } from 'styled-components/macro';

export default createGlobalStyle`
    :root {
        --background: #16171A;
        --foreground: #F26430;
        --card: #3D4047;
        --card-foreground: #F26430;
        --popover: #3D4047;
        --popover-foreground: #F26430;
        --primary: #E09F3E;
        --primary-foreground: #16171A;
        --secondary: rgba(70, 74, 82, 0.40);
        --secondary-foreground: #F26430;
        --muted: rgba(70, 74, 82, 0.30);
        --muted-foreground: rgba(242, 100, 48, 0.55);
        --accent: rgba(70, 74, 82, 0.50);
        --accent-foreground: #F26430;
        --destructive: #C0392B;
        --destructive-foreground: #FFFFFF;
        --border: rgba(70, 74, 82, 0.40);
        --input: rgba(70, 74, 82, 0.60);
        --ring: #E09F3E;
        --primary-rgb: 224, 159, 62;
        --background-rgb: 22, 23, 26;
        --card-rgb: 61, 64, 71;
        --primary-glow-soft: rgba(224, 159, 62, 0.18);
        --primary-glow-medium: rgba(224, 159, 62, 0.28);
        --primary-glow-strong: rgba(224, 159, 62, 0.4);
        --surface-elevated: rgba(70, 74, 82, 0.80);
        --surface-elevated-rgb: 61, 64, 71;
        --surface-subtle: rgba(70, 74, 82, 0.60);
        --surface-subtle-strong: rgba(70, 74, 82, 0.70);
        --surface-border: rgba(70, 74, 82, 0.40);
        --text-subtle: rgba(242, 100, 48, 0.55);
        --font-sans: 'Geist Mono', ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;
        --font-mono: 'Geist Mono', ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;
        --radius: 0.75rem;
    }

    html,
    body,
    #app {
        width: 100%;
        height: 100%;
        min-height: 100%;
    }

    body {
        ${tw`font-sans`};
        margin: 0;
        background-color: var(--background);
        color: var(--foreground);
        font-family: var(--font-sans);
        letter-spacing: 0.005em;
        overflow-x: hidden;
    }

    h1, h2, h3, h4, h5, h6 {
        ${tw`font-medium tracking-normal font-header`};
    }

    p {
        ${tw`leading-snug font-sans`};
        color: var(--foreground);
    }

    form {
        ${tw`m-0`};
    }

    textarea, select, input, button, button:focus, button:focus-visible {
        ${tw`outline-none`};
    }

    input[type=number]::-webkit-outer-spin-button,
    input[type=number]::-webkit-inner-spin-button {
        -webkit-appearance: none !important;
        margin: 0;
    }

    input[type=number] {
        -moz-appearance: textfield !important;
    }

    /* Scroll Bar Style */
    ::-webkit-scrollbar {
        background: none;
        width: 16px;
        height: 16px;
    }

    ::-webkit-scrollbar-thumb {
        border: solid 0 rgb(0 0 0 / 0%);
        border-right-width: 4px;
        border-left-width: 4px;
        -webkit-border-radius: 9px 4px;
        -webkit-box-shadow: inset 0 0 0 1px rgba(var(--primary-rgb), 0.22),
            inset 0 0 0 4px rgba(var(--surface-elevated-rgb), 1);
    }

    ::-webkit-scrollbar-track-piece {
        margin: 4px 0;
    }

    ::-webkit-scrollbar-thumb:horizontal {
        border-right-width: 0;
        border-left-width: 0;
        border-top-width: 4px;
        border-bottom-width: 4px;
        -webkit-border-radius: 4px 9px;
    }

    ::-webkit-scrollbar-corner {
        background: transparent;
    }

    /*
     * Legacy neon utility compatibility:
     * Many older components still render static Tailwind classes such as
     * text-[#d9ff93] or bg-[#0C0C0C]. These selectors remap them to active
     * theme variables so dashboard/server pages respond to selected theme too.
     */
    [class~='text-[#d9ff93]'],
    [class~='text-[#a3ff12]'] {
        color: var(--primary) !important;
    }

    [class~='hover:text-[#d9ff93]']:hover,
    [class~='hover:text-[#a3ff12]']:hover {
        color: var(--primary) !important;
    }

    [class~='border-[#a3ff12]'],
    [class~='hover:border-[#a3ff12]']:hover,
    [class~='focus:border-[#a3ff12]']:focus {
        border-color: var(--primary) !important;
    }

    [class~='ring-[#a3ff12]'],
    [class~='focus:ring-[#a3ff12]']:focus {
        --tw-ring-color: rgba(var(--primary-rgb), 0.5) !important;
    }

    [class~='border-[#1f2a14]'],
    [class~='hover:border-[#1f2a14]']:hover {
        border-color: var(--border) !important;
    }

    [class~='bg-[#0C0C0C]'],
    [class~='bg-[#050505]'],
    [class~='hover:bg-[#0C0C0C]']:hover,
    [class~='hover:bg-[#050505]']:hover {
        background-color: var(--card) !important;
    }

    [class~='bg-[#12220b]'],
    [class~='bg-[#17310d]'],
    [class~='hover:bg-[#12220b]']:hover,
    [class~='hover:bg-[#17310d]']:hover {
        background-color: rgba(var(--primary-rgb), 0.14) !important;
    }

    /* Catch arbitrary-value classnames that are escaped/combined by tooling. */
    [class*='text-[#d9ff93'],
    [class*='text-[#a3ff12'],
    [class*='hover:text-[#d9ff93']:hover,
    [class*='hover:text-[#a3ff12']:hover {
        color: var(--primary) !important;
    }

    [class*='border-[#a3ff12'],
    [class*='hover:border-[#a3ff12']:hover,
    [class*='focus:border-[#a3ff12']:focus {
        border-color: var(--primary) !important;
    }

    [class*='ring-[#a3ff12'],
    [class*='focus:ring-[#a3ff12']:focus {
        --tw-ring-color: rgba(var(--primary-rgb), 0.5) !important;
    }

    [class*='border-[#1f2a14'],
    [class*='hover:border-[#1f2a14']:hover {
        border-color: var(--border) !important;
    }

    [class*='bg-[#0C0C0C'],
    [class*='bg-[#050505'],
    [class*='hover:bg-[#0C0C0C']:hover,
    [class*='hover:bg-[#050505']:hover {
        background-color: var(--card) !important;
    }

    /* Last-resort inline style overrides for legacy hardcoded neon/dark values. */
    [style*='#a3ff12'],
    [style*='#d9ff93'] {
        color: var(--primary) !important;
        border-color: var(--primary) !important;
        box-shadow: 0 0 0 1px rgba(var(--primary-rgb), 0.35) !important;
    }

    [style*='rgba(163, 255, 18'],
    [style*='rgba(163,255,18'] {
        box-shadow: 0 0 0 1px rgba(var(--primary-rgb), 0.35) !important;
        filter: drop-shadow(0 0 10px rgba(var(--primary-rgb), 0.45)) !important;
    }

    [style*='#0C0C0C'],
    [style*='#050505'] {
        background-color: var(--card) !important;
    }

    [style*='#1f2a14'] {
        border-color: var(--border) !important;
    }

    [class~='bg-white'],
    [class*='bg-white'],
    [class*='bg-gray-800'],
    [class*='bg-gray-700'],
    [class*='bg-gray-600'],
    [class*='bg-neutral-700'],
    [class*='bg-neutral-800'],
    [class*='bg-neutral-900'],
    [class*='bg-[#1f2937]'] {
        background-color: var(--surface-elevated) !important;
    }

    [class*='border-gray-200'],
    [class*='border-gray-700'],
    [class*='border-neutral-200'],
    [class*='border-white/10'],
    [class*='border-white/8'] {
        border-color: var(--surface-border) !important;
    }

    [class~='text-white'],
    [class*='text-white'],
    [class*='text-neutral-100'],
    [class*='text-neutral-200'],
    [class*='text-neutral-300'],
    [class*='text-gray-50'],
    [class*='text-gray-200'],
    [class*='text-gray-300'],
    [class*='text-gray-100'] {
        color: var(--foreground) !important;
    }

    [class*='text-neutral-400'],
    [class*='text-neutral-500'],
    [class*='text-gray-400'],
    [class*='text-gray-500'],
    [class*='text-white/60'],
    [class*='text-white/40'] {
        color: var(--text-subtle) !important;
    }
`;
