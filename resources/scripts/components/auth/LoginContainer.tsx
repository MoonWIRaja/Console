import React, { useEffect, useMemo, useState } from 'react';
import { Link, RouteComponentProps } from 'react-router-dom';
import login from '@/api/auth/login';
import signup from '@/api/auth/signup';
import verifyEmailPin from '@/api/auth/verifyEmailPin';
import getPendingSignup from '@/api/auth/getPendingSignup';
import { PendingSignupData } from '@/api/auth/types';
import { useStoreState } from 'easy-peasy';
import { Form, Formik, FormikHelpers, FormikProps } from 'formik';
import { object, ref as yupRef, string } from 'yup';
import useFlash from '@/plugins/useFlash';
import FlashMessageRender from '@/components/FlashMessageRender';
import AuthTopbar from '@/components/auth/AuthTopbar';
import AuthBackdropGame from '@/components/auth/AuthBackdropGame';
import AuthBrandPanel from '@/components/auth/AuthBrandPanel';
import {
    authErrorClass,
    authFieldLabelClass,
    authInputClass,
    authInputWithSuffixClass,
    authPrimaryButtonClass,
    authSecondaryButtonClass,
    authTurnstileCopyClass,
    authTurnstileErrorClass,
    authTurnstileTitleClass,
    burhanAuthThemeStyles,
    honeypotFieldClass,
} from '@/components/auth/authTheme';
import { GlowCard } from '@/components/ui/spotlight-card';
import TurnstileWidget from '@/components/auth/TurnstileWidget';

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

type AuthMode = 'login' | 'signup' | 'verify' | 'signup_onboarding';

const LoginContainer = ({ history, location }: RouteComponentProps) => {
    const [showPassword, setShowPassword] = useState(false);
    const [showSignupPassword, setShowSignupPassword] = useState(false);
    const [showSignupConfirmationPassword, setShowSignupConfirmationPassword] = useState(false);
    const [mode, setMode] = useState<AuthMode>('login');
    const [pendingSignup, setPendingSignup] = useState<PendingSignupData | null>(null);
    const [verificationToken, setVerificationToken] = useState('');
    const [verificationIdentity, setVerificationIdentity] = useState('');
    const [requireCaptcha, setRequireCaptcha] = useState(false);
    const [captchaToken, setCaptchaToken] = useState('');
    const [captchaWidgetFailed, setCaptchaWidgetFailed] = useState(false);
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
    const pendingProvider = useMemo(() => {
        if (!pendingSignup) {
            return null;
        }

        if (pendingSignup.stage === 'pending_google_link') {
            return {
                provider: 'google' as const,
                label: 'Google',
                actionLabel: 'Link Google',
                description:
                    'Continue with the Google account that uses the exact same email address as your signup form.',
                linkUrl: pendingSignup.googleLinkUrl,
                available: pendingSignup.googleAvailable,
            };
        }

        if (pendingSignup.stage === 'pending_discord_link') {
            return {
                provider: 'discord' as const,
                label: 'Discord',
                actionLabel: 'Link Discord',
                description: 'Google is linked. Link your Discord account next before email verification unlocks.',
                linkUrl: pendingSignup.discordLinkUrl,
                available: pendingSignup.discordAvailable,
            };
        }

        return null;
    }, [pendingSignup]);

    useEffect(() => {
        if (typeof clearFlashes === 'function') {
            clearFlashes();
        }
    }, [clearFlashes]);

    useEffect(() => {
        let active = true;

        getPendingSignup()
            .then((data) => {
                if (!active) {
                    return;
                }

                if (!data) {
                    setPendingSignup(null);

                    if (mode === 'signup_onboarding') {
                        setMode('login');
                    }

                    return;
                }

                setPendingSignup(data);
                setVerificationIdentity(data.email);
                setVerificationToken(data.verificationToken || '');
                setMode(data.stage === 'pending_email_verification' ? 'verify' : 'signup_onboarding');
            })
            .catch(() => undefined);

        return () => {
            active = false;
        };
    }, [location.search]);

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
                title: 'Flow Cancelled',
                message: `${label} authorization was cancelled before it completed.`,
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
                title: 'OAuth Failed',
                message: `Unable to complete ${label} authorization right now.`,
            },
            invalid_state: {
                type: 'error' as const,
                title: 'Session Expired',
                message: `The ${label} OAuth session expired. Start the flow again.`,
            },
            not_linked: {
                type: 'error' as const,
                title: 'Account Not Linked',
                message: `Link your ${label} account in Account Settings before using ${label} login.`,
            },
            signup_google_linked: {
                type: 'success' as const,
                title: 'Google Linked',
                message: 'Google is linked. Continue by linking your Discord account.',
            },
            signup_google_email_mismatch: {
                type: 'error' as const,
                title: 'Google Email Mismatch',
                message:
                    'The Google email does not match the email you used in the signup form, so linking was rejected.',
            },
            signup_google_conflict: {
                type: 'error' as const,
                title: 'Google Already Linked',
                message: 'That Google account is already linked to another panel user.',
            },
            signup_discord_linked: {
                type: 'success' as const,
                title: 'Discord Linked',
                message: 'Discord is linked. Verify your email PIN to finish creating the account.',
            },
            signup_discord_conflict: {
                type: 'error' as const,
                title: 'Discord Already Linked',
                message: 'That Discord account is already linked to another panel user.',
            },
            signup_incomplete: {
                type: 'error' as const,
                title: 'Complete Signup First',
                message: 'Finish the Google, Discord, and email verification steps before logging in.',
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
        setPendingSignup(null);
        setMode(next);
        setVerificationToken('');
        setVerificationIdentity('');
        setRequireCaptcha(false);
        setCaptchaToken('');
        setCaptchaWidgetFailed(false);
    };

    const setPendingSignupState = (data: PendingSignupData) => {
        setPendingSignup(data);
        setVerificationIdentity(data.email);
        setVerificationToken(data.verificationToken || '');
        setRequireCaptcha(false);
        setCaptchaToken('');
        setCaptchaWidgetFailed(false);
        setMode(data.stage === 'pending_email_verification' ? 'verify' : 'signup_onboarding');
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

    const handleCaptchaVerified = (token: string) => {
        setCaptchaWidgetFailed(false);
        setCaptchaToken(token);
    };

    const handleCaptchaError = () => {
        setCaptchaToken('');
        setCaptchaWidgetFailed(true);

        if (typeof addFlash === 'function') {
            addFlash({
                type: 'error',
                title: 'Verification Unavailable',
                message:
                    'Cloudflare Turnstile could not initialize for this hostname. If this panel is not running behind a Cloudflare zone, disable Turnstile pre-clearance in the Cloudflare widget settings.',
            });
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

                if (response.pendingSignup) {
                    setPendingSignupState(response.pendingSignup);
                    setSubmitting(false);

                    return;
                }

                if (response.verificationRequired && response.verificationToken) {
                    setPendingSignup(null);
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

                if (response.pendingSignup) {
                    setPendingSignupState(response.pendingSignup);

                    return;
                }

                if (response.verificationRequired && response.verificationToken) {
                    setPendingSignup(null);
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
            setPendingSignup(null);
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
        <div className='burhan-auth-stage burhan-auth-stage-game fixed inset-0 z-50 flex h-[100dvh] w-full overflow-hidden text-[color:var(--foreground)]'>
            <style>{burhanAuthThemeStyles}</style>
            <AuthTopbar />
            <AuthBackdropGame className='burhan-auth-backdrop-full' />
            <div className='burhan-auth-rail burhan-auth-rail-floating h-full w-full overflow-y-auto px-6 pb-5 pt-24 sm:px-10 sm:pb-6 sm:pt-24 md:px-14 lg:w-full lg:overflow-y-auto lg:px-8 lg:pb-4 lg:pt-24 xl:px-10'>
                <div className='burhan-auth-shell burhan-auth-shell-floating mx-auto flex h-full min-h-0 w-full max-w-[32rem] flex-col justify-center py-0'>
                    <FlashMessageRender className='burhan-auth-flash mb-4 px-1' />

                    <GlowCard
                        glowColor='green'
                        customSize
                        orbit
                        orbitDurationMs={2800}
                        className={`burhan-auth-glow w-full ${mode === 'signup' ? '' : 'max-h-full'}`}
                    >
                        <div className='burhan-auth-card'>
                            <AuthBrandPanel />

                            {(mode === 'login' || mode === 'signup') && (
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
                                                            <p className={authTurnstileTitleClass}>
                                                                Verify Before Login
                                                            </p>
                                                            <p className={`mb-4 ${authTurnstileCopyClass}`}>
                                                                Complete the Cloudflare Turnstile check first. Login
                                                                fields unlock after verification.
                                                            </p>
                                                            <div className='flex justify-center'>
                                                                <TurnstileWidget
                                                                    siteKey={captcha.siteKey}
                                                                    onVerify={handleCaptchaVerified}
                                                                    onExpire={() => setCaptchaToken('')}
                                                                    onError={handleCaptchaError}
                                                                    className='flex justify-center'
                                                                />
                                                            </div>
                                                            {captchaWidgetFailed && (
                                                                <p
                                                                    className={`mt-4 text-[11px] ${authTurnstileErrorClass}`}
                                                                >
                                                                    Verification is blocked by the current Turnstile
                                                                    hostname configuration.
                                                                    <br />
                                                                    Contact the panel administrator if this keeps
                                                                    happening.
                                                                </p>
                                                            )}
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

                                            <p className='text-xs leading-6 text-gray-400'>
                                                New signups must link Google and Discord before the first login. Google
                                                must use the same email address as this signup form. If either provider
                                                is unavailable, signup falls back to email verification only.
                                            </p>

                                            {captchaEnabled && requireCaptcha && (
                                                <div className='pt-2'>
                                                    <TurnstileWidget
                                                        siteKey={captcha.siteKey}
                                                        onVerify={handleCaptchaVerified}
                                                        onExpire={() => setCaptchaToken('')}
                                                        onError={handleCaptchaError}
                                                        className='flex justify-center'
                                                    />
                                                    {captchaWidgetFailed && (
                                                        <p className={authTurnstileErrorClass}>
                                                            Verification could not be loaded on this hostname. Check the
                                                            Turnstile widget configuration in Cloudflare.
                                                        </p>
                                                    )}
                                                </div>
                                            )}
                                        </Form>
                                    )}
                                </Formik>
                            )}

                            {mode === 'signup_onboarding' && pendingSignup && pendingProvider && (
                                <div className='burhan-auth-form'>
                                    <div className='burhan-auth-pin-copy text-sm'>
                                        Account created for{' '}
                                        <span className='text-[color:var(--foreground)]'>{pendingSignup.email}</span>.
                                        <div className='mt-2'>{pendingProvider.description}</div>
                                    </div>

                                    <div className='rounded-[1.6rem] border border-[rgba(255,255,255,0.08)] bg-[rgba(255,255,255,0.03)] p-4 text-sm text-gray-300'>
                                        <div className='flex items-center justify-between gap-3'>
                                            <span>Google</span>
                                            <span
                                                className={`rounded-full px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.24em] ${
                                                    pendingSignup.googleLinked
                                                        ? 'bg-emerald-500/10 text-emerald-300'
                                                        : 'bg-white/5 text-gray-400'
                                                }`}
                                            >
                                                {pendingSignup.googleLinked ? 'Linked' : 'Pending'}
                                            </span>
                                        </div>
                                        <div className='mt-3 flex items-center justify-between gap-3'>
                                            <span>Discord</span>
                                            <span
                                                className={`rounded-full px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.24em] ${
                                                    pendingSignup.discordLinked
                                                        ? 'bg-emerald-500/10 text-emerald-300'
                                                        : 'bg-white/5 text-gray-400'
                                                }`}
                                            >
                                                {pendingSignup.discordLinked ? 'Linked' : 'Pending'}
                                            </span>
                                        </div>
                                    </div>

                                    <button
                                        type='button'
                                        onClick={() => window.location.assign(pendingProvider.linkUrl)}
                                        disabled={!pendingProvider.available || !pendingProvider.linkUrl}
                                        className={authPrimaryButtonClass}
                                    >
                                        {pendingProvider.actionLabel}
                                        <i className='fa-solid fa-arrow-right-long text-sm' />
                                    </button>

                                    {!pendingProvider.available && (
                                        <p className={authTurnstileErrorClass}>
                                            {pendingProvider.label} OAuth is unavailable right now. This signup is still
                                            pending until the provider is available again.
                                        </p>
                                    )}
                                </div>
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
                                                {pendingSignup ? 'Google and Discord are linked. ' : 'Enter '}
                                                {!pendingSignup && 'the 6-digit verification code sent to '}
                                                {pendingSignup && 'Enter the 6-digit verification code sent to '}
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
                                            {!pendingSignup && (
                                                <button
                                                    type='button'
                                                    onClick={() => switchMode('login')}
                                                    className={authSecondaryButtonClass}
                                                >
                                                    Back to Login
                                                </button>
                                            )}
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
