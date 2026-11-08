import {
    csv,
    deliverLead,
    publicWebsite,
    sourceId,
    webhookUrl,
} from './shared.js';

const browser = globalThis.chrome;
const $ = (id) => document.getElementById(id);
let rows = [];
let busy = false;
let stopped = false;
let socialTarget = null;
const status = (message) => {
    $('status').textContent = message;
    for (const id of ['settings', 'social']) {
        if ($(id).open) $(`${id}-status`).textContent = message;
    }
};
const pause = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
const save = () => browser.storage.local.set({ rows });
const setBusy = (value) => {
    busy = value;
    for (const id of ['start', 'send', 'clear', 'connect'])
        $(id).disabled = value;
    $('stop').disabled = !value;
    render();
};

function button(label, action) {
    const element = document.createElement('button');
    element.className = 'quiet';
    element.textContent = label;
    element.disabled = busy;
    element.addEventListener('click', () =>
        action().catch((error) => status(error.message)),
    );
    return element;
}

function render() {
    $('count').textContent = String(rows.length);
    $('empty').hidden = rows.length > 0;
    $('leads').replaceChildren(
        ...rows.map((row) => {
            const card = document.createElement('article');
            card.className = 'lead';
            const title = document.createElement('h3');
            title.textContent = row.lead.name;
            card.append(title);
            for (const value of [
                row.lead.category,
                row.lead.address,
                row.lead.phone,
                row.lead.email,
            ]) {
                if (!value) continue;
                const line = document.createElement('p');
                line.textContent = value;
                card.append(line);
            }
            const badge = document.createElement('span');
            badge.className = `badge ${row.state === 'failed' ? 'error' : ''}`;
            badge.textContent =
                row.state === 'sent'
                    ? `Saved · #${row.remoteId}`
                    : row.error || 'Ready to send';
            card.append(badge);
            const actions = document.createElement('div');
            actions.className = 'actions';
            actions.append(
                button('Maps ↗', () =>
                    browser.tabs.create({ url: row.lead.maps_url }),
                ),
                button('Website details', () => enrichWebsite(row)),
                button('LinkedIn', () => openSocial(row, 'linkedin')),
                button('Facebook', () => openSocial(row, 'facebook')),
            );
            card.append(actions);
            return card;
        }),
    );
}

async function openLoaded(tabId, url) {
    await browser.tabs.update(tabId, { url });
    for (let attempt = 0; attempt < 40; attempt++) {
        if (stopped) throw new Error('Stopped. Collected leads are kept.');
        await pause(500);
        const tab = await browser.tabs.get(tabId);
        if (tab.status === 'complete' && tab.url !== 'about:blank') {
            await pause(1500);
            return;
        }
    }
    throw new Error(
        'Page took too long to load. Try again after checking the search tab.',
    );
}

async function mapsCall(tabId, operation) {
    await browser.scripting.executeScript({
        target: { tabId },
        files: ['maps.js'],
    });
    const [result] = await browser.scripting.executeScript({
        target: { tabId },
        func: (method) => globalThis.valenceScoutMaps[method](),
        args: [operation],
    });
    return result.result;
}

$('search-form').addEventListener('submit', async (event) => {
    event.preventDefault();
    if (busy) return;
    stopped = false;
    setBusy(true);
    let failures = 0;
    try {
        const query = `${$('query').value.trim()} in ${$('location').value.trim()}`;
        const limit = Math.min(
            100,
            Math.max(1, Number($('limit').value) || 10),
        );
        const tab = await browser.tabs.create({
            url: 'about:blank',
            active: true,
        });
        status('Opening Google Maps…');
        await openLoaded(
            tab.id,
            `https://www.google.com/maps/search/${encodeURIComponent(query)}?hl=en`,
        );
        const urls = new Set();
        let unchanged = 0;
        for (
            let attempt = 0;
            attempt < 40 && urls.size < limit && unchanged < 4;
            attempt++
        ) {
            if (stopped) break;
            const before = urls.size;
            const page = await mapsCall(tab.id, 'collect');
            for (const url of page.urls) urls.add(url);
            if (page.url.includes('/maps/place/') && page.name)
                urls.add(page.url);
            unchanged = before === urls.size ? unchanged + 1 : 0;
            status(`Found ${urls.size} business links…`);
            await pause(1500);
        }
        if (!urls.size)
            throw new Error(
                'No listings found. Check the Maps tab for consent, verification, or an empty search.',
            );
        for (const [index, url] of [...urls].slice(0, limit).entries()) {
            if (stopped) break;
            status(
                `Reading business ${index + 1} of ${Math.min(urls.size, limit)}…`,
            );
            try {
                await openLoaded(tab.id, url);
                const lead = await mapsCall(tab.id, 'read');
                lead.source_id = await sourceId(lead.maps_url);
                lead.query = query.slice(0, 255);
                if (
                    !rows.some((row) => row.lead.source_id === lead.source_id)
                ) {
                    rows.push({ lead, state: 'pending' });
                    await save();
                    render();
                }
            } catch (error) {
                if (stopped) break;
                failures++;
                status(error.message);
            }
            await pause(1200);
        }
        status(
            `${stopped ? 'Stopped' : 'Search complete'}. ${rows.length} leads kept${failures ? `; ${failures} listings could not be read` : ''}. Review before sending.`,
        );
    } catch (error) {
        status(error.message);
    } finally {
        setBusy(false);
    }
});

$('stop').addEventListener('click', () => {
    stopped = true;
    status('Stopping after the current operation…');
});
$('connect').addEventListener('click', async () => {
    const { endpoint = '' } = await browser.storage.local.get('endpoint');
    $('endpoint').value = endpoint;
    $('token').value = '';
    $('settings-status').textContent = '';
    $('settings').showModal();
});
$('close-settings').addEventListener('click', () => {
    $('token').value = '';
    $('settings').close();
});
$('settings').addEventListener('close', () => {
    $('token').value = '';
});
$('settings-form').addEventListener('submit', async (event) => {
    event.preventDefault();
    try {
        const endpoint = webhookUrl($('endpoint').value.trim());
        const token = $('token').value.trim();
        if (!token) throw new Error('Paste a webhook token.');
        const allowed = await browser.permissions.request({
            origins: [`${new URL(endpoint).origin}/*`],
        });
        if (!allowed) throw new Error('Webhook permission was not granted.');
        await browser.storage.session.set({ connection: { endpoint, token } });
        await browser.storage.local.set({ endpoint });
        $('token').value = '';
        $('settings').close();
        status('Workspace connected for this browser session.');
    } catch (error) {
        status(error.message);
    }
});
$('disconnect').addEventListener('click', async () => {
    await browser.storage.session.remove('connection');
    await browser.storage.local.remove('endpoint');
    $('token').value = '';
    $('settings').close();
    status(
        'Disconnected. Revoke the token in your workspace if it is no longer needed.',
    );
});

$('send').addEventListener('click', async () => {
    if (busy) return;
    stopped = false;
    setBusy(true);
    try {
        const { connection } = await browser.storage.session.get('connection');
        if (!connection) throw new Error('Connect your workspace first.');
        const pending = rows.filter((row) => row.state !== 'sent');
        for (const [index, row] of pending.entries()) {
            if (stopped) break;
            status(`Sending ${index + 1} of ${pending.length}…`);
            try {
                row.remoteId = await deliverLead(
                    connection.endpoint,
                    connection.token,
                    row.lead,
                );
                row.state = 'sent';
                delete row.error;
            } catch (error) {
                row.state = 'failed';
                row.error = error.message;
            }
            await save();
            render();
            await pause(1100);
        }
        const failed = rows.filter((row) => row.state === 'failed').length;
        status(
            `${rows.filter((row) => row.state === 'sent').length} saved. ${failed ? `${failed} failed; fix the connection and retry.` : 'Unsent leads stay on this device.'}`,
        );
    } catch (error) {
        status(error.message);
    } finally {
        setBusy(false);
    }
});

$('export').addEventListener('click', () => {
    const url = URL.createObjectURL(
        new Blob([csv(rows.map((row) => row.lead))], {
            type: 'text/csv;charset=utf-8',
        }),
    );
    const anchor = document.createElement('a');
    anchor.href = url;
    anchor.download = 'valence-scout-leads.csv';
    anchor.click();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
});
$('clear').addEventListener('click', async () => {
    if (
        busy ||
        !confirm(
            'Clear all collected leads from this device? Leads already sent to your workspace remain there.',
        )
    )
        return;
    rows = [];
    await save();
    render();
    status('Local leads cleared.');
});

async function enrichWebsite(row) {
    if (!row.lead.website) throw new Error('This listing has no website.');
    const url = publicWebsite(row.lead.website);
    const allowed = await browser.permissions.request({
        origins: [`${url.origin}/*`],
    });
    if (!allowed) throw new Error('Website permission was not granted.');
    setBusy(true);
    try {
        status(`Reading ${url.hostname}…`);
        const response = await fetch(url, {
            credentials: 'omit',
            redirect: 'error',
            signal: AbortSignal.timeout(15000),
        });
        if (
            !response.ok ||
            !response.headers.get('content-type')?.includes('text/html')
        )
            throw new Error('Website did not return an HTML page.');
        if (Number(response.headers.get('content-length')) > 2_000_000)
            throw new Error('Website page is too large.');
        const reader = response.body.getReader();
        const decoder = new TextDecoder();
        let html = '';
        let size = 0;
        while (true) {
            const { done, value } = await reader.read();
            if (done) break;
            size += value.length;
            if (size > 2_000_000) {
                await reader.cancel();
                throw new Error('Website page is too large.');
            }
            html += decoder.decode(value, { stream: true });
        }
        const doc = new DOMParser().parseFromString(html, 'text/html');
        const socials = { ...row.lead.details?.socials };
        for (const link of doc.querySelectorAll('a[href]')) {
            const raw = link.getAttribute('href');
            if (raw.startsWith('mailto:') && !row.lead.email) {
                const email = raw.slice(7).split('?')[0];
                if (/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email))
                    row.lead.email = email.slice(0, 254);
            }
            try {
                const target = publicWebsite(new URL(raw, url).href);
                for (const [name, domain] of Object.entries({
                    facebook: 'facebook.com',
                    linkedin: 'linkedin.com',
                    instagram: 'instagram.com',
                    twitter: 'x.com',
                    youtube: 'youtube.com',
                    tiktok: 'tiktok.com',
                })) {
                    if (
                        target.hostname === domain ||
                        target.hostname.endsWith(`.${domain}`)
                    )
                        socials[name] = target.href.slice(0, 2048);
                }
            } catch {
                /* Skip non-public and non-HTTPS links. */
            }
        }
        row.lead.details = {
            ...row.lead.details,
            socials,
            description: (
                doc
                    .querySelector('meta[name="description"]')
                    ?.getAttribute('content') || ''
            ).slice(0, 5000),
        };
        row.state = 'pending';
        delete row.error;
        await save();
        status('Website details added. Review before sending.');
    } finally {
        setBusy(false);
    }
}

async function openSocial(row, network) {
    const origin =
        network === 'linkedin'
            ? 'https://www.linkedin.com'
            : 'https://www.facebook.com';
    const allowed = await browser.permissions.request({
        origins: [`${origin}/*`],
    });
    if (!allowed) throw new Error('Search permission was not granted.');
    const query = encodeURIComponent(row.lead.name);
    const url =
        network === 'linkedin'
            ? `${origin}/search/results/companies/?keywords=${query}`
            : `${origin}/search/pages/?q=${query}`;
    const tab = await browser.tabs.create({ url, active: true });
    socialTarget = { row, network, tabId: tab.id };
    $('social-status').textContent = '';
    $('social-description').textContent =
        `Find ${row.lead.name} on ${network === 'linkedin' ? 'LinkedIn' : 'Facebook'}. Confirm the name and address match.`;
    $('social').showModal();
}
$('close-social').addEventListener('click', () => {
    $('social').close();
    socialTarget = null;
});
$('read-social').addEventListener('click', async () => {
    if (!socialTarget) return;
    $('read-social').disabled = true;
    try {
        const { row, network, tabId } = socialTarget;
        const [result] = await browser.scripting.executeScript({
            target: { tabId },
            args: [network],
            func: (network) => {
                if (
                    /login|checkpoint|challenge|authwall|captcha/i.test(
                        location.pathname,
                    ) ||
                    document.querySelector('input[type="password"]')
                )
                    throw new Error(
                        'Complete sign-in or verification in the search tab first.',
                    );
                if (
                    network === 'linkedin' &&
                    !location.pathname.startsWith('/company/')
                )
                    throw new Error(
                        'Open a company page, not a search result or personal profile.',
                    );
                if (
                    network === 'facebook' &&
                    (/^\/(search|login|groups|messages|friends|profile\.php|people)(\/|$)/.test(
                        location.pathname,
                    ) ||
                        location.pathname === '/')
                )
                    throw new Error(
                        'Open the business Page from the search results first.',
                    );
                const visibleText =
                    document.querySelector('main, [role="main"]')?.innerText ||
                    '';
                if (
                    network === 'facebook' &&
                    !/Page\s*[·•]|Page transparency|পেজের স্বচ্ছতা|পেজ\s*[·•]/i.test(
                        visibleText,
                    )
                ) {
                    throw new Error(
                        'Could not confirm this is a business Page. Open its About section with the Page category visible.',
                    );
                }
                const email = visibleText.match(
                    /[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i,
                )?.[0];
                return {
                    url: location.origin + location.pathname,
                    email: email?.slice(0, 254) || null,
                };
            },
        });
        const data = result.result;
        row.lead.details = {
            ...row.lead.details,
            socials: { ...row.lead.details?.socials, [network]: data.url },
        };
        if (!row.lead.email && data.email) row.lead.email = data.email;
        row.state = 'pending';
        delete row.error;
        await save();
        render();
        $('social').close();
        socialTarget = null;
        status('Selected business page added. Review before sending.');
    } catch (error) {
        status(error.message);
    } finally {
        $('read-social').disabled = false;
    }
});

await browser.storage.local.setAccessLevel({ accessLevel: 'TRUSTED_CONTEXTS' });
await browser.storage.session.setAccessLevel({
    accessLevel: 'TRUSTED_CONTEXTS',
});
({ rows = [] } = await browser.storage.local.get('rows'));
render();
