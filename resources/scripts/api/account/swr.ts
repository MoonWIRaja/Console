import { AxiosError } from 'axios';
import { ConfigInterface } from 'swr';

type SwrAccountConfig<T> = ConfigInterface<T, AxiosError>;

const withAccountSwrConfig = <T>(config?: SwrAccountConfig<T>): SwrAccountConfig<T> => ({
    revalidateOnMount: true,
    revalidateOnFocus: false,
    shouldRetryOnError: false,
    onErrorRetry: (error, _key, _config, revalidate, options) => {
        const status = error?.response?.status ?? 0;

        if (status >= 400 && status < 500) {
            return;
        }

        if (options.retryCount >= 2) {
            return;
        }

        window.setTimeout(() => revalidate({ retryCount: options.retryCount + 1 }), 5000);
    },
    ...(config || {}),
});

export { withAccountSwrConfig };
