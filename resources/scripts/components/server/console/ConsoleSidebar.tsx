import React, { useEffect, useState } from 'react';
import { useLocation } from 'react-router-dom';
import Console from '@/components/server/console/Console';

interface Props {
    syncMode?: 'full' | 'live-only';
    contributeToSharedTranscript?: boolean;
}

export default ({ syncMode = 'full', contributeToSharedTranscript = true }: Props) => {
    const location = useLocation();
    const [open, setOpen] = useState(false);

    useEffect(() => {
        setOpen(false);
    }, [location.pathname]);

    useEffect(() => {
        if (!open) {
            return;
        }

        const onKeyDown = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                setOpen(false);
            }
        };

        window.addEventListener('keydown', onKeyDown);

        return () => window.removeEventListener('keydown', onKeyDown);
    }, [open]);

    return (
        <>
            <div className={'hidden xl:flex min-h-0 flex-none items-stretch'}>
                {!open ? (
                    <div className={'flex min-h-0 items-center pl-2'}>
                        <button
                            type={'button'}
                            aria-label={'Open console'}
                            title={'Open console'}
                            className={
                                'inline-flex h-[84px] w-[28px] items-center justify-center rounded-l-xl rounded-r-none border-2 border-r-0 border-[#2D4A3E] bg-[#FEF9E1] text-[#742220] transition-colors hover:text-[#2D4A3E]'
                            }
                            onClick={() => setOpen(true)}
                        >
                            <span className={'material-icons-round text-[18px]'}>chevron_left</span>
                        </button>
                    </div>
                ) : (
                    <aside className={'flex h-full min-h-0 w-[26rem] min-w-0 flex-none pl-4 py-4 pr-4'}>
                        <Console
                            variant={'sidebar'}
                            onRequestClose={() => setOpen(false)}
                            syncMode={syncMode}
                            contributeToSharedTranscript={contributeToSharedTranscript}
                        />
                    </aside>
                )}
            </div>

            <div className={'xl:hidden'}>
                {!open && (
                    <div className={'pointer-events-none absolute inset-y-0 right-0 z-40 flex items-center'}>
                        <button
                            type={'button'}
                            aria-label={'Open console'}
                            title={'Open console'}
                            className={
                                'pointer-events-auto inline-flex h-[84px] w-[28px] items-center justify-center rounded-l-xl rounded-r-none border-2 border-r-0 border-[#2D4A3E] bg-[#FEF9E1] text-[#742220] transition-colors hover:text-[#2D4A3E]'
                            }
                            onClick={() => setOpen(true)}
                        >
                            <span className={'material-icons-round text-[18px]'}>chevron_left</span>
                        </button>
                    </div>
                )}

                {open && (
                    <>
                        <button
                            type={'button'}
                            aria-label={'Close console drawer'}
                            className={'absolute inset-0 z-30 bg-black/20 backdrop-blur-[1px]'}
                            onClick={() => setOpen(false)}
                        />
                        <div className={'pointer-events-none absolute inset-y-2 right-0 z-40 flex justify-end pr-2'}>
                            <aside
                                className={
                                    'pointer-events-auto flex h-full min-h-0 w-[min(26rem,calc(100vw-0.5rem))] max-w-full min-w-0'
                                }
                            >
                                <Console
                                    variant={'sidebar'}
                                    onRequestClose={() => setOpen(false)}
                                    syncMode={syncMode}
                                    contributeToSharedTranscript={contributeToSharedTranscript}
                                />
                            </aside>
                        </div>
                    </>
                )}
            </div>
        </>
    );
};
