import React, { useContext, useEffect, useState } from 'react';
import asDialog from '@/hoc/asDialog';
import { Dialog, DialogWrapperContext } from '@/components/elements/dialog';
import { Input } from '@/components/elements/inputs';
import Tooltip from '@/components/elements/tooltip/Tooltip';
import disableAccountTwoFactor from '@/api/account/disableAccountTwoFactor';
import { useFlashKey } from '@/plugins/useFlash';
import { useStoreActions } from '@/state/hooks';
import FlashMessageRender from '@/components/FlashMessageRender';
import { InteractiveHoverButton } from '@/components/ui/interactive-hover-button';

const DisableTOTPDialog = () => {
    const [submitting, setSubmitting] = useState(false);
    const [password, setPassword] = useState('');
    const { clearAndAddHttpError } = useFlashKey('account:two-step');
    const { close, setProps } = useContext(DialogWrapperContext);
    const updateUserData = useStoreActions((actions) => actions.user.updateUserData);

    useEffect(() => {
        setProps((state) => ({ ...state, preventExternalClose: submitting }));
    }, [submitting]);

    const submit = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        e.stopPropagation();

        if (submitting) return;

        setSubmitting(true);
        clearAndAddHttpError();
        disableAccountTwoFactor(password)
            .then(() => {
                updateUserData({ useTotp: false });
                close();
            })
            .catch(clearAndAddHttpError)
            .then(() => setSubmitting(false));
    };

    return (
        <form id={'disable-totp-form'} className={'mt-6 font-mono'} onSubmit={submit}>
            <FlashMessageRender byKey={'account:two-step'} className={'-mt-2 mb-6'} />
            <label className={'block pb-1 text-xs uppercase tracking-wide text-neutral-400'} htmlFor={'totp-password'}>
                Password
            </label>
            <Input.Text
                id={'totp-password'}
                type={'password'}
                variant={Input.Text.Variants.Loose}
                value={password}
                onChange={(e: React.ChangeEvent<HTMLInputElement>) => setPassword(e.currentTarget.value)}
                className={
                    '!rounded-lg !border !border-[color:var(--border)] !bg-[color:var(--background)] !text-[color:var(--foreground)] placeholder:!text-[color:var(--muted-foreground)] focus:!ring-2 focus:!ring-[color:var(--primary)]'
                }
                required
            />
            <Dialog.Footer>
                <InteractiveHoverButton
                    type={'button'}
                    onClick={close}
                    text={'Cancel'}
                    className={'!h-10 !min-w-[8.5rem] !text-xs'}
                />
                <Tooltip
                    delay={100}
                    disabled={password.length > 0}
                    content={'You must enter your account password to continue.'}
                >
                    <InteractiveHoverButton
                        type={'submit'}
                        form={'disable-totp-form'}
                        disabled={submitting || !password.length}
                        text={'Disable'}
                        className={'!h-10 !min-w-[8.5rem] !text-xs'}
                    />
                </Tooltip>
            </Dialog.Footer>
        </form>
    );
};

export default asDialog({
    title: 'Disable Two-Step Verification',
    description: 'Disabling two-step verification will make your account less secure.',
})(DisableTOTPDialog);
