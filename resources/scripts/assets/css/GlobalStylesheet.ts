import tw from 'twin.macro';
import { createGlobalStyle } from 'styled-components/macro';
// @ts-expect-error untyped font file
import font from '@fontsource-variable/ibm-plex-sans/files/ibm-plex-sans-latin-wght-normal.woff2';

export default createGlobalStyle`
    @font-face {
        font-family: 'IBM Plex Sans';
        font-style: normal;
        font-display: swap;
        font-weight: 100 700;
        src: url(${font}) format('woff2-variations');
        unicode-range: U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+0304,U+0308,U+0329,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD;
    }

    :root {
        --background: #0C0C0C;
        --foreground: #f8f6ef;
        --card: #0C0C0C;
        --card-foreground: #f8f6ef;
        --popover: #0C0C0C;
        --popover-foreground: #f8f6ef;
        --primary: #a3ff12;
        --primary-foreground: #0C0C0C;
        --secondary: #12220b;
        --secondary-foreground: #f8f6ef;
        --muted: #1b1f1c;
        --muted-foreground: #94a3b8;
        --accent: #1f2a14;
        --accent-foreground: #f8f6ef;
        --destructive: #ef4444;
        --destructive-foreground: #fee2e2;
        --border: #1f2a14;
        --input: #0C0C0C;
        --ring: #a3ff12;
        --primary-rgb: 163, 255, 18;
        --background-rgb: 12, 12, 12;
        --card-rgb: 12, 12, 12;
        --primary-glow-soft: rgba(163, 255, 18, 0.22);
        --primary-glow-medium: rgba(163, 255, 18, 0.35);
        --primary-glow-strong: rgba(163, 255, 18, 0.55);
        --font-sans: 'IBM Plex Sans', 'Roboto', system-ui, sans-serif;
        --font-mono: 'IBM Plex Sans', 'Roboto', system-ui, sans-serif;
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
        letter-spacing: 0.015em;
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
        -webkit-box-shadow: inset 0 0 0 1px hsl(211, 10%, 53%), inset 0 0 0 4px hsl(209deg 18% 30%);
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
`;
