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
const StyledNameInput = styled(Input)`
    ${tw`rounded-lg border-gray-800 bg-[color:var(--card)] px-4 py-3 text-sm text-white placeholder:text-white/40 focus:border-[color:var(--primary)] focus:ring-1 focus:ring-[color:var(--primary)]`}
`;

const StyledPublicKeyTextarea = styled(CustomTextarea)`
    ${tw`rounded-lg border-gray-800 bg-[color:var(--card)] px-4 py-3 text-sm text-white placeholder:text-white/40 focus:border-[color:var(--primary)] focus:ring-1 focus:ring-[color:var(--primary)]`}
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
                            <Field name={'name'} as={StyledNameInput} autoComplete={'off'} />
                        </FormikFieldWrapper>
                        <FormikFieldWrapper
                            label={'Public Key'}
                            name={'publicKey'}
                            description={'Enter your public SSH key.'}
                            css={tw`[&>label]:text-white/80 [&>label]:uppercase [&>label]:text-xs [&>label]:tracking-wide [&>label]:font-bold [&>p]:text-white/60 [&>div>p]:text-white/60 [&.has-error>p]:text-red-400 [&.has-error>div>p]:text-red-400`}
                        >
                            <Field name={'publicKey'} as={StyledPublicKeyTextarea} autoComplete={'off'} />
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
