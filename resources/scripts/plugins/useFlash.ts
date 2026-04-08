import { useMemo } from 'react';
import { Actions, useStoreActions } from 'easy-peasy';
import { FlashStore } from '@/state/flashes';
import { ApplicationStore } from '@/state';

interface KeyedFlashStore {
    addError: (message: string, title?: string) => void;
    clearFlashes: () => void;
    clearAndAddHttpError: (error?: Error | string | null) => void;
}

const useFlash = (): Actions<FlashStore> => {
    return useStoreActions((actions: Actions<ApplicationStore>) => actions.flashes);
};

const useFlashKey = (key: string): KeyedFlashStore => {
    const { addFlash, clearFlashes, clearAndAddHttpError } = useFlash();

    return useMemo(
        () => ({
            addError: (message, title) => addFlash({ key, message, title, type: 'error' }),
            clearFlashes: () => clearFlashes(key),
            clearAndAddHttpError: (error) => clearAndAddHttpError({ key, error }),
        }),
        [addFlash, clearAndAddHttpError, clearFlashes, key]
    );
};

export { useFlashKey };
export default useFlash;
