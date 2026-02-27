import React from 'react';
import LumaSpin from '@/components/ui/luma-spin';

interface PageLoadingSkeletonProps {
    fullScreen?: boolean;
    showChrome?: boolean;
    showSpinner?: boolean;
    rows?: number;
    className?: string;
}

const cx = (...values: Array<string | false | null | undefined>) => values.filter(Boolean).join(' ');

const RowSkeleton = ({ index }: { index: number }) => (
    <div className='rounded-lg border border-[#1f2a14] bg-[#050505]/80 p-3'>
        <div className='flex items-center justify-between gap-3'>
            <div className='flex min-w-0 items-center gap-3'>
                <span className='h-3 w-3 rounded-full border border-[#2a3b18] bg-[#091102]' />
                <span
                    className='h-2.5 animate-pulse rounded bg-[#121212]'
                    style={{ width: `${Math.max(18, 42 - index * 3)}%` }}
                />
            </div>
            <span className='h-2.5 w-20 animate-pulse rounded bg-[#121212]' />
        </div>
    </div>
);

export const PageLoadingSkeleton: React.FC<PageLoadingSkeletonProps> = ({
    fullScreen = false,
    showChrome = true,
    showSpinner = true,
    rows = 9,
    className,
}) => {
    const safeRows = Math.max(4, rows);

    return (
        <div
            className={cx(
                'relative isolate w-full overflow-hidden border border-[#1f2a14] bg-[#000000]',
                fullScreen ? 'h-screen rounded-none border-0' : 'min-h-[420px] rounded-xl',
                className
            )}
        >
            <div className='pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_15%_-10%,rgba(163,255,18,0.16),transparent_45%)]' />
            <div className='pointer-events-none absolute inset-0 bg-[linear-gradient(180deg,rgba(255,255,255,0.03),transparent_25%)]' />

            <div className='relative z-10 flex h-full flex-col'>
                {showChrome && (
                    <div className='flex items-center justify-between border-b border-[#1f2a14] bg-[#050505] px-4 py-3'>
                        <div className='flex items-center gap-3'>
                            <span className='h-2 w-2 animate-pulse rounded-full bg-[#a3ff12]' />
                            <span className='h-2.5 w-32 animate-pulse rounded bg-[#121212]' />
                        </div>
                        <span className='h-8 w-28 animate-pulse rounded-full border border-[#1f2a14] bg-[#080808]' />
                    </div>
                )}

                <div className='relative flex-1 overflow-hidden p-4'>
                    <div className='pointer-events-none absolute inset-4 rounded-xl border border-[#1f2a14]/60 bg-[#000000]/35 backdrop-blur-[2px]' />
                    <div className='relative z-10 flex h-full flex-col gap-3'>
                        {Array.from({ length: safeRows }).map((_, index) => (
                            <RowSkeleton key={index} index={index} />
                        ))}
                    </div>
                </div>
            </div>

            {showSpinner && (
                <div className='pointer-events-none absolute inset-0 z-20 flex items-center justify-center'>
                    <div className='rounded-2xl border border-[#1f2a14] bg-black/55 p-4 backdrop-blur-md'>
                        <LumaSpin size={56} />
                    </div>
                </div>
            )}
        </div>
    );
};

export default PageLoadingSkeleton;
