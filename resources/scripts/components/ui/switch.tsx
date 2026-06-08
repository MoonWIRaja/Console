import React from 'react';
import styled from 'styled-components/macro';

interface SwitchProps {
    checked: boolean;
    onChange: (checked: boolean) => void;
    id?: string;
    disabled?: boolean;
    className?: string;
    ariaLabel?: string;
}

const Switch = ({
    checked,
    onChange,
    id = 'ui-switch',
    disabled = false,
    className,
    ariaLabel = 'Toggle switch',
}: SwitchProps) => (
    <StyledWrapper className={className} data-disabled={disabled ? 'true' : 'false'}>
        <input
            id={id}
            type='checkbox'
            checked={checked}
            disabled={disabled}
            aria-label={ariaLabel}
            onChange={(event) => onChange(event.currentTarget.checked)}
        />
        <label className='switch' htmlFor={id} aria-hidden='true'>
            <svg viewBox='0 0 212.4992 84.4688' overflow='visible'>
                <path
                    pathLength={360}
                    fill='none'
                    stroke='currentColor'
                    d='M 42.2496 0 A 42.24 42.24 90 0 0 0 42.2496 A 42.24 42.24 90 0 0 42.2496 84.4688 A 42.24 42.24 90 0 0 84.4992 42.2496 A 42.24 42.24 90 0 0 42.2496 0 A 42.24 42.24 90 0 0 0 42.2496 A 42.24 42.24 90 0 0 42.2496 84.4688 L 170.2496 84.4688 A 42.24 42.24 90 0 0 212.4992 42.2496 A 42.24 42.24 90 0 0 170.2496 0 A 42.24 42.24 90 0 0 128 42.2496 A 42.24 42.24 90 0 0 170.2496 84.4688 A 42.24 42.24 90 0 0 212.4992 42.2496 A 42.24 42.24 90 0 0 170.2496 0 L 42.2496 0'
                />
            </svg>
        </label>
    </StyledWrapper>
);

const StyledWrapper = styled.div`
    display: inline-flex;
    align-items: center;
    justify-content: center;

    input {
        position: absolute;
        opacity: 0;
        width: 0;
        height: 0;
        pointer-events: none;
    }

    .switch {
        --switch-duration: 0.44s ease-out;
        cursor: pointer;
        position: relative;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        height: 2.15rem;
        aspect-ratio: 212.4992 / 84.4688;
        border-radius: 999px;
        color: #742220;
        border: 2px solid #2d4a3e;
        background-color: #f5efd5;
        background-image:
            repeating-linear-gradient(
                0deg,
                transparent,
                transparent 4.5px,
                rgba(116, 34, 32, 0.04) 4.5px,
                rgba(116, 34, 32, 0.04) 5px
            ),
            repeating-linear-gradient(
                60deg,
                transparent,
                transparent 4.5px,
                rgba(116, 34, 32, 0.04) 4.5px,
                rgba(116, 34, 32, 0.04) 5px
            ),
            repeating-linear-gradient(
                120deg,
                transparent,
                transparent 4.5px,
                rgba(116, 34, 32, 0.04) 4.5px,
                rgba(116, 34, 32, 0.04) 5px
            );
        box-shadow: 4px 4px 0 0 #2d4a3e;
        transition:
            transform 0.18s ease,
            box-shadow 0.18s ease,
            border-color 0.18s ease,
            background-color 0.18s ease;
    }

    .switch:hover {
        transform: translate(1px, 1px);
        box-shadow: 3px 3px 0 0 #2d4a3e;
    }

    .switch svg {
        height: 100%;
        width: auto;
    }

    .switch svg path {
        stroke-width: 16;
        stroke-linecap: round;
        stroke-linejoin: round;
        stroke-dasharray: 136 224;
        transition:
            stroke-dashoffset var(--switch-duration),
            transform var(--switch-duration),
            color 0.18s ease;
        transform-origin: center;
        color: #742220;
    }

    input:checked + .switch {
        color: #14532d;
        border-color: #166534;
        background-color: #dcfce7;
        background-image:
            repeating-linear-gradient(
                0deg,
                transparent,
                transparent 4.5px,
                rgba(22, 101, 52, 0.05) 4.5px,
                rgba(22, 101, 52, 0.05) 5px
            ),
            repeating-linear-gradient(
                60deg,
                transparent,
                transparent 4.5px,
                rgba(22, 101, 52, 0.05) 4.5px,
                rgba(22, 101, 52, 0.05) 5px
            ),
            repeating-linear-gradient(
                120deg,
                transparent,
                transparent 4.5px,
                rgba(22, 101, 52, 0.05) 4.5px,
                rgba(22, 101, 52, 0.05) 5px
            );
        box-shadow: 4px 4px 0 0 #166534;
    }

    input:checked + .switch:hover {
        box-shadow: 3px 3px 0 0 #166534;
    }

    input:checked + .switch svg path {
        stroke-dashoffset: 180;
        transform: scaleY(-1);
        color: #166534;
    }

    input:focus-visible + .switch {
        outline: none;
        box-shadow:
            4px 4px 0 0 #2d4a3e,
            0 0 0 4px rgba(45, 74, 62, 0.18);
    }

    input:checked:focus-visible + .switch {
        box-shadow:
            4px 4px 0 0 #166534,
            0 0 0 4px rgba(34, 197, 94, 0.2);
    }

    &[data-disabled='true'] .switch {
        cursor: not-allowed;
        opacity: 0.56;
        transform: none;
        box-shadow: 2px 2px 0 0 rgba(45, 74, 62, 0.45);
    }
`;

export default Switch;
