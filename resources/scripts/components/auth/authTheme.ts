export const honeypotFieldClass =
    'pointer-events-none absolute left-[-10000px] top-[-10000px] h-0 w-0 overflow-hidden opacity-0';

export const authInputClass =
    'burhan-auth-input w-full rounded-[1.55rem] border px-[1.15rem] py-[1.1rem] text-base text-[color:var(--foreground)] outline-none transition-all placeholder:text-[color:var(--muted-foreground)]';

export const authInputWithSuffixClass = `${authInputClass} pr-20`;

export const authFieldLabelClass =
    'burhan-auth-label block text-[0.72rem] font-extrabold uppercase tracking-[0.18em] text-[color:var(--muted-foreground)]';

export const authErrorClass =
    'burhan-auth-error mt-1 text-[0.63rem] font-extrabold uppercase tracking-[0.12em] text-red-600';

export const authPrimaryButtonClass =
    'burhan-auth-submit mt-1 flex min-h-[5rem] w-full items-center justify-center gap-2 rounded-[1.6rem] border text-[0.92rem] font-black uppercase tracking-[0.18em] transition-all';

export const authSecondaryButtonClass =
    'burhan-auth-secondary flex min-h-[4rem] w-full items-center justify-center rounded-[1.6rem] border text-[0.88rem] font-extrabold uppercase tracking-[0.14em] transition-all';

export const authTurnstileTitleClass =
    'burhan-auth-turnstile-title mb-3 text-xs font-semibold uppercase tracking-[0.18em]';

export const authTurnstileCopyClass = 'burhan-auth-turnstile-copy text-xs leading-6';

export const authTurnstileErrorClass = 'burhan-auth-turnstile-error mt-3 text-xs leading-6';

export const burhanAuthTopbarStyles = `
    .burhan-auth-topbar {
        position: absolute;
        top: 0;
        right: 0;
        left: 0;
        z-index: 4;
        height: var(--auth-topbar-height);
        overflow: hidden;
        border-bottom: 1px solid rgba(255, 255, 255, 0.06);
        background: #742220;
    }

    .burhan-auth-topbar::before {
        content: '';
        position: absolute;
        inset: 0;
        pointer-events: none;
        z-index: 0;
        background-image:
            repeating-linear-gradient(0deg, transparent, transparent 4.5px, rgba(255,255,255,0.035) 4.5px, rgba(255,255,255,0.035) 5px),
            repeating-linear-gradient(60deg, transparent, transparent 4.5px, rgba(255,255,255,0.035) 4.5px, rgba(255,255,255,0.035) 5px),
            repeating-linear-gradient(120deg, transparent, transparent 4.5px, rgba(255,255,255,0.035) 4.5px, rgba(255,255,255,0.035) 5px);
    }

    .burhan-auth-topbar > * {
        position: relative;
        z-index: 1;
    }

    .burhan-auth-topbar-inner {
        display: flex;
        height: 100%;
        min-height: 0;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: 0 1.25rem;
    }

    .burhan-auth-topbar-brand {
        display: flex;
        height: 100%;
        max-width: min(100%, 32rem);
        min-width: 0;
        align-items: center;
        overflow: hidden;
        text-decoration: none;
        color: inherit;
    }

    .burhan-auth-topbar-logo {
        display: block;
        height: calc(var(--auth-topbar-height) - 1.25rem);
        width: calc(var(--auth-topbar-height) - 1.25rem);
        max-height: calc(var(--auth-topbar-height) - 1.25rem);
        flex-shrink: 0;
        margin-right: 0.8rem;
        padding: 0.15rem;
        box-sizing: border-box;
        border-radius: 0.75rem;
        object-fit: contain;
        object-position: center;
    }

    .burhan-auth-topbar-name {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        color: #FEF9E1;
        font-size: 1.1rem;
        font-weight: 800;
        line-height: 1.05;
        letter-spacing: 0.04em;
        text-transform: uppercase;
    }

    .burhan-auth-topbar-button {
        display: inline-flex;
        height: 2.8rem;
        width: 2.8rem;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        border: 1px solid rgba(255, 255, 255, 0.12);
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.06);
        color: rgba(254, 249, 225, 0.65);
        transition: color 0.2s ease, background-color 0.2s ease;
    }

    .burhan-auth-topbar-button:hover {
        color: #FEF9E1;
        background: rgba(255, 255, 255, 0.12);
    }

    @media (max-width: 640px) {
        .burhan-auth-topbar-inner {
            padding: 0 1rem;
        }

        .burhan-auth-topbar-name {
            font-size: 1rem;
        }
    }

    @media (min-width: 640px) {
        .burhan-auth-topbar-inner {
            padding: 0 1.5rem;
        }
    }

    @media (min-width: 1024px) {
        .burhan-auth-topbar-inner {
            padding: 0 2rem;
        }
    }
`;

export const burhanAuthThemeStyles = `
    .burhan-auth-stage {
        --auth-topbar-height: 5.25rem;
        --auth-mode-surface-rgb: 214, 210, 199;
        --auth-panel-bg: #FEF9E1;
        --auth-panel-surface: rgba(116, 34, 32, 0.04);
        --auth-panel-border: #C8BCA0;
        --auth-panel-border-soft: rgba(200, 188, 160, 0.60);
        --auth-panel-text: #742220;
        --auth-panel-muted: rgba(116, 34, 32, 0.55);
        isolation: isolate;
        background: rgb(var(--auth-mode-surface-rgb)) !important;
    }

    .dark .burhan-auth-stage {
        --auth-mode-surface-rgb: 214, 210, 199;
    }

    @media (max-width: 640px) {
        .burhan-auth-stage {
            --auth-topbar-height: 4.75rem;
        }
    }

    .burhan-auth-stage::before {
        display: none;
    }

    .burhan-auth-stage::after {
        content: '';
        position: fixed;
        inset: 0;
        z-index: 9999;
        pointer-events: none;
        background-image:
            repeating-linear-gradient(0deg, transparent, transparent 4.5px, rgba(116, 34, 32, 0.05) 4.5px, rgba(116, 34, 32, 0.05) 5px),
            repeating-linear-gradient(60deg, transparent, transparent 4.5px, rgba(116, 34, 32, 0.05) 4.5px, rgba(116, 34, 32, 0.05) 5px),
            repeating-linear-gradient(120deg, transparent, transparent 4.5px, rgba(116, 34, 32, 0.05) 4.5px, rgba(116, 34, 32, 0.05) 5px);
    }

    .burhan-auth-stage-game {
        position: relative;
        --auth-mode-surface-rgb: 214, 210, 199;
        background: #D6D2C7 !important;
    }

    .burhan-auth-backdrop {
        position: relative;
        overflow: hidden;
        isolation: isolate;
        background: rgb(var(--auth-mode-surface-rgb)) !important;
    }

    .burhan-auth-backdrop::before {
        display: none;
    }

    .burhan-auth-backdrop-game {
        border-right: 2px solid #C8BCA0;
    }

    .burhan-auth-backdrop-full {
        position: absolute;
        inset: 0;
        z-index: 0;
        width: 100% !important;
        height: 100%;
        border-right: 0;
    }

    .burhan-auth-backdrop-fallback {
        position: absolute;
        inset: 0;
        background: #D6D2C7;
    }

    .burhan-auth-backdrop-fallback::before {
        content: '';
        position: absolute;
        inset: 0;
        background-image:
            repeating-linear-gradient(0deg, transparent, transparent 4.5px, rgba(116, 34, 32, 0.05) 4.5px, rgba(116, 34, 32, 0.05) 5px),
            repeating-linear-gradient(60deg, transparent, transparent 4.5px, rgba(116, 34, 32, 0.05) 4.5px, rgba(116, 34, 32, 0.05) 5px),
            repeating-linear-gradient(120deg, transparent, transparent 4.5px, rgba(116, 34, 32, 0.05) 4.5px, rgba(116, 34, 32, 0.05) 5px);
        pointer-events: none;
    }

    .burhan-auth-backdrop-frame {
        position: absolute;
        inset: 0;
        display: block;
        width: 100%;
        height: 100%;
        border: 0;
        background: #D6D2C7;
        opacity: 0;
        transition: opacity 220ms ease;
    }

    .burhan-auth-backdrop-frame.is-ready {
        opacity: 1;
    }

    .burhan-auth-rail {
        position: relative;
        z-index: 1;
        padding-top: calc(var(--auth-topbar-height) + 1.25rem) !important;
        background: #D6D2C7 !important;
        box-shadow: inset 1px 0 0 #C8BCA0;
        overscroll-behavior: contain;
        scrollbar-gutter: stable both-edges;
        -webkit-overflow-scrolling: touch;
    }

    .burhan-auth-shell {
        position: relative;
        z-index: 1;
    }

    .burhan-auth-rail-floating {
        background: transparent !important;
        box-shadow: none;
        scrollbar-gutter: auto;
    }

    .burhan-auth-shell-floating {
        width: min(100%, 38.4rem);
        max-width: 38.4rem;
    }

    ${burhanAuthTopbarStyles}

    .burhan-auth-glow {
        --backdrop: var(--auth-panel-bg) !important;
        --backup-border: var(--auth-panel-border) !important;
        --radius: 24;
        --border: 2;
        --size: 160;
        --bg-spot-opacity: 0;
        --border-spot-opacity: 0;
        --border-light-opacity: 0;
        --outer: 0;
        border-radius: 1.75rem;
        background-color: var(--auth-panel-bg) !important;
        background-image: none !important;
        border: 2px solid #2D4A3E !important;
        box-shadow: 4px 4px 0px 0px #2D4A3E !important;
        touch-action: pan-y !important;
    }

    .burhan-auth-glow[data-glow]::before,
    .burhan-auth-glow[data-glow]::after {
        opacity: 0 !important;
        display: none !important;
    }

    .burhan-auth-glow [data-glow] {
        display: none !important;
    }

    .burhan-auth-card {
        position: relative;
        overflow: hidden;
        border-radius: 1.75rem;
        padding: 22px;
        border: 2px solid #2D4A3E;
        background: #FEF9E1;
        box-shadow: 4px 4px 0px 0px #2D4A3E;
    }

    .burhan-auth-card::before,
    .burhan-auth-card::after {
        display: none;
    }

    .burhan-auth-card > * {
        position: relative;
        z-index: 1;
    }

    .burhan-auth-brand-panel {
        margin-bottom: 0.95rem;
        border-radius: 1.25rem;
        border: 2px solid #C8BCA0;
        background: #F5EFD5;
        padding: 1.5rem 1.25rem;
        text-align: center;
        box-shadow: 3px 3px 0px 0px #C8BCA0;
    }

    .burhan-auth-title {
        margin: 0;
        color: #742220;
        font-size: clamp(2.7rem, 5vw, 4rem);
        font-weight: 800;
        line-height: 0.9;
        letter-spacing: -0.06em;
        word-break: break-word;
    }

    .burhan-auth-brand-logo {
        display: block;
        width: min(100%, 26rem);
        max-height: 8.75rem;
        margin: 0 auto;
        object-fit: contain;
        object-position: center;
    }

    .burhan-auth-switch {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.35rem;
        margin-bottom: 1.1rem;
        padding: 0.45rem;
        border-radius: 1.35rem;
        border: 2px solid #C8BCA0;
        background: #F5EFD5;
        box-shadow: 2px 2px 0px 0px #C8BCA0;
    }

    .burhan-auth-tab {
        border: 2px solid transparent;
        border-radius: 1rem;
        padding: 0.9rem 1rem;
        background: transparent;
        color: rgba(116, 34, 32, 0.55);
        font-size: 0.82rem;
        font-weight: 800;
        letter-spacing: 0.18em;
        text-transform: uppercase;
        transition: all 0.2s ease;
    }

    .burhan-auth-tab.is-active {
        color: #FEF9E1;
        border-color: #5a1a18;
        background: #742220;
        box-shadow: 2px 2px 0px 0px #5a1a18;
    }

    .burhan-auth-tab:not(.is-active):hover {
        color: #742220;
        border-color: #C8BCA0;
        background: rgba(116, 34, 32, 0.06);
    }

    .burhan-auth-form {
        display: grid;
        gap: 0.85rem;
    }

    .burhan-auth-label-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        margin-bottom: 0.45rem;
    }

    .burhan-auth-label {
        color: rgba(116, 34, 32, 0.55);
    }

    .burhan-auth-meta-link,
    .burhan-auth-field-token {
        color: rgba(116, 34, 32, 0.55);
        font-size: 0.72rem;
        font-weight: 800;
        letter-spacing: 0.18em;
        text-transform: uppercase;
    }

    .burhan-auth-meta-link {
        color: #2D4A3E;
        transition: color 0.2s ease;
    }

    .burhan-auth-meta-link:hover {
        color: #1a2e26;
    }

    .burhan-auth-input-wrap {
        position: relative;
    }

    .burhan-auth-input {
        min-height: 4.35rem;
        border: 2px solid #C8BCA0 !important;
        background: #FEF9E1 !important;
        color: #742220 !important;
        box-shadow: 2px 2px 0px 0px #C8BCA0;
    }

    .burhan-auth-input:focus {
        border-color: #742220 !important;
        box-shadow: 2px 2px 0px 0px #742220;
    }

    .burhan-auth-input:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }

    .burhan-auth-input::placeholder {
        color: rgba(116, 34, 32, 0.40) !important;
    }

    .burhan-auth-input.is-centered {
        padding-right: 1.15rem;
        text-align: center;
        letter-spacing: 0.45em;
    }

    .burhan-auth-field-token {
        position: absolute;
        top: 50%;
        right: 1rem;
        transform: translateY(-50%);
    }

    .burhan-auth-field-token.is-button {
        border: none;
        background: none;
        padding: 0;
        cursor: pointer;
        transition: color 0.2s ease;
    }

    .burhan-auth-field-token.is-button:hover {
        color: #742220;
    }

    .burhan-auth-submit {
        border: 2px solid #5a1a18;
        background: #742220;
        color: #FEF9E1;
        box-shadow: 3px 3px 0px 0px #5a1a18;
    }

    .burhan-auth-submit:hover:not(:disabled) {
        transform: translate(-1px, -1px);
        box-shadow: 5px 5px 0px 0px #5a1a18;
    }

    .burhan-auth-submit:disabled {
        opacity: 0.55;
        cursor: not-allowed;
    }

    .burhan-auth-secondary {
        border: 2px solid #2D4A3E;
        background: #F5EFD5;
        color: #2D4A3E;
        box-shadow: 2px 2px 0px 0px #2D4A3E;
    }

    .burhan-auth-secondary:hover {
        transform: translate(-1px, -1px);
        box-shadow: 3px 3px 0px 0px #2D4A3E;
    }

    .burhan-auth-overlay {
        background: rgba(214, 210, 199, 0.85);
        backdrop-filter: blur(10px);
    }

    .burhan-auth-overlay-card {
        border-radius: 1.5rem;
        border: 2px solid #2D4A3E;
        background: #FEF9E1;
        box-shadow: 4px 4px 0px 0px #2D4A3E;
    }

    .burhan-auth-divider {
        position: relative;
        margin: 1.1rem 0 0.8rem;
        text-align: center;
    }

    .burhan-auth-divider::before {
        content: '';
        position: absolute;
        left: 0;
        right: 0;
        top: 50%;
        border-top: 2px solid #C8BCA0;
    }

    .burhan-auth-divider span {
        position: relative;
        z-index: 1;
        display: inline-block;
        padding: 0 0.85rem;
        background: #FEF9E1;
        color: rgba(116, 34, 32, 0.55);
        font-size: 0.66rem;
        font-weight: 800;
        letter-spacing: 0.22em;
        text-transform: uppercase;
    }

    .burhan-auth-provider-list {
        display: grid;
        gap: 0.85rem;
    }

    .burhan-auth-provider {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.85rem;
        padding: 1rem 1rem 1rem 1.05rem;
        border: 2px solid #C8BCA0;
        border-radius: 1.45rem;
        background: #F5EFD5;
        box-shadow: 2px 2px 0px 0px #C8BCA0;
        transition: transform 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
    }

    .burhan-auth-provider:hover {
        border-color: #2D4A3E;
        box-shadow: 3px 3px 0px 0px #2D4A3E;
        transform: translate(-1px, -1px);
    }

    .burhan-auth-provider-main {
        display: flex;
        min-width: 0;
        align-items: center;
        gap: 0.85rem;
    }

    .burhan-auth-provider-icon {
        display: grid;
        height: 2.75rem;
        width: 2.75rem;
        place-items: center;
        border-radius: 1rem;
        border: 2px solid #C8BCA0;
        background: #FEF9E1;
        color: #742220;
        box-shadow: 2px 2px 0px 0px #C8BCA0;
    }

    .burhan-auth-provider-icon.is-discord {
        color: #742220;
    }

    .burhan-auth-provider-label {
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        color: #742220;
        font-size: 0.98rem;
        font-weight: 700;
    }

    .burhan-auth-provider-arrow {
        color: rgba(116, 34, 32, 0.55);
        font-size: 0.95rem;
    }

    .burhan-auth-provider:hover .burhan-auth-provider-arrow {
        color: #742220;
    }

    .burhan-auth-pin-copy {
        margin-bottom: 0.2rem;
        color: rgba(116, 34, 32, 0.55);
        line-height: 1.75;
    }

    .burhan-auth-turnstile-title {
        color: #742220;
    }

    .burhan-auth-turnstile-copy {
        color: rgba(116, 34, 32, 0.55);
    }

    .burhan-auth-turnstile-error {
        color: #991b1b;
    }

    .burhan-auth-stage input:-webkit-autofill,
    .burhan-auth-stage input:-webkit-autofill:hover,
    .burhan-auth-stage input:-webkit-autofill:focus {
        -webkit-text-fill-color: #742220;
        -webkit-box-shadow: 0 0 0 1000px #FEF9E1 inset;
        transition: background-color 9999s ease-in-out 0s;
    }

    @media (max-width: 1024px) {
        .burhan-auth-stage {
            background: #D6D2C7 !important;
        }
    }

    @media (min-width: 1024px) {
        .burhan-auth-rail-floating {
            position: relative;
            z-index: 3;
            margin-left: auto;
            width: min(43.2rem, 50.4vw) !important;
            padding-left: 1.5rem;
            padding-right: 12rem;
        }

        .burhan-auth-rail-floating::before {
            display: none;
        }

        .burhan-auth-rail-floating > * {
            position: relative;
            z-index: 1;
        }
    }

    @media (max-height: 900px) {
        .burhan-auth-rail {
            overflow-y: auto !important;
        }

        .burhan-auth-shell {
            justify-content: flex-start !important;
            padding-top: 0.4rem;
            padding-bottom: 0.4rem;
        }

        .burhan-auth-card {
            padding: 16px;
        }

        .burhan-auth-brand-panel {
            margin-bottom: 0.75rem;
            padding: 1.2rem 1rem;
        }

        .burhan-auth-switch {
            margin-bottom: 0.8rem;
            padding: 0.45rem;
        }

        .burhan-auth-form {
            gap: 0.7rem;
        }

        .burhan-auth-input {
            min-height: 3.9rem;
        }

        .burhan-auth-submit {
            min-height: 4.2rem;
        }

        .burhan-auth-secondary {
            min-height: 3.4rem;
        }

        .burhan-auth-divider {
            margin: 0.8rem 0 0.6rem;
        }
    }
`;
