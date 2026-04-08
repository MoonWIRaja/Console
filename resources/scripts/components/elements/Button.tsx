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
    border-color: var(--border);
    background-color: var(--card);
    color: var(--foreground);

    &:hover:not(:disabled) {
        border-color: var(--primary);
        color: var(--primary);
    }

    &:focus-visible {
        outline: none;
        box-shadow: 0 0 0 2px rgba(var(--primary-rgb), 0.45);
    }

    ${(props) =>
        ((!props.isSecondary && !props.color) || props.color === 'primary') &&
        css<Props>`
            ${(props) =>
                !props.isSecondary &&
                css`
                    border-color: var(--primary);
                    background-color: var(--primary);
                    color: var(--primary-foreground);
                `};

            &:hover:not(:disabled) {
                border-color: var(--primary);
                background-color: var(--primary);
                color: var(--primary-foreground);
                filter: brightness(1.06);
            }
        `};

    ${(props) =>
        props.color === 'grey' &&
        css`
            border-color: var(--border);
            background-color: var(--muted);
            color: var(--foreground);

            &:hover:not(:disabled) {
                border-color: var(--ring);
                background-color: var(--accent);
                color: var(--foreground);
            }
        `};

    ${(props) =>
        props.color === 'green' &&
        css<Props>`
            border-color: var(--primary);
            background-color: var(--primary);
            color: var(--primary-foreground);

            &:hover:not(:disabled) {
                border-color: var(--primary);
                background-color: var(--primary);
                color: var(--primary-foreground);
                filter: brightness(1.06);
            }

            ${(props) =>
                props.isSecondary &&
                css`
                    &:active:not(:disabled) {
                        filter: brightness(0.95);
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
            border-color: var(--border);
            background-color: var(--card);
            color: var(--foreground);

            &:hover:not(:disabled) {
                border-color: var(--primary);
                background-color: var(--background);
                color: var(--primary);
                ${(props) => props.color === 'red' && tw`border-[#ef4444] bg-[#2a0707] text-[#fee2e2]`};
                ${(props) =>
                    (props.color === 'primary' || props.color === 'green') &&
                    css`
                        border-color: var(--primary);
                        background-color: rgba(var(--primary-rgb), 0.12);
                        color: var(--primary);
                    `};
                ${(props) =>
                    props.color === 'grey' &&
                    css`
                        border-color: var(--ring);
                        background-color: var(--accent);
                        color: var(--foreground);
                    `};
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
