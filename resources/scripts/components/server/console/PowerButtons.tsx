import React, { useEffect, useState } from 'react';
import Can from '@/components/elements/Can';
import { ServerContext } from '@/state/server';
import { PowerAction } from '@/components/server/console/ServerConsoleContainer';
import { Dialog } from '@/components/elements/dialog';
import { InteractiveHoverButton } from '@/components/ui/interactive-hover-button';

interface PowerButtonProps {
    className?: string;
    variant?: 'default' | 'glass';
}

export default ({ className, variant = 'default' }: PowerButtonProps) => {
    const [open, setOpen] = useState(false);
    const status = ServerContext.useStoreState((state) => state.status.value);
    const instance = ServerContext.useStoreState((state) => state.socket.instance);

    const killable = status === 'stopping';
    const onButtonClick = (
        action: PowerAction | 'kill-confirmed',
        e: React.MouseEvent<HTMLButtonElement, MouseEvent>
    ): void => {
        e.preventDefault();
        if (action === 'kill') {
            return setOpen(true);
        }

        if (instance) {
            setOpen(false);
            instance.send('set state', action === 'kill-confirmed' ? 'kill' : action);
        }
    };

    useEffect(() => {
        if (status === 'offline') {
            setOpen(false);
        }
    }, [status]);

    return variant === 'glass' ? (
        <div className={className}>
            <Dialog.Confirm
                open={open}
                hideCloseIcon
                onClose={() => setOpen(false)}
                title={'Forcibly Stop Process'}
                confirm={'Continue'}
                onConfirmed={onButtonClick.bind(this, 'kill-confirmed')}
            >
                Forcibly stopping a server can lead to data corruption.
            </Dialog.Confirm>
            <Can action={'control.start'}>
                <InteractiveHoverButton
                    type={'button'}
                    className={'w-full'}
                    text={'Start Server'}
                    variant={'success'}
                    disabled={status !== 'offline'}
                    onClick={onButtonClick.bind(this, 'start')}
                />
            </Can>
            <Can action={'control.restart'}>
                <InteractiveHoverButton
                    type={'button'}
                    className={'w-full'}
                    text={'Restart Server'}
                    variant={'warning'}
                    disabled={!status}
                    onClick={onButtonClick.bind(this, 'restart')}
                />
            </Can>
            <Can action={'control.stop'}>
                <InteractiveHoverButton
                    type={'button'}
                    className={'w-full'}
                    variant={'danger'}
                    text={killable ? 'Kill Server' : 'Stop Server'}
                    disabled={status === 'offline'}
                    onClick={onButtonClick.bind(this, killable ? 'kill' : 'stop')}
                />
            </Can>
        </div>
    ) : (
        <div className={className}>
            <Dialog.Confirm
                open={open}
                hideCloseIcon
                onClose={() => setOpen(false)}
                title={'Forcibly Stop Process'}
                confirm={'Continue'}
                onConfirmed={onButtonClick.bind(this, 'kill-confirmed')}
            >
                Forcibly stopping a server can lead to data corruption.
            </Dialog.Confirm>
            <Can action={'control.start'}>
                <InteractiveHoverButton
                    type={'button'}
                    className={'flex-1'}
                    text={'Start'}
                    variant={'success'}
                    disabled={status !== 'offline'}
                    onClick={onButtonClick.bind(this, 'start')}
                />
            </Can>
            <Can action={'control.restart'}>
                <InteractiveHoverButton
                    type={'button'}
                    className={'flex-1'}
                    text={'Restart'}
                    variant={'warning'}
                    disabled={!status}
                    onClick={onButtonClick.bind(this, 'restart')}
                />
            </Can>
            <Can action={'control.stop'}>
                <InteractiveHoverButton
                    type={'button'}
                    className={'flex-1'}
                    variant={'danger'}
                    text={killable ? 'Kill' : 'Stop'}
                    disabled={status === 'offline'}
                    onClick={onButtonClick.bind(this, killable ? 'kill' : 'stop')}
                />
            </Can>
        </div>
    );
};
