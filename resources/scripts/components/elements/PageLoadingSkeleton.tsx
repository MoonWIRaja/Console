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
    <div className='rounded-lg border p-3' style={{ borderColor: 'var(--border)', backgroundColor: 'rgba(var(--card-rgb), 0.8)' }}>
        <div className='flex items-center justify-between gap-3'>
            <div className='flex min-w-0 items-center gap-3'>
                <span
                    className='h-3 w-3 rounded-full border'
                    style={{
                        borderColor: 'rgba(var(--primary-rgb), 0.35)',
                        backgroundColor: 'rgba(var(--primary-rgb), 0.16)',
                    }}
                />
                <span
                    className='h-2.5 animate-pulse rounded'
                    style={{ backgroundColor: 'var(--muted)', width: `${Math.max(18, 42 - index * 3)}%` }}
                />
            </div>
            <span className='h-2.5 w-20 animate-pulse rounded' style={{ backgroundColor: 'var(--muted)' }} />
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
                'relative isolate w-full overflow-hidden border',
                fullScreen ? 'h-screen rounded-none border-0' : 'min-h-[420px] rounded-xl',
                className
            )}
            style={{ borderColor: 'var(--border)', backgroundColor: 'var(--background)' }}
        >
            <div
                className='pointer-events-none absolute inset-0'
                style={{ background: 'radial-gradient(circle at 15% -10%, rgba(var(--primary-rgb), 0.16), transparent 45%)' }}
            />
            <div
                className='pointer-events-none absolute inset-0'
                style={{ background: 'linear-gradient(180deg, rgba(255,255,255,0.03), transparent 25%)' }}
            />

            <div className='relative z-10 flex h-full flex-col'>
                {showChrome && (
                    <div
                        className='flex items-center justify-between border-b px-4 py-3'
                        style={{ borderColor: 'var(--border)', backgroundColor: 'var(--card)' }}
                    >
                        <div className='flex items-center gap-3'>
                            <span className='h-2 w-2 animate-pulse rounded-full' style={{ backgroundColor: 'var(--primary)' }} />
                            <span className='h-2.5 w-32 animate-pulse rounded' style={{ backgroundColor: 'var(--muted)' }} />
                        </div>
                        <span
                            className='h-8 w-28 animate-pulse rounded-full border'
                            style={{ borderColor: 'var(--border)', backgroundColor: 'var(--background)' }}
                        />
                    </div>
                )}

                <div className='relative flex-1 overflow-hidden p-4'>
                    <div
                        className='pointer-events-none absolute inset-4 rounded-xl border backdrop-blur-[2px]'
                        style={{
                            borderColor: 'rgba(var(--primary-rgb), 0.18)',
                            backgroundColor: 'rgba(var(--background-rgb), 0.35)',
                        }}
                    />
                    <div className='relative z-10 flex h-full flex-col gap-3'>
                        {Array.from({ length: safeRows }).map((_, index) => (
                            <RowSkeleton key={index} index={index} />
                        ))}
                    </div>
                </div>
            </div>

            {showSpinner && (
                <div className='pointer-events-none absolute inset-0 z-20 flex items-center justify-center'>
                    <div
                        className='rounded-2xl border p-4 backdrop-blur-md'
                        style={{
                            borderColor: 'var(--border)',
                            backgroundColor: 'rgba(var(--background-rgb), 0.55)',
                        }}
                    >
                        <LumaSpin size={56} />
                    </div>
                </div>
            )}
        </div>
    );
};

export default PageLoadingSkeleton;
