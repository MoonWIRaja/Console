import React, { useContext, useEffect, useState } from 'react';
import { Dialog, DialogWrapperContext } from '@/components/elements/dialog';
import getTwoFactorTokenData, { TwoFactorTokenData } from '@/api/account/getTwoFactorTokenData';
import { useFlashKey } from '@/plugins/useFlash';
import tw from 'twin.macro';
import QRCode from 'qrcode.react';
import Spinner from '@/components/elements/Spinner';
import { Input } from '@/components/elements/inputs';
import CopyOnClick from '@/components/elements/CopyOnClick';
import Tooltip from '@/components/elements/tooltip/Tooltip';
import enableAccountTwoFactor from '@/api/account/enableAccountTwoFactor';
import FlashMessageRender from '@/components/FlashMessageRender';
import { Actions, useStoreActions } from 'easy-peasy';
import { ApplicationStore } from '@/state';
import asDialog from '@/hoc/asDialog';
import { InteractiveHoverButton } from '@/components/ui/interactive-hover-button';

interface Props {
    onTokens: (tokens: string[]) => void;
}

const ConfigureTwoFactorForm = ({ onTokens }: Props) => {
    const [submitting, setSubmitting] = useState(false);
    const [value, setValue] = useState('');
    const [password, setPassword] = useState('');
    const [token, setToken] = useState<TwoFactorTokenData | null>(null);
    const { clearAndAddHttpError } = useFlashKey('account:two-step');
    const updateUserData = useStoreActions((actions: Actions<ApplicationStore>) => actions.user.updateUserData);

    const { close, setProps } = useContext(DialogWrapperContext);

    useEffect(() => {
        getTwoFactorTokenData()
            .then(setToken)
            .catch((error) => clearAndAddHttpError(error));
    }, []);

    useEffect(() => {
        setProps((state) => ({ ...state, preventExternalClose: submitting }));
    }, [submitting]);

    const submit = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        e.stopPropagation();

        if (submitting) return;

        setSubmitting(true);
        clearAndAddHttpError();
        enableAccountTwoFactor(value, password)
            .then((tokens) => {
                updateUserData({ useTotp: true });
                onTokens(tokens);
            })
            .catch((error) => {
                clearAndAddHttpError(error);
                setSubmitting(false);
            });
    };

    return (
        <form id={'enable-totp-form'} onSubmit={submit} className={'font-mono'}>
            <FlashMessageRender byKey={'account:two-step'} className={'mt-4'} />
            <div
                className={
                    'mx-auto mt-6 flex h-56 w-56 items-center justify-center rounded-lg border border-[color:var(--border)] bg-[color:var(--card)] p-3'
                }
            >
                {!token ? (
                    <Spinner />
                ) : (
                    <QRCode renderAs={'svg'} value={token.image_url_data} css={tw`w-full h-full shadow-none`} />
                )}
            </div>
            <CopyOnClick text={token?.secret}>
                <p className={'mt-3 text-center font-mono text-sm tracking-wide text-[#f8f6ef]'}>
                    {token?.secret?.match(/.{1,4}/g)?.join(' ') || 'Loading...'}
                </p>
            </CopyOnClick>
            <p id={'totp-code-description'} className={'mt-6 text-sm text-neutral-300'}>
                Scan the QR code above using the two-step authentication app of your choice. Then, enter the 6-digit
                code generated into the field below.
            </p>
            <Input.Text
                aria-labelledby={'totp-code-description'}
                variant={Input.Text.Variants.Loose}
                value={value}
                onChange={(e: React.ChangeEvent<HTMLInputElement>) =>
                    setValue(e.currentTarget.value.replace(/\D/g, '').slice(0, 6))
                }
                className={
                    'mt-3 !rounded-lg !border !border-[color:var(--border)] !bg-[color:var(--background)] !text-[color:var(--foreground)] placeholder:!text-[color:var(--muted-foreground)] focus:!ring-2 focus:!ring-[color:var(--primary)]'
                }
                placeholder={'000000'}
                type={'text'}
                inputMode={'numeric'}
                autoComplete={'one-time-code'}
                pattern={'\\d{6}'}
                maxLength={6}
                required
            />
            <label htmlFor={'totp-password'} className={'mt-3 block text-xs uppercase tracking-wide text-neutral-400'}>
                Account Password
            </label>
            <Input.Text
                id={'totp-password'}
                variant={Input.Text.Variants.Loose}
                className={
                    'mt-1 !rounded-lg !border !border-[color:var(--border)] !bg-[color:var(--background)] !text-[color:var(--foreground)] placeholder:!text-[color:var(--muted-foreground)] focus:!ring-2 focus:!ring-[color:var(--primary)]'
                }
                type={'password'}
                value={password}
                onChange={(e: React.ChangeEvent<HTMLInputElement>) => setPassword(e.currentTarget.value)}
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
                    disabled={password.length > 0 && value.length === 6}
                    content={
                        !token
                            ? 'Waiting for QR code to load...'
                            : 'You must enter the 6-digit code and your password to continue.'
                    }
                    delay={100}
                >
                    <InteractiveHoverButton
                        disabled={submitting || !token || value.length !== 6 || !password.length}
                        type={'submit'}
                        form={'enable-totp-form'}
                        text={'Enable'}
                        className={'!h-10 !min-w-[8.5rem] !text-xs'}
                    />
                </Tooltip>
            </Dialog.Footer>
        </form>
    );
};

export default asDialog({
    title: 'Enable Two-Step Verification',
    description:
        "Help protect your account from unauthorized access. You'll be prompted for a verification code each time you sign in.",
})(ConfigureTwoFactorForm);
