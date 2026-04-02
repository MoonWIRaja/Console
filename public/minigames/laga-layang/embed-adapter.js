(function () {
    const STORAGE_NAMESPACE = 'panel.minigames.laga-layang.';
    const LEADERBOARD_STORAGE_KEY = 'leaderboard.entries';
    const LEADERBOARD_ENDPOINT_PREFIX = '/minigames/laga-layang/local-leaderboard/';

    const prefixKey = (key) => {
        const normalized = String(key ?? '');

        return normalized.startsWith(STORAGE_NAMESPACE) ? normalized : `${STORAGE_NAMESPACE}${normalized}`;
    };

    const jsonResponse = (payload, status) =>
        new Response(JSON.stringify(payload), {
            status: status || 200,
            headers: {
                'Content-Type': 'application/json',
            },
        });

    try {
        const storageRef = window.localStorage;
        const storageProto = window.Storage && window.Storage.prototype;

        if (storageRef && storageProto && !storageProto.__lagaLayangNamespaced) {
            const nativeGetItem = storageProto.getItem;
            const nativeSetItem = storageProto.setItem;
            const nativeRemoveItem = storageProto.removeItem;

            storageProto.getItem = function (key) {
                if (this === storageRef) {
                    return nativeGetItem.call(this, prefixKey(key));
                }

                return nativeGetItem.call(this, key);
            };

            storageProto.setItem = function (key, value) {
                if (this === storageRef) {
                    return nativeSetItem.call(this, prefixKey(key), value);
                }

                return nativeSetItem.call(this, key, value);
            };

            storageProto.removeItem = function (key) {
                if (this === storageRef) {
                    return nativeRemoveItem.call(this, prefixKey(key));
                }

                return nativeRemoveItem.call(this, key);
            };

            Object.defineProperty(storageProto, '__lagaLayangNamespaced', {
                configurable: true,
                value: true,
            });
        }
    } catch (_error) {
        // Leave storage unmodified if the browser blocks patching.
    }

    const readEntries = () => {
        try {
            const raw = window.localStorage.getItem(LEADERBOARD_STORAGE_KEY);
            const parsed = raw ? JSON.parse(raw) : [];

            return Array.isArray(parsed) ? parsed : [];
        } catch (_error) {
            return [];
        }
    };

    const writeEntries = (entries) => {
        try {
            const normalized = Array.isArray(entries) ? entries : [];
            window.localStorage.setItem(LEADERBOARD_STORAGE_KEY, JSON.stringify(normalized));

            return normalized;
        } catch (_error) {
            return [];
        }
    };

    const nativeFetch = window.fetch.bind(window);

    window.fetch = async function (input, init) {
        const requestUrl =
            typeof input === 'string' ? input : input instanceof Request ? input.url : String(input || '');
        const method =
            String(
                (init && init.method) || (input instanceof Request ? input.method : undefined) || 'GET'
            ).toUpperCase();

        let url;

        try {
            url = new URL(requestUrl, window.location.origin);
        } catch (_error) {
            return nativeFetch(input, init);
        }

        if (!url.pathname.startsWith(LEADERBOARD_ENDPOINT_PREFIX)) {
            return nativeFetch(input, init);
        }

        if (method === 'GET') {
            return jsonResponse({
                record: {
                    entries: readEntries(),
                },
            });
        }

        if (method === 'PUT') {
            let body = null;

            try {
                const rawBody = init && typeof init.body === 'string' ? init.body : '{}';
                const parsed = JSON.parse(rawBody);
                body = parsed && typeof parsed === 'object' ? parsed : {};
            } catch (_error) {
                body = {};
            }

            const entries = writeEntries(body.entries);

            return jsonResponse({
                record: {
                    entries,
                },
            });
        }

        return jsonResponse({ error: 'Method not allowed.' }, 405);
    };
})();
