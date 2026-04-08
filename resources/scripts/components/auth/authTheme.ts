export const honeypotFieldClass =
    'pointer-events-none absolute left-[-10000px] top-[-10000px] h-0 w-0 overflow-hidden opacity-0';

export const authInputClass =
    'burhan-auth-input w-full rounded-[1.55rem] border px-[1.15rem] py-[1.1rem] text-base text-[color:var(--foreground)] outline-none transition-all placeholder:text-[color:var(--muted-foreground)]';

export const authInputWithSuffixClass = `${authInputClass} pr-20`;

export const authFieldLabelClass =
    'burhan-auth-label block text-[0.72rem] font-extrabold uppercase tracking-[0.18em] text-[color:var(--muted-foreground)]';

export const authErrorClass =
    'burhan-auth-error mt-1 text-[0.63rem] font-extrabold uppercase tracking-[0.12em] text-red-400';

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
        border-bottom: 1px solid rgba(245, 231, 198, 0.12);
        background: #2D2D2D;
        box-shadow: 0 18px 40px rgba(0, 0, 0, 0.16);
        backdrop-filter: blur(18px);
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
        color: #F5E7C6;
        font-size: 1.1rem;
        font-weight: 700;
        line-height: 1.05;
    }

    .burhan-auth-topbar-button {
        display: inline-flex;
        height: 2.8rem;
        width: 2.8rem;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        border: 1px solid rgba(245, 231, 198, 0.12);
        border-radius: 999px;
        background: rgba(245, 231, 198, 0.06);
        color: rgba(245, 231, 198, 0.76);
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.04);
        transition: color 0.2s ease, background-color 0.2s ease, transform 0.2s ease;
    }

    .burhan-auth-topbar-button:hover {
        color: #F5E7C6;
        background: rgba(245, 231, 198, 0.12);
        transform: rotate(10deg);
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
        --auth-mode-surface-rgb: 245, 231, 198;
        --auth-panel-bg: #2D2D2D;
        --auth-panel-surface: rgba(255, 255, 255, 0.02);
        --auth-panel-border: rgba(245, 231, 198, 0.12);
        --auth-panel-border-soft: rgba(245, 231, 198, 0.08);
        --auth-panel-text: #F5E7C6;
        --auth-panel-muted: #A0A0A0;
        isolation: isolate;
        background: rgb(var(--auth-mode-surface-rgb)) !important;
    }

    .dark .burhan-auth-stage {
        --auth-mode-surface-rgb: 34, 34, 34;
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
        display: none;
    }

    .burhan-auth-stage-game {
        position: relative;
        --auth-mode-surface-rgb: 13, 13, 13;
        background: #0d0d0d !important;
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
        border-right: 1px solid rgba(var(--primary-rgb), 0.08);
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
        background:
            radial-gradient(circle at 18% 20%, rgba(var(--primary-rgb), 0.16), transparent 26%),
            radial-gradient(circle at 78% 18%, rgba(245, 231, 198, 0.11), transparent 24%),
            linear-gradient(180deg, rgba(12, 18, 10, 0.94), rgba(7, 10, 8, 0.98));
    }

    .burhan-auth-backdrop-fallback::before {
        content: '';
        position: absolute;
        inset: 0;
        background:
            linear-gradient(90deg, rgba(255, 255, 255, 0.03) 1px, transparent 1px),
            linear-gradient(180deg, rgba(255, 255, 255, 0.02) 1px, transparent 1px);
        background-size: 38px 38px, 38px 38px;
        opacity: 0.08;
        pointer-events: none;
    }

    .burhan-auth-backdrop-frame {
        position: absolute;
        inset: 0;
        display: block;
        width: 100%;
        height: 100%;
        border: 0;
        background: #0d0d0d;
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
        background: rgb(var(--auth-mode-surface-rgb)) !important;
        box-shadow: inset 1px 0 0 rgba(var(--primary-rgb), 0.1);
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
        --glow-rgb: 245, 231, 198 !important;
        --radius: 32;
        --border: 1;
        --size: 160;
        --bg-spot-opacity: 0.07;
        --border-spot-opacity: 0.16;
        --border-light-opacity: 0.04;
        --outer: 0.18;
        border-radius: 2rem;
        background-color: var(--auth-panel-bg) !important;
        border-color: var(--auth-panel-border) !important;
        box-shadow:
            0 24px 56px rgba(0, 0, 0, 0.2),
            0 0 0 1px var(--auth-panel-border-soft) inset;
        touch-action: pan-y !important;
    }

    .burhan-auth-card {
        position: relative;
        overflow: hidden;
        border-radius: 30px;
        padding: 22px;
        border: 1px solid var(--auth-panel-border);
        background: var(--auth-panel-bg);
        box-shadow:
            inset 0 1px 0 rgba(255, 255, 255, 0.03),
            0 16px 36px rgba(0, 0, 0, 0.14);
    }

    .burhan-auth-card::before {
        content: '';
        position: absolute;
        inset: 0;
        background:
            radial-gradient(circle at 14% 20%, rgba(245, 231, 198, 0.04), transparent 20%),
            radial-gradient(circle at 86% 12%, rgba(245, 231, 198, 0.025), transparent 18%);
        pointer-events: none;
    }

    .burhan-auth-card::after {
        content: '';
        position: absolute;
        inset: 10px;
        border-radius: 24px;
        border: 1px solid var(--auth-panel-border-soft);
        pointer-events: none;
    }

    .burhan-auth-card > * {
        position: relative;
        z-index: 1;
    }

    .burhan-auth-brand-panel {
        margin-bottom: 0.95rem;
        border-radius: 1.75rem;
        border: 1px solid var(--auth-panel-border);
        background:
            linear-gradient(180deg, rgba(245, 231, 198, 0.045), transparent 40%),
            var(--auth-panel-surface);
        padding: 1.5rem 1.25rem;
        text-align: center;
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.03);
    }

    .burhan-auth-title {
        margin: 0;
        color: var(--auth-panel-text);
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
        border: 1px solid var(--auth-panel-border);
        background: var(--auth-panel-surface);
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.03);
    }

    .burhan-auth-tab {
        border: 1px solid transparent;
        border-radius: 1rem;
        padding: 0.9rem 1rem;
        background: transparent;
        color: var(--auth-panel-muted);
        font-size: 0.82rem;
        font-weight: 800;
        letter-spacing: 0.18em;
        text-transform: uppercase;
        transition: all 0.25s ease;
    }

    .burhan-auth-tab.is-active {
        color: var(--auth-panel-text);
        border-color: var(--auth-panel-border);
        background: rgba(245, 231, 198, 0.08);
        box-shadow:
            inset 0 -2px 0 #F5E7C6,
            inset 0 1px 0 rgba(255, 255, 255, 0.03);
    }

    .burhan-auth-tab:not(.is-active):hover {
        color: var(--auth-panel-text);
        border-color: var(--auth-panel-border-soft);
        background: rgba(245, 231, 198, 0.05);
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
        color: var(--auth-panel-muted);
    }

    .burhan-auth-meta-link,
    .burhan-auth-field-token {
        color: var(--auth-panel-muted);
        font-size: 0.72rem;
        font-weight: 800;
        letter-spacing: 0.18em;
        text-transform: uppercase;
    }

    .burhan-auth-meta-link {
        transition: color 0.2s ease;
    }

    .burhan-auth-meta-link:hover {
        color: var(--auth-panel-text);
    }

    .burhan-auth-input-wrap {
        position: relative;
    }

    .burhan-auth-input {
        min-height: 4.35rem;
        border-color: var(--auth-panel-border);
        background:
            linear-gradient(180deg, rgba(245, 231, 198, 0.03), transparent 34%),
            var(--auth-panel-surface);
        color: var(--auth-panel-text) !important;
        box-shadow:
            inset 0 1px 0 rgba(255, 255, 255, 0.03),
            inset 0 -8px 16px rgba(0, 0, 0, 0.08);
    }

    .burhan-auth-input:focus {
        border-color: rgba(245, 231, 198, 0.46);
        box-shadow:
            inset 0 1px 0 rgba(255, 255, 255, 0.03),
            0 0 0 1px rgba(245, 231, 198, 0.16),
            0 0 18px rgba(245, 231, 198, 0.08);
    }

    .burhan-auth-input:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }

    .burhan-auth-input::placeholder {
        color: var(--auth-panel-muted);
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
        color: var(--auth-panel-text);
    }

    .burhan-auth-submit {
        border-color: var(--auth-panel-border);
        background: #F5E7C6;
        color: #222222;
        box-shadow:
            inset 0 1px 0 rgba(255, 255, 255, 0.25),
            0 14px 24px rgba(245, 231, 198, 0.12);
    }

    .burhan-auth-submit:hover:not(:disabled) {
        transform: translateY(-1px);
        box-shadow:
            inset 0 1px 0 rgba(255, 255, 255, 0.25),
            0 18px 28px rgba(245, 231, 198, 0.14);
    }

    .burhan-auth-submit:disabled {
        opacity: 0.55;
        cursor: not-allowed;
    }

    .burhan-auth-secondary {
        border-color: var(--auth-panel-border);
        background: var(--auth-panel-surface);
        color: var(--auth-panel-text);
        box-shadow:
            inset 0 1px 0 rgba(255, 255, 255, 0.03),
            inset 0 -8px 14px rgba(0, 0, 0, 0.08);
    }

    .burhan-auth-secondary:hover {
        border-color: rgba(245, 231, 198, 0.24);
        color: var(--auth-panel-text);
    }

    .burhan-auth-overlay {
        background: rgba(var(--background-rgb), 0.78);
        backdrop-filter: blur(10px);
    }

    .burhan-auth-overlay-card {
        border-radius: 1.5rem;
        border: 1px solid var(--auth-panel-border);
        background:
            linear-gradient(180deg, rgba(245, 231, 198, 0.03), transparent 24%),
            var(--auth-panel-bg);
        box-shadow:
            0 24px 46px rgba(0, 0, 0, 0.14),
            inset 0 1px 0 rgba(255, 255, 255, 0.03);
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
        border-top: 1px solid var(--auth-panel-border);
    }

    .burhan-auth-divider span {
        position: relative;
        z-index: 1;
        display: inline-block;
        padding: 0 0.85rem;
        background: var(--auth-panel-bg);
        color: var(--auth-panel-muted);
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
        border: 1px solid var(--auth-panel-border);
        border-radius: 1.45rem;
        background:
            linear-gradient(180deg, rgba(245, 231, 198, 0.03), transparent 42%),
            var(--auth-panel-surface);
        box-shadow:
            inset 0 1px 0 rgba(255, 255, 255, 0.03),
            inset 0 -8px 16px rgba(0, 0, 0, 0.08);
        transition: transform 0.2s ease, border-color 0.2s ease;
    }

    .burhan-auth-provider:hover {
        border-color: rgba(245, 231, 198, 0.24);
        transform: translateY(-1px);
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
        border: 1px solid var(--auth-panel-border);
        background: rgba(245, 231, 198, 0.05);
        color: var(--auth-panel-text);
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.03);
    }

    .burhan-auth-provider-icon.is-discord {
        color: var(--auth-panel-text);
    }

    .burhan-auth-provider-label {
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        color: var(--auth-panel-text);
        font-size: 0.98rem;
        font-weight: 700;
    }

    .burhan-auth-provider-arrow {
        color: var(--auth-panel-muted);
        font-size: 0.95rem;
    }

    .burhan-auth-provider:hover .burhan-auth-provider-arrow {
        color: var(--auth-panel-text);
    }

    .burhan-auth-pin-copy {
        margin-bottom: 0.2rem;
        color: var(--auth-panel-muted);
        line-height: 1.75;
    }

    .burhan-auth-turnstile-title {
        color: var(--auth-panel-text);
    }

    .burhan-auth-turnstile-copy {
        color: var(--auth-panel-muted);
    }

    .burhan-auth-turnstile-error {
        color: #F5E7C6;
    }

    .burhan-auth-stage input:-webkit-autofill,
    .burhan-auth-stage input:-webkit-autofill:hover,
    .burhan-auth-stage input:-webkit-autofill:focus {
        -webkit-text-fill-color: var(--auth-panel-text);
        -webkit-box-shadow: 0 0 0 1000px var(--auth-panel-bg) inset;
        transition: background-color 9999s ease-in-out 0s;
    }

    @media (max-width: 1024px) {
        .burhan-auth-stage {
            background: rgb(var(--auth-mode-surface-rgb)) !important;
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
