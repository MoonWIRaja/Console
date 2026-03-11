export const honeypotFieldClass =
    'pointer-events-none absolute left-[-10000px] top-[-10000px] h-0 w-0 overflow-hidden opacity-0';

export const authInputClass =
    'burhan-auth-input w-full rounded-[1.55rem] border border-[rgba(255,255,255,0.075)] bg-[rgba(5,8,14,0.84)] px-[1.15rem] py-[1.1rem] text-base text-[color:var(--foreground)] outline-none transition-all placeholder:text-[rgba(151,160,171,0.75)]';

export const authInputWithSuffixClass = `${authInputClass} pr-20`;

export const authFieldLabelClass =
    'burhan-auth-label block text-[0.72rem] font-extrabold uppercase tracking-[0.18em] text-[rgba(248,246,239,0.68)]';

export const authErrorClass =
    'burhan-auth-error mt-1 text-[0.63rem] font-extrabold uppercase tracking-[0.12em] text-red-400';

export const authPrimaryButtonClass =
    'burhan-auth-submit mt-1 flex min-h-[5rem] w-full items-center justify-center gap-2 rounded-[1.6rem] border border-[rgba(var(--primary-rgb),0.34)] text-[0.92rem] font-black uppercase tracking-[0.18em] text-[#0a0d10] transition-all';

export const authSecondaryButtonClass =
    'burhan-auth-secondary flex min-h-[4rem] w-full items-center justify-center rounded-[1.6rem] border border-[rgba(255,255,255,0.08)] text-[0.88rem] font-extrabold uppercase tracking-[0.14em] text-[color:var(--foreground)] transition-all';

export const burhanAuthThemeStyles = `
    @import url('https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css');

    .burhan-auth-stage {
        isolation: isolate;
        background:
            radial-gradient(circle at 14% 18%, rgba(var(--primary-rgb), 0.08), transparent 24%),
            radial-gradient(circle at 86% 12%, rgba(94, 150, 255, 0.12), transparent 18%),
            linear-gradient(180deg, #020304 0%, #05070a 50%, #080b10 100%);
    }

    .burhan-auth-stage::before {
        content: '';
        position: absolute;
        inset: 0;
        background-image:
            linear-gradient(rgba(255, 255, 255, 0.02) 1px, transparent 1px),
            linear-gradient(90deg, rgba(255, 255, 255, 0.02) 1px, transparent 1px);
        background-size: 52px 52px;
        mask-image: radial-gradient(circle at center, black 36%, transparent 90%);
        opacity: 0.34;
        pointer-events: none;
    }

    .burhan-auth-stage::after {
        content: '';
        position: absolute;
        inset: 0;
        background:
            radial-gradient(circle at 30% 35%, rgba(var(--primary-rgb), 0.06), transparent 28%),
            radial-gradient(circle at 78% 62%, rgba(124, 227, 223, 0.06), transparent 24%);
        filter: blur(54px);
        pointer-events: none;
    }

    .burhan-auth-backdrop {
        position: relative;
        background:
            radial-gradient(circle at 24% 28%, rgba(var(--primary-rgb), 0.1), transparent 24%),
            radial-gradient(circle at 76% 18%, rgba(94, 150, 255, 0.14), transparent 20%),
            linear-gradient(180deg, rgba(3, 4, 6, 0.98), rgba(7, 10, 15, 0.94));
    }

    .burhan-auth-backdrop::before {
        content: '';
        position: absolute;
        inset: 0;
        background:
            radial-gradient(circle at 34% 40%, rgba(var(--primary-rgb), 0.08), transparent 16%),
            radial-gradient(circle at 72% 22%, rgba(124, 227, 223, 0.08), transparent 14%);
        filter: blur(24px);
    }

    .burhan-auth-backdrop::after {
        content: '';
        position: absolute;
        inset: 48px;
        border-radius: 36px;
        border: 1px solid rgba(255, 255, 255, 0.04);
        background: linear-gradient(135deg, rgba(255, 255, 255, 0.02), transparent 38%);
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.04);
        opacity: 0.7;
    }

    .burhan-auth-rail {
        position: relative;
        z-index: 1;
        background: linear-gradient(180deg, rgba(4, 6, 9, 0.88), rgba(6, 8, 12, 0.96));
        box-shadow: inset 1px 0 0 rgba(255, 255, 255, 0.04);
        overscroll-behavior: contain;
    }

    .burhan-auth-shell {
        position: relative;
        z-index: 1;
    }

    .burhan-auth-glow {
        --radius: 32;
        --border: 1.5;
        --size: 240;
        --backdrop: rgba(7, 10, 15, 0.96);
        --backup-border: rgba(255, 255, 255, 0.08);
        --bg-spot-opacity: 0.18;
        --border-spot-opacity: 0.92;
        --border-light-opacity: 0.72;
        --outer: 0.82;
        border-radius: 2rem;
        box-shadow: 0 38px 96px rgba(0, 0, 0, 0.62), 0 0 0 1px rgba(255, 255, 255, 0.03) inset;
    }

    .burhan-auth-card {
        position: relative;
        overflow: hidden;
        border-radius: 30px;
        padding: 20px;
        background:
            linear-gradient(180deg, rgba(255, 255, 255, 0.035), transparent 16%),
            linear-gradient(180deg, rgba(9, 12, 18, 0.98), rgba(5, 7, 11, 0.99));
    }

    .burhan-auth-card::before {
        content: '';
        position: absolute;
        inset: 0;
        background:
            radial-gradient(circle at 18% 26%, rgba(var(--primary-rgb), 0.08), transparent 24%),
            radial-gradient(circle at 82% 14%, rgba(110, 148, 255, 0.12), transparent 20%);
        pointer-events: none;
    }

    .burhan-auth-card::after {
        content: '';
        position: absolute;
        inset: 10px;
        border-radius: 24px;
        border: 1px solid rgba(255, 255, 255, 0.04);
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.04);
        pointer-events: none;
    }

    .burhan-auth-card > * {
        position: relative;
        z-index: 1;
    }

    .burhan-auth-brand-panel {
        margin-bottom: 0.95rem;
        border-radius: 1.75rem;
        border: 1px solid rgba(255, 255, 255, 0.06);
        background:
            linear-gradient(180deg, rgba(255, 255, 255, 0.035), transparent 36%),
            rgba(255, 255, 255, 0.02);
        padding: 1.5rem 1.25rem;
        text-align: center;
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.05);
    }

    .burhan-auth-title {
        margin: 0;
        color: var(--foreground);
        font-size: clamp(2.4rem, 5vw, 3.4rem);
        font-weight: 800;
        line-height: 0.9;
        letter-spacing: -0.06em;
        text-shadow: 0 0 18px rgba(248, 246, 239, 0.28);
        text-transform: uppercase;
        word-break: break-word;
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
        color: rgba(248, 246, 239, 0.68);
    }

    .burhan-auth-meta-link,
    .burhan-auth-field-token {
        color: rgba(248, 246, 239, 0.68);
        font-size: 0.72rem;
        font-weight: 800;
        letter-spacing: 0.18em;
        text-transform: uppercase;
    }

    .burhan-auth-meta-link {
        transition: color 0.2s ease;
    }

    .burhan-auth-meta-link:hover {
        color: var(--primary);
    }

    .burhan-auth-input-wrap {
        position: relative;
    }

    .burhan-auth-input {
        min-height: 4.35rem;
        border-radius: 1.55rem;
        background:
            linear-gradient(180deg, rgba(255, 255, 255, 0.035), transparent 30%),
            rgba(5, 8, 14, 0.84);
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.05), inset 0 -18px 26px rgba(0, 0, 0, 0.24);
    }

    .burhan-auth-input:focus {
        border-color: rgba(var(--primary-rgb), 0.42);
        box-shadow:
            inset 0 1px 0 rgba(255, 255, 255, 0.06),
            inset 0 -18px 26px rgba(0, 0, 0, 0.24),
            0 0 0 1px rgba(var(--primary-rgb), 0.12),
            0 0 24px rgba(var(--primary-rgb), 0.12);
    }

    .burhan-auth-input:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }

    .burhan-auth-input::placeholder {
        color: rgba(151, 160, 171, 0.75);
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
        color: var(--foreground);
    }

    .burhan-auth-submit {
        background: linear-gradient(90deg, rgba(var(--primary-rgb), 0.38), rgba(var(--primary-rgb), 0.2));
        box-shadow:
            inset 0 1px 0 rgba(255, 255, 255, 0.16),
            0 24px 36px rgba(var(--primary-rgb), 0.14),
            0 0 50px rgba(var(--primary-rgb), 0.12);
    }

    .burhan-auth-submit:hover:not(:disabled) {
        transform: translateY(-1px);
        box-shadow:
            inset 0 1px 0 rgba(255, 255, 255, 0.16),
            0 28px 40px rgba(var(--primary-rgb), 0.18),
            0 0 56px rgba(var(--primary-rgb), 0.16);
    }

    .burhan-auth-submit:disabled {
        opacity: 0.55;
        cursor: not-allowed;
    }

    .burhan-auth-secondary {
        background: linear-gradient(180deg, rgba(255, 255, 255, 0.03), rgba(255, 255, 255, 0.015));
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.04), inset 0 -12px 20px rgba(0, 0, 0, 0.2);
    }

    .burhan-auth-secondary:hover {
        border-color: rgba(var(--primary-rgb), 0.22);
        color: var(--primary);
    }

    .burhan-auth-pin-copy {
        margin-bottom: 0.2rem;
        color: rgba(248, 246, 239, 0.72);
        line-height: 1.75;
    }

    .burhan-auth-stage input:-webkit-autofill,
    .burhan-auth-stage input:-webkit-autofill:hover,
    .burhan-auth-stage input:-webkit-autofill:focus {
        -webkit-text-fill-color: var(--foreground);
        -webkit-box-shadow: 0 0 0 1000px rgba(5, 8, 14, 0.96) inset;
        transition: background-color 9999s ease-in-out 0s;
    }

    @media (max-width: 1024px) {
        .burhan-auth-stage {
            background: linear-gradient(180deg, #030406 0%, #070a0f 100%);
        }
    }
`;
