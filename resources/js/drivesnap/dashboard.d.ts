export type BackupRow = {
    id: string;
    status: string;
    driver: string | null;
    size: number;
    uploaded_bytes: number;
    upload_to_drive: boolean;
    error_code: string | null;
    created_at: string;
    completed_at: string | null;
    local: boolean;
    remote: boolean;
    local_path: string | null;
    drive_url: string | null;
    download_url: string;
    delete_url: string;
    retry_url: string;
};
export type DashboardData = {
    readiness: {
        checked_at: string | null;
        checks: {
            key: string;
            label: string;
            status: 'ok' | 'warning' | 'error';
            message: string;
            last_seen_at?: string | null;
        }[];
    };
    configuration: {
        binary_key: string;
        binaries: { key: string; label: string }[];
        driver: string;
        dump_timeout: number;
        job_timeout: number;
    };
    feedback?: { message: string | null; error: boolean };
    backups: {
        data: BackupRow[];
        current_page: number;
        last_page: number;
        total: number;
        from: number | null;
        to: number | null;
    };
    stats: {
        completed: number;
        failed: number;
        bytes: number;
        last_completed_at: string | null;
    };
    connection: {
        folder_url: string | null;
        connected: boolean;
        configured: boolean;
        revoke_on_disconnect: boolean;
        email: string | null;
        schedule_enabled: boolean;
        retention_days: number;
        daily_at: string;
        timezone: string;
    };
    can: Record<'view' | 'create' | 'download' | 'delete' | 'manage', boolean>;
    endpoints: Record<
        'index' | 'status' | 'store' | 'settings' | 'connect' | 'disconnect',
        string
    >;
};
export function mountDashboard(
    root: HTMLElement,
    initial: DashboardData,
): () => void;
export function formatBytes(bytes: number): string;
export function safeEndpoint(path: string, origin: string): string;
