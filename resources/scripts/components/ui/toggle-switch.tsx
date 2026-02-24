import React from 'react';
import styled from 'styled-components';

interface ToggleSwitchProps {
    checked: boolean;
    onChange: (checked: boolean) => void;
    id?: string;
    className?: string;
    label?: string;
}

const Wrapper = styled.div`
    display: inline-flex;
    align-items: center;
    gap: 10px;

    .switch-text {
        color: #d1d5db;
        font-size: 10px;
        letter-spacing: 0.12em;
        font-weight: 700;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .switch {
        background: rgba(0, 0, 0, 0.85);
        border-radius: 30px;
        border: 2px solid rgba(255, 255, 255, 0.15);
        box-shadow: inset 0 0 6px rgba(0, 0, 0, 0.55);
        height: 36px;
        position: relative;
        width: 92px;
        display: inline-block;
        user-select: none;
    }

    .switch-check {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }

    .switch-label {
        cursor: pointer;
        display: block;
        height: 100%;
        text-indent: -9999px;
        width: 100%;
        user-select: none;
    }

    .switch-label:before {
        background: radial-gradient(circle, #3f3f46 0%, #71717a 100%);
        border-radius: 9999px;
        border: 1px solid #27272a;
        box-shadow: 0 0 5px rgba(24, 24, 27, 0.75);
        content: '';
        height: 8px;
        left: 8px;
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        transition: all 0.2s ease;
        width: 8px;
    }

    .switch-label:after {
        background: radial-gradient(circle, #84cc16 0%, #a3ff12 100%);
        border-radius: 9999px;
        border: 1px solid #3f6212;
        box-shadow: 0 0 8px rgba(163, 255, 18, 0.6), inset 0 0 3px rgba(0, 0, 0, 0.2);
        content: '';
        height: 8px;
        position: absolute;
        right: 8px;
        top: 50%;
        transform: translateY(-50%);
        transition: all 0.2s ease;
        width: 8px;
    }

    .switch-label span {
        background: linear-gradient(#454545, #1f1f1f);
        border-radius: 9999px;
        border: 1px solid #171717;
        box-shadow: 0 0 4px rgba(0, 0, 0, 0.45), inset 0 1px 1px rgba(255, 255, 255, 0.08);
        display: block;
        height: 28px;
        left: 3px;
        position: absolute;
        top: 2px;
        transition: all 0.2s linear;
        width: 40px;
    }

    .switch-check:checked + .switch-label span {
        left: 47px;
        background: linear-gradient(#84cc16, #65a30d);
        border-color: #4d7c0f;
        box-shadow: 0 0 10px rgba(163, 255, 18, 0.45), inset 0 1px 1px rgba(255, 255, 255, 0.2);
    }

    .switch-check:checked + .switch-label:before {
        background: radial-gradient(circle, #3f3f46 0%, #71717a 100%);
        border-color: #27272a;
        box-shadow: 0 0 5px rgba(24, 24, 27, 0.75);
    }

    .switch-check:checked + .switch-label:after {
        background: radial-gradient(circle, #84cc16 0%, #a3ff12 100%);
        border-color: #3f6212;
        box-shadow: 0 0 10px rgba(163, 255, 18, 0.7), inset 0 0 4px rgba(0, 0, 0, 0.2);
    }
`;

const ToggleSwitch = ({ checked, onChange, id = 'toggle-switch', className, label }: ToggleSwitchProps) => {
    return (
        <Wrapper className={className}>
            {label && <span className='switch-text'>{label}</span>}
            <div className='switch'>
                <input
                    className='switch-check'
                    id={id}
                    type='checkbox'
                    checked={checked}
                    onChange={(e) => onChange(e.currentTarget.checked)}
                />
                <label className='switch-label' htmlFor={id}>
                    Toggle
                    <span />
                </label>
            </div>
        </Wrapper>
    );
};

export default ToggleSwitch;
