import React, { useEffect, useState } from 'react';

const GAME_SRC = '/minigames/laga-layang/index.html?v=cover-1';
const GAME_HEALTHCHECK_SRC = '/minigames/laga-layang/assets/CloudsBack.png';

interface Props {
    className?: string;
    label?: string;
}

const AuthBackdropGame = ({ className = '', label = 'Laga Layang mini game backdrop.' }: Props) => {
    const [checked, setChecked] = useState(false);
    const [ready, setReady] = useState(false);
    const [failed, setFailed] = useState(false);

    useEffect(() => {
        let active = true;

        setChecked(false);
        setReady(false);
        setFailed(false);

        fetch(GAME_HEALTHCHECK_SRC, {
            method: 'HEAD',
            cache: 'no-store',
            credentials: 'same-origin',
        })
            .then((response) => {
                if (!active) {
                    return;
                }

                const contentType = response.headers.get('content-type') || '';
                const assetAvailable = response.ok && contentType.startsWith('image/');

                setFailed(!assetAvailable);
                setChecked(true);
            })
            .catch(() => {
                if (!active) {
                    return;
                }

                setFailed(true);
                setChecked(true);
            });

        return () => {
            active = false;
        };
    }, []);

    return (
        <div className={`burhan-auth-backdrop burhan-auth-backdrop-game hidden h-full w-[70%] lg:block ${className}`}>
            <span className='sr-only'>{label}</span>
            <div aria-hidden='true' className='burhan-auth-backdrop-fallback' />

            {checked && !failed ? (
                <iframe
                    className={`burhan-auth-backdrop-frame ${ready ? 'is-ready' : ''}`}
                    src={GAME_SRC}
                    title='Laga Layang mini game'
                    tabIndex={-1}
                    onLoad={() => setReady(true)}
                    onError={() => setFailed(true)}
                />
            ) : null}
        </div>
    );
};

export default AuthBackdropGame;
