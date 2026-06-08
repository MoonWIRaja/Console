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
                    className={
                        '!border-[color:var(--surface-border)] !bg-[color:var(--surface-elevated)] !text-[color:var(--foreground)] hover:!border-[color:var(--primary)] hover:!bg-[color:var(--surface-subtle)] hover:!text-[color:var(--primary)]'
                    }
                    onClick={props.onClose}
                >
                    Cancel
                </Button.Text>
                <Button.Danger
                    className={
                        '!border-[#742220] !bg-[#742220] !text-[#FEF9E1] hover:!border-[#5f1c1a] hover:!bg-[#5f1c1a]'
                    }
                    onClick={onConfirmed}
                >
                    {confirm}
                </Button.Danger>
            </Dialog.Footer>
        </Dialog>
    );
};
