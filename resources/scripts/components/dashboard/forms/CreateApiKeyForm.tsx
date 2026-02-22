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

interface Values {
    description: string;
    allowedIps: string;
}

const CustomTextarea = styled(Textarea)`
    ${tw`h-32`}
`;

const SubmitButton = styled.button`
    ${tw`w-full sm:w-auto px-8 py-3 text-xs font-bold tracking-wider uppercase border transition-all duration-150`};
    border-radius: 0;
    background-color: #000000;
    color: #ffffff;
    border-color: #000000;

    &:hover:not(:disabled) {
        opacity: 0.9;
    }

    &:disabled {
        opacity: 0.55;
        cursor: default;
    }
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
                            css={tw`mb-6 [&>label]:text-neutral-800 [&>label]:tracking-wide [&>label]:font-bold [&>p]:text-neutral-500 [&>div>p]:text-neutral-500 [&.has-error>p]:text-red-500 [&.has-error>div>p]:text-red-500`}
                        >
                            <Field
                                name={'description'}
                                as={Input}
                                isLight
                                css={tw`rounded-none border-black text-black focus:border-black focus:ring-black focus:ring-opacity-20`}
                            />
                        </FormikFieldWrapper>
                        <FormikFieldWrapper
                            label={'Allowed IPs'}
                            name={'allowedIps'}
                            description={
                                'Leave blank to allow any IP address to use this API key, otherwise provide each IP address on a new line.'
                            }
                            css={tw`[&>label]:text-neutral-800 [&>label]:tracking-wide [&>label]:font-bold [&>p]:text-neutral-500 [&>div>p]:text-neutral-500 [&.has-error>p]:text-red-500 [&.has-error>div>p]:text-red-500`}
                        >
                            <Field
                                name={'allowedIps'}
                                as={CustomTextarea}
                                isLight
                                css={tw`rounded-none border-black text-black focus:border-black focus:ring-black focus:ring-opacity-20`}
                            />
                        </FormikFieldWrapper>
                        <div css={tw`flex justify-end mt-6`}>
                            <SubmitButton disabled={isSubmitting} type={'submit'}>
                                Create
                            </SubmitButton>
                        </div>
                    </Form>
                )}
            </Formik>
        </>
    );
};
