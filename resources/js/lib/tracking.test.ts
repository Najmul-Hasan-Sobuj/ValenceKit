import { afterEach, beforeEach, expect, test, vi } from 'vitest';
import {
    readTrackingConsent,
    saveTrackingConsent,
    trackContactLead,
    type TrackingState,
} from './tracking';

const tracker = {
    dispatchEvent: vi.fn(),
    gtag: vi.fn(),
    clarity: vi.fn(),
    fbq: vi.fn(),
    dataLayer: [] as unknown[],
};
let cookies: Map<string, string>;
let writes: string[];
const state: TrackingState = {
    available: true,
    allowed: true,
    consent: null,
    providers: {
        ga4: true,
        gtm: true,
        meta: true,
        clarity: true,
        custom: true,
    },
};
function consent(analytics: boolean, marketing: boolean) {
    cookies.set(
        'tracking_consent',
        encodeURIComponent(
            JSON.stringify({ version: 1, analytics, marketing }),
        ),
    );
}
beforeEach(() => {
    vi.clearAllMocks();
    cookies = new Map();
    writes = [];
    tracker.dataLayer = [];
    vi.stubGlobal('window', tracker);
    vi.stubGlobal('location', {
        protocol: 'https:',
        hostname: 'valencekit.test',
    });
    vi.stubGlobal('document', {
        get cookie() {
            return [...cookies]
                .map(([key, value]) => key + '=' + value)
                .join('; ');
        },
        set cookie(value: string) {
            writes.push(value);
            const [key, ...parts] = value.split(';')[0].split('=');
            if (value.includes('Max-Age=0')) cookies.delete(key);
            else cookies.set(key, parts.join('='));
        },
    });
});
afterEach(() => vi.unstubAllGlobals());

test('missing malformed and non-boolean consent never enables tracking', () => {
    for (const value of [
        '',
        '%bad',
        '{"version":1,"analytics":"true","marketing":true}',
        '{"version":0,"analytics":true,"marketing":true}',
    ]) {
        cookies.set('tracking_consent', value);
        expect(readTrackingConsent()).toBeNull();
        expect(trackContactLead(state)).toBe(false);
    }
    expect(tracker.gtag).not.toHaveBeenCalled();
    expect(tracker.fbq).not.toHaveBeenCalled();
    expect(tracker.dispatchEvent).not.toHaveBeenCalled();
});
test('analytics-only consent sends a single GA4 lead with no contact details', () => {
    consent(true, false);
    expect(trackContactLead(state)).toBe(true);
    expect(tracker.gtag).toHaveBeenCalledExactlyOnceWith(
        'event',
        'generate_lead',
        { form_name: 'contact' },
    );
    expect(tracker.dispatchEvent).toHaveBeenCalledOnce();
    expect(tracker.dataLayer).toEqual([]);
    expect(tracker.fbq).not.toHaveBeenCalled();
});
test('marketing-only consent sends Meta Lead and blocks GA4 and GTM', () => {
    consent(false, true);
    expect(trackContactLead(state)).toBe(true);
    expect(tracker.fbq).toHaveBeenCalledExactlyOnceWith('track', 'Lead');
    expect(tracker.gtag).not.toHaveBeenCalled();
    expect(tracker.dataLayer).toEqual([]);
});
test('GTM receives a lead only with both categories and direct GA4 disabled', () => {
    const gtm = {
        ...state,
        providers: { ...state.providers, ga4: false, meta: false },
    };
    consent(true, false);
    expect(trackContactLead(gtm)).toBe(false);
    consent(true, true);
    expect(trackContactLead(gtm)).toBe(true);
    expect(tracker.dataLayer).toEqual([
        { event: 'generate_lead', form_name: 'contact' },
    ]);
});
test('administrator exclusion blocks all lead events despite granted consent', () => {
    consent(true, true);
    expect(trackContactLead({ ...state, allowed: false })).toBe(false);
    expect(tracker.gtag).not.toHaveBeenCalled();
    expect(tracker.fbq).not.toHaveBeenCalled();
});
test('withdrawing consent updates providers deletes their first-party cookies and preserves essential cookies', () => {
    cookies.set('_ga', 'test');
    cookies.set('_fbp', 'test');
    cookies.set('_clck', 'test');
    cookies.set('laravel_session', 'keep');
    saveTrackingConsent({ version: 1, analytics: false, marketing: false });
    expect(readTrackingConsent()).toEqual({
        version: 1,
        analytics: false,
        marketing: false,
    });
    expect(cookies.has('_ga')).toBe(false);
    expect(cookies.has('_fbp')).toBe(false);
    expect(cookies.has('_clck')).toBe(false);
    expect(cookies.get('laravel_session')).toBe('keep');
    expect(tracker.gtag).toHaveBeenCalledWith('consent', 'update', {
        analytics_storage: 'denied',
        ad_storage: 'denied',
        ad_user_data: 'denied',
        ad_personalization: 'denied',
    });
    expect(tracker.clarity).toHaveBeenCalledWith('consentv2', {
        analytics_Storage: 'denied',
        ad_Storage: 'denied',
    });
    expect(tracker.fbq).toHaveBeenCalledWith('consent', 'revoke');
    expect(writes[0]).toContain('SameSite=Lax; Secure');
});
test('SDK failures cannot break contact success or prevent consent persistence', () => {
    consent(true, true);
    tracker.gtag.mockImplementationOnce(() => {
        throw new Error('SDK unavailable');
    });
    expect(() => trackContactLead(state)).not.toThrow();
    tracker.gtag.mockImplementationOnce(() => {
        throw new Error('SDK unavailable');
    });
    expect(() =>
        saveTrackingConsent({ version: 1, analytics: false, marketing: false }),
    ).not.toThrow();
    expect(readTrackingConsent()?.analytics).toBe(false);
});

test('blocked first-party cookies report failure before notifying tracking SDKs', () => {
    vi.stubGlobal('document', {
        get cookie() {
            return '';
        },
        set cookie(_value: string) {},
    });
    expect(() =>
        saveTrackingConsent({ version: 1, analytics: true, marketing: true }),
    ).toThrow('Your browser could not save these preferences.');
    expect(tracker.gtag).not.toHaveBeenCalled();
    expect(tracker.clarity).not.toHaveBeenCalled();
});

test('a failed Google SDK does not suppress Meta lead delivery', () => {
    consent(true, true);
    tracker.gtag.mockImplementationOnce(() => {
        throw new Error('Google SDK unavailable');
    });
    expect(trackContactLead(state)).toBe(true);
    expect(tracker.fbq).toHaveBeenCalledExactlyOnceWith('track', 'Lead');
});

test('consent withdrawal still reaches Clarity and Meta when Google throws', () => {
    consent(true, true);
    tracker.gtag.mockImplementationOnce(() => {
        throw new Error('Google SDK unavailable');
    });
    saveTrackingConsent({ version: 1, analytics: false, marketing: false });
    expect(tracker.clarity).toHaveBeenCalledWith('consentv2', {
        analytics_Storage: 'denied',
        ad_Storage: 'denied',
    });
    expect(tracker.fbq).toHaveBeenCalledWith('consent', 'revoke');
});
