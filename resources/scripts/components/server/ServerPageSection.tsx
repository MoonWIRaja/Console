import React from 'react';

const CARD_TEXTURE = [
    'repeating-linear-gradient(0deg, transparent, transparent 4.5px, rgba(116, 34, 32, 0.04) 4.5px, rgba(116, 34, 32, 0.04) 5px)',
    'repeating-linear-gradient(60deg, transparent, transparent 4.5px, rgba(116, 34, 32, 0.04) 4.5px, rgba(116, 34, 32, 0.04) 5px)',
    'repeating-linear-gradient(120deg, transparent, transparent 4.5px, rgba(116, 34, 32, 0.04) 4.5px, rgba(116, 34, 32, 0.04) 5px)',
].join(', ');

interface ServerPageSectionProps {
    title: string;
    eyebrow?: string;
    description?: string;
    actions?: React.ReactNode;
    className?: string;
    bodyClassName?: string;
    children: React.ReactNode;
}

export const ServerPageSection = ({
    title,
    eyebrow,
    description,
    actions,
    className,
    bodyClassName,
    children,
}: ServerPageSectionProps) => (
    <section
        className={['overflow-hidden rounded-[22px]', className].filter(Boolean).join(' ')}
        style={{ border: '2px solid #2D4A3E', backgroundColor: '#FEF9E1', backgroundImage: CARD_TEXTURE, boxShadow: '4px 4px 0px 0px #2D4A3E', color: '#742220' }}
    >
        <div
            className={'flex flex-wrap items-start justify-between gap-4 border-b px-5 py-4'}
            style={{ borderColor: '#2D4A3E', background: '#F5EFD5' }}
        >
            <div className={'min-w-0'}>
                {eyebrow && (
                    <p className={'text-[10px] font-semibold uppercase tracking-[0.28em]'} style={{ color: 'rgba(116,34,32,0.55)' }}>
                        {eyebrow}
                    </p>
                )}
                <h2 className={'mt-2 text-xl font-semibold'} style={{ color: '#742220' }}>{title}</h2>
                {description && (
                    <p className={'mt-2 max-w-3xl text-sm leading-6'} style={{ color: 'rgba(116,34,32,0.55)' }}>{description}</p>
                )}
            </div>
            {actions && <div className={'flex w-full flex-wrap justify-end gap-3 sm:w-auto'}>{actions}</div>}
        </div>
        <div className={['px-5 py-5', bodyClassName].filter(Boolean).join(' ')}>{children}</div>
    </section>
);

interface ServerMetricCardProps {
    label: string;
    value: React.ReactNode;
    helper?: React.ReactNode;
    className?: string;
}

export const ServerMetricCard = ({ label, value, helper, className }: ServerMetricCardProps) => (
    <div
        className={['rounded-[16px] px-4 py-4', className].filter(Boolean).join(' ')}
        style={{ border: '2px solid #2D4A3E', backgroundColor: '#FEF9E1', backgroundImage: CARD_TEXTURE, boxShadow: '4px 4px 0px 0px #2D4A3E', color: '#742220' }}
    >
        <p className={'text-[10px] font-semibold uppercase tracking-[0.22em]'} style={{ color: 'rgba(116,34,32,0.55)' }}>
            {label}
        </p>
        <div className={'mt-2 text-2xl font-semibold leading-none'} style={{ color: '#742220' }}>{value}</div>
        {helper && <p className={'mt-2 text-sm'} style={{ color: 'rgba(116,34,32,0.55)' }}>{helper}</p>}
    </div>
);
