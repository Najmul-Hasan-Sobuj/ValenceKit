import { router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import type { FlashToast } from '@/types/ui';

export function initializeFlashToast(): void {
    let lastToastKey = '';
    let lastToastAt = 0;

    const showToast = (data: FlashToast): void => {
        if (!data.type || !data.message) {
            return;
        }

        const key = data.type + ':' + data.message;
        const now = Date.now();

        if (key === lastToastKey && now - lastToastAt < 1000) {
            return;
        }

        lastToastKey = key;
        lastToastAt = now;
        toast[data.type](data.message);
    };

    // 1. Inertia v3 built-in flash event (Inertia::flash)
    router.on('flash', (event: any) => {
        const flash = event.detail?.flash ?? event.flash;
        const data = flash?.toast as FlashToast | undefined;

        if (data && data.type && data.message) {
            showToast(data);
        }
    });

    // 2. Inertia success event (catches props returned on page updates/redirects)
    router.on('success', (event: any) => {
        const page = event.detail?.page;
        const props = page?.props as any;

        if (!props) return;

        if (props.success) {
            showToast({ type: 'success', message: props.success });
            props.success = null;
        }

        const data = (props.flash?.toast || page.flash?.toast) as
            | FlashToast
            | undefined;
        if (data && data.type && data.message) {
            showToast(data);
            if (props.flash) props.flash.toast = null;
        }
    });
}
