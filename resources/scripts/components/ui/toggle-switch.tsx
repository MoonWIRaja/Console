import React from 'react';
import styled from 'styled-components';
import Switch from '@/components/ui/switch';

interface ToggleSwitchProps {
    checked: boolean;
    onChange: (checked: boolean) => void;
    id?: string;
    className?: string;
    label?: string;
    disabled?: boolean;
}

const Wrapper = styled.div`
    display: inline-flex;
    align-items: center;
    gap: 10px;

    .switch-text {
        color: rgba(116, 34, 32, 0.60);
        font-size: 10px;
        letter-spacing: 0.12em;
        font-weight: 700;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .switch-state {
        color: #742220;
        font-size: 11px;
        letter-spacing: 0.14em;
        font-weight: 800;
        text-transform: uppercase;
        white-space: nowrap;
    }
`;

const ToggleSwitch = ({
    checked,
    onChange,
    id = 'toggle-switch',
    className,
    label,
    disabled = false,
}: ToggleSwitchProps) => {
    const labelIsState = ['on', 'off'].includes((label || '').toLowerCase());
    const stateLabel = checked ? 'On' : 'Off';

    return (
        <Wrapper className={className}>
            {label && !labelIsState && <span className='switch-text'>{label}</span>}
            <input type={'hidden'} id={id} value={checked ? '1' : '0'} disabled={disabled} />
            <Switch checked={checked} onChange={onChange} id={`${id}-control`} disabled={disabled} ariaLabel={label || 'Toggle status'} />
            {labelIsState && <span className='switch-state'>{stateLabel}</span>}
        </Wrapper>
    );
};

export default ToggleSwitch;
