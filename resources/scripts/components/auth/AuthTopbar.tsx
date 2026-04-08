import React from 'react';
import useSiteBranding from '@/hooks/useSiteBranding';

const AuthTopbar = () => {
    const { name, logo } = useSiteBranding();

    return (
        <header className='burhan-auth-topbar'>
            <div className='burhan-auth-topbar-inner'>
                <a className='burhan-auth-topbar-brand' href='/'>
                    <img className='burhan-auth-topbar-logo' draggable={false} src={logo} alt={`${name} logo`} />
                    <div className='burhan-auth-topbar-name'>{name}</div>
                </a>
            </div>
        </header>
    );
};

export default AuthTopbar;
