import React from 'react';
import { Field, Form, Formik, FormikHelpers } from 'formik';
import { object, string } from 'yup';
import FormikFieldWrapper from '@/components/elements/FormikFieldWrapper';
import SpinnerOverlay from '@/components/elements/SpinnerOverlay';
import tw from 'twin.macro';
import Input, { Textarea } from '@/components/elements/Input';
import styled from 'styled-components/macro';
import { useFlashKey } from '@/plugins/useFlash';
import { createSSHKey, useSSHKeys } from '@/api/account/ssh-keys';
import { InteractiveHoverButton } from '@/components/ui/interactive-hover-button';

interface Values {
    name: string;
    publicKey: string;
}

const CustomTextarea = styled(Textarea)`
    ${tw`h-32`}
`;

export default () => {
    const { clearAndAddHttpError } = useFlashKey('account');
    const { mutate } = useSSHKeys();

    const submit = (values: Values, { setSubmitting, resetForm }: FormikHelpers<Values>) => {
        clearAndAddHttpError();

        createSSHKey(values.name, values.publicKey)
            .then((key) => {
                resetForm();
                mutate((data) => (data || []).concat(key));
            })
            .catch((error) => clearAndAddHttpError(error))
            .then(() => setSubmitting(false));
    };

    return (
        <>
            <Formik
                onSubmit={submit}
                initialValues={{ name: '', publicKey: '' }}
                validationSchema={object().shape({
                    name: string().required(),
                    publicKey: string().required(),
                })}
            >
                {({ isSubmitting }) => (
                    <Form css={tw`font-mono`}>
                        <SpinnerOverlay visible={isSubmitting} />
                        <FormikFieldWrapper
                            label={'SSH Key Name'}
                            name={'name'}
                            css={tw`mb-6 [&>label]:text-white/80 [&>label]:uppercase [&>label]:text-xs [&>label]:tracking-wide [&>label]:font-bold [&>p]:text-white/60 [&>div>p]:text-white/60 [&.has-error>p]:text-red-400 [&.has-error>div>p]:text-red-400`}
                        >
                            <Field
                                name={'name'}
                                as={Input}
                                css={tw`rounded-lg border-gray-800 bg-[color:var(--card)] px-4 py-3 text-sm text-white placeholder:text-white/40 focus:border-[color:var(--primary)] focus:ring-1 focus:ring-[color:var(--primary)]`}
                            />
                        </FormikFieldWrapper>
                        <FormikFieldWrapper
                            label={'Public Key'}
                            name={'publicKey'}
                            description={'Enter your public SSH key.'}
                            css={tw`[&>label]:text-white/80 [&>label]:uppercase [&>label]:text-xs [&>label]:tracking-wide [&>label]:font-bold [&>p]:text-white/60 [&>div>p]:text-white/60 [&.has-error>p]:text-red-400 [&.has-error>div>p]:text-red-400`}
                        >
                            <Field
                                name={'publicKey'}
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
