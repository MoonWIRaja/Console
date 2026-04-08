import styled from 'styled-components/macro';
import tw from 'twin.macro';

export default styled.div<{ $hoverable?: boolean }>`
    ${tw`flex items-center overflow-hidden rounded-xl border border-[color:var(--border)] bg-[color:var(--card)] p-4 text-[#f8f6ef] no-underline transition-colors duration-150`};

    ${(props) =>
        props.$hoverable !== false && tw`hover:border-[color:var(--primary)] hover:bg-[color:var(--background)]`};

    & .icon {
        ${tw`flex w-16 items-center justify-center rounded-full border border-[color:var(--border)] bg-[color:var(--background)] p-3`};
    }
`;
