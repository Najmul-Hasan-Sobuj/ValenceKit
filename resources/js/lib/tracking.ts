export type TrackingConsent = {
    version: 1;
    analytics: boolean;
    marketing: boolean;
};
export type TrackingState = {
    available: boolean;
    allowed: boolean;
    consent: TrackingConsent | null;
    providers: {
        clarity: boolean;
        ga4: boolean;
        gtm: boolean;
        meta: boolean;
        custom: boolean;
    };
};
type TrackingWindow = Window & {
    gtag?: (...args: unknown[]) => void;
    clarity?: (...args: unknown[]) => void;
    fbq?: (...args: unknown[]) => void;
    dataLayer?: unknown[];
};

function sendToTracker(send: () => void): boolean {
    try {
        send();
        return true;
    } catch {
        return false;
    }
}

export function readTrackingConsent(): TrackingConsent | null {
    try {
        const cookie = document.cookie
            .split('; ')
            .find((item) => item.startsWith('tracking_consent='));
        const value = JSON.parse(
            decodeURIComponent(
                cookie?.slice('tracking_consent='.length) ?? 'null',
            ),
        );
        return value?.version === 1 &&
            typeof value.analytics === 'boolean' &&
            typeof value.marketing === 'boolean'
            ? value
            : null;
    } catch {
        return null;
    }
}

export function saveTrackingConsent(consent: TrackingConsent): void {
    document.cookie =
        'tracking_consent=' +
        encodeURIComponent(JSON.stringify(consent)) +
        '; Path=/; Max-Age=15552000; SameSite=Lax' +
        (location.protocol === 'https:' ? '; Secure' : '');
    if (JSON.stringify(readTrackingConsent()) !== JSON.stringify(consent))
        throw new Error(
            'Your browser could not save these preferences. Please allow first-party cookies and try again.',
        );
    const tracker = window as TrackingWindow;
    sendToTracker(() =>
        tracker.gtag?.('consent', 'update', {
            analytics_storage: consent.analytics ? 'granted' : 'denied',
            ad_storage: consent.marketing ? 'granted' : 'denied',
            ad_user_data: consent.marketing ? 'granted' : 'denied',
            ad_personalization: consent.marketing ? 'granted' : 'denied',
        }),
    );
    sendToTracker(() =>
        tracker.clarity?.('consentv2', {
            analytics_Storage: consent.analytics ? 'granted' : 'denied',
            ad_Storage: consent.marketing ? 'granted' : 'denied',
        }),
    );
    if (!consent.marketing)
        sendToTracker(() => tracker.fbq?.('consent', 'revoke'));

    for (const part of document.cookie.split('; ')) {
        const name = part.split('=')[0];
        if (
            (!consent.analytics &&
                /^(_ga|_gid|_gat|_clck|_clsk)(_|$)/.test(name)) ||
            (!consent.marketing && /^(_fbp|_fbc|_gcl)(_|$)/.test(name))
        ) {
            const domains = [
                '',
                ...location.hostname
                    .split('.')
                    .map((_, i, parts) => parts.slice(i).join('.')),
            ];
            for (const domain of domains)
                document.cookie =
                    name +
                    '=; Path=/; Max-Age=0' +
                    (domain ? '; Domain=' + domain : '');
        }
    }
}

export function trackContactLead(state: TrackingState | undefined): boolean {
    const consent = readTrackingConsent();
    if (!state?.allowed || !consent) return false;
    const tracker = window as TrackingWindow;
    let queued = false;
    if (consent.analytics && state.providers.ga4 && tracker.gtag) {
        queued = sendToTracker(() =>
            tracker.gtag?.('event', 'generate_lead', { form_name: 'contact' }),
        );
    } else if (consent.analytics && consent.marketing && state.providers.gtm) {
        queued = sendToTracker(() => {
            (tracker.dataLayer ??= []).push({
                event: 'generate_lead',
                form_name: 'contact',
            });
        });
    }
    if (consent.marketing && state.providers.meta && tracker.fbq) {
        queued = sendToTracker(() => tracker.fbq?.('track', 'Lead')) || queued;
    }
    if (queued) window.dispatchEvent(new Event('tracking:lead-queued'));
    return queued;
}
