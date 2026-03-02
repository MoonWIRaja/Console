import React from 'react';
import ReactDOM from 'react-dom';
import App from '@/components/App';
import { setConfig } from 'react-hot-loader';
import { applyThemePreset, DEFAULT_THEME_ID } from '@/components/ui/theme-presets';

// Enable language support.
import './i18n';

// Prevents page reloads while making component changes which
// also avoids triggering constant loading indicators all over
// the place in development.
//
// @see https://github.com/gaearon/react-hot-loader#hook-support
setConfig({ reloadHooks: false });

// Silence Canvas2D willReadFrequently warnings emitted by third-party bundles (xterm internals).
if (typeof window !== 'undefined' && typeof HTMLCanvasElement !== 'undefined') {
    const nativeGetContext = HTMLCanvasElement.prototype.getContext;

    HTMLCanvasElement.prototype.getContext = function (
        contextId: '2d' | 'bitmaprenderer' | 'webgl' | 'webgl2' | string,
        options?: CanvasRenderingContext2DSettings
    ) {
        if (contextId === '2d') {
            const contextOptions = options ? { ...options } : {};
            if (typeof contextOptions.willReadFrequently === 'undefined') {
                contextOptions.willReadFrequently = true;
            }

            return nativeGetContext.call(this, contextId, contextOptions);
        }

        return nativeGetContext.call(this, contextId, options);
    };
}

if (typeof window !== 'undefined') {
    const savedTheme = window.localStorage.getItem('panel.theme.id') || DEFAULT_THEME_ID;
    window.localStorage.setItem('panel.theme.mode', 'dark');
    applyThemePreset(savedTheme, 'dark');
}

ReactDOM.render(<App />, document.getElementById('app'));
