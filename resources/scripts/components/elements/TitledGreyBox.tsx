import React, { memo } from 'react';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { IconProp } from '@fortawesome/fontawesome-svg-core';
import tw from 'twin.macro';
import isEqual from 'react-fast-compare';

interface Props {
    icon?: IconProp;
    title: string | React.ReactNode;
    className?: string;
    children: React.ReactNode;
}

const TitledGreyBox = ({ icon, title, children, className }: Props) => (
    <div css={tw`rounded-xl border border-[#1f2a14] bg-[#000000] shadow-none`} className={className}>
        <div css={tw`rounded-t-xl border-b border-[#1f2a14] bg-[#050505] p-3`}>
            {typeof title === 'string' ? (
                <p css={tw`text-sm font-bold uppercase tracking-wide text-[#f8f6ef]`}>
                    {icon && <FontAwesomeIcon icon={icon} css={tw`mr-2 text-[#a3ff12]`} />}
                    {title}
                </p>
            ) : (
                title
            )}
        </div>
        <div css={tw`p-3 text-[#f8f6ef]`}>{children}</div>
    </div>
);

export default memo(TitledGreyBox, isEqual);
