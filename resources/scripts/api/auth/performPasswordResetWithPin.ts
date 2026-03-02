import http from '@/api/http';

interface Data {
    verificationToken?: string;
    pin: string;
    password: string;
    passwordConfirmation: string;
}

interface PasswordResetResponse {
    redirectTo?: string | null;
    sendToLogin: boolean;
}

export default (data: Data): Promise<PasswordResetResponse> => {
    return new Promise((resolve, reject) => {
        http.post('/auth/password/reset', {
            verification_token: data.verificationToken,
            pin: data.pin,
            password: data.password,
            password_confirmation: data.passwordConfirmation,
        })
            .then((response) =>
                resolve({
                    redirectTo: response.data.redirect_to,
                    sendToLogin: response.data.send_to_login,
                })
            )
            .catch(reject);
    });
};
