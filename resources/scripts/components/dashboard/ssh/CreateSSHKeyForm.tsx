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

interface Values {
    name: string;
    publicKey: string;
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
                            css={tw`mb-6 [&>label]:text-neutral-800 [&>label]:tracking-wide [&>label]:font-bold [&>p]:text-neutral-500 [&>div>p]:text-neutral-500 [&.has-error>p]:text-red-500 [&.has-error>div>p]:text-red-500`}
                        >
                            <Field
                                name={'name'}
                                as={Input}
                                isLight
                                css={tw`rounded-none border-black text-black focus:border-black focus:ring-black focus:ring-opacity-20`}
                            />
                        </FormikFieldWrapper>
                        <FormikFieldWrapper
                            label={'Public Key'}
                            name={'publicKey'}
                            description={'Enter your public SSH key.'}
                            css={tw`[&>label]:text-neutral-800 [&>label]:tracking-wide [&>label]:font-bold [&>p]:text-neutral-500 [&>div>p]:text-neutral-500 [&.has-error>p]:text-red-500 [&.has-error>div>p]:text-red-500`}
                        >
                            <Field
                                name={'publicKey'}
                                as={CustomTextarea}
                                isLight
                                css={tw`rounded-none border-black text-black focus:border-black focus:ring-black focus:ring-opacity-20`}
                            />
                        </FormikFieldWrapper>
                        <div css={tw`flex justify-end mt-6`}>
                            <SubmitButton disabled={isSubmitting} type={'submit'}>
                                Save
                            </SubmitButton>
                        </div>
                    </Form>
                )}
            </Formik>
        </>
    );
};
