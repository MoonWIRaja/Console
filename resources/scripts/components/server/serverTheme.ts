import type React from 'react';

export const SERVER_THEME_VARIABLES = {
    '--background': '#D6D2C7',
    '--background-rgb': '214, 210, 199',
    '--foreground': '#742220',
    '--card': '#F5EFD5',
    '--card-foreground': '#742220',
    '--card-rgb': '245, 239, 213',
    '--popover': '#FEF9E1',
    '--popover-foreground': '#742220',
    '--border': '#E8E0C8',
    '--input': '#F5EFD5',
    '--surface-border': '#E8E0C8',
    '--surface-elevated': '#FEF9E1',
    '--surface-elevated-rgb': '254, 249, 225',
    '--surface-subtle': '#F5EFD5',
    '--surface-subtle-strong': '#EDE6D0',
    '--text-subtle': 'rgba(116, 34, 32, 0.55)',
    '--accent': '#EDE6D0',
    '--accent-foreground': '#742220',
    '--muted': 'rgba(116, 34, 32, 0.06)',
    '--muted-foreground': 'rgba(116, 34, 32, 0.55)',
    '--primary': '#2D4A3E',
    '--primary-foreground': '#FEF9E1',
    '--primary-rgb': '45, 74, 62',
    '--primary-glow-soft': 'rgba(45, 74, 62, 0.18)',
    '--ring': '#2D4A3E',
} as const;

export const SERVER_THEME_VARIABLE_NAMES = Object.keys(SERVER_THEME_VARIABLES) as Array<
    keyof typeof SERVER_THEME_VARIABLES
>;

type ThemeVariableRecord = Partial<Record<keyof typeof SERVER_THEME_VARIABLES, string>>;

export const serverThemeStyle = SERVER_THEME_VARIABLES as unknown as React.CSSProperties;

export const readThemeVariables = (
    source: HTMLElement | null,
    variableNames: readonly (keyof typeof SERVER_THEME_VARIABLES)[] = SERVER_THEME_VARIABLE_NAMES
): ThemeVariableRecord => {
    const styles = source ? window.getComputedStyle(source) : null;

    return variableNames.reduce<ThemeVariableRecord>((accumulator, variableName) => {
        accumulator[variableName] = styles?.getPropertyValue(variableName).trim() || SERVER_THEME_VARIABLES[variableName];

        return accumulator;
    }, {});
};

export const applyThemeVariables = (
    target: HTMLElement | null,
    variables: ThemeVariableRecord = SERVER_THEME_VARIABLES
): (() => void) => {
    if (!target) {
        return () => undefined;
    }

    const previous = readThemeVariables(target, Object.keys(variables) as Array<keyof typeof SERVER_THEME_VARIABLES>);

    Object.entries(variables).forEach(([variableName, value]) => {
        if (value !== undefined) {
            target.style.setProperty(variableName, value);
        }
    });

    return () => {
        Object.entries(previous).forEach(([variableName, value]) => {
            if (value !== undefined) {
                target.style.setProperty(variableName, value);
            }
        });
    };
};

export const copyThemeVariables = (
    source: HTMLElement | null,
    target: HTMLElement | null,
    variableNames: readonly (keyof typeof SERVER_THEME_VARIABLES)[] = SERVER_THEME_VARIABLE_NAMES
): (() => void) => applyThemeVariables(target, readThemeVariables(source, variableNames));
