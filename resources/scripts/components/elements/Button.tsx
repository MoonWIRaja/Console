import React from 'react';
import styled, { css } from 'styled-components/macro';
import tw from 'twin.macro';
import Spinner from '@/components/elements/Spinner';

interface Props {
    isLoading?: boolean;
    size?: 'xsmall' | 'small' | 'large' | 'xlarge';
    color?: 'green' | 'red' | 'primary' | 'grey';
    isSecondary?: boolean;
}

const ButtonStyle = styled.button<Omit<Props, 'isLoading'>>`
    ${tw`relative inline-flex items-center justify-center rounded-full border text-sm font-semibold tracking-wide transition-all duration-150`};
    ${tw`border-[#1f2a14] bg-[#000000] text-[#f8f6ef]`};
    ${tw`hover:border-[#a3ff12] hover:text-[#d9ff93] focus:ring-2 focus:ring-[#a3ff12] focus:ring-opacity-50 focus:ring-offset-2 focus:ring-offset-black`};

    ${(props) =>
        ((!props.isSecondary && !props.color) || props.color === 'primary') &&
        css<Props>`
            ${(props) => !props.isSecondary && tw`border-[#2f5e1b] bg-[#12220b] text-[#d9ff93]`};

            &:hover:not(:disabled) {
                ${tw`border-[#a3ff12] bg-[#17310d] text-[#ecfccb]`};
            }
        `};

    ${(props) =>
        props.color === 'grey' &&
        css`
            ${tw`border-[#334155] bg-[#0b0f14] text-[#e2e8f0]`};

            &:hover:not(:disabled) {
                ${tw`border-[#64748b] bg-[#111827] text-white`};
            }
        `};

    ${(props) =>
        props.color === 'green' &&
        css<Props>`
            ${tw`border-[#2f5e1b] bg-[#12220b] text-[#d9ff93]`};

            &:hover:not(:disabled) {
                ${tw`border-[#a3ff12] bg-[#17310d] text-[#ecfccb]`};
            }

            ${(props) =>
                props.isSecondary &&
                css`
                    &:active:not(:disabled) {
                        ${tw`border-[#a3ff12] bg-[#17310d]`};
                    }
                `};
        `};

    ${(props) =>
        props.color === 'red' &&
        css<Props>`
            ${tw`border-[#7f1d1d] bg-[#2a0707] text-[#fee2e2]`};

            &:hover:not(:disabled) {
                ${tw`border-[#ef4444] bg-[#360909] text-white`};
            }

            ${(props) =>
                props.isSecondary &&
                css`
                    &:active:not(:disabled) {
                        ${tw`border-[#ef4444] bg-[#360909]`};
                    }
                `};
        `};

    ${(props) => props.size === 'xsmall' && tw`px-2 py-1 text-xs`};
    ${(props) => (!props.size || props.size === 'small') && tw`px-4 py-2`};
    ${(props) => props.size === 'large' && tw`px-5 py-3 text-sm`};
    ${(props) => props.size === 'xlarge' && tw`px-5 py-3 w-full text-sm`};

    ${(props) =>
        props.isSecondary &&
        css<Props>`
            ${tw`border-[#1f2a14] bg-[#000000] text-[#f8f6ef]`};

            &:hover:not(:disabled) {
                ${tw`border-[#a3ff12] bg-[#050505] text-[#d9ff93]`};
                ${(props) => props.color === 'red' && tw`border-[#ef4444] bg-[#2a0707] text-[#fee2e2]`};
                ${(props) => props.color === 'primary' && tw`border-[#a3ff12] bg-[#12220b] text-[#d9ff93]`};
                ${(props) => props.color === 'green' && tw`border-[#a3ff12] bg-[#12220b] text-[#d9ff93]`};
                ${(props) => props.color === 'grey' && tw`border-[#64748b] bg-[#111827] text-white`};
            }
        `};

    &:disabled {
        ${tw`cursor-not-allowed opacity-50`};
    }
`;

type ComponentProps = Omit<JSX.IntrinsicElements['button'], 'ref' | keyof Props> & Props;

const Button: React.FC<ComponentProps> = ({ children, isLoading, ...props }) => (
    <ButtonStyle {...props}>
        {isLoading && (
            <div css={tw`absolute left-0 top-0 flex h-full w-full items-center justify-center`}>
                <Spinner size={'small'} />
            </div>
        )}
        <span css={isLoading ? tw`text-transparent` : undefined}>{children}</span>
    </ButtonStyle>
);

type LinkProps = Omit<JSX.IntrinsicElements['a'], 'ref' | keyof Props> & Props;

const LinkButton: React.FC<LinkProps> = (props) => <ButtonStyle as={'a'} {...props} />;

export { LinkButton, ButtonStyle };
export default Button;
