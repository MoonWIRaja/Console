import React from 'react';
import { Dialog, DialogProps } from '@/components/elements/dialog';
import CopyOnClick from '@/components/elements/CopyOnClick';
import { Alert } from '@/components/elements/alert';
import { InteractiveHoverButton } from '@/components/ui/interactive-hover-button';

interface RecoveryTokenDialogProps extends DialogProps {
    tokens: string[];
}

export default ({ tokens, open, onClose }: RecoveryTokenDialogProps) => {
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
                        'mt-6 rounded-lg border border-[color:var(--border)] bg-[color:var(--background)] p-3 font-mono text-sm leading-6 text-[color:var(--foreground)]'
                    }
                >
                    {grouped.map((value) => (
                        <span key={value.join('_')} className={'block'}>
                            {value[0]}
                            <span className={'mx-2 selection:bg-[color:var(--card)] selection:text-white'}>&nbsp;</span>
                            {value[1]}
                            <span className={'selection:bg-[color:var(--card)] selection:text-white'}>&nbsp;</span>
                        </span>
                    ))}
                </pre>
            </CopyOnClick>
            <Alert type={'danger'} className={'mt-3'}>
                These codes will not be shown again.
            </Alert>
            <div className={'mt-6 flex justify-end'}>
                <InteractiveHoverButton type={'button'} onClick={onClose} text={'Done'} />
            </div>
        </Dialog>
    );
};
