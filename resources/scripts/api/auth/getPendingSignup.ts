import http from '@/api/http';
import { PendingSignupData, transformPendingSignup } from '@/api/auth/types';

export default async (): Promise<PendingSignupData | null> => {
    const { data } = await http.get('/auth/signup/pending');

    return transformPendingSignup(data.data) || null;
};
