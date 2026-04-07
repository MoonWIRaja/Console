import tw from 'twin.macro';
import { createGlobalStyle } from 'styled-components/macro';

export default createGlobalStyle`
    :root {
        --background: #222222;
        --foreground: #F5E7C6;
        --card: #222222;
        --card-foreground: #F5E7C6;
        --popover: #222222;
        --popover-foreground: #F5E7C6;
        --primary: #F5E7C6;
        --primary-foreground: #222222;
        --secondary: rgba(245, 231, 198, 0.07);
        --secondary-foreground: #F5E7C6;
        --muted: rgba(245, 231, 198, 0.05);
        --muted-foreground: rgba(245, 231, 198, 0.74);
        --accent: rgba(245, 231, 198, 0.1);
        --accent-foreground: #F5E7C6;
        --destructive: #F5E7C6;
        --destructive-foreground: #222222;
        --border: rgba(245, 231, 198, 0.18);
        --input: rgba(245, 231, 198, 0.08);
        --ring: #F5E7C6;
        --primary-rgb: 245, 231, 198;
        --background-rgb: 34, 34, 34;
        --card-rgb: 34, 34, 34;
        --primary-glow-soft: rgba(245, 231, 198, 0.18);
        --primary-glow-medium: rgba(245, 231, 198, 0.28);
        --primary-glow-strong: rgba(245, 231, 198, 0.4);
        --surface-elevated: #2D2D2D;
        --surface-elevated-rgb: 45, 45, 45;
        --surface-subtle: rgba(245, 231, 198, 0.02);
        --surface-subtle-strong: rgba(245, 231, 198, 0.04);
        --surface-border: rgba(245, 231, 198, 0.12);
        --text-subtle: #A0A0A0;
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
