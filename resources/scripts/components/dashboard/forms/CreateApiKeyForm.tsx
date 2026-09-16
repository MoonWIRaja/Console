import React, { useState } from 'react';
import { Field, Form, Formik, FormikHelpers } from 'formik';
import { object, string } from 'yup';
import FormikFieldWrapper from '@/components/elements/FormikFieldWrapper';
import createApiKey from '@/api/account/createApiKey';
import { Actions, useStoreActions } from 'easy-peasy';
import { ApplicationStore } from '@/state';
import { httpErrorToHuman } from '@/api/http';
import SpinnerOverlay from '@/components/elements/SpinnerOverlay';
import { ApiKey } from '@/api/account/getApiKeys';
import tw from 'twin.macro';
import Input, { Textarea } from '@/components/elements/Input';
import styled from 'styled-components/macro';
import ApiKeyModal from '@/components/dashboard/ApiKeyModal';
import { InteractiveHoverButton } from '@/components/ui/interactive-hover-button';

interface Values {
    description: string;
    allowedIps: string;
}

const CustomTextarea = styled(Textarea)`
    ${tw`h-32`}
`;

// Formik's <Field> reads its own `as` prop to choose what to render. styled-components'
// css prop ALSO treats `as` as a reserved prop (for polymorphic rendering) once babel
// wraps the element in styled(...) to apply that prop. Putting both `as` and `css` on
// the same <Field> lets styled-components' `as` handling win: it renders the target
// directly and never invokes Field's own render logic, so the field never receives
// Formik's value/onChange/onBlur — it silently becomes an uncontrolled input that
// visually accepts typing but never updates Formik state (submit always sees the
// initial empty values). Pre-styling the target components here, and passing the
// already-styled component as `as` with no css prop on <Field> itself, avoids the
// collision entirely.
const StyledDescriptionInput = styled(Input)`
    ${tw`rounded-lg border-gray-800 bg-[color:var(--card)] px-4 py-3 text-sm text-white placeholder:text-white/40 focus:border-[color:var(--primary)] focus:ring-1 focus:ring-[color:var(--primary)]`}
`;

const StyledAllowedIpsTextarea = styled(CustomTextarea)`
    ${tw`rounded-lg border-gray-800 bg-[color:var(--card)] px-4 py-3 text-sm text-white placeholder:text-white/40 focus:border-[color:var(--primary)] focus:ring-1 focus:ring-[color:var(--primary)]`}
`;

export default ({ onKeyCreated }: { onKeyCreated: (key: ApiKey) => void }) => {
    const [apiKey, setApiKey] = useState('');
    const { addError, clearFlashes } = useStoreActions((actions: Actions<ApplicationStore>) => actions.flashes);

    const submit = (values: Values, { setSubmitting, resetForm }: FormikHelpers<Values>) => {
        clearFlashes('account');
        createApiKey(values.description, values.allowedIps)
            .then(({ secretToken, ...key }) => {
                resetForm();
                setSubmitting(false);
                setApiKey(`${key.identifier}${secretToken}`);
                onKeyCreated(key);
            })
            .catch((error) => {
                console.error(error);

                addError({ key: 'account', message: httpErrorToHuman(error) });
                setSubmitting(false);
            });
    };

    return (
        <>
            <ApiKeyModal visible={apiKey.length > 0} onModalDismissed={() => setApiKey('')} apiKey={apiKey} />
            <Formik
                onSubmit={submit}
                initialValues={{ description: '', allowedIps: '' }}
                validationSchema={object().shape({
                    allowedIps: string(),
                    description: string().required().min(4),
                })}
            >
                {({ isSubmitting }) => (
                    <Form css={tw`font-mono`}>
                        <SpinnerOverlay visible={isSubmitting} />
                        <FormikFieldWrapper
                            label={'Description'}
                            name={'description'}
                            description={'A description of this API key.'}
                            css={tw`mb-6 [&>label]:text-white/80 [&>label]:uppercase [&>label]:text-xs [&>label]:tracking-wide [&>label]:font-bold [&>p]:text-white/60 [&>div>p]:text-white/60 [&.has-error>p]:text-red-400 [&.has-error>div>p]:text-red-400`}
                        >
                            <Field name={'description'} as={StyledDescriptionInput} />
                        </FormikFieldWrapper>
                        <FormikFieldWrapper
                            label={'Allowed IPs'}
                            name={'allowedIps'}
                            description={
                                'Leave blank to allow any IP address to use this API key, otherwise provide each IP address on a new line.'
                            }
                            css={tw`[&>label]:text-white/80 [&>label]:uppercase [&>label]:text-xs [&>label]:tracking-wide [&>label]:font-bold [&>p]:text-white/60 [&>div>p]:text-white/60 [&.has-error>p]:text-red-400 [&.has-error>div>p]:text-red-400`}
                        >
                            <Field name={'allowedIps'} as={StyledAllowedIpsTextarea} />
                        </FormikFieldWrapper>
                        <div css={tw`flex justify-end mt-6`}>
                            <InteractiveHoverButton
                                disabled={isSubmitting}
                                type={'submit'}
                                text={'Create'}
                                className={'w-full sm:w-auto'}
                            />
                        </div>
                    </Form>
                )}
            </Formik>
        </>
    );
};
