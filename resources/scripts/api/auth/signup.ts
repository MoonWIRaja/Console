import http from '@/api/http';

export interface SignupData {
    email: string;
    username: string;
    firstName: string;
    lastName: string;
    password: string;
    passwordConfirmation: string;
    captchaToken?: string | null;
    website?: string;
    company?: string;
    formRenderedAt?: number;
}

export interface SignupResponse {
    complete: boolean;
    verificationRequired?: boolean;
    verificationToken?: string;
}

export default (data: SignupData): Promise<SignupResponse> => {
    return new Promise((resolve, reject) => {
        http.get('/sanctum/csrf-cookie')
            .then(() =>
                http.post('/auth/signup', {
                    email: data.email,
                    username: data.username,
                    first_name: data.firstName,
                    last_name: data.lastName,
                    password: data.password,
                    password_confirmation: data.passwordConfirmation,
                    website: data.website || '',
                    company: data.company || '',
                    form_rendered_at: data.formRenderedAt,
                    'cf-turnstile-response': data.captchaToken,
                })
            )
            .then((response) => {
                if (!(response.data instanceof Object)) {
                    return reject(new Error('An error occurred while processing the signup request.'));
                }

                return resolve({
                    complete: response.data.data.complete,
                    verificationRequired: !!response.data.data.email_verification_required,
                    verificationToken: response.data.data.verification_token || undefined,
                });
            })
            .catch(reject);
    });
};
