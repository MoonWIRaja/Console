import React from 'react';
import useSiteBranding from '@/hooks/useSiteBranding';

const DASHBOARD_VERSION = 'V3.0.0';
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
                        color: '#FEF9E1',
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
                        background: 'rgba(255, 255, 255, 0.06)',
                        color: '#FEF9E1',
                        fontSize: '9px',
                        fontWeight: 800,
                        letterSpacing: '0.16em',
                        textTransform: 'uppercase',
                        border: '1px solid rgba(255, 255, 255, 0.08)',
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
