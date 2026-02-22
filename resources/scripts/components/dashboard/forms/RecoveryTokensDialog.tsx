import React from 'react';
import { Dialog, DialogProps } from '@/components/elements/dialog';
import { Button } from '@/components/elements/button/index';
import CopyOnClick from '@/components/elements/CopyOnClick';
import { Alert } from '@/components/elements/alert';

interface RecoveryTokenDialogProps extends DialogProps {
    tokens: string[];
}

export default ({ tokens, open, onClose }: RecoveryTokenDialogProps) => {
    const doneButtonClass =
        '!bg-black !text-white !border !border-black !rounded-none hover:!bg-white hover:!text-black focus:!ring-black focus:!ring-offset-white';
    const grouped = [] as [string, string][];
    tokens.forEach((token, index) => {
        if (index % 2 === 0) {
            grouped.push([token, tokens[index + 1] || '']);
        }
    });

    return (
        <Dialog
            open={open}
            onClose={onClose}
            title={'Two-Step Authentication Enabled'}
            description={
                'Store the codes below somewhere safe. If you lose access to your phone you can use these backup codes to sign in.'
            }
            hideCloseIcon
            preventExternalClose
        >
            <Dialog.Icon position={'container'} type={'success'} />
            <CopyOnClick text={tokens.join('\n')} showInNotification={false}>
                <pre
                    className={
                        'mt-6 border border-black bg-white text-black rounded-none p-3 font-mono text-sm leading-6'
                    }
                >
                    {grouped.map((value) => (
                        <span key={value.join('_')} className={'block'}>
                            {value[0]}
                            <span className={'mx-2 selection:bg-black selection:text-white'}>&nbsp;</span>
                            {value[1]}
                            <span className={'selection:bg-black selection:text-white'}>&nbsp;</span>
                        </span>
                    ))}
                </pre>
            </CopyOnClick>
            <Alert type={'danger'} className={'mt-3'}>
                These codes will not be shown again.
            </Alert>
            <Dialog.Footer>
                <Button.Text type={'button'} onClick={onClose} className={doneButtonClass}>
                    Done
                </Button.Text>
            </Dialog.Footer>
        </Dialog>
    );
};
