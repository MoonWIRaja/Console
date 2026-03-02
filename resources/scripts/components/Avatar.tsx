import React from 'react';
import BoringAvatar, { AvatarProps } from 'boring-avatars';
import { useStoreState } from '@/state/hooks';

const palette = ['#FFAD08', '#EDD75A', '#73B06F', '#0C8F8F', '#587291'];

type Props = Omit<AvatarProps, 'colors'>;

const _Avatar = ({ variant = 'beam', ...props }: AvatarProps) => (
    <BoringAvatar colors={palette} variant={variant} {...props} />
);

const _UserAvatar = ({ variant = 'beam', ...props }: Omit<Props, 'name'>) => {
    const uuid = useStoreState((state) => state.user.data?.uuid);
    const image = useStoreState((state) => state.user.data?.image);

    if (image) {
        const size = typeof props.size === 'number' ? props.size : Number(props.size || 40);

        return (
            <img
                src={image}
                alt={'User avatar'}
                style={{
                    width: size,
                    height: size,
                    borderRadius: '9999px',
                    objectFit: 'cover',
                }}
            />
        );
    }

    return <BoringAvatar colors={palette} name={uuid || 'system'} variant={variant} {...props} />;
};

_Avatar.displayName = 'Avatar';
_UserAvatar.displayName = 'Avatar.User';

const Avatar = Object.assign(_Avatar, {
    User: _UserAvatar,
});

export default Avatar;
