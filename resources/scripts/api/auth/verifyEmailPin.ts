import http from '@/api/http';
import { LoginResponse } from '@/api/auth/login';

export default (verificationToken: string, pin: string): Promise<LoginResponse> => {
    return new Promise((resolve, reject) => {
        http.post('/auth/signup/verify', {
            verification_token: verificationToken,
            pin,
        })
            .then((response) => {
                if (!(response.data instanceof Object)) {
                    return reject(new Error('An error occurred while verifying your account.'));
                }

                return resolve({
                    complete: response.data.data.complete,
                    intended: response.data.data.intended || undefined,
                });
            })
            .catch(reject);
    });
};

