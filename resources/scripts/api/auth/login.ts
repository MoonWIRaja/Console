import http from '@/api/http';

export interface LoginResponse {
    complete: boolean;
    intended?: string;
    confirmationToken?: string;
    verificationRequired?: boolean;
    verificationToken?: string;
    challengeRequired?: boolean;
    retryAfter?: number;
    nextAction?: 'retry' | 'checkpoint' | 'email_pin';
}

export interface LoginData {
    username: string;
    password: string;
    captchaToken?: string | null;
    website?: string;
    company?: string;
    formRenderedAt?: number;
}

export default ({
    username,
    password,
    captchaToken,
    website = '',
    company = '',
    formRenderedAt,
}: LoginData): Promise<LoginResponse> => {
    return new Promise((resolve, reject) => {
        http.get('/sanctum/csrf-cookie')
            .then(() =>
                http.post('/auth/login', {
                    user: username,
                    password,
                    website,
                    company,
                    form_rendered_at: formRenderedAt,
                    'cf-turnstile-response': captchaToken,
                })
            )
            .then((response) => {
                if (!(response.data instanceof Object)) {
                    return reject(new Error('An error occurred while processing the login request.'));
                }

                return resolve({
                    complete: response.data.data.complete,
                    intended: response.data.data.intended || undefined,
                    confirmationToken: response.data.data.confirmation_token || undefined,
                    verificationRequired: !!response.data.data.email_verification_required,
                    verificationToken: response.data.data.verification_token || undefined,
                    challengeRequired: !!response.data.challenge_required,
                    retryAfter: response.data.retry_after || undefined,
                    nextAction: response.data.next_action || undefined,
                });
            })
            .catch(reject);
    });
};
