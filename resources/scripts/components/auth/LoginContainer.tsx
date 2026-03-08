import React, { useEffect, useState } from 'react';
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

const honeypotFieldClass = 'pointer-events-none absolute left-[-10000px] top-[-10000px] h-0 w-0 overflow-hidden opacity-0';

const LoginContainer = ({ history }: RouteComponentProps) => {
    const [showPassword, setShowPassword] = useState(false);
    const [showSignupPassword, setShowSignupPassword] = useState(false);
    const [showSignupConfirmationPassword, setShowSignupConfirmationPassword] = useState(false);
    const [mode, setMode] = useState<AuthMode>('login');
    const [verificationToken, setVerificationToken] = useState('');
    const [verificationIdentity, setVerificationIdentity] = useState('');
    const [requireCaptcha, setRequireCaptcha] = useState(false);
    const [captchaToken, setCaptchaToken] = useState('');

    const { addFlash, clearFlashes, clearAndAddHttpError } = useFlash();
    const captcha = useStoreState((state) => state.settings.data!.captcha);

    const captchaEnabled = !!captcha?.enabled && captcha?.provider === 'turnstile' && !!captcha?.siteKey;

    useEffect(() => {
        clearFlashes();
    }, []);

    const switchMode = (next: 'login' | 'signup') => {
        clearFlashes();
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

        clearAndAddHttpError({ error });
    };

    const onLoginSubmit = (values: LoginValues, { setSubmitting }: FormikHelpers<LoginValues>) => {
        clearFlashes();

        if (captchaEnabled && !captchaToken) {
            addFlash({
                type: 'error',
                title: 'Verification Required',
                message: 'Complete Cloudflare Turnstile verification before continuing.',
            });
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
        clearFlashes();

        if (captchaEnabled && requireCaptcha && !captchaToken) {
            addFlash({
                type: 'error',
                title: 'Verification Required',
                message: 'Complete the security verification before continuing.',
            });
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

                addFlash({
                    type: 'error',
                    title: 'Error',
                    message: 'Unable to start verification. Please try again.',
                });
            })
            .catch((error) => {
                console.error(error);
                handleSecurityError(error);
            })
            .finally(() => setSubmitting(false));
    };

    const onVerifySubmit = (values: VerificationValues, { setSubmitting }: FormikHelpers<VerificationValues>) => {
        clearFlashes();

        if (!verificationToken) {
            addFlash({
                type: 'error',
                title: 'Error',
                message: 'Verification session has expired. Please login again.',
            });
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

                addFlash({
                    type: 'error',
                    title: 'Error',
                    message: 'Verification failed. Please try again.',
                });
            })
            .catch((error) => {
                console.error(error);
                clearAndAddHttpError({ error });
            })
            .finally(() => setSubmitting(false));
    };

    return (
        <div className='fixed inset-0 z-50 flex h-screen w-full overflow-hidden bg-[color:var(--background)] text-[color:var(--foreground)]'>
            <style>{`
                @import url('https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css');
                .font-mono {
                    font-family: 'Space Mono', monospace;
                }
            `}</style>
            <div className='hidden h-full w-[70%] bg-[color:var(--background)] font-mono lg:block'>
                <span className='sr-only'>Dark neon background area.</span>
            </div>
            <div className='w-full overflow-y-auto bg-[color:var(--background)] px-8 font-mono sm:px-12 md:px-16 lg:w-[30%] lg:px-10 xl:px-12'>
                <div className='mx-auto flex h-full w-full max-w-md flex-col justify-center py-12'>
                    <FlashMessageRender className='mb-4 px-1' />

                    <GlowCard
                        glowColor='green'
                        customSize
                        orbit
                        orbitDurationMs={2800}
                        className='w-full rounded-xl [--radius:12] [--border:2] [--size:185]'
                    >
                        <div className='rounded-xl bg-[color:var(--background)] p-8'>
                            <div className='mb-6 border-b border-[color:var(--border)] pb-5 text-center'>
                                <h1 className='text-4xl font-bold leading-tight tracking-tight text-[color:var(--foreground)] [text-shadow:0_0_14px_rgba(248,246,239,0.32)]'>
                                    BurHan Console
                                </h1>
                            </div>

                            {mode !== 'verify' && (
                                <div className='mb-8 flex rounded-lg bg-[color:var(--muted)] p-1 text-sm font-medium'>
                                    <button
                                        type='button'
                                        onClick={() => switchMode('login')}
                                        className={
                                            mode === 'login'
                                                ? 'flex-1 rounded-md bg-[color:var(--primary)] py-2 font-bold text-[color:var(--primary-foreground)] shadow-[0_0_16px_rgba(var(--primary-rgb),0.45)] transition-all hover:shadow-[0_0_20px_rgba(var(--primary-rgb),0.55)]'
                                                : 'flex-1 rounded-md py-2 text-[color:var(--foreground)] transition-all hover:text-[color:var(--foreground)]'
                                        }
                                    >
                                        LOG IN
                                    </button>
                                    <button
                                        type='button'
                                        onClick={() => switchMode('signup')}
                                        className={
                                            mode === 'signup'
                                                ? 'flex-1 rounded-md bg-[color:var(--primary)] py-2 font-bold text-[color:var(--primary-foreground)] shadow-[0_0_16px_rgba(var(--primary-rgb),0.45)] transition-all hover:shadow-[0_0_20px_rgba(var(--primary-rgb),0.55)]'
                                                : 'flex-1 rounded-md py-2 text-[color:var(--foreground)] transition-all hover:text-[color:var(--foreground)]'
                                        }
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
                                    {({ isSubmitting, values, errors, touched, handleChange, handleBlur }: FormikProps<LoginValues>) => {
                                        const lockLoginForm = captchaEnabled && !captchaToken;

                                        return (
                                            <Form className='relative space-y-5'>
                                            <div className={honeypotFieldClass} aria-hidden='true'>
                                                <label htmlFor='website'>Website</label>
                                                <input id='website' name='website' type='text' value={values.website} onChange={handleChange} tabIndex={-1} autoComplete='off' />
                                                <label htmlFor='company'>Company</label>
                                                <input id='company' name='company' type='text' value={values.company} onChange={handleChange} tabIndex={-1} autoComplete='off' />
                                            </div>
                                            <input type='hidden' name='formRenderedAt' value={values.formRenderedAt} />

                                            <div className='group space-y-1'>
                                                <label htmlFor='user' className='block text-xs uppercase text-[color:var(--foreground)] transition-colors group-focus-within:text-[color:var(--primary)]'>
                                                    Email or Username
                                                </label>
                                                <div className='relative'>
                                                    <input
                                                        name='username'
                                                        type='text'
                                                        value={values.username}
                                                        onChange={handleChange}
                                                        onBlur={handleBlur}
                                                        disabled={isSubmitting || lockLoginForm}
                                                        id='user'
                                                        autoComplete='username'
                                                        className='w-full rounded-lg border border-[color:var(--border)] bg-[color:var(--background)] px-4 py-3 pr-11 text-sm text-[color:var(--foreground)] outline-none transition-all placeholder:text-[color:var(--muted-foreground)] focus:border-[color:var(--ring)] focus:ring-1 focus:ring-[color:var(--ring)]'
                                                        placeholder='Enter your credentials'
                                                    />
                                                    <i className='fa-solid fa-user absolute right-4 top-1/2 -translate-y-1/2 text-xs text-[color:var(--muted-foreground)]' />
                                                </div>
                                                {errors.username && touched.username && <div className='text-[10px] font-bold text-red-400'>{errors.username}</div>}
                                            </div>

                                            <div className='group space-y-1'>
                                                <div className='flex items-center justify-between'>
                                                    <label htmlFor='password' className='block text-xs uppercase text-[color:var(--foreground)] transition-colors group-focus-within:text-[color:var(--primary)]'>
                                                        Password
                                                    </label>
                                                    <Link to={'/auth/password'} className='text-xs text-[color:var(--foreground)] transition-colors hover:text-[color:var(--primary)]'>
                                                        Forgot?
                                                    </Link>
                                                </div>
                                                <div className='relative'>
                                                    <input
                                                        name='password'
                                                        type={showPassword ? 'text' : 'password'}
                                                        value={values.password}
                                                        onChange={handleChange}
                                                        onBlur={handleBlur}
                                                        disabled={isSubmitting || lockLoginForm}
                                                        id='password'
                                                        autoComplete='current-password'
                                                        className='w-full rounded-lg border border-[color:var(--border)] bg-[color:var(--background)] px-4 py-3 pr-16 text-sm text-[color:var(--foreground)] outline-none transition-all placeholder:text-[color:var(--muted-foreground)] focus:border-[color:var(--ring)] focus:ring-1 focus:ring-[color:var(--ring)]'
                                                        placeholder='••••••••'
                                                    />
                                                    <button
                                                        type='button'
                                                        onClick={() => setShowPassword(!showPassword)}
                                                        className='absolute right-4 top-1/2 -translate-y-1/2 text-[10px] font-bold uppercase tracking-wider text-[color:var(--foreground)] transition-colors hover:text-[color:var(--foreground)]'
                                                    >
                                                        {showPassword ? 'HIDE' : 'SHOW'}
                                                    </button>
                                                </div>
                                                {errors.password && touched.password && <div className='text-[10px] font-bold text-red-400'>{errors.password}</div>}
                                            </div>

                                            <button
                                                type='submit'
                                                disabled={isSubmitting || lockLoginForm}
                                                className='mt-2 flex w-full items-center justify-center gap-2 rounded-lg bg-[color:var(--primary)] px-4 py-3 text-sm font-bold uppercase tracking-wide text-[color:var(--primary-foreground)] transition-all duration-300 hover:-translate-y-0.5 hover:shadow-[0_0_20px_rgba(var(--primary-rgb),0.55)] disabled:opacity-50'
                                            >
                                                Log In
                                                <i className='fa-solid fa-arrow-right text-xs' />
                                            </button>

                                            {lockLoginForm && (
                                                <div className='absolute inset-0 z-20 flex items-center justify-center rounded-lg bg-[rgba(5,10,14,0.88)] px-5 text-center backdrop-blur-sm'>
                                                    <div className='w-full max-w-xs rounded-xl border border-[color:var(--border)] bg-[color:var(--background)] p-4'>
                                                        <p className='mb-3 text-xs font-semibold uppercase tracking-wider text-[color:var(--foreground)]'>
                                                            Verify Before Login
                                                        </p>
                                                        <p className='mb-4 text-xs text-[color:var(--muted-foreground)]'>
                                                            Complete the Cloudflare Turnstile check first. Login fields unlock after verification.
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
                                        email: string().email('Please use a valid email.').required('Email is required.'),
                                        username: string().min(3).max(191).required('Username is required.'),
                                        firstName: string().required('First name is required.'),
                                        lastName: string().required('Last name is required.'),
                                        password: string().min(8, 'Password must be at least 8 characters.').required('Password is required.'),
                                        passwordConfirmation: string().oneOf([yupRef('password')], 'Password confirmation does not match.').required('Please confirm your password.'),
                                    })}
                                >
                                    {({ isSubmitting, values, errors, touched, handleChange, handleBlur }: FormikProps<SignupValues>) => (
                                        <Form className='space-y-4'>
                                            <div className={honeypotFieldClass} aria-hidden='true'>
                                                <label htmlFor='signup-website'>Website</label>
                                                <input id='signup-website' name='website' type='text' value={values.website} onChange={handleChange} tabIndex={-1} autoComplete='off' />
                                                <label htmlFor='signup-company'>Company</label>
                                                <input id='signup-company' name='company' type='text' value={values.company} onChange={handleChange} tabIndex={-1} autoComplete='off' />
                                            </div>
                                            <input type='hidden' name='formRenderedAt' value={values.formRenderedAt} />

                                            <div>
                                                <label className='mb-1 block text-xs uppercase text-[color:var(--foreground)]'>Email</label>
                                                <input
                                                    name='email'
                                                    type='email'
                                                    value={values.email}
                                                    onChange={handleChange}
                                                    onBlur={handleBlur}
                                                    disabled={isSubmitting}
                                                    className='w-full rounded-lg border border-[color:var(--border)] bg-[color:var(--background)] px-4 py-3 text-sm text-[color:var(--foreground)] outline-none transition-all placeholder:text-[color:var(--muted-foreground)] focus:border-[color:var(--ring)] focus:ring-1 focus:ring-[color:var(--ring)]'
                                                />
                                                {errors.email && touched.email && <div className='mt-1 text-[10px] font-bold text-red-400'>{errors.email}</div>}
                                            </div>
                                            <div>
                                                <label className='mb-1 block text-xs uppercase text-[color:var(--foreground)]'>Username</label>
                                                <input
                                                    name='username'
                                                    type='text'
                                                    value={values.username}
                                                    onChange={handleChange}
                                                    onBlur={handleBlur}
                                                    disabled={isSubmitting}
                                                    className='w-full rounded-lg border border-[color:var(--border)] bg-[color:var(--background)] px-4 py-3 text-sm text-[color:var(--foreground)] outline-none transition-all placeholder:text-[color:var(--muted-foreground)] focus:border-[color:var(--ring)] focus:ring-1 focus:ring-[color:var(--ring)]'
                                                />
                                                {errors.username && touched.username && <div className='mt-1 text-[10px] font-bold text-red-400'>{errors.username}</div>}
                                            </div>
                                            <div className='grid grid-cols-2 gap-3'>
                                                <div>
                                                    <label className='mb-1 block text-xs uppercase text-[color:var(--foreground)]'>First Name</label>
                                                    <input
                                                        name='firstName'
                                                        type='text'
                                                        value={values.firstName}
                                                        onChange={handleChange}
                                                        onBlur={handleBlur}
                                                        disabled={isSubmitting}
                                                        className='w-full rounded-lg border border-[color:var(--border)] bg-[color:var(--background)] px-4 py-3 text-sm text-[color:var(--foreground)] outline-none transition-all placeholder:text-[color:var(--muted-foreground)] focus:border-[color:var(--ring)] focus:ring-1 focus:ring-[color:var(--ring)]'
                                                    />
                                                    {errors.firstName && touched.firstName && <div className='mt-1 text-[10px] font-bold text-red-400'>{errors.firstName}</div>}
                                                </div>
                                                <div>
                                                    <label className='mb-1 block text-xs uppercase text-[color:var(--foreground)]'>Last Name</label>
                                                    <input
                                                        name='lastName'
                                                        type='text'
                                                        value={values.lastName}
                                                        onChange={handleChange}
                                                        onBlur={handleBlur}
                                                        disabled={isSubmitting}
                                                        className='w-full rounded-lg border border-[color:var(--border)] bg-[color:var(--background)] px-4 py-3 text-sm text-[color:var(--foreground)] outline-none transition-all placeholder:text-[color:var(--muted-foreground)] focus:border-[color:var(--ring)] focus:ring-1 focus:ring-[color:var(--ring)]'
                                                    />
                                                    {errors.lastName && touched.lastName && <div className='mt-1 text-[10px] font-bold text-red-400'>{errors.lastName}</div>}
                                                </div>
                                            </div>
                                            <div>
                                                <label className='mb-1 block text-xs uppercase text-[color:var(--foreground)]'>Password</label>
                                                <div className='relative'>
                                                    <input
                                                        name='password'
                                                        type={showSignupPassword ? 'text' : 'password'}
                                                        value={values.password}
                                                        onChange={handleChange}
                                                        onBlur={handleBlur}
                                                        disabled={isSubmitting}
                                                        className='w-full rounded-lg border border-[color:var(--border)] bg-[color:var(--background)] px-4 py-3 pr-16 text-sm text-[color:var(--foreground)] outline-none transition-all placeholder:text-[color:var(--muted-foreground)] focus:border-[color:var(--ring)] focus:ring-1 focus:ring-[color:var(--ring)]'
                                                    />
                                                    <button
                                                        type='button'
                                                        onClick={() => setShowSignupPassword(!showSignupPassword)}
                                                        className='absolute right-4 top-1/2 -translate-y-1/2 text-[10px] font-bold uppercase tracking-wider text-[color:var(--foreground)] transition-colors hover:text-[color:var(--foreground)]'
                                                    >
                                                        {showSignupPassword ? 'HIDE' : 'SHOW'}
                                                    </button>
                                                </div>
                                                {errors.password && touched.password && <div className='mt-1 text-[10px] font-bold text-red-400'>{errors.password}</div>}
                                            </div>
                                            <div>
                                                <label className='mb-1 block text-xs uppercase text-[color:var(--foreground)]'>Confirm Password</label>
                                                <div className='relative'>
                                                    <input
                                                        name='passwordConfirmation'
                                                        type={showSignupConfirmationPassword ? 'text' : 'password'}
                                                        value={values.passwordConfirmation}
                                                        onChange={handleChange}
                                                        onBlur={handleBlur}
                                                        disabled={isSubmitting}
                                                        className='w-full rounded-lg border border-[color:var(--border)] bg-[color:var(--background)] px-4 py-3 pr-16 text-sm text-[color:var(--foreground)] outline-none transition-all placeholder:text-[color:var(--muted-foreground)] focus:border-[color:var(--ring)] focus:ring-1 focus:ring-[color:var(--ring)]'
                                                    />
                                                    <button
                                                        type='button'
                                                        onClick={() => setShowSignupConfirmationPassword(!showSignupConfirmationPassword)}
                                                        className='absolute right-4 top-1/2 -translate-y-1/2 text-[10px] font-bold uppercase tracking-wider text-[color:var(--foreground)] transition-colors hover:text-[color:var(--foreground)]'
                                                    >
                                                        {showSignupConfirmationPassword ? 'HIDE' : 'SHOW'}
                                                    </button>
                                                </div>
                                                {errors.passwordConfirmation && touched.passwordConfirmation && (
                                                    <div className='mt-1 text-[10px] font-bold text-red-400'>{errors.passwordConfirmation}</div>
                                                )}
                                            </div>

                                            <button
                                                type='submit'
                                                disabled={isSubmitting}
                                                className='mt-2 flex w-full items-center justify-center gap-2 rounded-lg bg-[color:var(--primary)] px-4 py-3 text-sm font-bold uppercase tracking-wide text-[color:var(--primary-foreground)] transition-all duration-300 hover:-translate-y-0.5 hover:shadow-[0_0_20px_rgba(var(--primary-rgb),0.55)] disabled:opacity-50'
                                            >
                                                Sign Up
                                                <i className='fa-solid fa-arrow-right text-xs' />
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
                                        pin: string().matches(/^[0-9]{6}$/, 'Please enter a valid 6-digit pin.').required('Verification pin is required.'),
                                    })}
                                >
                                    {({ isSubmitting, values, errors, touched, handleChange, handleBlur }: FormikProps<VerificationValues>) => (
                                        <Form className='space-y-5'>
                                            <div className='mb-1 text-sm text-neutral-300'>
                                                Enter the 6-digit verification code sent to{' '}
                                                <span className='text-[color:var(--foreground)]'>{verificationIdentity}</span>.
                                            </div>
                                            <div>
                                                <label className='mb-1 block text-xs uppercase text-[color:var(--foreground)]'>Verification PIN</label>
                                                <input
                                                    name='pin'
                                                    type='text'
                                                    inputMode='numeric'
                                                    maxLength={6}
                                                    value={values.pin}
                                                    onChange={handleChange}
                                                    onBlur={handleBlur}
                                                    disabled={isSubmitting}
                                                    className='w-full rounded-lg border border-[color:var(--border)] bg-[color:var(--background)] px-4 py-3 text-center tracking-[0.4em] text-sm text-[color:var(--foreground)] outline-none transition-all placeholder:text-[color:var(--muted-foreground)] focus:border-[color:var(--ring)] focus:ring-1 focus:ring-[color:var(--ring)]'
                                                    placeholder='000000'
                                                />
                                                {errors.pin && touched.pin && <div className='mt-1 text-[10px] font-bold text-red-400'>{errors.pin}</div>}
                                            </div>
                                            <button
                                                type='submit'
                                                disabled={isSubmitting}
                                                className='mt-2 flex w-full items-center justify-center gap-2 rounded-lg bg-[color:var(--primary)] px-4 py-3 text-sm font-bold uppercase tracking-wide text-[color:var(--primary-foreground)] transition-all duration-300 hover:-translate-y-0.5 hover:shadow-[0_0_20px_rgba(var(--primary-rgb),0.55)] disabled:opacity-50'
                                            >
                                                Verify Account
                                                <i className='fa-solid fa-check text-xs' />
                                            </button>
                                            <button
                                                type='button'
                                                onClick={() => switchMode('login')}
                                                className='w-full rounded-lg border border-[color:var(--border)] py-3 text-sm font-medium text-[color:var(--foreground)] transition-colors hover:border-[color:var(--ring)] hover:text-[color:var(--primary)]'
                                            >
                                                Back to Login
                                            </button>
                                        </Form>
                                    )}
                                </Formik>
                            )}

                            {mode !== 'verify' && (
                                <>
                                    <div className='relative my-8'>
                                        <div className='absolute inset-0 flex items-center'>
                                            <div className='w-full border-t border-[color:var(--border)]' />
                                        </div>
                                        <div className='relative flex justify-center text-xs'>
                                            <span className='bg-[color:var(--background)] px-2 uppercase text-[color:var(--foreground)]'>
                                                Or continue with
                                            </span>
                                        </div>
                                    </div>

                                    <div className='space-y-3'>
                                        <button
                                            type='button'
                                            className='group flex w-full items-center justify-center gap-3 rounded-lg border border-[color:var(--border)] bg-[color:var(--background)] px-4 py-2.5 text-sm font-medium text-[color:var(--foreground)] transition-colors hover:border-[color:var(--ring)]'
                                        >
                                            <i className='fab fa-google text-base text-[color:var(--foreground)] transition-colors group-hover:text-[color:var(--foreground)]' />
                                            <span>Continue with Google</span>
                                        </button>
                                        <button
                                            type='button'
                                            className='group flex w-full items-center justify-center gap-3 rounded-lg border border-[color:var(--border)] bg-[color:var(--background)] px-4 py-2.5 text-sm font-medium text-[color:var(--foreground)] transition-colors hover:border-[color:var(--ring)]'
                                        >
                                            <i className='fab fa-discord text-base text-[#5865F2]' />
                                            <span className='transition-colors group-hover:text-[color:var(--primary)]'>Continue with Discord</span>
                                        </button>
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
