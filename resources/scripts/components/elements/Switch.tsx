import React, { useMemo } from 'react';
import styled from 'styled-components/macro';
import { v4 } from 'uuid';
import tw from 'twin.macro';
import Label from '@/components/elements/Label';
import Input from '@/components/elements/Input';

const ToggleContainer = styled.div`
    ${tw`relative select-none w-12 leading-normal`};

    & > input[type='checkbox'] {
        ${tw`hidden`};

        &:checked + label {
            ${tw`border-[#2f5e1b] bg-[#17310d] shadow-none`};
        }

        &:checked + label:before {
            right: 0.125rem;
            border-color: #2f5e1b;
            background: #d9ff93;
        }
    }

    & > label {
        ${tw`mb-0 block h-6 cursor-pointer overflow-hidden rounded-full border border-[#1f2a14] bg-[#050505] shadow-inner`};
        transition: all 75ms linear;

        &::before {
            ${tw`absolute block h-5 w-5 rounded-full border border-[#1f2a14] bg-[#f8f6ef]`};
            top: 0.125rem;
            right: calc(50% + 0.125rem);
            //width: 1.25rem;
            //height: 1.25rem;
            content: '';
            transition: all 75ms ease-in;
        }
    }
`;

export interface SwitchProps {
    name: string;
    label?: string;
    description?: string;
    defaultChecked?: boolean;
    readOnly?: boolean;
    onChange?: (e: React.ChangeEvent<HTMLInputElement>) => void;
    children?: React.ReactNode;
}

const Switch = ({ name, label, description, defaultChecked, readOnly, onChange, children }: SwitchProps) => {
    const uuid = useMemo(() => v4(), []);

    return (
        <div css={tw`flex items-center`}>
            <ToggleContainer css={tw`flex-none`}>
                {children || (
                    <Input
                        id={uuid}
                        name={name}
                        type={'checkbox'}
                        onChange={(e: React.ChangeEvent<HTMLInputElement>) => onChange && onChange(e)}
                        defaultChecked={defaultChecked}
                        disabled={readOnly}
                    />
                )}
                <Label htmlFor={uuid} />
            </ToggleContainer>
            {(label || description) && (
                <div css={tw`ml-4 w-full`}>
                    {label && (
                        <Label css={[tw`cursor-pointer`, !!description && tw`mb-0`]} htmlFor={uuid}>
                            {label}
                        </Label>
                    )}
                    {description && <p css={tw`mt-2 text-sm text-neutral-300`}>{description}</p>}
                </div>
            )}
        </div>
    );
};

export default Switch;
