import http from '@/api/http';

export interface LoginResponse {
    complete: boolean;
    intended?: string;
    confirmationToken?: string;
    verificationRequired?: boolean;
    verificationToken?: string;
}

export interface LoginData {
    username: string;
    password: string;
    recaptchaData?: string | null;
}

export default ({ username, password, recaptchaData }: LoginData): Promise<LoginResponse> => {
    return new Promise((resolve, reject) => {
        http.get('/sanctum/csrf-cookie')
            .then(() =>
                http.post('/auth/login', {
                    user: username,
                    password,
                    'g-recaptcha-response': recaptchaData,
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
                });
            })
            .catch(reject);
    });
};
