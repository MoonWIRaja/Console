import styled, { css } from 'styled-components/macro';
import tw from 'twin.macro';

export default styled.div<{ $hoverable?: boolean }>`
    ${tw`relative flex items-center overflow-hidden rounded-[22px] p-4 no-underline transition-all duration-200`};
    border: 2px solid #2D4A3E;
    background-color: #FEF9E1;
    background-image:
        repeating-linear-gradient(0deg, transparent, transparent 4.5px, rgba(116, 34, 32, 0.04) 4.5px, rgba(116, 34, 32, 0.04) 5px),
        repeating-linear-gradient(60deg, transparent, transparent 4.5px, rgba(116, 34, 32, 0.04) 4.5px, rgba(116, 34, 32, 0.04) 5px),
        repeating-linear-gradient(120deg, transparent, transparent 4.5px, rgba(116, 34, 32, 0.04) 4.5px, rgba(116, 34, 32, 0.04) 5px);
    color: var(--foreground);
    box-shadow: 4px 4px 0px 0px #2D4A3E;

    ${(props) =>
        props.$hoverable !== false &&
        css`
            &:hover {
                transform: translate(-2px, -2px);
                box-shadow: 6px 6px 0px 0px #2D4A3E;
            }
        `};

    & .icon {
        ${tw`flex w-16 items-center justify-center rounded-full p-3`};
        border: 1px solid #EDE6D0;
        background: #F5EFD5;
    }
`;
