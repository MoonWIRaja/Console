import http from '@/api/http';

export interface RecycleBinFile {
    name: string;
    mode: string;
    mode_bits: string;
    size: number;
    is_file: boolean;
    is_symlink: boolean;
    mimetype: string;
    created_at: string;
    modified_at: string;
    metadata?: {
        originalPath: string;
        recycleBinPath: string;
        originalName: string;
        deletedAt: string;
    };
}

export default (uuid: string): Promise<RecycleBinFile[]> => {
    return new Promise((resolve, reject) => {
        http.get(`/api/client/servers/${uuid}/files/recycle-bin`)
            .then(({ data }) => resolve(data.data || []))
            .catch(reject);
    });
};
