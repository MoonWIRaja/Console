import React from 'react';
import useSiteBranding from '@/hooks/useSiteBranding';
import { applyThemePreset, DEFAULT_THEME_ID, THEME_PRESETS } from '@/components/ui/theme-presets';

const AuthTopbar = () => {
    const { name, logo } = useSiteBranding();

    const cycleThemePreset = () => {
        if (typeof window === 'undefined') {
            return;
        }

        const currentThemeId = window.localStorage.getItem('panel.theme.id') || DEFAULT_THEME_ID;
        const currentIndex = THEME_PRESETS.findIndex((preset) => preset.id === currentThemeId);
        const nextPreset =
            THEME_PRESETS[(currentIndex + 1 + THEME_PRESETS.length) % THEME_PRESETS.length] || THEME_PRESETS[0];

        window.localStorage.setItem('panel.theme.id', nextPreset.id);
        window.localStorage.setItem('panel.theme.mode', 'dark');
        applyThemePreset(nextPreset.id, 'dark');
    };

    return (
        <header className='burhan-auth-topbar'>
            <div className='burhan-auth-topbar-inner'>
                <a className='burhan-auth-topbar-brand' href='/'>
                    <span
                        className='burhan-auth-topbar-logo'
                        draggable={false}
                        style={{ backgroundImage: `url(${logo})` }}
                    />
                    <div className='burhan-auth-topbar-name'>{name}</div>
                </a>
                <button
                    type='button'
                    className='burhan-auth-topbar-button'
                    onClick={cycleThemePreset}
                    aria-label='Switch panel theme'
                    title='Switch panel theme'
                >
                    <svg
                        xmlns='http://www.w3.org/2000/svg'
                        width='24'
                        height='24'
                        viewBox='0 0 24 24'
                        fill='none'
                        stroke='currentColor'
                        strokeWidth='2'
                        strokeLinecap='round'
                        strokeLinejoin='round'
                        className='tabler-icon tabler-icon-sun'
                    >
                        <path d='M12 12m-4 0a4 4 0 1 0 8 0a4 4 0 1 0 -8 0' />
                        <path d='M3 12h1m8 -9v1m8 8h1m-9 8v1m-6.4 -15.4l.7 .7m12.1 -.7l-.7 .7m0 11.4l.7 .7m-12.1 -.7l-.7 .7' />
                    </svg>
                </button>
            </div>
        </header>
    );
};

export default AuthTopbar;
