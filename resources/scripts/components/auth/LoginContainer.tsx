import React, { useEffect, useRef, useState } from 'react';
import { Link, RouteComponentProps } from 'react-router-dom';
import login from '@/api/auth/login';
import signup from '@/api/auth/signup';
import verifyEmailPin from '@/api/auth/verifyEmailPin';
import { useStoreState } from 'easy-peasy';
import { Form, Formik, FormikHelpers, FormikProps } from 'formik';
import { object, ref as yupRef, string } from 'yup';
import Reaptcha from 'reaptcha';
import useFlash from '@/plugins/useFlash';
import FlashMessageRender from '@/components/FlashMessageRender';
import { GlowCard } from '@/components/ui/spotlight-card';

interface LoginValues {
    username: string;
    password: string;
}

interface SignupValues {
    email: string;
    username: string;
    firstName: string;
    lastName: string;
    password: string;
    passwordConfirmation: string;
}

interface VerificationValues {
    pin: string;
}

type AuthMode = 'login' | 'signup' | 'verify';

const LoginContainer = ({ history }: RouteComponentProps) => {
    const ref = useRef<Reaptcha>(null);
    const tokenRef = useRef('');

    const [showPassword, setShowPassword] = useState(false);
    const [showSignupPassword, setShowSignupPassword] = useState(false);
    const [showSignupConfirmationPassword, setShowSignupConfirmationPassword] = useState(false);
    const [mode, setMode] = useState<AuthMode>('login');
    const [verificationToken, setVerificationToken] = useState('');
    const [verificationIdentity, setVerificationIdentity] = useState('');

    const { addFlash, clearFlashes, clearAndAddHttpError } = useFlash();
    const { enabled: recaptchaEnabled, siteKey } = useStoreState((state) => state.settings.data!.recaptcha);

    useEffect(() => {
        clearFlashes();
    }, []);

    const switchMode = (next: 'login' | 'signup') => {
        clearFlashes();
        setMode(next);
        setVerificationToken('');
        setVerificationIdentity('');
    };

    const onLoginSubmit = (values: LoginValues, { setSubmitting }: FormikHelpers<LoginValues>) => {
        clearFlashes();

        if (recaptchaEnabled && !tokenRef.current) {
            ref.current!.execute();
            setSubmitting(false);
            return;
        }

        login({ ...values, recaptchaData: tokenRef.current })
            .then((response) => {
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

                if (ref.current) ref.current.reset();

                setSubmitting(false);
                clearAndAddHttpError({ error });
            });
    };

    const onSignupSubmit = (values: SignupValues, { setSubmitting }: FormikHelpers<SignupValues>) => {
        clearFlashes();

        signup(values)
            .then((response) => {
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
                clearAndAddHttpError({ error });
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
                                                ? 'flex-1 rounded-md bg-[color:var(--primary)] py-2 font-bold text-[color:var(--primary-foreground)] shadow-[0_0_16px_rgba(var(--primary-rgb), 0.45)] transition-all hover:shadow-[0_0_20px_rgba(var(--primary-rgb), 0.55)]'
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
                                                ? 'flex-1 rounded-md bg-[color:var(--primary)] py-2 font-bold text-[color:var(--primary-foreground)] shadow-[0_0_16px_rgba(var(--primary-rgb), 0.45)] transition-all hover:shadow-[0_0_20px_rgba(var(--primary-rgb), 0.55)]'
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
                                    initialValues={{ username: '', password: '' }}
                                    validationSchema={object().shape({
                                        username: string().required('A username or email must be provided.'),
                                        password: string().required('Please enter your account password.'),
                                    })}
                                >
                                    {({
                                        isSubmitting,
                                        setSubmitting,
                                        submitForm,
                                        values,
                                        errors,
                                        touched,
                                        handleChange,
                                        handleBlur,
                                    }: FormikProps<LoginValues>) => (
                                        <>
                                            <Form className='space-y-5'>
                                                <div className='group space-y-1'>
                                                    <label
                                                        htmlFor='user'
                                                        className='block text-xs uppercase text-[color:var(--foreground)] transition-colors group-focus-within:text-[color:var(--primary)]'
                                                    >
                                                        Email or Username
                                                    </label>
                                                    <div className='relative'>
                                                        <input
                                                            name='username'
                                                            type='text'
                                                            value={values.username}
                                                            onChange={handleChange}
                                                            onBlur={handleBlur}
                                                            disabled={isSubmitting}
                                                            id='user'
                                                            autoComplete='username'
                                                            className='w-full rounded-lg border border-[color:var(--border)] bg-[color:var(--background)] px-4 py-3 pr-11 text-sm text-[color:var(--foreground)] outline-none transition-all placeholder:text-[color:var(--muted-foreground)] focus:border-[color:var(--ring)] focus:ring-1 focus:ring-[color:var(--ring)]'
                                                            placeholder='Enter your credentials'
                                                        />
                                                        <i className='fa-solid fa-user absolute right-4 top-1/2 -translate-y-1/2 text-xs text-[color:var(--muted-foreground)]' />
                                                    </div>
                                                    {errors.username && touched.username && (
                                                        <div className='text-[10px] font-bold text-red-400'>
                                                            {errors.username}
                                                        </div>
                                                    )}
                                                </div>

                                                <div className='group space-y-1'>
                                                    <div className='flex items-center justify-between'>
                                                        <label
                                                            htmlFor='password'
                                                            className='block text-xs uppercase text-[color:var(--foreground)] transition-colors group-focus-within:text-[color:var(--primary)]'
                                                        >
                                                            Password
                                                        </label>
                                                        <Link
                                                            to={'/auth/password'}
                                                            className='text-xs text-[color:var(--foreground)] transition-colors hover:text-[color:var(--primary)]'
                                                        >
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
                                                            disabled={isSubmitting}
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
                                                    {errors.password && touched.password && (
                                                        <div className='text-[10px] font-bold text-red-400'>
                                                            {errors.password}
                                                        </div>
                                                    )}
                                                </div>

                                                <button
                                                    type='submit'
                                                    disabled={isSubmitting}
                                                    className='mt-2 flex w-full items-center justify-center gap-2 rounded-lg bg-[color:var(--primary)] px-4 py-3 text-sm font-bold uppercase tracking-wide text-[color:var(--primary-foreground)] transition-all duration-300 hover:-translate-y-0.5 hover:shadow-[0_0_20px_rgba(var(--primary-rgb), 0.55)] disabled:opacity-50'
                                                >
                                                    Log In
                                                    <i className='fa-solid fa-arrow-right text-xs' />
                                                </button>
                                            </Form>

                                            {recaptchaEnabled && (
                                                <Reaptcha
                                                    ref={ref}
                                                    size={'invisible'}
                                                    sitekey={siteKey || '_invalid_key'}
                                                    onVerify={(response) => {
                                                        tokenRef.current = response;
                                                        submitForm();
                                                    }}
                                                    onExpire={() => {
                                                        setSubmitting(false);
                                                        tokenRef.current = '';
                                                    }}
                                                />
                                            )}
                                        </>
                                    )}
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
                                    }}
                                    validationSchema={object().shape({
                                        email: string().email('Please use a valid email.').required('Email is required.'),
                                        username: string().min(3).max(191).required('Username is required.'),
                                        firstName: string().required('First name is required.'),
                                        lastName: string().required('Last name is required.'),
                                        password: string().min(8, 'Password must be at least 8 characters.').required('Password is required.'),
                                        passwordConfirmation: string()
                                            .oneOf([yupRef('password')], 'Password confirmation does not match.')
                                            .required('Please confirm your password.'),
                                    })}
                                >
                                    {({ isSubmitting, values, errors, touched, handleChange, handleBlur }: FormikProps<SignupValues>) => (
                                        <Form className='space-y-4'>
                                            <div>
                                                <label className='block text-xs uppercase text-[color:var(--foreground)] mb-1'>Email</label>
                                                <input
                                                    name='email'
                                                    type='email'
                                                    value={values.email}
                                                    onChange={handleChange}
                                                    onBlur={handleBlur}
                                                    disabled={isSubmitting}
                                                    className='w-full rounded-lg border border-[color:var(--border)] bg-[color:var(--background)] px-4 py-3 text-sm text-[color:var(--foreground)] outline-none transition-all placeholder:text-[color:var(--muted-foreground)] focus:border-[color:var(--ring)] focus:ring-1 focus:ring-[color:var(--ring)]'
                                                />
                                                {errors.email && touched.email && <div className='text-[10px] font-bold text-red-400 mt-1'>{errors.email}</div>}
                                            </div>
                                            <div>
                                                <label className='block text-xs uppercase text-[color:var(--foreground)] mb-1'>Username</label>
                                                <input
                                                    name='username'
                                                    type='text'
                                                    value={values.username}
                                                    onChange={handleChange}
                                                    onBlur={handleBlur}
                                                    disabled={isSubmitting}
                                                    className='w-full rounded-lg border border-[color:var(--border)] bg-[color:var(--background)] px-4 py-3 text-sm text-[color:var(--foreground)] outline-none transition-all placeholder:text-[color:var(--muted-foreground)] focus:border-[color:var(--ring)] focus:ring-1 focus:ring-[color:var(--ring)]'
                                                />
                                                {errors.username && touched.username && (
                                                    <div className='text-[10px] font-bold text-red-400 mt-1'>{errors.username}</div>
                                                )}
                                            </div>
                                            <div className='grid grid-cols-2 gap-3'>
                                                <div>
                                                    <label className='block text-xs uppercase text-[color:var(--foreground)] mb-1'>First Name</label>
                                                    <input
                                                        name='firstName'
                                                        type='text'
                                                        value={values.firstName}
                                                        onChange={handleChange}
                                                        onBlur={handleBlur}
                                                        disabled={isSubmitting}
                                                        className='w-full rounded-lg border border-[color:var(--border)] bg-[color:var(--background)] px-4 py-3 text-sm text-[color:var(--foreground)] outline-none transition-all placeholder:text-[color:var(--muted-foreground)] focus:border-[color:var(--ring)] focus:ring-1 focus:ring-[color:var(--ring)]'
                                                    />
                                                    {errors.firstName && touched.firstName && (
                                                        <div className='text-[10px] font-bold text-red-400 mt-1'>{errors.firstName}</div>
                                                    )}
                                                </div>
                                                <div>
                                                    <label className='block text-xs uppercase text-[color:var(--foreground)] mb-1'>Last Name</label>
                                                    <input
                                                        name='lastName'
                                                        type='text'
                                                        value={values.lastName}
                                                        onChange={handleChange}
                                                        onBlur={handleBlur}
                                                        disabled={isSubmitting}
                                                        className='w-full rounded-lg border border-[color:var(--border)] bg-[color:var(--background)] px-4 py-3 text-sm text-[color:var(--foreground)] outline-none transition-all placeholder:text-[color:var(--muted-foreground)] focus:border-[color:var(--ring)] focus:ring-1 focus:ring-[color:var(--ring)]'
                                                    />
                                                    {errors.lastName && touched.lastName && (
                                                        <div className='text-[10px] font-bold text-red-400 mt-1'>{errors.lastName}</div>
                                                    )}
                                                </div>
                                            </div>
                                            <div>
                                                <label className='block text-xs uppercase text-[color:var(--foreground)] mb-1'>Password</label>
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
                                                {errors.password && touched.password && (
                                                    <div className='text-[10px] font-bold text-red-400 mt-1'>{errors.password}</div>
                                                )}
                                            </div>
                                            <div>
                                                <label className='block text-xs uppercase text-[color:var(--foreground)] mb-1'>Confirm Password</label>
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
                                                    <div className='text-[10px] font-bold text-red-400 mt-1'>{errors.passwordConfirmation}</div>
                                                )}
                                            </div>

                                            <button
                                                type='submit'
                                                disabled={isSubmitting}
                                                className='mt-2 flex w-full items-center justify-center gap-2 rounded-lg bg-[color:var(--primary)] px-4 py-3 text-sm font-bold uppercase tracking-wide text-[color:var(--primary-foreground)] transition-all duration-300 hover:-translate-y-0.5 hover:shadow-[0_0_20px_rgba(var(--primary-rgb), 0.55)] disabled:opacity-50'
                                            >
                                                Sign Up
                                                <i className='fa-solid fa-arrow-right text-xs' />
                                            </button>
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
                                                Enter the 6-digit verification code sent to <span className='text-[color:var(--foreground)]'>{verificationIdentity}</span>.
                                            </div>
                                            <div>
                                                <label className='block text-xs uppercase text-[color:var(--foreground)] mb-1'>Verification PIN</label>
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
                                                {errors.pin && touched.pin && <div className='text-[10px] font-bold text-red-400 mt-1'>{errors.pin}</div>}
                                            </div>
                                            <button
                                                type='submit'
                                                disabled={isSubmitting}
                                                className='mt-2 flex w-full items-center justify-center gap-2 rounded-lg bg-[color:var(--primary)] px-4 py-3 text-sm font-bold uppercase tracking-wide text-[color:var(--primary-foreground)] transition-all duration-300 hover:-translate-y-0.5 hover:shadow-[0_0_20px_rgba(var(--primary-rgb), 0.55)] disabled:opacity-50'
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
                                            <span className='transition-colors group-hover:text-[color:var(--primary)]'>
                                                Continue with Discord
                                            </span>
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

