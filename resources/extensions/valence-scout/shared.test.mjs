import test from 'node:test';
import assert from 'node:assert/strict';
import {
    csv,
    deliverLead,
    publicWebsite,
    sourceId,
    webhookUrl,
} from './shared.js';

test('webhook credentials require HTTPS and cannot leak through URL fields', () => {
    for (const url of [
        'http://example.com/import',
        'https://user:secret@example.com',
        'https://example.com?token=secret',
        'https://example.com/#token',
    ]) {
        assert.throws(() => webhookUrl(url));
    }
    assert.equal(
        webhookUrl('http://localhost:8000/import'),
        'http://localhost:8000/import',
    );
    assert.equal(
        webhookUrl('https://valencekit.test/api/v1/leads/webhook'),
        'https://valencekit.test/api/v1/leads/webhook',
    );
});

test('website enrichment rejects local addresses and non-web protocols', () => {
    for (const url of [
        'https://127.0.0.1/',
        'https://[::1]/',
        'https://localhost/',
        'https://internal.test/',
        'file:///etc/passwd',
        'http://example.com/',
    ])
        assert.throws(() => publicWebsite(url));
    assert.equal(
        publicWebsite('https://example.com/contact').hostname,
        'example.com',
    );
});

test('CSV preserves international phone numbers and neutralizes formulas', () => {
    const output = csv([
        {
            name: '=HYPERLINK("bad")',
            phone: '+8801712345678',
            address: 'Dhaka, Bangladesh',
        },
    ]);
    assert.ok(output.includes('"\'=HYPERLINK(""bad"")"'));
    assert.ok(output.includes('"\'+8801712345678"'));
    assert.ok(output.includes('"Dhaka, Bangladesh"'));
});

test('a Maps CID stays stable across URL forms', async () => {
    assert.equal(
        await sourceId(
            'https://www.google.com/maps/place/Test/data=!1s0x1:0xff!8m2',
        ),
        'cid:255',
    );
    assert.equal(
        await sourceId('https://www.google.com/maps?cid=255'),
        'cid:255',
    );
});

test('delivery only succeeds after a persisted ID and disallows redirects', async () => {
    const lead = { name: 'Clinic' };
    const result = await deliverLead(
        'https://example.com/import',
        'session-token',
        lead,
        async (url, options) => {
            assert.equal(options.redirect, 'error');
            assert.equal(options.credentials, 'omit');
            assert.equal(JSON.parse(options.body).name, 'Clinic');
            return new Response(JSON.stringify({ data: { id: 5 } }), {
                status: 201,
            });
        },
    );
    assert.equal(result, 5);
    for (const status of [401, 403, 422, 429, 500]) {
        await assert.rejects(
            deliverLead(
                'https://example.com/import',
                'token',
                lead,
                async () => new Response('{}', { status }),
            ),
        );
    }
    await assert.rejects(
        deliverLead(
            'https://example.com/import',
            'token',
            lead,
            async () => new Response('{}', { status: 200 }),
        ),
    );
});
