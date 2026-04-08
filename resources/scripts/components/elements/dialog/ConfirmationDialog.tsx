import React from 'react';
import { Dialog, RenderDialogProps } from './';
import { Button } from '@/components/elements/button/index';

type ConfirmationProps = Omit<RenderDialogProps, 'description' | 'children'> & {
    children: React.ReactNode;
    confirm?: string | undefined;
    onConfirmed: (e: React.MouseEvent<HTMLButtonElement, MouseEvent>) => void;
};

export default ({ confirm = 'Okay', children, onConfirmed, ...props }: ConfirmationProps) => {
    return (
        <Dialog {...props} description={typeof children === 'string' ? children : undefined}>
            {typeof children !== 'string' && children}
            <Dialog.Footer>
                <Button.Text
                    className={'!border-[color:var(--border)] !bg-[color:var(--card)] hover:!border-[#a3ff12] hover:!text-[color:var(--primary)]'}
                    onClick={props.onClose}
                >
                    Cancel
                </Button.Text>
                <Button.Danger
                    className={'!border-red-500 !bg-red-900 hover:!bg-red-800'}
                    onClick={onConfirmed}
                >
                    {confirm}
                </Button.Danger>
            </Dialog.Footer>
        </Dialog>
    );
};
