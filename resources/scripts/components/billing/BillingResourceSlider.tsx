import React, { memo } from 'react';
import isEqual from 'react-fast-compare';

interface Props {
    label: string;
    value: number;
    min: number;
    max: number;
    step?: number;
    unit: string;
    helper: string;
    onChange: (value: number) => void;
}

const BillingResourceSlider = ({ label, value, min, max, step = 1, unit, helper, onChange }: Props) => {
    const safeMax = Math.max(max, min);
    const percent = safeMax === min ? 100 : ((value - min) / (safeMax - min)) * 100;

    return (
        <div className={'rounded-2xl border border-[color:var(--border)] bg-[color:var(--background)] p-4 shadow-[0_0_0_1px_rgba(var(--primary-rgb),0.03)]'}>
            <div className={'mb-4 flex items-start justify-between gap-3'}>
                <div>
                    <label className={'block text-xs font-bold uppercase tracking-wide text-[color:var(--muted-foreground)]'}>
                        {label}
                    </label>
                    <p className={'mt-1 text-xs text-[color:var(--muted-foreground)]'}>{helper}</p>
                </div>
                <div className={'rounded-full border border-[color:var(--primary)] bg-[color:var(--primary)]/10 px-3 py-1 text-sm font-bold text-[color:var(--primary)]'}>
                    {value} {unit}
                </div>
            </div>

            <input
                type={'range'}
                min={min}
                max={safeMax}
                step={step}
                value={value}
                onChange={(event) => onChange(parseInt(event.currentTarget.value, 10))}
                className={'h-2 w-full cursor-pointer appearance-none rounded-full'}
                style={{
                    accentColor: 'var(--primary)',
                    background: `linear-gradient(90deg, var(--primary) 0%, var(--primary) ${percent}%, rgba(var(--primary-rgb), 0.14) ${percent}%, rgba(var(--primary-rgb), 0.14) 100%)`,
                }}
            />

            <div className={'mt-3 flex items-center justify-between text-[11px] uppercase tracking-wide text-[color:var(--muted-foreground)]'}>
                <span>
                    Min {min} {unit}
                </span>
                <span>
                    Max {safeMax} {unit}
                </span>
            </div>
        </div>
    );
};

export default memo(BillingResourceSlider, isEqual);
