import React, { useEffect } from 'react';
import ContentContainer from '@/components/elements/ContentContainer';
import { CSSTransition } from 'react-transition-group';
import tw from 'twin.macro';
import FlashMessageRender from '@/components/FlashMessageRender';

export interface PageContentBlockProps {
    title?: string;
    className?: string;
    showFlashKey?: string;
    hideFooter?: boolean;
    fullHeight?: boolean;
}

const PageContentBlock: React.FC<PageContentBlockProps> = ({
    title,
    showFlashKey,
    className,
    hideFooter,
    fullHeight,
    children,
}) => {
    useEffect(() => {
        if (title) {
            document.title = title;
        }
    }, [title]);

    return (
        <CSSTransition timeout={150} classNames={'fade'} appear in>
            <>
                <ContentContainer css={fullHeight ? tw`my-0 h-full` : tw`my-4 sm:my-10`} className={className}>
                    {showFlashKey && <FlashMessageRender byKey={showFlashKey} css={tw`mb-4`} />}
                    {children}
                </ContentContainer>
                {!hideFooter && null}
            </>
        </CSSTransition>
    );
};

export default PageContentBlock;
