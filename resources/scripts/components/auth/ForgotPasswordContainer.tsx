import * as React from 'react';
import { useEffect, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import requestPasswordResetEmail from '@/api/auth/requestPasswordResetEmail';
import { httpErrorToHuman } from '@/api/http';
import { useStoreState } from 'easy-peasy';
import { Form, Formik, FormikHelpers, FormikProps } from 'formik';
import { object, string } from 'yup';
import Reaptcha from 'reaptcha';
import useFlash from '@/plugins/useFlash';
import FlashMessageRender from '@/components/FlashMessageRender';
import { GlowCard } from '@/components/ui/spotlight-card';

interface Values {
    email: string;
}

export default () => {
    const ref = useRef<Reaptcha>(null);
    const [token, setToken] = useState('');

    const { clearFlashes, addFlash } = useFlash();
    const { enabled: recaptchaEnabled, siteKey } = useStoreState((state) => state.settings.data!.recaptcha);

    useEffect(() => {
        clearFlashes();
    }, []);

    const handleSubmission = ({ email }: Values, { setSubmitting, resetForm }: FormikHelpers<Values>) => {
        clearFlashes();

        if (recaptchaEnabled && !token) {
            ref.current!.execute().catch((error) => {
                console.error(error);
                setSubmitting(false);
                addFlash({ type: 'error', title: 'Error', message: httpErrorToHuman(error) });
            });
            return;
        }

        requestPasswordResetEmail(email, token)
            .then((response) => {
                resetForm();
                addFlash({ type: 'success', title: 'Success', message: response });
            })
            .catch((error) => {
                console.error(error);
                addFlash({ type: 'error', title: 'Error', message: httpErrorToHuman(error) });
            })
            .then(() => {
                setToken('');
                if (ref.current) ref.current.reset();
                setSubmitting(false);
            });
    };

    return (
        <Formik
            onSubmit={handleSubmission}
            initialValues={{ email: '' }}
            validationSchema={object().shape({
                email: string()
                    .email('A valid email address must be provided to continue.')
                    .required('A valid email address must be provided to continue.'),
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
                        .font-mono {
                            font-family: 'Space Mono', monospace;
                        }
                    `}</style>
                    <div className='hidden h-full w-[70%] bg-[#000000] font-mono lg:block'>
                        <span className='sr-only'>A dark neon background area.</span>
                    </div>
                    <div className='w-full overflow-y-auto bg-[#000000] px-8 font-mono sm:px-12 md:px-16 lg:w-[30%] lg:px-10 xl:px-12'>
                        <div className='mx-auto flex h-full w-full max-w-md flex-col justify-center py-12'>
                            <div className='mb-10'>
                                <h1 className='text-4xl font-bold leading-tight tracking-tight text-[#f8f6ef] [text-shadow:0_0_14px_rgba(248,246,239,0.32)]'>
                                    BurHan Console
                                </h1>
                            </div>

                            <div className='mb-6'>
                                <h2 className='text-sm font-bold uppercase tracking-wider text-gray-200'>
                                    RESET PASSWORD
                                </h2>
                                <p className='mt-2 text-xs text-gray-400'>
                                    Enter your account email address to receive instructions on resetting your password.
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
                                <div className='rounded-xl bg-[#000000] p-8'>
                                    <Form className='space-y-5'>
                                        <div className='group space-y-1'>
                                            <label
                                                htmlFor='email'
                                                className='block text-xs uppercase text-gray-400 transition-colors group-focus-within:text-[#a3ff12]'
                                            >
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
                                                    className='w-full rounded-lg border border-gray-800 bg-[#000000] px-4 py-3 pr-11 text-sm text-gray-100 outline-none transition-all placeholder:text-gray-500 focus:border-[#a3ff12] focus:ring-1 focus:ring-[#a3ff12]'
                                                    placeholder='Enter your email'
                                                />
                                                <i className='fa-solid fa-envelope absolute right-4 top-1/2 -translate-y-1/2 text-xs text-gray-500' />
                                            </div>
                                            {errors.email && touched.email && (
                                                <div className='text-[10px] font-bold text-red-400'>{errors.email}</div>
                                            )}
                                        </div>
                                        <button
                                            type='submit'
                                            disabled={isSubmitting}
                                            className='mt-2 flex w-full items-center justify-center gap-2 rounded-lg bg-[#a3ff12] px-4 py-3 text-sm font-bold uppercase tracking-wide text-black transition-all duration-300 hover:-translate-y-0.5 hover:shadow-[0_0_20px_rgba(163,255,18,0.55)] disabled:opacity-50'
                                        >
                                            {isSubmitting ? 'Sending...' : 'Send Email'}
                                            <i className='fa-solid fa-arrow-right text-xs' />
                                        </button>
                                    </Form>
                                </div>
                            </GlowCard>

                            {recaptchaEnabled && (
                                <Reaptcha
                                    ref={ref}
                                    size={'invisible'}
                                    sitekey={siteKey || '_invalid_key'}
                                    onVerify={(response) => {
                                        setToken(response);
                                        submitForm();
                                    }}
                                    onExpire={() => {
                                        setSubmitting(false);
                                        setToken('');
                                    }}
                                />
                            )}

                            <div className='mt-8 text-center'>
                                <Link
                                    className='text-xs text-gray-500 transition-colors hover:text-[#a3ff12]'
                                    to={'/auth/login'}
                                >
                                    Return to Login
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            )}
        </Formik>
    );
};
