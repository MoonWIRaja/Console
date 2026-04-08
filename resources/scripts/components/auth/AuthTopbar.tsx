import React from 'react';
import useSiteBranding from '@/hooks/useSiteBranding';

const DASHBOARD_VERSION = 'V2.5.0';
const DASHBOARD_CHANNEL = 'BETA';

const AuthTopbar = () => {
    const { name, logo } = useSiteBranding();

    return (
        <header className='burhan-auth-topbar'>
            <div className='burhan-auth-topbar-inner'>
                <a className='burhan-auth-topbar-brand' href='/'>
                    <img className='burhan-auth-topbar-logo' draggable={false} src={logo} alt={`${name} logo`} />
                    <div className='burhan-auth-topbar-name'>{name}</div>
                </a>
                <div style={{
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'flex-end',
                    gap: '10px',
                    flexShrink: 0,
                    minWidth: 'fit-content',
                }}>
                    <span style={{
                        color: 'rgba(248, 246, 239, 0.82)',
                        fontSize: '0.76rem',
                        fontWeight: 800,
                        letterSpacing: '0.18em',
                        textTransform: 'uppercase',
                        whiteSpace: 'nowrap',
                    }}>
                        {DASHBOARD_VERSION}
                    </span>
                    <span style={{
                        display: 'inline-flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        padding: '0.35rem 0.5rem',
                        borderRadius: '999px',
                        background: 'rgba(245, 231, 198, 0.16)',
                        color: 'rgb(245, 231, 198)',
                        fontSize: '9px',
                        fontWeight: 800,
                        letterSpacing: '0.16em',
                        textTransform: 'uppercase',
                        border: '1px solid rgba(245, 231, 198, 0.14)',
                        boxShadow: 'rgba(255, 255, 255, 0.06) 0px 1px 0px inset',
                        whiteSpace: 'nowrap',
                    }}>
                        {DASHBOARD_CHANNEL}
                    </span>
                </div>
            </div>
        </header>
    );
};

export default AuthTopbar;
