import http from '@/api/http';

export interface PasswordResetPinResponse {
    status: string;
    pinRequired: boolean;
    resetToken?: string;
}

export default (email: string, recaptchaData?: string): Promise<PasswordResetPinResponse> => {
    return new Promise((resolve, reject) => {
        http.post('/auth/password', { email, 'g-recaptcha-response': recaptchaData })
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
