import styled from 'styled-components/macro';
import { breakpoint } from '@/theme';
import tw from 'twin.macro';

const ContentContainer = styled.div`
    max-width: 1200px;
    ${tw`mx-4`};

    &.content-container-full {
        max-width: 100%;
        width: 100%;
        margin-left: 0;
        margin-right: 0;
    }

    ${breakpoint('xl')`
        ${tw`mx-auto`};
    `};
`;
ContentContainer.displayName = 'ContentContainer';

export default ContentContainer;
