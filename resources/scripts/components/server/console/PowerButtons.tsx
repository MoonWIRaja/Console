import React, { useEffect, useState } from 'react';
import { Button } from '@/components/elements/button/index';
import Can from '@/components/elements/Can';
import { ServerContext } from '@/state/server';
import { PowerAction } from '@/components/server/console/ServerConsoleContainer';
import { Dialog } from '@/components/elements/dialog';
import classNames from 'classnames';

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
                <button
                    type={'button'}
                    className={classNames(
                        'flex w-full items-center justify-center rounded-lg bg-green-600 px-4 py-3 text-sm font-bold text-white transition-colors',
                        'hover:bg-green-700',
                        status !== 'offline' &&
                        'cursor-not-allowed bg-green-600/50 text-white/50 hover:bg-green-600/50'
                    )}
                    disabled={status !== 'offline'}
                    onClick={onButtonClick.bind(this, 'start')}
                >
                    <span className={'material-icons-round mr-2 text-xl'}>play_arrow</span>
                    Start Server
                </button>
            </Can>
            <Can action={'control.restart'}>
                <button
                    type={'button'}
                    className={classNames(
                        'flex w-full items-center justify-center rounded-lg bg-amber-500 px-4 py-3 text-sm font-bold text-white transition-colors',
                        'hover:bg-amber-600',
                        !status &&
                        'cursor-not-allowed bg-amber-500/50 text-white/50 hover:bg-amber-500/50'
                    )}
                    disabled={!status}
                    onClick={onButtonClick.bind(this, 'restart')}
                >
                    <span className={'material-icons-round mr-2 text-xl'}>refresh</span>
                    Restart Server
                </button>
            </Can>
            <Can action={'control.stop'}>
                <button
                    type={'button'}
                    className={classNames(
                        'flex w-full items-center justify-center rounded-lg bg-red-600 px-4 py-3 text-sm font-bold text-white transition-colors',
                        'hover:bg-red-700',
                        status === 'offline' &&
                        'cursor-not-allowed bg-red-600/50 text-white/50 hover:bg-red-600/50'
                    )}
                    disabled={status === 'offline'}
                    onClick={onButtonClick.bind(this, killable ? 'kill' : 'stop')}
                >
                    <span className={'material-icons-round mr-2 text-xl'}>{killable ? 'warning' : 'stop'}</span>
                    {killable ? 'Kill Server' : 'Stop Server'}
                </button>
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
                <Button
                    className={'flex-1'}
                    disabled={status !== 'offline'}
                    onClick={onButtonClick.bind(this, 'start')}
                >
                    Start
                </Button>
            </Can>
            <Can action={'control.restart'}>
                <Button.Text className={'flex-1'} disabled={!status} onClick={onButtonClick.bind(this, 'restart')}>
                    Restart
                </Button.Text>
            </Can>
            <Can action={'control.stop'}>
                <Button.Danger
                    className={'flex-1'}
                    disabled={status === 'offline'}
                    onClick={onButtonClick.bind(this, killable ? 'kill' : 'stop')}
                >
                    {killable ? 'Kill' : 'Stop'}
                </Button.Danger>
            </Can>
        </div>
    );
};
