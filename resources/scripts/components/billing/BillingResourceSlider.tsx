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
    baselineValue?: number;
    baselineLabel?: string;
    disabled?: boolean;
    onChange: (value: number) => void;
}

const BillingResourceSlider = ({
    label,
    value,
    min,
    max,
    step = 1,
    unit,
    helper,
    baselineValue,
    baselineLabel = 'Current',
    disabled = false,
    onChange,
}: Props) => {
    const safeMax = Math.max(max, min);
    const percent = safeMax === min ? 100 : ((value - min) / (safeMax - min)) * 100;
    const hasBaselineMarker = typeof baselineValue === 'number';
    const normalizedBaseline = hasBaselineMarker ? Math.min(Math.max(baselineValue, min), safeMax) : null;
    const baselinePercent =
        normalizedBaseline === null || safeMax === min ? 0 : ((normalizedBaseline - min) / (safeMax - min)) * 100;

    return (
        <div className={'billing-slider-card'}>
            <div className={'mb-4 flex items-start justify-between gap-3'}>
                <div>
                    <label
                        className={
                            'block text-xs font-bold uppercase tracking-[0.2em] text-[color:var(--muted-foreground)]'
                        }
                    >
                        {label}
                    </label>
                    <p className={'mt-1 text-xs text-[color:var(--muted-foreground)]'}>{helper}</p>
                </div>
                <div className={'billing-slider-value'}>{value}</div>
            </div>

            <div className={'relative'}>
                <input
                    type={'range'}
                    min={min}
                    max={safeMax}
                    step={step}
                    value={value}
                    onChange={(event) => onChange(parseInt(event.currentTarget.value, 10))}
                    disabled={disabled}
                    className={
                        'billing-slider-input relative z-10 h-2 w-full appearance-none rounded-full bg-transparent disabled:cursor-not-allowed disabled:opacity-60'
                    }
                    style={{
                        accentColor: 'var(--primary)',
                        background: `linear-gradient(90deg, var(--primary) 0%, var(--primary) ${percent}%, rgba(var(--primary-rgb), 0.14) ${percent}%, rgba(var(--primary-rgb), 0.14) 100%)`,
                    }}
                />

                {normalizedBaseline !== null && (
                    <div className={'pointer-events-none absolute inset-0'}>
                        <span
                            className={
                                'absolute top-1/2 h-3.5 w-3.5 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-[#f8f6ef] bg-[color:var(--card)] shadow-[0_0_0_3px_rgba(var(--primary-rgb),0.14)]'
                            }
                            style={{ left: `${baselinePercent}%` }}
                        />
                    </div>
                )}
            </div>

            <div
                className={
                    'mt-3 flex items-center justify-between text-[11px] uppercase tracking-wide text-[color:var(--muted-foreground)]'
                }
            >
                <span>
                    Min {min} {unit}
                </span>
                {normalizedBaseline !== null && (
                    <span>
                        {baselineLabel} {normalizedBaseline} {unit}
                    </span>
                )}
                <span>
                    Max {safeMax} {unit}
                </span>
            </div>
        </div>
    );
};

export default memo(BillingResourceSlider, isEqual);
