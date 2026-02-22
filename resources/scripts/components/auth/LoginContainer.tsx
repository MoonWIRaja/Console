import React, { useEffect, useRef, useState } from 'react';
import { Link, RouteComponentProps } from 'react-router-dom';
import login from '@/api/auth/login';
import LoginFormContainer from '@/components/auth/LoginFormContainer';
import { useStoreState } from 'easy-peasy';
import { Form, Formik, FormikHelpers, FormikProps } from 'formik';
import { object, string } from 'yup';
import Field from '@/components/elements/Field';
import tw from 'twin.macro';
import Button from '@/components/elements/Button';
import Reaptcha from 'reaptcha';
import useFlash from '@/plugins/useFlash';
import FlashMessageRender from '@/components/FlashMessageRender';

interface Values {
    username: string;
    password: string;
}

const LoginContainer = ({ history }: RouteComponentProps) => {
    const ref = useRef<Reaptcha>(null);
    const tokenRef = useRef('');
    const [token, setToken] = useState('');
    const [showPassword, setShowPassword] = useState(false);

    const { clearFlashes, clearAndAddHttpError } = useFlash();
    const { enabled: recaptchaEnabled, siteKey } = useStoreState((state) => state.settings.data!.recaptcha);

    useEffect(() => {
        clearFlashes();
    }, []);

    const onSubmit = (values: Values, { setSubmitting }: FormikHelpers<Values>) => {
        clearFlashes();

        // If there is no token in the state yet, request the token and then abort this submit request
        // since it will be re-submitted when the recaptcha data is returned by the component.
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

                setToken('');
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
            {({ isSubmitting, setSubmitting, submitForm, values, errors, touched, handleChange, handleBlur }: FormikProps<Values>) => (
                <div className="h-screen w-full overflow-hidden flex fixed inset-0 z-50" style={{ backgroundColor: '#ffffff' }}>
                    <style>{`
                        @import url('https://fonts.googleapis.com/css2?family=Space+Mono:ital,wght@0,400;0,700;1,400&display=swap');
                        @import url('https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css');
                        .font-mono {
                            font-family: 'Space Mono', monospace;
                        }
                        .sharp-corners {
                            border-radius: 0;
                        }
                        .login-panel input:focus {
                            outline: none;
                            border-color: #000000 !important;
                            box-shadow: none;
                        }
                    `}</style>
                    <div className="hidden lg:block w-[70%] h-full font-mono" style={{ backgroundColor: '#000000' }}>
                        <span className="sr-only">A minimalist solid black background area.</span>
                    </div>
                    <div className="login-panel w-full lg:w-[30%] h-full flex flex-col justify-center px-8 sm:px-12 md:px-16 lg:px-10 xl:px-12 overflow-y-auto font-mono" style={{ backgroundColor: '#ffffff' }}>
                        <div className="w-full max-w-md mx-auto">
                            <div className="mb-10">
                                <h1 className="text-4xl font-bold leading-tight tracking-tight" style={{ color: '#000000' }}>
                                    BurHan<br />CONSOLE
                                </h1>
                            </div>
                            <div className="flex gap-4 mb-4">
                                <button type="button" className="flex-1 py-3 text-xs font-bold tracking-wider sharp-corners border transition-colors" style={{ backgroundColor: '#000000', color: '#ffffff', borderColor: '#000000' }}>
                                    LOG IN
                                </button>
                                <button type="button" className="flex-1 py-3 text-xs font-bold tracking-wider sharp-corners border transition-colors hover:bg-gray-50" style={{ backgroundColor: 'transparent', color: '#000000', borderColor: '#000000' }}>
                                    SIGN UP
                                </button>
                            </div>

                            <FlashMessageRender className="mb-4 px-1" />

                            <Form className="space-y-5">
                                <div className="relative">
                                    <input
                                        name="username"
                                        type="text"
                                        value={values.username}
                                        onChange={handleChange}
                                        onBlur={handleBlur}
                                        disabled={isSubmitting}
                                        id="user"
                                        autoComplete="username"
                                        className="w-full border bg-transparent px-4 py-3 text-sm focus:ring-0 sharp-corners"
                                        style={{ borderColor: errors.username && touched.username ? '#ef4444' : '#000000', color: '#000000' }}
                                        placeholder="email or username"
                                    />
                                    {errors.username && touched.username && (
                                        <div className="absolute -bottom-5 left-0 text-[10px] text-red-500 font-bold">{errors.username}</div>
                                    )}
                                </div>
                                <div className="relative">
                                    <input
                                        name="password"
                                        type={showPassword ? 'text' : 'password'}
                                        value={values.password}
                                        onChange={handleChange}
                                        onBlur={handleBlur}
                                        disabled={isSubmitting}
                                        id="password"
                                        autoComplete="current-password"
                                        className="w-full border bg-transparent px-4 py-3 text-sm focus:ring-0 sharp-corners pr-16"
                                        style={{ borderColor: errors.password && touched.password ? '#ef4444' : '#000000', color: '#000000' }}
                                        placeholder="password"
                                    />
                                    <button
                                        type="button"
                                        onClick={() => setShowPassword(!showPassword)}
                                        className="absolute right-4 top-1/2 -translate-y-1/2 text-[10px] font-bold uppercase tracking-wide hover:opacity-60"
                                        style={{ color: '#000000' }}
                                    >
                                        {showPassword ? 'HIDE' : 'SHOW'}
                                    </button>
                                    {errors.password && touched.password && (
                                        <div className="absolute -bottom-5 left-0 text-[10px] text-red-500 font-bold">{errors.password}</div>
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
                                    LOG IN
                                </button>
                            </Form>

                            {recaptchaEnabled && (
                                <Reaptcha
                                    ref={ref}
                                    size={'invisible'}
                                    sitekey={siteKey || '_invalid_key'}
                                    onVerify={(response) => {
                                        tokenRef.current = response;
                                        setToken(response);
                                        submitForm();
                                    }}
                                    onExpire={() => {
                                        setSubmitting(false);
                                        tokenRef.current = '';
                                        setToken('');
                                    }}
                                />
                            )}

                            <div className="relative flex items-center py-8">
                                <div className="flex-grow border-t" style={{ borderColor: '#d1d5db' }}></div>
                                <span className="flex-shrink-0 mx-4 text-[10px] tracking-wide" style={{ color: '#9ca3af' }}>OR</span>
                                <div className="flex-grow border-t" style={{ borderColor: '#d1d5db' }}></div>
                            </div>
                            <div className="space-y-4">
                                <button type="button" className="w-full flex items-center justify-center gap-3 py-3 text-xs font-bold tracking-wide sharp-corners border transition-colors hover:bg-gray-50" style={{ backgroundColor: 'transparent', color: '#000000', borderColor: '#000000' }}>
                                    <i className="fab fa-google text-sm"></i>
                                    <span>Continue with Google</span>
                                </button>
                                <button type="button" className="w-full flex items-center justify-center gap-3 py-3 text-xs font-bold tracking-wide sharp-corners border transition-colors hover:bg-gray-50" style={{ backgroundColor: 'transparent', color: '#000000', borderColor: '#000000' }}>
                                    <i className="fab fa-discord text-sm"></i>
                                    <span>Continue with Discord</span>
                                </button>
                            </div>
                            <div className="mt-8 text-center">
                                <Link className="text-xs transition-colors hover:opacity-70" style={{ color: '#6b7280' }} to={'/auth/password'}>
                                    Forgot password?
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            )}
        </Formik>
    );
};

export default LoginContainer;
