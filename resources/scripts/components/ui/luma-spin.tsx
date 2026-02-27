import React from 'react';
import styled, { keyframes } from 'styled-components/macro';

interface LumaSpinProps {
    size?: number;
    className?: string;
}

const loaderAnim = keyframes`
    0% {
        inset: 0 35px 35px 0;
    }
    12.5% {
        inset: 0 35px 0 0;
    }
    25% {
        inset: 35px 35px 0 0;
    }
    37.5% {
        inset: 35px 0 0 0;
    }
    50% {
        inset: 35px 0 0 35px;
    }
    62.5% {
        inset: 0 0 0 35px;
    }
    75% {
        inset: 0 0 35px 35px;
    }
    87.5% {
        inset: 0 0 35px 0;
    }
    100% {
        inset: 0 35px 35px 0;
    }
`;

const Wrapper = styled.div<{ $size: number }>`
    position: relative;
    width: ${(props) => props.$size}px;
    aspect-ratio: 1 / 1;
`;

const Ring = styled.span<{ $delay?: boolean }>`
    position: absolute;
    border-radius: 50px;
    box-shadow: inset 0 0 0 3px #a3ff12;
    opacity: 0.9;
    animation: ${loaderAnim} 2.5s infinite;
    ${(props) => props.$delay && 'animation-delay: -1.25s;'}
`;

export const LumaSpin: React.FC<LumaSpinProps> = ({ size = 65, className }) => (
    <Wrapper className={className} $size={size}>
        <Ring />
        <Ring $delay />
    </Wrapper>
);

export default LumaSpin;
