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
    <div
        css={tw`relative overflow-hidden rounded-[22px]`}
        style={{
            border: '2px solid #2D4A3E',
            backgroundColor: '#FEF9E1',
            backgroundImage: [
                'repeating-linear-gradient(0deg, transparent, transparent 4.5px, rgba(116, 34, 32, 0.04) 4.5px, rgba(116, 34, 32, 0.04) 5px)',
                'repeating-linear-gradient(60deg, transparent, transparent 4.5px, rgba(116, 34, 32, 0.04) 4.5px, rgba(116, 34, 32, 0.04) 5px)',
                'repeating-linear-gradient(120deg, transparent, transparent 4.5px, rgba(116, 34, 32, 0.04) 4.5px, rgba(116, 34, 32, 0.04) 5px)',
            ].join(', '),
            boxShadow: '4px 4px 0px 0px #2D4A3E',
            color: 'var(--foreground)',
        }}
        className={className}
    >
        <div
            css={tw`rounded-t-[20px] border-b p-4`}
            style={{
                borderColor: '#2D4A3E',
                background: '#F5EFD5',
            }}
        >
            {typeof title === 'string' ? (
                <p css={tw`text-sm font-bold uppercase tracking-wide`} style={{ color: '#742220' }}>
                    {icon && <FontAwesomeIcon icon={icon} css={tw`mr-2`} style={{ color: '#2D4A3E' }} />}
                    {title}
                </p>
            ) : (
                title
            )}
        </div>
        <div css={tw`p-4`} style={{ color: '#742220' }}>{children}</div>
    </div>
);

export default memo(TitledGreyBox, isEqual);
