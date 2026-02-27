import styled from 'styled-components/macro';
import tw from 'twin.macro';

const Label = styled.label<{ isLight?: boolean }>`
    ${tw`mb-1 block text-xs uppercase tracking-wide text-neutral-300 sm:mb-2`};
    ${(props) => props.isLight && tw`text-neutral-700`};
`;

export default Label;
