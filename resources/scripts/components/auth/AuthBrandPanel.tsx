import React from 'react';
import useSiteBranding from '@/hooks/useSiteBranding';

const AuthBrandPanel = () => {
    const { name, authCardLogo } = useSiteBranding();

    return (
        <div className='burhan-auth-brand-panel'>
            {authCardLogo ? (
                <img
                    className='burhan-auth-brand-logo'
                    draggable={false}
                    src={authCardLogo}
                    alt={`${name} auth logo`}
                />
            ) : (
                <h1 className='burhan-auth-title'>{name}</h1>
            )}
        </div>
    );
};

export default AuthBrandPanel;
