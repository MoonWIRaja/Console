import styled, { css } from 'styled-components/macro';
import tw from 'twin.macro';

export interface Props {
    isLight?: boolean;
    hasError?: boolean;
}

const light = css<Props>`
    ${tw`bg-white border-neutral-200 text-neutral-800`};
    &:focus {
        ${tw`border-primary-400`}
    }

    &:disabled {
        ${tw`bg-neutral-100 border-neutral-200`};
    }
`;

const checkboxStyle = css<Props>`
    ${tw`inline-block h-4 w-4 cursor-pointer select-none appearance-none rounded-sm border align-middle`};
    border-color: var(--border);
    background-color: var(--card);
    color: var(--primary);
    color-adjust: exact;
    background-origin: border-box;
    transition: all 75ms linear, box-shadow 25ms linear;

    &:checked {
        ${tw`border-transparent bg-no-repeat bg-center`};
        background-image: url("data:image/svg+xml,%3csvg viewBox='0 0 16 16' fill='white' xmlns='http://www.w3.org/2000/svg'%3e%3cpath d='M5.707 7.293a1 1 0 0 0-1.414 1.414l2 2a1 1 0 0 0 1.414 0l4-4a1 1 0 0 0-1.414-1.414L7 8.586 5.707 7.293z'/%3e%3c/svg%3e");
        background-color: currentColor;
        background-size: 100% 100%;
    }

    &:focus {
        border-color: var(--primary);
        ${tw`outline-none`};
        box-shadow: 0 0 0 1px rgba(var(--primary-rgb), 0.35);
    }
`;

const inputStyle = css<Props>`
    // Reset to normal styling.
    resize: none;
    ${tw`appearance-none outline-none w-full min-w-0`};
    ${tw`p-3 border-2 rounded text-sm transition-all duration-150`};
    border-color: var(--border);
    background-color: var(--card);
    color: var(--foreground);
    ${tw`shadow-none focus:ring-0`};
    &::placeholder {
        color: var(--muted-foreground);
    }
    &:hover:not(:disabled):not(:read-only) {
        border-color: var(--ring);
    }

    & + .input-help {
        ${tw`mt-1 text-xs`};
        ${(props) => (props.hasError ? tw`text-red-300` : tw`text-neutral-300`)};
    }

    &:required,
    &:invalid {
        ${tw`shadow-none`};
    }

    &:not(:disabled):not(:read-only):focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 2px rgba(var(--primary-rgb), 0.3);
        ${(props) => props.hasError && tw`border-red-400 ring-red-400 ring-opacity-30`};
    }

    &:disabled {
        ${tw`cursor-not-allowed opacity-75`};
        border-color: var(--border);
        background-color: var(--background);
    }

    ${(props) => props.isLight && light};
    ${(props) => props.hasError && tw`border-red-500 text-red-100 hover:border-red-400`};
`;

const Input = styled.input<Props>`
    &:not([type='checkbox']):not([type='radio']) {
        ${inputStyle};
    }

    &[type='checkbox'],
    &[type='radio'] {
        ${checkboxStyle};

        &[type='radio'] {
            ${tw`rounded-full`};
        }
    }
`;
const Textarea = styled.textarea.attrs((props: Record<string, unknown>) => ({
    value: props.value === null ? '' : props.value,
}))<Props>`
    ${inputStyle}
`;

export { Textarea };
export default Input;
