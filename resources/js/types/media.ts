export type MediaItem = {
    id: number;
    name: string;
    alt: string | null;
    folder: string;
    collection: string;
    mime_type: string;
    size: number;
    width: number;
    height: number;
    created_at: string;
    url: string;
    owner?: string | null;
    can_edit: boolean;
    can_delete: boolean;
};

export function formatMediaSize(bytes: number): string {
    if (bytes === 0) return '0 KB';
    return bytes >= 1048576
        ? `${(bytes / 1048576).toFixed(1)} MB`
        : `${Math.max(1, Math.round(bytes / 1024))} KB`;
}
