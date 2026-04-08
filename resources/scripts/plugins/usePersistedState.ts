import { Dispatch, SetStateAction, useEffect, useState } from 'react';

export function usePersistedState<S = undefined>(
    key: string,
    defaultValue: S
): [S | undefined, Dispatch<SetStateAction<S | undefined>>] {
    const [state, setState] = useState(() => {
        try {
            if (typeof localStorage === 'undefined') {
                return defaultValue;
            }

            const item = localStorage.getItem(key);
            if (item === null) {
                return defaultValue;
            }

            return JSON.parse(item) as S;
        } catch {
            try {
                localStorage.removeItem(key);
            } catch {
                // Ignore storage cleanup failures and just fall back to the default value.
            }

            return defaultValue;
        }
    });

    useEffect(() => {
        try {
            localStorage.setItem(key, JSON.stringify(state));
        } catch {
            // Ignore storage write errors, this state should still function in-memory.
        }
    }, [key, state]);

    return [state, setState];
}
