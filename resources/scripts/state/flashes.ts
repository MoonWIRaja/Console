import { Action, action } from 'easy-peasy';
import { FlashMessageType } from '@/components/MessageBox';
import { httpErrorToHuman } from '@/api/http';

export interface FlashStore {
    items: FlashMessage[];
    addFlash: Action<FlashStore, FlashMessage>;
    addError: Action<FlashStore, { message: string; key?: string }>;
    clearAndAddHttpError: Action<FlashStore, { error?: Error | any | null; key?: string }>;
    clearFlashes: Action<FlashStore, string | void>;
    removeFlash: Action<FlashStore, string>;
}

export interface FlashMessage {
    id?: string;
    key?: string;
    type: FlashMessageType;
    title?: string;
    message: string;
}

let flashSequence = 0;

const withFlashId = (flash: FlashMessage): FlashMessage => ({
    ...flash,
    id: flash.id ?? `flash-${Date.now()}-${flashSequence++}`,
});

const flashes: FlashStore = {
    items: [],

    addFlash: action((state, payload) => {
        state.items.push(withFlashId(payload));
    }),

    addError: action((state, payload) => {
        state.items.push(withFlashId({ type: 'error', title: 'Error', ...payload }));
    }),

    clearAndAddHttpError: action((state, payload) => {
        if (!payload.error) {
            state.items = [];
        } else {
            const status = payload.error?.response?.status;
            if (typeof status !== 'number' || status >= 500) {
                console.error(payload.error);
            }

            state.items = [
                withFlashId({
                    type: 'error',
                    title: 'Error',
                    key: payload.key,
                    message: httpErrorToHuman(payload.error),
                }),
            ];
        }
    }),

    clearFlashes: action((state, payload) => {
        state.items = payload ? state.items.filter((flashes) => flashes.key !== payload) : [];
    }),

    removeFlash: action((state, payload) => {
        state.items = state.items.filter((flash) => flash.id !== payload);
    }),
};

export default flashes;
