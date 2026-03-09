import * as React from 'react';
import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import requestPasswordResetEmail from '@/api/auth/requestPasswordResetEmail';
import performPasswordResetWithPin from '@/api/auth/performPasswordResetWithPin';
import { httpErrorToHuman } from '@/api/http';
import { useStoreState } from 'easy-peasy';
import { Form, Formik, FormikHelpers, FormikProps } from 'formik';
import { object, ref as yupRef, string } from 'yup';
import useFlash from '@/plugins/useFlash';
import FlashMessageRender from '@/components/FlashMessageRender';
import { GlowCard } from '@/components/ui/spotlight-card';
import TurnstileWidget from '@/components/auth/TurnstileWidget';
import useSiteBranding from '@/hooks/useSiteBranding';

interface RequestValues {
    email: string;
    website: string;
    company: string;
    formRenderedAt: number;
}

interface ResetValues {
    pin: string;
    password: string;
    passwordConfirmation: string;
    website: string;
    company: string;
    formRenderedAt: number;
}

type ResetMode = 'request' | 'verify';

const honeypotFieldClass = 'pointer-events-none absolute left-[-10000px] top-[-10000px] h-0 w-0 overflow-hidden opacity-0';

export default () => {
    const [mode, setMode] = useState<ResetMode>('request');
    const [email, setEmail] = useState('');
    const [resetToken, setResetToken] = useState('');
    const [requireCaptcha, setRequireCaptcha] = useState(false);
    const [captchaToken, setCaptchaToken] = useState('');
    const { name } = useSiteBranding();

    const { clearFlashes, addFlash } = useFlash();
    const captcha = useStoreState((state) => state.settings.data!.captcha);
    const captchaEnabled = !!captcha?.enabled && captcha?.provider === 'turnstile' && !!captcha?.siteKey;

    useEffect(() => {
        clearFlashes();
    }, []);

    const handleSecurityError = (error: any) => {
        if (error?.response?.data?.challenge_required) {
            setRequireCaptcha(true);
        }

        addFlash({ type: 'error', title: 'Error', message: httpErrorToHuman(error) });
    };

    const handleRequest = (values: RequestValues, { setSubmitting }: FormikHelpers<RequestValues>) => {
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

        requestPasswordResetEmail({
            email: values.email,
            captchaToken,
            website: values.website,
            company: values.company,
            formRenderedAt: values.formRenderedAt,
        })
            .then((response) => {
                addFlash({ type: 'success', title: 'Success', message: response.status });

                setEmail(values.email);
                setResetToken(response.resetToken || '');
                setMode('verify');
                setCaptchaToken('');
            })
            .catch((error) => {
                console.error(error);
                handleSecurityError(error);
            })
            .finally(() => setSubmitting(false));
    };

    const handleReset = (
        values: ResetValues,
        { setSubmitting }: FormikHelpers<ResetValues>
    ) => {
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

        performPasswordResetWithPin({
            verificationToken: resetToken,
            pin: values.pin,
            password: values.password,
            passwordConfirmation: values.passwordConfirmation,
            captchaToken,
            website: values.website,
            company: values.company,
            formRenderedAt: values.formRenderedAt,
        })
            .then((response) => {
                // @ts-expect-error this is valid
                window.location = response.sendToLogin ? '/auth/login' : response.redirectTo || '/';
            })
            .catch((error) => {
                console.error(error);
                handleSecurityError(error);
            })
            .finally(() => setSubmitting(false));
    };

    return (
        <div className='fixed inset-0 z-50 flex h-screen w-full overflow-hidden bg-[color:var(--card)] text-gray-100'>
            <style>{`
                .font-mono {
                    font-family: 'Space Mono', monospace;
                }
            `}</style>
            <div className='hidden h-full w-[70%] bg-[color:var(--card)] font-mono lg:block'>
                <span className='sr-only'>A dark neon background area.</span>
            </div>
            <div className='w-full overflow-y-auto bg-[color:var(--card)] px-8 font-mono sm:px-12 md:px-16 lg:w-[30%] lg:px-10 xl:px-12'>
                <div className='mx-auto flex h-full w-full max-w-md flex-col justify-center py-12'>
                    <div className='mb-10'>
                        <h1 className='text-4xl font-bold leading-tight tracking-tight text-[#f8f6ef] [text-shadow:0_0_14px_rgba(248,246,239,0.32)]'>
                            {name}
                        </h1>
                    </div>

                    <div className='mb-6'>
                        <h2 className='text-sm font-bold uppercase tracking-wider text-gray-200'>RESET PASSWORD</h2>
                        <p className='mt-2 text-xs text-gray-400'>
                            {mode === 'request'
                                ? 'Enter your account email to receive a 6-digit PIN.'
                                : `Enter the PIN sent to ${email} and set a new password.`}
                        </p>
                    </div>

                    <FlashMessageRender className='mb-4 px-1' />

                    <GlowCard
                        glowColor='green'
                        customSize
                        orbit
                        orbitDurationMs={2800}
                        className='w-full rounded-xl [--radius:12] [--border:2] [--size:185]'
                    >
                        <div className='rounded-xl bg-[color:var(--card)] p-8'>
                            {mode === 'request' ? (
                                <Formik
                                    onSubmit={handleRequest}
                                    initialValues={{
                                        email: '',
                                        website: '',
                                        company: '',
                                        formRenderedAt: Date.now(),
                                    }}
                                    validationSchema={object().shape({
                                        email: string()
                                            .email('A valid email address must be provided to continue.')
                                            .required('A valid email address must be provided to continue.'),
                                    })}
                                >
                                    {({ isSubmitting, values, errors, touched, handleChange, handleBlur }: FormikProps<RequestValues>) => (
                                        <Form className='space-y-5'>
                                            <div className={honeypotFieldClass} aria-hidden='true'>
                                                <label htmlFor='reset-website'>Website</label>
                                                <input id='reset-website' name='website' type='text' value={values.website} onChange={handleChange} tabIndex={-1} autoComplete='off' />
                                                <label htmlFor='reset-company'>Company</label>
                                                <input id='reset-company' name='company' type='text' value={values.company} onChange={handleChange} tabIndex={-1} autoComplete='off' />
                                            </div>
                                            <input type='hidden' name='formRenderedAt' value={values.formRenderedAt} />

                                            <div className='group space-y-1'>
                                                <label htmlFor='email' className='block text-xs uppercase text-gray-400 transition-colors group-focus-within:text-[color:var(--primary)]'>
                                                    Email Address
                                                </label>
                                                <div className='relative'>
                                                    <input
                                                        name='email'
                                                        type='email'
                                                        value={values.email}
                                                        onChange={handleChange}
                                                        onBlur={handleBlur}
                                                        disabled={isSubmitting}
                                                        id='email'
                                                        autoComplete='email'
                                                        className='w-full rounded-lg border border-gray-800 bg-[color:var(--card)] px-4 py-3 pr-11 text-sm text-gray-100 outline-none transition-all placeholder:text-gray-500 focus:border-[color:var(--primary)] focus:ring-1 focus:ring-[color:var(--primary)]'
                                                        placeholder='Enter your email'
                                                    />
                                                    <i className='fa-solid fa-envelope absolute right-4 top-1/2 -translate-y-1/2 text-xs text-gray-500' />
                                                </div>
                                                {errors.email && touched.email && <div className='text-[10px] font-bold text-red-400'>{errors.email}</div>}
                                            </div>
                                            <button
                                                type='submit'
                                                disabled={isSubmitting}
                                                className='mt-2 flex w-full items-center justify-center gap-2 rounded-lg bg-[color:var(--primary)] px-4 py-3 text-sm font-bold uppercase tracking-wide text-[color:var(--primary-foreground)] transition-all duration-300 hover:-translate-y-0.5 hover:shadow-[0_0_20px_rgba(var(--primary-rgb),0.55)] disabled:opacity-50'
                                            >
                                                {isSubmitting ? 'Sending...' : 'Send PIN'}
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
                            ) : (
                                <Formik
                                    onSubmit={handleReset}
                                    initialValues={{
                                        pin: '',
                                        password: '',
                                        passwordConfirmation: '',
                                        website: '',
                                        company: '',
                                        formRenderedAt: Date.now(),
                                    }}
                                    validationSchema={object().shape({
                                        pin: string().matches(/^[0-9]{6}$/, 'PIN must be 6 digits.').required('PIN is required.'),
                                        password: string().required('A new password is required.').min(8, 'Your new password should be at least 8 characters in length.'),
                                        passwordConfirmation: string().oneOf([yupRef('password')], 'Your new password does not match.').required('Your new password does not match.'),
                                    })}
                                >
                                    {({ isSubmitting, values, errors, touched, handleChange, handleBlur }: FormikProps<ResetValues>) => (
                                        <Form className='space-y-5'>
                                            <div className={honeypotFieldClass} aria-hidden='true'>
                                                <label htmlFor='verify-website'>Website</label>
                                                <input id='verify-website' name='website' type='text' value={values.website} onChange={handleChange} tabIndex={-1} autoComplete='off' />
                                                <label htmlFor='verify-company'>Company</label>
                                                <input id='verify-company' name='company' type='text' value={values.company} onChange={handleChange} tabIndex={-1} autoComplete='off' />
                                            </div>
                                            <input type='hidden' name='formRenderedAt' value={values.formRenderedAt} />

                                            <div className='group space-y-1'>
                                                <label htmlFor='pin' className='block text-xs uppercase text-gray-400 transition-colors group-focus-within:text-[color:var(--primary)]'>
                                                    Verification PIN
                                                </label>
                                                <input
                                                    name='pin'
                                                    type='text'
                                                    inputMode='numeric'
                                                    maxLength={6}
                                                    value={values.pin}
                                                    onChange={handleChange}
                                                    onBlur={handleBlur}
                                                    disabled={isSubmitting}
                                                    id='pin'
                                                    className='w-full rounded-lg border border-gray-800 bg-[color:var(--card)] px-4 py-3 text-center tracking-[0.35em] text-sm text-gray-100 outline-none transition-all placeholder:text-gray-500 focus:border-[color:var(--primary)] focus:ring-1 focus:ring-[color:var(--primary)]'
                                                    placeholder='000000'
                                                />
                                                {errors.pin && touched.pin && <div className='text-[10px] font-bold text-red-400'>{errors.pin}</div>}
                                            </div>
                                            <div className='group space-y-1'>
                                                <label htmlFor='password' className='block text-xs uppercase text-gray-400 transition-colors group-focus-within:text-[color:var(--primary)]'>
                                                    New Password
                                                </label>
                                                <input
                                                    name='password'
                                                    type='password'
                                                    value={values.password}
                                                    onChange={handleChange}
                                                    onBlur={handleBlur}
                                                    disabled={isSubmitting}
                                                    id='password'
                                                    className='w-full rounded-lg border border-gray-800 bg-[color:var(--card)] px-4 py-3 text-sm text-gray-100 outline-none transition-all placeholder:text-gray-500 focus:border-[color:var(--primary)] focus:ring-1 focus:ring-[color:var(--primary)]'
                                                />
                                                {errors.password && touched.password && <div className='text-[10px] font-bold text-red-400'>{errors.password}</div>}
                                            </div>
                                            <div className='group space-y-1'>
                                                <label htmlFor='passwordConfirmation' className='block text-xs uppercase text-gray-400 transition-colors group-focus-within:text-[color:var(--primary)]'>
                                                    Confirm Password
                                                </label>
                                                <input
                                                    name='passwordConfirmation'
                                                    type='password'
                                                    value={values.passwordConfirmation}
                                                    onChange={handleChange}
                                                    onBlur={handleBlur}
                                                    disabled={isSubmitting}
                                                    id='passwordConfirmation'
                                                    className='w-full rounded-lg border border-gray-800 bg-[color:var(--card)] px-4 py-3 text-sm text-gray-100 outline-none transition-all placeholder:text-gray-500 focus:border-[color:var(--primary)] focus:ring-1 focus:ring-[color:var(--primary)]'
                                                />
                                                {errors.passwordConfirmation && touched.passwordConfirmation && (
                                                    <div className='text-[10px] font-bold text-red-400'>{errors.passwordConfirmation}</div>
                                                )}
                                            </div>
                                            <button
                                                type='submit'
                                                disabled={isSubmitting}
                                                className='mt-2 flex w-full items-center justify-center gap-2 rounded-lg bg-[color:var(--primary)] px-4 py-3 text-sm font-bold uppercase tracking-wide text-[color:var(--primary-foreground)] transition-all duration-300 hover:-translate-y-0.5 hover:shadow-[0_0_20px_rgba(var(--primary-rgb),0.55)] disabled:opacity-50'
                                            >
                                                {isSubmitting ? 'Resetting...' : 'Reset Password'}
                                                <i className='fa-solid fa-check text-xs' />
                                            </button>
                                            <button
                                                type='button'
                                                disabled={isSubmitting}
                                                onClick={() => {
                                                    setMode('request');
                                                    setResetToken('');
                                                    setCaptchaToken('');
                                                }}
                                                className='w-full rounded-lg border border-gray-700 py-3 text-sm font-medium text-white/80 transition-colors hover:border-[color:var(--primary)] hover:text-[color:var(--primary)]'
                                            >
                                                Back
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
                        </div>
                    </GlowCard>

                    <div className='mt-8 text-center'>
                        <Link className='text-xs text-gray-500 transition-colors hover:text-[color:var(--primary)]' to={'/auth/login'}>
                            Return to Login
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    );
};
