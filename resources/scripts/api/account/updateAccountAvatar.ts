import http from '@/api/http';

export default async (avatar: File): Promise<string> => {
    const formData = new FormData();
    formData.append('avatar', avatar);

    const { data } = await http.post('/api/client/account/avatar', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
    });

    const image =
        data?.image ??
        data?.attributes?.image ??
        data?.data?.image ??
        data?.data?.attributes?.image;

    if (!image) {
        throw new Error('Avatar upload response did not include an image URL.');
    }

    return image as string;
};
