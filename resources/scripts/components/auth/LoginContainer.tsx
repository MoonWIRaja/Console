import React, { useEffect, useMemo, useState } from 'react';
import { Link, RouteComponentProps } from 'react-router-dom';
import login from '@/api/auth/login';
import signup from '@/api/auth/signup';
import verifyEmailPin from '@/api/auth/verifyEmailPin';
import { useStoreState } from 'easy-peasy';
import { Form, Formik, FormikHelpers, FormikProps } from 'formik';
import { object, ref as yupRef, string } from 'yup';
import useFlash from '@/plugins/useFlash';
import FlashMessageRender from '@/components/FlashMessageRender';
import { GlowCard } from '@/components/ui/spotlight-card';
import TurnstileWidget from '@/components/auth/TurnstileWidget';
import useSiteBranding from '@/hooks/useSiteBranding';

interface LoginValues {
    username: string;
    password: string;
    website: string;
    company: string;
    formRenderedAt: number;
}

interface SignupValues {
    email: string;
    username: string;
    firstName: string;
    lastName: string;
    password: string;
    passwordConfirmation: string;
    website: string;
    company: string;
    formRenderedAt: number;
}

interface VerificationValues {
    pin: string;
}

type AuthMode = 'login' | 'signup' | 'verify';

const honeypotFieldClass =
    'pointer-events-none absolute left-[-10000px] top-[-10000px] h-0 w-0 overflow-hidden opacity-0';
const authInputClass =
    'burhan-auth-input w-full rounded-[1.55rem] border border-[rgba(255,255,255,0.075)] bg-[rgba(5,8,14,0.84)] px-[1.15rem] py-[1.1rem] text-base text-[color:var(--foreground)] outline-none transition-all placeholder:text-[rgba(151,160,171,0.75)]';
const authInputWithSuffixClass = `${authInputClass} pr-20`;
const authFieldLabelClass =
    'burhan-auth-label block text-[0.72rem] font-extrabold uppercase tracking-[0.18em] text-[rgba(248,246,239,0.68)]';
const authErrorClass = 'burhan-auth-error mt-1 text-[0.63rem] font-extrabold uppercase tracking-[0.12em] text-red-400';
const authPrimaryButtonClass =
    'burhan-auth-submit mt-1 flex min-h-[5rem] w-full items-center justify-center gap-2 rounded-[1.6rem] border border-[rgba(var(--primary-rgb),0.34)] text-[0.92rem] font-black uppercase tracking-[0.18em] text-[#0a0d10] transition-all';
const authSecondaryButtonClass =
    'burhan-auth-secondary flex min-h-[4rem] w-full items-center justify-center rounded-[1.6rem] border border-[rgba(255,255,255,0.08)] text-[0.88rem] font-extrabold uppercase tracking-[0.14em] text-[color:var(--foreground)] transition-all';

const LoginContainer = ({ history, location }: RouteComponentProps) => {
    const [showPassword, setShowPassword] = useState(false);
    const [showSignupPassword, setShowSignupPassword] = useState(false);
    const [showSignupConfirmationPassword, setShowSignupConfirmationPassword] = useState(false);
    const [mode, setMode] = useState<AuthMode>('login');
    const [verificationToken, setVerificationToken] = useState('');
    const [verificationIdentity, setVerificationIdentity] = useState('');
    const [requireCaptcha, setRequireCaptcha] = useState(false);
    const [captchaToken, setCaptchaToken] = useState('');
    const { name } = useSiteBranding();

    const { addFlash, clearFlashes, clearAndAddHttpError } = useFlash();
    const captcha = useStoreState((state) => state.settings.data!.captcha);
    const oauth = useStoreState((state) => state.settings.data?.oauth);

    const captchaEnabled = !!captcha?.enabled && captcha?.provider === 'turnstile' && !!captcha?.siteKey;
    const availableOAuthProviders = useMemo(
        () =>
            [
                oauth?.google?.enabled
                    ? { provider: 'google' as const, label: oauth.google.label, icon: 'fab fa-google' }
                    : null,
                oauth?.discord?.enabled
                    ? { provider: 'discord' as const, label: oauth.discord.label, icon: 'fab fa-discord' }
                    : null,
            ].filter((provider): provider is NonNullable<typeof provider> => !!provider),
        [oauth]
    );

    useEffect(() => {
        if (typeof clearFlashes === 'function') {
            clearFlashes();
        }
    }, [clearFlashes]);

    useEffect(() => {
        const search = new URLSearchParams(location.search);
        const status = search.get('oauth_status');
        const provider = search.get('oauth_provider');

        if (!status || !provider) {
            return;
        }

        const label = provider === 'discord' ? 'Discord' : 'Google';
        const flashes = {
            cancelled: {
                type: 'error' as const,
                title: 'Sign-in Cancelled',
                message: `${label} sign-in was cancelled before it completed.`,
            },
            disabled: {
                type: 'error' as const,
                title: 'Provider Disabled',
                message: `${label} OAuth is not available right now.`,
            },
            email_verification_required: {
                type: 'error' as const,
                title: 'Email Verification Required',
                message: 'Verify your panel account email before using OAuth login.',
            },
            failed: {
                type: 'error' as const,
                title: 'Sign-in Failed',
                message: `Unable to complete ${label} sign-in right now.`,
            },
            invalid_state: {
                type: 'error' as const,
                title: 'Session Expired',
                message: `The ${label} OAuth session expired. Start the sign-in flow again.`,
            },
            not_linked: {
                type: 'error' as const,
                title: 'Account Not Linked',
                message: `Link your ${label} account in Account Settings before using ${label} login.`,
            },
        } as const;

        const flash = flashes[status as keyof typeof flashes];
        if (flash && typeof addFlash === 'function') {
            addFlash(flash);
        }

        search.delete('oauth_status');
        search.delete('oauth_provider');
        if (typeof history.replace === 'function') {
            history.replace({
                pathname: location.pathname,
                search: search.toString() ? `?${search.toString()}` : '',
            });
        }
    }, [addFlash, history, location.pathname, location.search]);

    const switchMode = (next: 'login' | 'signup') => {
        if (typeof clearFlashes === 'function') {
            clearFlashes();
        }
        setMode(next);
        setVerificationToken('');
        setVerificationIdentity('');
        setRequireCaptcha(false);
        setCaptchaToken('');
    };

    const handleSecurityError = (error: any) => {
        const challengeRequired = !!error?.response?.data?.challenge_required;
        if (challengeRequired) {
            setRequireCaptcha(true);
        }

        if (typeof clearAndAddHttpError === 'function') {
            clearAndAddHttpError({ error });
        }
    };

    const onLoginSubmit = (values: LoginValues, { setSubmitting }: FormikHelpers<LoginValues>) => {
        if (typeof clearFlashes === 'function') {
            clearFlashes();
        }

        if (captchaEnabled && !captchaToken) {
            if (typeof addFlash === 'function') {
                addFlash({
                    type: 'error',
                    title: 'Verification Required',
                    message: 'Complete Cloudflare Turnstile verification before continuing.',
                });
            }
            setSubmitting(false);

            return;
        }

        login({
            username: values.username,
            password: values.password,
            captchaToken,
            website: values.website,
            company: values.company,
            formRenderedAt: values.formRenderedAt,
        })
            .then((response) => {
                setCaptchaToken('');

                if (response.complete) {
                    // @ts-expect-error this is valid
                    window.location = response.intended || '/';

                    return;
                }

                if (response.verificationRequired && response.verificationToken) {
                    setVerificationToken(response.verificationToken);
                    setVerificationIdentity(values.username);
                    setMode('verify');
                    setSubmitting(false);

                    return;
                }

                history.replace('/auth/login/checkpoint', { token: response.confirmationToken });
            })
            .catch((error) => {
                console.error(error);
                setCaptchaToken('');
                setSubmitting(false);
                handleSecurityError(error);
            });
    };

    const onSignupSubmit = (values: SignupValues, { setSubmitting }: FormikHelpers<SignupValues>) => {
        if (typeof clearFlashes === 'function') {
            clearFlashes();
        }

        if (captchaEnabled && requireCaptcha && !captchaToken) {
            if (typeof addFlash === 'function') {
                addFlash({
                    type: 'error',
                    title: 'Verification Required',
                    message: 'Complete the security verification before continuing.',
                });
            }
            setSubmitting(false);

            return;
        }

        signup({
            email: values.email,
            username: values.username,
            firstName: values.firstName,
            lastName: values.lastName,
            password: values.password,
            passwordConfirmation: values.passwordConfirmation,
            captchaToken,
            website: values.website,
            company: values.company,
            formRenderedAt: values.formRenderedAt,
        })
            .then((response) => {
                setCaptchaToken('');

                if (response.verificationRequired && response.verificationToken) {
                    setVerificationToken(response.verificationToken);
                    setVerificationIdentity(values.email);
                    setMode('verify');

                    return;
                }

                if (typeof addFlash === 'function') {
                    addFlash({
                        type: 'error',
                        title: 'Error',
                        message: 'Unable to start verification. Please try again.',
                    });
                }
            })
            .catch((error) => {
                console.error(error);
                handleSecurityError(error);
            })
            .finally(() => setSubmitting(false));
    };

    const onVerifySubmit = (values: VerificationValues, { setSubmitting }: FormikHelpers<VerificationValues>) => {
        if (typeof clearFlashes === 'function') {
            clearFlashes();
        }

        if (!verificationToken) {
            if (typeof addFlash === 'function') {
                addFlash({
                    type: 'error',
                    title: 'Error',
                    message: 'Verification session has expired. Please login again.',
                });
            }
            setMode('login');
            setSubmitting(false);

            return;
        }

        verifyEmailPin(verificationToken, values.pin)
            .then((response) => {
                if (response.complete) {
                    // @ts-expect-error this is valid
                    window.location = response.intended || '/';

                    return;
                }

                if (typeof addFlash === 'function') {
                    addFlash({
                        type: 'error',
                        title: 'Error',
                        message: 'Verification failed. Please try again.',
                    });
                }
            })
            .catch((error) => {
                console.error(error);
                if (typeof clearAndAddHttpError === 'function') {
                    clearAndAddHttpError({ error });
                }
            })
            .finally(() => setSubmitting(false));
    };

    const getTabClassName = (tab: Extract<AuthMode, 'login' | 'signup'>) =>
        `burhan-auth-tab ${mode === tab ? 'is-active' : ''}`;

    return (
        <div className='burhan-auth-stage fixed inset-0 z-50 flex h-[100dvh] w-full overflow-hidden text-[color:var(--foreground)]'>
            <style>{`
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
                    scrollbar-gutter: stable both-edges;
                    -webkit-overflow-scrolling: touch;
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
                    touch-action: pan-y !important;
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
                    font-size: clamp(2.7rem, 5vw, 4rem);
                    font-weight: 800;
                    line-height: 0.9;
                    letter-spacing: -0.06em;
                    text-shadow: 0 0 18px rgba(248, 246, 239, 0.28);
                    text-transform: uppercase;
                    word-break: break-word;
                }

                .burhan-auth-switch {
                    display: grid;
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                    gap: 0.75rem;
                    margin-bottom: 1.1rem;
                    padding: 0.55rem;
                    border-radius: 1.75rem;
                    border: 1px solid rgba(255, 255, 255, 0.06);
                    background:
                        linear-gradient(180deg, rgba(255, 255, 255, 0.025), transparent 40%),
                        rgba(255, 255, 255, 0.02);
                    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.04);
                }

                .burhan-auth-tab {
                    border: none;
                    border-radius: 1.1rem;
                    padding: 0.95rem 1rem;
                    background: linear-gradient(180deg, rgba(255, 255, 255, 0.03), rgba(255, 255, 255, 0.015));
                    color: rgba(248, 246, 239, 0.62);
                    font-size: 0.9rem;
                    font-weight: 800;
                    letter-spacing: 0.16em;
                    text-transform: uppercase;
                    transition: all 0.25s ease;
                    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.04), inset 0 -12px 22px rgba(0, 0, 0, 0.18);
                }

                .burhan-auth-tab.is-active {
                    color: #eff7dc;
                    border: 1px solid rgba(var(--primary-rgb), 0.34);
                    background: linear-gradient(90deg, rgba(var(--primary-rgb), 0.3), rgba(var(--primary-rgb), 0.14));
                    box-shadow:
                        inset 0 1px 0 rgba(255, 255, 255, 0.12),
                        0 0 0 1px rgba(var(--primary-rgb), 0.08),
                        0 18px 28px rgba(var(--primary-rgb), 0.14),
                        0 0 36px rgba(var(--primary-rgb), 0.12);
                }

                .burhan-auth-tab:not(.is-active):hover {
                    color: rgba(248, 246, 239, 0.82);
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

                .burhan-auth-overlay {
                    background: rgba(4, 6, 10, 0.82);
                    backdrop-filter: blur(10px);
                }

                .burhan-auth-overlay-card {
                    border-radius: 1.5rem;
                    border: 1px solid rgba(255, 255, 255, 0.08);
                    background:
                        linear-gradient(180deg, rgba(255, 255, 255, 0.035), transparent 30%),
                        rgba(9, 12, 18, 0.98);
                    box-shadow: 0 30px 60px rgba(0, 0, 0, 0.45), inset 0 1px 0 rgba(255, 255, 255, 0.04);
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
                    border-top: 1px solid rgba(255, 255, 255, 0.08);
                }

                .burhan-auth-divider span {
                    position: relative;
                    z-index: 1;
                    display: inline-block;
                    padding: 0 0.85rem;
                    background: rgba(7, 10, 15, 0.92);
                    color: rgba(248, 246, 239, 0.62);
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
                    background:
                        linear-gradient(180deg, rgba(255, 255, 255, 0.03), transparent 40%),
                        rgba(5, 8, 14, 0.84);
                    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.04), inset 0 -12px 20px rgba(0, 0, 0, 0.18);
                }

                .burhan-auth-provider:hover {
                    border-color: rgba(var(--primary-rgb), 0.24);
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
                    background: rgba(255, 255, 255, 0.05);
                    color: var(--foreground);
                    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.05);
                }

                .burhan-auth-provider-icon.is-discord {
                    color: #8c94ff;
                }

                .burhan-auth-provider-label {
                    min-width: 0;
                    overflow: hidden;
                    text-overflow: ellipsis;
                    white-space: nowrap;
                    color: var(--foreground);
                    font-size: 0.98rem;
                    font-weight: 700;
                }

                .burhan-auth-provider-arrow {
                    color: rgba(248, 246, 239, 0.68);
                    font-size: 0.95rem;
                }

                .burhan-auth-provider:hover .burhan-auth-provider-arrow {
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

            `}</style>
            <div className='burhan-auth-backdrop hidden h-full w-[70%] lg:block'>
                <span className='sr-only'>Dark neon background area.</span>
            </div>
            <div className='burhan-auth-rail h-full w-full overflow-y-auto px-6 py-5 sm:px-10 sm:py-6 md:px-14 lg:w-[30%] lg:overflow-y-auto lg:px-8 lg:py-4 xl:px-10'>
                <div
                    className={`burhan-auth-shell mx-auto flex h-full min-h-0 w-full max-w-[32rem] flex-col py-0 ${
                        mode === 'signup' ? 'justify-start' : 'justify-center'
                    }`}
                >
                    <FlashMessageRender className='burhan-auth-flash mb-4 px-1' />

                    <GlowCard
                        glowColor='green'
                        customSize
                        orbit
                        orbitDurationMs={2800}
                        className={`burhan-auth-glow w-full ${mode === 'signup' ? '' : 'max-h-full'}`}
                    >
                        <div className='burhan-auth-card'>
                            <div className='burhan-auth-brand-panel'>
                                <h1 className='burhan-auth-title'>{name}</h1>
                            </div>

                            {mode !== 'verify' && (
                                <div className='burhan-auth-switch'>
                                    <button
                                        type='button'
                                        onClick={() => switchMode('login')}
                                        className={getTabClassName('login')}
                                    >
                                        LOG IN
                                    </button>
                                    <button
                                        type='button'
                                        onClick={() => switchMode('signup')}
                                        className={getTabClassName('signup')}
                                    >
                                        SIGN UP
                                    </button>
                                </div>
                            )}

                            {mode === 'login' && (
                                <Formik
                                    onSubmit={onLoginSubmit}
                                    initialValues={{
                                        username: '',
                                        password: '',
                                        website: '',
                                        company: '',
                                        formRenderedAt: Date.now(),
                                    }}
                                    validationSchema={object().shape({
                                        username: string().required('A username or email must be provided.'),
                                        password: string().required('Please enter your account password.'),
                                    })}
                                >
                                    {({
                                        isSubmitting,
                                        values,
                                        errors,
                                        touched,
                                        handleChange,
                                        handleBlur,
                                    }: FormikProps<LoginValues>) => {
                                        const lockLoginForm = captchaEnabled && !captchaToken;

                                        return (
                                            <Form className='burhan-auth-form relative'>
                                                <div className={honeypotFieldClass} aria-hidden='true'>
                                                    <label htmlFor='website'>Website</label>
                                                    <input
                                                        id='website'
                                                        name='website'
                                                        type='text'
                                                        value={values.website}
                                                        onChange={handleChange}
                                                        tabIndex={-1}
                                                        autoComplete='off'
                                                    />
                                                    <label htmlFor='company'>Company</label>
                                                    <input
                                                        id='company'
                                                        name='company'
                                                        type='text'
                                                        value={values.company}
                                                        onChange={handleChange}
                                                        tabIndex={-1}
                                                        autoComplete='off'
                                                    />
                                                </div>
                                                <input
                                                    type='hidden'
                                                    name='formRenderedAt'
                                                    value={values.formRenderedAt}
                                                />

                                                <div>
                                                    <label htmlFor='user' className={authFieldLabelClass}>
                                                        Email or Username
                                                    </label>
                                                    <div className='burhan-auth-input-wrap'>
                                                        <input
                                                            name='username'
                                                            type='text'
                                                            value={values.username}
                                                            onChange={handleChange}
                                                            onBlur={handleBlur}
                                                            disabled={isSubmitting || lockLoginForm}
                                                            id='user'
                                                            autoComplete='username'
                                                            className={authInputWithSuffixClass}
                                                            placeholder='Enter your credentials'
                                                        />
                                                        <span className='burhan-auth-field-token'>USER</span>
                                                    </div>
                                                    {errors.username && touched.username && (
                                                        <div className={authErrorClass}>{errors.username}</div>
                                                    )}
                                                </div>

                                                <div>
                                                    <div className='burhan-auth-label-row'>
                                                        <label htmlFor='password' className={authFieldLabelClass}>
                                                            Password
                                                        </label>
                                                        <Link to={'/auth/password'} className='burhan-auth-meta-link'>
                                                            Forgot?
                                                        </Link>
                                                    </div>
                                                    <div className='burhan-auth-input-wrap'>
                                                        <input
                                                            name='password'
                                                            type={showPassword ? 'text' : 'password'}
                                                            value={values.password}
                                                            onChange={handleChange}
                                                            onBlur={handleBlur}
                                                            disabled={isSubmitting || lockLoginForm}
                                                            id='password'
                                                            autoComplete='current-password'
                                                            className={authInputWithSuffixClass}
                                                            placeholder='........'
                                                        />
                                                        <button
                                                            type='button'
                                                            onClick={() => setShowPassword(!showPassword)}
                                                            className='burhan-auth-field-token is-button'
                                                        >
                                                            {showPassword ? 'HIDE' : 'SHOW'}
                                                        </button>
                                                    </div>
                                                    {errors.password && touched.password && (
                                                        <div className={authErrorClass}>{errors.password}</div>
                                                    )}
                                                </div>

                                                <button
                                                    type='submit'
                                                    disabled={isSubmitting || lockLoginForm}
                                                    className={authPrimaryButtonClass}
                                                >
                                                    Log In
                                                    <i className='fa-solid fa-arrow-right-long text-sm' />
                                                </button>

                                                {lockLoginForm && (
                                                    <div className='burhan-auth-overlay absolute inset-0 z-20 flex items-center justify-center rounded-[1.6rem] px-5 text-center'>
                                                        <div className='burhan-auth-overlay-card w-full max-w-xs p-5'>
                                                            <p className='mb-3 text-xs font-semibold uppercase tracking-[0.18em] text-[color:var(--foreground)]'>
                                                                Verify Before Login
                                                            </p>
                                                            <p className='mb-4 text-xs leading-6 text-[color:var(--muted-foreground)]'>
                                                                Complete the Cloudflare Turnstile check first. Login
                                                                fields unlock after verification.
                                                            </p>
                                                            <div className='flex justify-center'>
                                                                <TurnstileWidget
                                                                    siteKey={captcha.siteKey}
                                                                    onVerify={(token) => setCaptchaToken(token)}
                                                                    onExpire={() => setCaptchaToken('')}
                                                                    onError={() => setCaptchaToken('')}
                                                                    className='flex justify-center'
                                                                />
                                                            </div>
                                                        </div>
                                                    </div>
                                                )}
                                            </Form>
                                        );
                                    }}
                                </Formik>
                            )}

                            {mode === 'signup' && (
                                <Formik
                                    onSubmit={onSignupSubmit}
                                    initialValues={{
                                        email: '',
                                        username: '',
                                        firstName: '',
                                        lastName: '',
                                        password: '',
                                        passwordConfirmation: '',
                                        website: '',
                                        company: '',
                                        formRenderedAt: Date.now(),
                                    }}
                                    validationSchema={object().shape({
                                        email: string()
                                            .email('Please use a valid email.')
                                            .required('Email is required.'),
                                        username: string().min(3).max(191).required('Username is required.'),
                                        firstName: string().required('First name is required.'),
                                        lastName: string().required('Last name is required.'),
                                        password: string()
                                            .min(8, 'Password must be at least 8 characters.')
                                            .required('Password is required.'),
                                        passwordConfirmation: string()
                                            .oneOf([yupRef('password')], 'Password confirmation does not match.')
                                            .required('Please confirm your password.'),
                                    })}
                                >
                                    {({
                                        isSubmitting,
                                        values,
                                        errors,
                                        touched,
                                        handleChange,
                                        handleBlur,
                                    }: FormikProps<SignupValues>) => (
                                        <Form className='burhan-auth-form'>
                                            <div className={honeypotFieldClass} aria-hidden='true'>
                                                <label htmlFor='signup-website'>Website</label>
                                                <input
                                                    id='signup-website'
                                                    name='website'
                                                    type='text'
                                                    value={values.website}
                                                    onChange={handleChange}
                                                    tabIndex={-1}
                                                    autoComplete='off'
                                                />
                                                <label htmlFor='signup-company'>Company</label>
                                                <input
                                                    id='signup-company'
                                                    name='company'
                                                    type='text'
                                                    value={values.company}
                                                    onChange={handleChange}
                                                    tabIndex={-1}
                                                    autoComplete='off'
                                                />
                                            </div>
                                            <input type='hidden' name='formRenderedAt' value={values.formRenderedAt} />

                                            <div>
                                                <label htmlFor='signup-email' className={authFieldLabelClass}>
                                                    Email
                                                </label>
                                                <input
                                                    id='signup-email'
                                                    name='email'
                                                    type='email'
                                                    value={values.email}
                                                    onChange={handleChange}
                                                    onBlur={handleBlur}
                                                    disabled={isSubmitting}
                                                    className={authInputClass}
                                                />
                                                {errors.email && touched.email && (
                                                    <div className={authErrorClass}>{errors.email}</div>
                                                )}
                                            </div>

                                            <div>
                                                <label htmlFor='signup-username' className={authFieldLabelClass}>
                                                    Username
                                                </label>
                                                <input
                                                    id='signup-username'
                                                    name='username'
                                                    type='text'
                                                    value={values.username}
                                                    onChange={handleChange}
                                                    onBlur={handleBlur}
                                                    disabled={isSubmitting}
                                                    className={authInputClass}
                                                />
                                                {errors.username && touched.username && (
                                                    <div className={authErrorClass}>{errors.username}</div>
                                                )}
                                            </div>

                                            <div className='grid grid-cols-2 gap-3'>
                                                <div>
                                                    <label htmlFor='signup-first-name' className={authFieldLabelClass}>
                                                        First Name
                                                    </label>
                                                    <input
                                                        id='signup-first-name'
                                                        name='firstName'
                                                        type='text'
                                                        value={values.firstName}
                                                        onChange={handleChange}
                                                        onBlur={handleBlur}
                                                        disabled={isSubmitting}
                                                        className={authInputClass}
                                                    />
                                                    {errors.firstName && touched.firstName && (
                                                        <div className={authErrorClass}>{errors.firstName}</div>
                                                    )}
                                                </div>

                                                <div>
                                                    <label htmlFor='signup-last-name' className={authFieldLabelClass}>
                                                        Last Name
                                                    </label>
                                                    <input
                                                        id='signup-last-name'
                                                        name='lastName'
                                                        type='text'
                                                        value={values.lastName}
                                                        onChange={handleChange}
                                                        onBlur={handleBlur}
                                                        disabled={isSubmitting}
                                                        className={authInputClass}
                                                    />
                                                    {errors.lastName && touched.lastName && (
                                                        <div className={authErrorClass}>{errors.lastName}</div>
                                                    )}
                                                </div>
                                            </div>

                                            <div>
                                                <div className='burhan-auth-label-row'>
                                                    <label htmlFor='signup-password' className={authFieldLabelClass}>
                                                        Password
                                                    </label>
                                                </div>
                                                <div className='burhan-auth-input-wrap'>
                                                    <input
                                                        name='password'
                                                        id='signup-password'
                                                        type={showSignupPassword ? 'text' : 'password'}
                                                        value={values.password}
                                                        onChange={handleChange}
                                                        onBlur={handleBlur}
                                                        disabled={isSubmitting}
                                                        className={authInputWithSuffixClass}
                                                    />
                                                    <button
                                                        type='button'
                                                        onClick={() => setShowSignupPassword(!showSignupPassword)}
                                                        className='burhan-auth-field-token is-button'
                                                    >
                                                        {showSignupPassword ? 'HIDE' : 'SHOW'}
                                                    </button>
                                                </div>
                                                {errors.password && touched.password && (
                                                    <div className={authErrorClass}>{errors.password}</div>
                                                )}
                                            </div>

                                            <div>
                                                <label
                                                    htmlFor='signup-password-confirmation'
                                                    className={authFieldLabelClass}
                                                >
                                                    Confirm Password
                                                </label>
                                                <div className='burhan-auth-input-wrap'>
                                                    <input
                                                        name='passwordConfirmation'
                                                        id='signup-password-confirmation'
                                                        type={showSignupConfirmationPassword ? 'text' : 'password'}
                                                        value={values.passwordConfirmation}
                                                        onChange={handleChange}
                                                        onBlur={handleBlur}
                                                        disabled={isSubmitting}
                                                        className={authInputWithSuffixClass}
                                                    />
                                                    <button
                                                        type='button'
                                                        onClick={() =>
                                                            setShowSignupConfirmationPassword(
                                                                !showSignupConfirmationPassword
                                                            )
                                                        }
                                                        className='burhan-auth-field-token is-button'
                                                    >
                                                        {showSignupConfirmationPassword ? 'HIDE' : 'SHOW'}
                                                    </button>
                                                </div>
                                                {errors.passwordConfirmation && touched.passwordConfirmation && (
                                                    <div className={authErrorClass}>{errors.passwordConfirmation}</div>
                                                )}
                                            </div>

                                            <button
                                                type='submit'
                                                disabled={isSubmitting}
                                                className={authPrimaryButtonClass}
                                            >
                                                Sign Up
                                                <i className='fa-solid fa-arrow-right-long text-sm' />
                                            </button>

                                            {captchaEnabled && requireCaptcha && (
                                                <div className='pt-2'>
                                                    <TurnstileWidget
                                                        siteKey={captcha.siteKey}
                                                        onVerify={(token) => setCaptchaToken(token)}
                                                        onExpire={() => setCaptchaToken('')}
                                                        onError={() => setCaptchaToken('')}
                                                        className='flex justify-center'
                                                    />
                                                </div>
                                            )}
                                        </Form>
                                    )}
                                </Formik>
                            )}

                            {mode === 'verify' && (
                                <Formik
                                    onSubmit={onVerifySubmit}
                                    initialValues={{ pin: '' }}
                                    validationSchema={object().shape({
                                        pin: string()
                                            .matches(/^[0-9]{6}$/, 'Please enter a valid 6-digit pin.')
                                            .required('Verification pin is required.'),
                                    })}
                                >
                                    {({
                                        isSubmitting,
                                        values,
                                        errors,
                                        touched,
                                        handleChange,
                                        handleBlur,
                                    }: FormikProps<VerificationValues>) => (
                                        <Form className='burhan-auth-form'>
                                            <div className='burhan-auth-pin-copy text-sm'>
                                                Enter the 6-digit verification code sent to{' '}
                                                <span className='text-[color:var(--foreground)]'>
                                                    {verificationIdentity}
                                                </span>
                                                .
                                            </div>
                                            <div>
                                                <label htmlFor='verification-pin' className={authFieldLabelClass}>
                                                    Verification PIN
                                                </label>
                                                <input
                                                    name='pin'
                                                    id='verification-pin'
                                                    type='text'
                                                    inputMode='numeric'
                                                    maxLength={6}
                                                    value={values.pin}
                                                    onChange={handleChange}
                                                    onBlur={handleBlur}
                                                    disabled={isSubmitting}
                                                    className={`${authInputClass} is-centered`}
                                                    placeholder='000000'
                                                />
                                                {errors.pin && touched.pin && (
                                                    <div className={authErrorClass}>{errors.pin}</div>
                                                )}
                                            </div>
                                            <button
                                                type='submit'
                                                disabled={isSubmitting}
                                                className={authPrimaryButtonClass}
                                            >
                                                Verify Account
                                                <i className='fa-solid fa-check text-sm' />
                                            </button>
                                            <button
                                                type='button'
                                                onClick={() => switchMode('login')}
                                                className={authSecondaryButtonClass}
                                            >
                                                Back to Login
                                            </button>
                                        </Form>
                                    )}
                                </Formik>
                            )}

                            {mode === 'login' && availableOAuthProviders.length > 0 && (
                                <>
                                    <div className='burhan-auth-divider'>
                                        <span>Or continue with</span>
                                    </div>

                                    <div className='burhan-auth-provider-list'>
                                        {availableOAuthProviders.map((provider) => (
                                            <button
                                                key={provider.provider}
                                                type='button'
                                                onClick={() =>
                                                    window.location.assign(
                                                        `/oauth/${provider.provider}/redirect?intent=login`
                                                    )
                                                }
                                                className='burhan-auth-provider group w-full rounded-[1.6rem] border border-[rgba(255,255,255,0.075)] transition-all'
                                            >
                                                <span className='burhan-auth-provider-main'>
                                                    <span
                                                        className={`burhan-auth-provider-icon ${
                                                            provider.provider === 'discord' ? 'is-discord' : ''
                                                        }`}
                                                    >
                                                        <i className={provider.icon} />
                                                    </span>
                                                    <span className='burhan-auth-provider-label'>
                                                        Continue with {provider.label}
                                                    </span>
                                                </span>
                                                <i className='fa-solid fa-arrow-right-long burhan-auth-provider-arrow' />
                                            </button>
                                        ))}
                                    </div>
                                </>
                            )}
                        </div>
                    </GlowCard>
                </div>
            </div>
        </div>
    );
};

export default LoginContainer;
