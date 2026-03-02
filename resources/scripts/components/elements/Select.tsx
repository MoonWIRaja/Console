import styled, { css } from 'styled-components/macro';
import tw from 'twin.macro';

interface Props {
    hideDropdownArrow?: boolean;
}

const Select = styled.select<Props>`
    ${tw`block h-11 w-full rounded-[14px] border px-3 py-2 pr-9 text-sm shadow-none transition-colors duration-150 ease-linear`};
    border-color: var(--border);
    background-color: var(--card);
    color: var(--foreground);

    &,
    &:hover:not(:disabled),
    &:focus {
        ${tw`outline-none`};
    }

    -webkit-appearance: none;
    -moz-appearance: none;
    background-size: 0.45rem 0.45rem, 0.45rem 0.45rem;
    background-repeat: no-repeat;
    background-position:
        calc(100% - 0.95rem) calc(50% - 1px),
        calc(100% - 0.65rem) calc(50% - 1px);
    background-image:
        linear-gradient(45deg, transparent 50%, var(--primary) 50%),
        linear-gradient(135deg, var(--primary) 50%, transparent 50%);

    &::-ms-expand {
        display: none;
    }

    ${(props) =>
        !props.hideDropdownArrow &&
        css`
            &:hover:not(:disabled),
            &:focus {
                border-color: var(--primary);
            }
        `};

    ${(props) =>
        props.hideDropdownArrow &&
        css`
            background-image: none;
        `};

    &:disabled {
        ${tw`cursor-not-allowed opacity-60`};
    }
`;

export default Select;
