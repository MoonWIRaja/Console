import React, { useEffect, useMemo, useState } from 'react';
import { AnimatePresence, motion } from 'framer-motion';
import { Palette } from 'lucide-react';
import Select, { TSelectData } from '@/components/ui/select';
import {
    applyThemePreset,
    DEFAULT_THEME_ID,
    THEME_PRESETS,
} from '@/components/ui/theme-presets';

const STORAGE_THEME_KEY = 'panel.theme.id';
const STORAGE_MODE_KEY = 'panel.theme.mode';

const readStoredTheme = () => {
    if (typeof window === 'undefined') return DEFAULT_THEME_ID;
    return window.localStorage.getItem(STORAGE_THEME_KEY) || DEFAULT_THEME_ID;
};

const ThemeFloatingMenu = ({ className }: { className?: string }) => {
    const [themeId, setThemeId] = useState<string>(DEFAULT_THEME_ID);
    const [isOpen, setIsOpen] = useState(false);

    useEffect(() => {
        const nextTheme = readStoredTheme();

        setThemeId(nextTheme);
        applyThemePreset(nextTheme, 'dark');
        if (typeof window !== 'undefined') {
            window.localStorage.setItem(STORAGE_MODE_KEY, 'dark');
        }
    }, []);

    const setTheme = (nextThemeId: string) => {
        setThemeId(nextThemeId);
        applyThemePreset(nextThemeId, 'dark');

        if (typeof window !== 'undefined') {
            window.localStorage.setItem(STORAGE_THEME_KEY, nextThemeId);
            window.localStorage.setItem(STORAGE_MODE_KEY, 'dark');
        }
    };

    const options = useMemo<TSelectData[]>(
        () =>
            THEME_PRESETS.map((theme) => ({
                id: theme.id,
                label: theme.label,
                value: theme.id,
                icon: <Palette className='h-4 w-4' />,
                description: 'Dark Mode',
            })),
        []
    );

    return (
        <div
            className={`fixed bottom-8 right-8 z-[1200] ${className || ''}`}
            onMouseEnter={() => setIsOpen(true)}
            onMouseLeave={() => setIsOpen(false)}
        >
            <button
                type='button'
                onClick={() => setIsOpen((value) => !value)}
                className='flex h-14 w-14 items-center justify-center rounded-full border border-[color:var(--border)] bg-[color:var(--card)] text-[color:var(--foreground)] shadow-[0_12px_30px_rgba(0,0,0,0.35)] transition-all hover:scale-105 hover:border-[color:var(--primary)] hover:text-[color:var(--primary)]'
                aria-label='Open theme picker'
                title='Theme Picker'
            >
                <Palette className='h-5 w-5' />
            </button>

            <AnimatePresence>
                {isOpen && (
                    <motion.div
                        initial={{ opacity: 0, y: 10 }}
                        animate={{ opacity: 1, y: 0 }}
                        exit={{ opacity: 0, y: 10 }}
                        transition={{ duration: 0.2, ease: 'easeOut' }}
                        className='absolute bottom-16 right-0 w-[280px] rounded-2xl border border-[color:var(--border)] bg-[color:var(--card)] p-3 shadow-[0_14px_38px_rgba(0,0,0,0.45)]'
                    >
                        <p className='mb-2 text-xs font-semibold uppercase tracking-wide text-[color:var(--muted-foreground)]'>
                            Theme Picker
                        </p>

                        <Select
                            title='Pilih Theme'
                            data={options}
                            defaultValue={themeId}
                            onChange={(value) => setTheme(value)}
                        />
                    </motion.div>
                )}
            </AnimatePresence>
        </div>
    );
};

export default ThemeFloatingMenu;
