import styled from 'styled-components/macro';
import tw from 'twin.macro';

export default styled.div<{ $hoverable?: boolean }>`
    ${tw`flex items-center overflow-hidden rounded-xl border border-[#1f2a14] bg-[#000000] p-4 text-[#f8f6ef] no-underline transition-colors duration-150`};

    ${(props) => props.$hoverable !== false && tw`hover:border-[#2d3c1f] hover:bg-[#040404]`};

    & .icon {
        ${tw`flex w-16 items-center justify-center rounded-full border border-[#1f2a14] bg-[#090909] p-3`};
    }
`;
