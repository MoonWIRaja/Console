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
            {({ isSubmitting, setSubmitting, submitForm, values, errors, touched, handleChange, handleBlur }: FormikProps<Values>) => (
                <div className="h-screen w-full overflow-hidden flex fixed inset-0 z-50" style={{ backgroundColor: '#ffffff' }}>
                    <style>{`
                        @import url('https://fonts.googleapis.com/css2?family=Space+Mono:ital,wght@0,400;0,700;1,400&display=swap');
                        .font-mono {
                            font-family: 'Space Mono', monospace;
                        }
                        .sharp-corners {
                            border-radius: 0;
                        }
                        .forgot-panel input:focus {
                            outline: none;
                            border-color: #000000 !important;
                            box-shadow: none;
                        }
                    `}</style>
                    <div className="hidden lg:block w-[70%] h-full font-mono" style={{ backgroundColor: '#000000' }}>
                        <span className="sr-only">A minimalist solid black background area.</span>
                    </div>
                    <div className="forgot-panel w-full lg:w-[30%] h-full flex flex-col justify-center px-8 sm:px-12 md:px-16 lg:px-10 xl:px-12 overflow-y-auto font-mono" style={{ backgroundColor: '#ffffff' }}>
                        <div className="w-full max-w-md mx-auto">
                            <div className="mb-10">
                                <h1 className="text-4xl font-bold leading-tight tracking-tight" style={{ color: '#000000' }}>
                                    BurHan<br />CONSOLE
                                </h1>
                            </div>

                            <div className="mb-6">
                                <h2 className="text-sm font-bold tracking-wider uppercase" style={{ color: '#000000' }}>
                                    RESET PASSWORD
                                </h2>
                                <p className="mt-2 text-xs" style={{ color: '#6b7280' }}>
                                    Enter your account email address to receive instructions on resetting your password.
                                </p>
                            </div>

                            <FlashMessageRender className="mb-4 px-1" />

                            <Form className="space-y-5">
                                <div className="relative">
                                    <input
                                        name="email"
                                        type="email"
                                        value={values.email}
                                        onChange={handleChange}
                                        onBlur={handleBlur}
                                        disabled={isSubmitting}
                                        id="email"
                                        autoComplete="email"
                                        className="w-full border bg-transparent px-4 py-3 text-sm focus:ring-0 sharp-corners"
                                        style={{ borderColor: errors.email && touched.email ? '#ef4444' : '#000000', color: '#000000' }}
                                        placeholder="email address"
                                    />
                                    {errors.email && touched.email && (
                                        <div className="absolute -bottom-5 left-0 text-[10px] text-red-500 font-bold">{errors.email}</div>
                                    )}
                                </div>
                                <div className="mt-4">
                                </div>
                                <button
                                    type="submit"
                                    disabled={isSubmitting}
                                    className="w-full py-4 text-xs font-bold tracking-wider sharp-corners border mt-2 transition-colors disabled:opacity-50 hover:opacity-90"
                                    style={{ backgroundColor: '#000000', color: '#ffffff', borderColor: '#000000' }}
                                >
                                    {isSubmitting ? 'SENDING...' : 'SEND EMAIL'}
                                </button>
                            </Form>

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

                            <div className="mt-8 text-center">
                                <Link className="text-xs transition-colors hover:opacity-70" style={{ color: '#6b7280' }} to={'/auth/login'}>
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
