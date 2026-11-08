export function webhookUrl(value) {
    const url = new URL(value);
    if (
        url.username ||
        url.password ||
        url.hash ||
        url.search ||
        (url.protocol !== 'https:' &&
            !(
                url.protocol === 'http:' &&
                ['localhost', '127.0.0.1'].includes(url.hostname)
            ))
    ) {
        throw new Error(
            'Use an HTTPS webhook URL without credentials, query, or fragment. HTTP is allowed only on localhost.',
        );
    }
    return url.href;
}

export function publicWebsite(value) {
    const url = new URL(value);
    if (
        url.protocol !== 'https:' ||
        url.username ||
        url.password ||
        !url.hostname.includes('.') ||
        /(^|\.)(localhost|local|test|internal)$/.test(url.hostname) ||
        /^[\d.]+$/.test(url.hostname) ||
        url.hostname.includes(':')
    ) {
        throw new Error('Website enrichment requires a public HTTPS domain.');
    }
    return url;
}

export function csv(rows) {
    const keys = ['name', 'phone', 'email', 'address', 'website', 'maps_url'];
    const cell = (value) => {
        let text = String(value ?? '');
        // oxlint-disable-next-line no-control-regex -- Formula prefixes can follow control characters.
        if (/^[\s\u0000-\u0020]*[=+@-]/.test(text)) text = `'${text}`;
        return `"${text.replaceAll('"', '""')}"`;
    };
    return (
        '\uFEFF' +
        [keys, ...rows.map((row) => keys.map((key) => row[key]))]
            .map((row) => row.map(cell).join(','))
            .join('\r\n')
    );
}

export async function sourceId(value) {
    const url = new URL(value);
    const cid =
        url.searchParams.get('cid') ||
        value.match(/!1s(0x[\da-f]+:0x[\da-f]+)/i)?.[1];
    if (cid)
        return cid.startsWith('0x')
            ? `cid:${BigInt(cid.split(':')[1]).toString()}`
            : `cid:${cid}`;
    const stable = url.pathname.split('/data=')[0];
    const digest = await crypto.subtle.digest(
        'SHA-256',
        new TextEncoder().encode(stable),
    );
    return (
        'url:' +
        [...new Uint8Array(digest)]
            .map((byte) => byte.toString(16).padStart(2, '0'))
            .join('')
    );
}

export async function deliverLead(endpoint, token, lead, fetcher = fetch) {
    const response = await fetcher(webhookUrl(endpoint), {
        method: 'POST',
        redirect: 'error',
        credentials: 'omit',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            Authorization: `Bearer ${token}`,
        },
        body: JSON.stringify(lead),
        signal: AbortSignal.timeout(20000),
    });
    if (![200, 201].includes(response.status))
        throw new Error(
            `Webhook returned ${response.status}. Check your token, permissions, and payload.`,
        );
    const result = await response.json();
    if (!Number.isInteger(result?.data?.id) || result.data.id < 1)
        throw new Error('Webhook did not confirm a saved lead ID.');
    return result.data.id;
}
