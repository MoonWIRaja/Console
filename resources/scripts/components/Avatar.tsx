import React from 'react';
import BoringAvatar, { AvatarProps } from 'boring-avatars';
import { useStoreState } from '@/state/hooks';

const palette = ['#FFAD08', '#EDD75A', '#73B06F', '#0C8F8F', '#587291'];

type Props = Omit<AvatarProps, 'colors'>;

const hasReactUseId = typeof (React as { useId?: unknown }).useId === 'function';

const hashSeed = (value: string): number => {
    let hash = 0;
    for (let index = 0; index < value.length; index++) {
        hash = (hash << 5) - hash + value.charCodeAt(index);
        hash |= 0;
    }

    return Math.abs(hash);
};

const normalizeSize = (size?: number | string): number => {
    if (typeof size === 'number' && Number.isFinite(size)) {
        return size;
    }

    if (typeof size === 'string') {
        const parsed = Number(size);
        if (Number.isFinite(parsed)) {
            return parsed;
        }
    }

    return 40;
};

const getInitials = (value: string): string => {
    const normalized = value.replace(/[_-]+/g, ' ').trim();
    if (!normalized) {
        return '?';
    }

    const tokens = normalized.split(/\s+/).filter(Boolean);
    if (tokens.length === 1) {
        return tokens[0].slice(0, 2).toUpperCase();
    }

    return `${tokens[0][0] || ''}${tokens[1][0] || ''}`.toUpperCase();
};

const FallbackAvatar = ({ name = 'system', square, size, className, style }: AvatarProps) => {
    const pixelSize = normalizeSize(size);
    const seed = hashSeed(name);
    const colorStart = palette[seed % palette.length];
    const colorEnd = palette[(seed + 2) % palette.length];
    const text = getInitials(name);

    return (
        <div
            role={'img'}
            aria-label={`Avatar for ${name}`}
            className={className}
            style={{
                width: pixelSize,
                height: pixelSize,
                borderRadius: square ? 12 : 9999,
                background: `linear-gradient(135deg, ${colorStart}, ${colorEnd})`,
                color: '#f8f6ef',
                display: 'inline-flex',
                alignItems: 'center',
                justifyContent: 'center',
                fontSize: Math.max(10, Math.floor(pixelSize * 0.36)),
                fontWeight: 800,
                letterSpacing: '0.02em',
                textTransform: 'uppercase',
                userSelect: 'none',
                ...style,
            }}
        >
            {text}
        </div>
    );
};

const _Avatar = ({ variant = 'beam', ...props }: AvatarProps) =>
    hasReactUseId ? (
        <BoringAvatar colors={palette} variant={variant} {...props} />
    ) : (
        <FallbackAvatar variant={variant} {...props} />
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

    return hasReactUseId ? (
        <BoringAvatar colors={palette} name={uuid || 'system'} variant={variant} {...props} />
    ) : (
        <FallbackAvatar name={uuid || 'system'} variant={variant} {...props} />
    );
};

_Avatar.displayName = 'Avatar';
_UserAvatar.displayName = 'Avatar.User';

const Avatar = Object.assign(_Avatar, {
    User: _UserAvatar,
});

export default Avatar;
