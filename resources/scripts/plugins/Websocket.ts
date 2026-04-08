import { EventEmitter } from 'events';

type PendingClose = {
    code?: number;
    reason?: string;
};

const NORMAL_CLOSE_CODES = new Set([1000, 1001, 1005]);
const NON_RECONNECT_CODES = new Set([4400, 4409]);

export class Websocket extends EventEmitter {
    private socket: WebSocket | null = null;

    private url: string | null = null;

    private token = '';

    private manuallyClosed = false;

    private reconnectAttempts = 0;

    private reconnectTimer: ReturnType<typeof setTimeout> | null = null;

    private pendingClose: PendingClose | null = null;

    private readonly reconnectDelay = 5000;

    private readonly maxReconnectAttempts = 20;

    connect(url: string): this {
        this.url = url;
        this.manuallyClosed = false;
        this.pendingClose = null;
        this.clearReconnectTimer();
        this.open();

        return this;
    }

    setToken(token: string, isUpdate = false): this {
        this.token = token;

        if (isUpdate) {
            this.authenticate();
        }

        return this;
    }

    authenticate() {
        if (this.token) {
            this.send('auth', this.token);
        }
    }

    close(code?: number, reason?: string) {
        this.url = null;
        this.token = '';
        this.manuallyClosed = true;
        this.clearReconnectTimer();

        if (!this.socket) {
            return;
        }

        if (this.socket.readyState === WebSocket.CONNECTING) {
            // Avoid Chrome's "closed before the connection is established" noise.
            this.pendingClose = { code: code ?? 1000, reason };
            return;
        }

        this.pendingClose = null;

        if (this.socket.readyState === WebSocket.OPEN) {
            const socket = this.socket;
            this.socket = null;
            socket.close(code ?? 1000, reason);
            return;
        }

        if (this.socket.readyState === WebSocket.CLOSED) {
            this.socket = null;
        }
    }

    open() {
        if (!this.url || this.manuallyClosed) {
            return;
        }

        const socket = new WebSocket(this.url);
        this.socket = socket;

        socket.onmessage = (event) => {
            if (this.socket !== socket || this.manuallyClosed) {
                return;
            }

            try {
                const { event: eventName, args } = JSON.parse(event.data);
                args ? this.emit(eventName, ...args) : this.emit(eventName);
            } catch (error) {
                console.warn('Failed to parse incoming websocket message.', error);
            }
        };

        socket.onopen = () => {
            if (this.socket !== socket) {
                return;
            }

            if (this.pendingClose) {
                const { code, reason } = this.pendingClose;
                this.pendingClose = null;
                this.socket = null;
                socket.close(code ?? 1000, reason);

                return;
            }

            if (this.manuallyClosed) {
                this.socket = null;
                socket.close(1000);

                return;
            }

            this.reconnectAttempts = 0;
            this.emit('SOCKET_OPEN');
            this.authenticate();
        };

        socket.onclose = (event) => {
            if (this.socket === socket) {
                this.socket = null;
            }

            if (this.manuallyClosed) {
                return;
            }

            if (!NORMAL_CLOSE_CODES.has(event.code) && !NON_RECONNECT_CODES.has(event.code)) {
                this.scheduleReconnect(event);
            }

            this.emit('SOCKET_CLOSE');
        };

        socket.onerror = (error) => {
            if (this.socket !== socket || this.manuallyClosed) {
                return;
            }

            this.emit('SOCKET_ERROR', error);
        };
    }

    reconnect() {
        if (this.manuallyClosed) {
            return;
        }

        this.scheduleReconnect();
    }

    send(event: string, payload?: string | string[]) {
        if (!this.socket || this.socket.readyState !== WebSocket.OPEN) {
            return;
        }

        this.socket.send(JSON.stringify({ event, args: Array.isArray(payload) ? payload : [payload] }));
    }

    private scheduleReconnect(event?: Event | CloseEvent) {
        if (this.manuallyClosed || this.reconnectTimer || !this.url) {
            return;
        }

        if (this.reconnectAttempts >= this.maxReconnectAttempts) {
            this.emit('SOCKET_CONNECT_ERROR', event);

            return;
        }

        this.reconnectAttempts += 1;
        this.reconnectTimer = setTimeout(() => {
            this.reconnectTimer = null;

            if (this.manuallyClosed || !this.url) {
                return;
            }

            this.emit('SOCKET_RECONNECT', event);
            this.open();
        }, this.reconnectDelay);
    }

    private clearReconnectTimer() {
        if (!this.reconnectTimer) {
            return;
        }

        clearTimeout(this.reconnectTimer);
        this.reconnectTimer = null;
    }
}
