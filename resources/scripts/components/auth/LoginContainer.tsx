import React, { useEffect, useRef, useState } from 'react';
import { Link, RouteComponentProps } from 'react-router-dom';
import login from '@/api/auth/login';
import { useStoreState } from 'easy-peasy';
import { Form, Formik, FormikHelpers, FormikProps } from 'formik';
import { object, string } from 'yup';
import Reaptcha from 'reaptcha';
import useFlash from '@/plugins/useFlash';
import FlashMessageRender from '@/components/FlashMessageRender';
import { GlowCard } from '@/components/ui/spotlight-card';

interface Values {
    username: string;
    password: string;
}

const LoginContainer = ({ history }: RouteComponentProps) => {
    const ref = useRef<Reaptcha>(null);
    const tokenRef = useRef('');
    const [showPassword, setShowPassword] = useState(false);

    const { clearFlashes, clearAndAddHttpError } = useFlash();
    const { enabled: recaptchaEnabled, siteKey } = useStoreState((state) => state.settings.data!.recaptcha);

    useEffect(() => {
        clearFlashes();
    }, []);

    const onSubmit = (values: Values, { setSubmitting }: FormikHelpers<Values>) => {
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

                history.replace('/auth/login/checkpoint', { token: response.confirmationToken });
            })
            .catch((error) => {
                console.error(error);

                if (ref.current) ref.current.reset();

                setSubmitting(false);
                clearAndAddHttpError({ error });
            });
    };

    return (
        <Formik
            onSubmit={onSubmit}
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
            }: FormikProps<Values>) => (
                <div className='fixed inset-0 z-50 flex h-screen w-full overflow-hidden bg-[#000000] text-gray-100'>
                    <style>{`
                        @import url('https://fonts.googleapis.com/css2?family=Space+Mono:ital,wght@0,400;0,700;1,400&display=swap');
                        @import url('https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css');
                        .font-mono {
                            font-family: 'Space Mono', monospace;
                        }
                    `}</style>
                    <div className='hidden h-full w-[70%] bg-[#000000] font-mono lg:block'>
                        <span className='sr-only'>Dark neon background area.</span>
                    </div>
                    <div className='w-full overflow-y-auto bg-[#000000] px-8 font-mono sm:px-12 md:px-16 lg:w-[30%] lg:px-10 xl:px-12'>
                        <div className='mx-auto flex h-full w-full max-w-md flex-col justify-center py-12'>
                            <FlashMessageRender className='mb-4 px-1' />

                            <GlowCard
                                glowColor='green'
                                customSize
                                orbit
                                orbitDurationMs={2800}
                                className='w-full rounded-xl [--radius:12] [--border:2] [--size:185]'
                            >
                                <div className='rounded-xl bg-[#000000] p-8'>
                                    <div className='mb-6 border-b border-gray-800 pb-5 text-center'>
                                        <h1 className='text-4xl font-bold leading-tight tracking-tight text-[#f8f6ef] [text-shadow:0_0_14px_rgba(248,246,239,0.32)]'>
                                            BurHan Console
                                        </h1>
                                    </div>

                                    <div className='mb-8 flex rounded-lg bg-black/40 p-1 text-sm font-medium'>
                                        <button
                                            type='button'
                                            className='flex-1 rounded-md bg-[#a3ff12] py-2 font-bold text-black shadow-[0_0_16px_rgba(163,255,18,0.45)] transition-all hover:shadow-[0_0_20px_rgba(163,255,18,0.55)]'
                                        >
                                            LOG IN
                                        </button>
                                        <button
                                            type='button'
                                            className='flex-1 rounded-md py-2 text-white/70 transition-all hover:text-white'
                                        >
                                            SIGN UP
                                        </button>
                                    </div>

                                    <Form className='space-y-5'>
                                        <div className='group space-y-1'>
                                            <label
                                                htmlFor='user'
                                                className='block text-xs uppercase text-white/80 transition-colors group-focus-within:text-[#a3ff12]'
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
                                                    className='w-full rounded-lg border border-gray-800 bg-[#000000] px-4 py-3 pr-11 text-sm text-white outline-none transition-all placeholder:text-white/40 focus:border-[#a3ff12] focus:ring-1 focus:ring-[#a3ff12]'
                                                    placeholder='Enter your credentials'
                                                />
                                                <i className='fa-solid fa-user absolute right-4 top-1/2 -translate-y-1/2 text-xs text-white/50' />
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
                                                    className='block text-xs uppercase text-white/80 transition-colors group-focus-within:text-[#a3ff12]'
                                                >
                                                    Password
                                                </label>
                                                <Link
                                                    to={'/auth/password'}
                                                    className='text-xs text-white/70 transition-colors hover:text-[#a3ff12]'
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
                                                    className='w-full rounded-lg border border-gray-800 bg-[#000000] px-4 py-3 pr-16 text-sm text-white outline-none transition-all placeholder:text-white/40 focus:border-[#a3ff12] focus:ring-1 focus:ring-[#a3ff12]'
                                                    placeholder='••••••••'
                                                />
                                                <button
                                                    type='button'
                                                    onClick={() => setShowPassword(!showPassword)}
                                                    className='absolute right-4 top-1/2 -translate-y-1/2 text-[10px] font-bold uppercase tracking-wider text-white/70 transition-colors hover:text-white'
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
                                            className='mt-2 flex w-full items-center justify-center gap-2 rounded-lg bg-[#a3ff12] px-4 py-3 text-sm font-bold uppercase tracking-wide text-black transition-all duration-300 hover:-translate-y-0.5 hover:shadow-[0_0_20px_rgba(163,255,18,0.55)] disabled:opacity-50'
                                        >
                                            Log In
                                            <i className='fa-solid fa-arrow-right text-xs' />
                                        </button>
                                    </Form>

                                    <div className='relative my-8'>
                                        <div className='absolute inset-0 flex items-center'>
                                            <div className='w-full border-t border-gray-700' />
                                        </div>
                                        <div className='relative flex justify-center text-xs'>
                                            <span className='bg-[#000000] px-2 uppercase text-white/70'>
                                                Or continue with
                                            </span>
                                        </div>
                                    </div>

                                    <div className='space-y-3'>
                                        <button
                                            type='button'
                                            className='group flex w-full items-center justify-center gap-3 rounded-lg border border-gray-700 bg-[#000000] px-4 py-2.5 text-sm font-medium text-white transition-colors hover:border-white/60'
                                        >
                                            <i className='fab fa-google text-base text-white/90 transition-colors group-hover:text-white' />
                                            <span>Continue with Google</span>
                                        </button>
                                        <button
                                            type='button'
                                            className='group flex w-full items-center justify-center gap-3 rounded-lg border border-gray-700 bg-[#000000] px-4 py-2.5 text-sm font-medium text-white transition-colors hover:border-[#a3ff12]'
                                        >
                                            <i className='fab fa-discord text-base text-[#5865F2]' />
                                            <span className='transition-colors group-hover:text-[#a3ff12]'>
                                                Continue with Discord
                                            </span>
                                        </button>
                                    </div>
                                </div>
                            </GlowCard>

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
                        </div>
                    </div>
                </div>
            )}
        </Formik>
    );
};

export default LoginContainer;
