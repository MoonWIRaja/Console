import React, { useState } from 'react';

const GAME_SRC = '/minigames/laga-layang/index.html';

interface Props {
    className?: string;
    label?: string;
}

const AuthBackdropGame = ({ className = '', label = 'Laga Layang mini game backdrop.' }: Props) => {
    const [ready, setReady] = useState(false);
    const [failed, setFailed] = useState(false);

    return (
        <div className={`burhan-auth-backdrop burhan-auth-backdrop-game hidden h-full w-[70%] lg:block ${className}`}>
            <span className='sr-only'>{label}</span>
            <div aria-hidden='true' className='burhan-auth-backdrop-fallback' />

            {!failed ? (
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
