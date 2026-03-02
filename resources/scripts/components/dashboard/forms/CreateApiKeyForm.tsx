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
                            <Field
                                name={'description'}
                                as={Input}
                                css={tw`rounded-lg border-gray-800 bg-[color:var(--card)] px-4 py-3 text-sm text-white placeholder:text-white/40 focus:border-[color:var(--primary)] focus:ring-1 focus:ring-[color:var(--primary)]`}
                            />
                        </FormikFieldWrapper>
                        <FormikFieldWrapper
                            label={'Allowed IPs'}
                            name={'allowedIps'}
                            description={
                                'Leave blank to allow any IP address to use this API key, otherwise provide each IP address on a new line.'
                            }
                            css={tw`[&>label]:text-white/80 [&>label]:uppercase [&>label]:text-xs [&>label]:tracking-wide [&>label]:font-bold [&>p]:text-white/60 [&>div>p]:text-white/60 [&.has-error>p]:text-red-400 [&.has-error>div>p]:text-red-400`}
                        >
                            <Field
                                name={'allowedIps'}
                                as={CustomTextarea}
                                css={tw`rounded-lg border-gray-800 bg-[color:var(--card)] px-4 py-3 text-sm text-white placeholder:text-white/40 focus:border-[color:var(--primary)] focus:ring-1 focus:ring-[color:var(--primary)]`}
                            />
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
