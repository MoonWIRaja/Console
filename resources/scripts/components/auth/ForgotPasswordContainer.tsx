import React, { useEffect, useState } from 'react';
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
import {
    authErrorClass,
    authFieldLabelClass,
    authInputClass,
    authInputWithSuffixClass,
    authPrimaryButtonClass,
    authSecondaryButtonClass,
    burhanAuthThemeStyles,
    honeypotFieldClass,
} from '@/components/auth/authTheme';

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

const ForgotPasswordContainer = () => {
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
    }, [clearFlashes]);

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

    const handleReset = (values: ResetValues, { setSubmitting }: FormikHelpers<ResetValues>) => {
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
        <div className='burhan-auth-stage fixed inset-0 z-50 flex h-[100dvh] w-full overflow-hidden text-[color:var(--foreground)]'>
            <style>{burhanAuthThemeStyles}</style>
            <div className='burhan-auth-backdrop hidden h-full w-[70%] lg:block'>
                <span className='sr-only'>Dark neon background area.</span>
            </div>
            <div
                className={`burhan-auth-rail h-full w-full overflow-y-auto px-6 py-5 sm:px-10 sm:py-6 md:px-14 lg:w-[30%] lg:px-8 lg:py-4 xl:px-10 ${
                    mode === 'verify' ? 'lg:overflow-y-auto' : 'lg:overflow-y-hidden'
                }`}
            >
                <div
                    className={`burhan-auth-shell mx-auto flex h-full min-h-0 w-full max-w-[32rem] flex-col py-0 ${
                        mode === 'verify' ? 'justify-start' : 'justify-center'
                    }`}
                >
                    <FlashMessageRender className='burhan-auth-flash mb-4 px-1' />

                    <GlowCard
                        glowColor='green'
                        customSize
                        orbit
                        orbitDurationMs={2800}
                        className={`burhan-auth-glow w-full ${mode === 'verify' ? '' : 'max-h-full'}`}
                    >
                        <div className='burhan-auth-card'>
                            <div className='burhan-auth-brand-panel'>
                                <h1 className='burhan-auth-title'>{name}</h1>
                            </div>

                            <div className='mb-4 rounded-[1.35rem] border border-[rgba(255,255,255,0.06)] bg-[rgba(255,255,255,0.02)] px-4 py-3'>
                                <h2 className='text-[0.72rem] font-extrabold uppercase tracking-[0.18em] text-[rgba(248,246,239,0.72)]'>
                                    Reset Password
                                </h2>
                                <p className='mt-2 text-sm leading-7 text-[rgba(151,160,171,0.94)]'>
                                    {mode === 'request'
                                        ? 'Enter your account email to receive a 6-digit PIN.'
                                        : `Enter the PIN sent to ${email} and set your new password.`}
                                </p>
                            </div>

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
                                    {({
                                        isSubmitting,
                                        values,
                                        errors,
                                        touched,
                                        handleChange,
                                        handleBlur,
                                    }: FormikProps<RequestValues>) => (
                                        <Form className='burhan-auth-form relative'>
                                            <div className={honeypotFieldClass} aria-hidden='true'>
                                                <label htmlFor='reset-website'>Website</label>
                                                <input
                                                    id='reset-website'
                                                    name='website'
                                                    type='text'
                                                    value={values.website}
                                                    onChange={handleChange}
                                                    tabIndex={-1}
                                                    autoComplete='off'
                                                />
                                                <label htmlFor='reset-company'>Company</label>
                                                <input
                                                    id='reset-company'
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
                                                <label htmlFor='email' className={authFieldLabelClass}>
                                                    Email Address
                                                </label>
                                                <div className='burhan-auth-input-wrap'>
                                                    <input
                                                        name='email'
                                                        type='email'
                                                        value={values.email}
                                                        onChange={handleChange}
                                                        onBlur={handleBlur}
                                                        disabled={isSubmitting}
                                                        id='email'
                                                        autoComplete='email'
                                                        className={authInputWithSuffixClass}
                                                        placeholder='your@email.com'
                                                    />
                                                    <span className='burhan-auth-field-token'>Email</span>
                                                </div>
                                                {errors.email && touched.email && (
                                                    <div className={authErrorClass}>{errors.email}</div>
                                                )}
                                            </div>

                                            <button
                                                type='submit'
                                                disabled={isSubmitting}
                                                className={authPrimaryButtonClass}
                                            >
                                                {isSubmitting ? 'Sending...' : 'Send PIN'}
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
                                        password: string()
                                            .required('A new password is required.')
                                            .min(8, 'Your new password should be at least 8 characters in length.'),
                                        passwordConfirmation: string()
                                            .oneOf([yupRef('password')], 'Your new password does not match.')
                                            .required('Your new password does not match.'),
                                    })}
                                >
                                    {({
                                        isSubmitting,
                                        values,
                                        errors,
                                        touched,
                                        handleChange,
                                        handleBlur,
                                    }: FormikProps<ResetValues>) => (
                                        <Form className='burhan-auth-form relative'>
                                            <div className={honeypotFieldClass} aria-hidden='true'>
                                                <label htmlFor='verify-website'>Website</label>
                                                <input
                                                    id='verify-website'
                                                    name='website'
                                                    type='text'
                                                    value={values.website}
                                                    onChange={handleChange}
                                                    tabIndex={-1}
                                                    autoComplete='off'
                                                />
                                                <label htmlFor='verify-company'>Company</label>
                                                <input
                                                    id='verify-company'
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
                                                <label htmlFor='pin' className={authFieldLabelClass}>
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
                                                    className={`${authInputClass} is-centered`}
                                                    placeholder='000000'
                                                />
                                                {errors.pin && touched.pin && (
                                                    <div className={authErrorClass}>{errors.pin}</div>
                                                )}
                                            </div>

                                            <div>
                                                <label htmlFor='password' className={authFieldLabelClass}>
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
                                                    className={authInputClass}
                                                />
                                                {errors.password && touched.password && (
                                                    <div className={authErrorClass}>{errors.password}</div>
                                                )}
                                            </div>

                                            <div>
                                                <label htmlFor='passwordConfirmation' className={authFieldLabelClass}>
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
                                                    className={authInputClass}
                                                />
                                                {errors.passwordConfirmation && touched.passwordConfirmation && (
                                                    <div className={authErrorClass}>{errors.passwordConfirmation}</div>
                                                )}
                                            </div>

                                            <button
                                                type='submit'
                                                disabled={isSubmitting}
                                                className={authPrimaryButtonClass}
                                            >
                                                {isSubmitting ? 'Resetting...' : 'Reset Password'}
                                                <i className='fa-solid fa-check text-sm' />
                                            </button>

                                            <button
                                                type='button'
                                                disabled={isSubmitting}
                                                onClick={() => {
                                                    setMode('request');
                                                    setResetToken('');
                                                    setCaptchaToken('');
                                                }}
                                                className={authSecondaryButtonClass}
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

                    <div className='mt-6 text-center'>
                        <Link className='burhan-auth-meta-link' to={'/auth/login'}>
                            Return to Login
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default ForgotPasswordContainer;
