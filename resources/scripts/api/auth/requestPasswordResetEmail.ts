import http from '@/api/http';

export interface PasswordResetPinResponse {
    status: string;
    pinRequired: boolean;
    resetToken?: string;
}

interface RequestData {
    email: string;
    captchaToken?: string | null;
    website?: string;
    company?: string;
    formRenderedAt?: number;
}

export default ({
    email,
    captchaToken,
    website = '',
    company = '',
    formRenderedAt,
}: RequestData): Promise<PasswordResetPinResponse> => {
    return new Promise((resolve, reject) => {
        http.post('/auth/password', {
            email,
            website,
            company,
            form_rendered_at: formRenderedAt,
            'cf-turnstile-response': captchaToken,
        })
            .then((response) => {
                // Support both legacy string responses and object responses.
                if (typeof response.data === 'string') {
                    return resolve({
                        status: response.data,
                        pinRequired: true,
                        resetToken: undefined,
                    });
                }

                return resolve({
                    status: response.data.status || '',
                    pinRequired: response.data.pin_required !== false,
                    resetToken: response.data.reset_token || undefined,
                });
            })
            .catch(reject);
    });
};
