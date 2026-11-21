export function formatBytes(bytes) {
    if (!Number.isFinite(Number(bytes)) || Number(bytes) < 0) return '—';
    const value = Number(bytes);
    if (value < 1024) return value + ' B';
    const unit = Math.min(4, Math.floor(Math.log(value) / Math.log(1024)));
    return (
        (value / 1024 ** unit).toFixed(1) +
        ' ' +
        ['B', 'KiB', 'MiB', 'GiB', 'TiB'][unit]
    );
}
export function safeEndpoint(path, origin) {
    const url = new URL(path, origin);
    if (
        !['http:', 'https:'].includes(url.protocol) ||
        url.origin !== origin ||
        url.username ||
        url.password
    )
        throw new Error('Invalid dashboard endpoint.');
    return url.pathname + url.search;
}
export function mountDashboard(root, initial) {
    if (!root) return () => {};
    let state = initial,
        disposed = false,
        busy = false,
        page = initial.backups.current_page,
        selected = null;
    let settingsDirty = false;
    const abort = new AbortController();
    const shield =
        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M12 3 4 6v6c0 5 8 9 8 9s8-4 8-9V6z"/><path d="m8.5 12 2.5 2.5 4.5-5"/></svg>';
    root.innerHTML = `
        <div class="ds-shell">
            <header class="ds-header"><div><div class="ds-eyebrow"><span class="ds-brand-icon">${shield}</span>DRIVESNAP <span class="ds-version">DATABASE BACKUPS</span></div><h1>Keep a safe copy.</h1><p>Encrypted database snapshots, close at hand and safely off site.</p></div><button class="ds-button ds-primary" data-action="new">＋ New backup</button></header>
            <div class="ds-notice" role="status" aria-live="polite" data-notice hidden></div>
            <div class="ds-stats"><article><span class="ds-stat-label">Completed backups</span><strong data-stat="completed">0</strong><span>Ready when you need them</span></article><article><span class="ds-stat-label">Archive size</span><strong data-stat="bytes">0 B</strong><span>Encrypted snapshots</span></article><article><span class="ds-stat-label">Last successful backup</span><strong class="ds-date-stat" data-stat="last">Not yet</strong><span data-stat="failed">No failed backups</span></article></div>
            <details class="ds-readiness" open><summary><span>Backup readiness</span><span class="ds-readiness-summary" data-readiness-summary>Not checked</span><span aria-hidden="true">⌄</span></summary><div class="ds-readiness-grid" data-readiness></div><div class="ds-readiness-footer"><span data-doctor-checked>Not checked yet.</span><span>Server checks: <code>php artisan drivesnap:doctor</code></span></div></details>
            <div class="ds-workspace"><section class="ds-history"><div class="ds-section-head"><div><h2>Backup history</h2><p>A record of every snapshot.</p></div><button class="ds-button ds-secondary" data-action="refresh">↻ Refresh</button></div>
                <div class="ds-table-scroll"><table><thead><tr><th>Snapshot</th><th>Status</th><th>Size</th><th>Storage</th><th><span class="ds-sr-only">Actions</span></th></tr></thead><tbody data-rows></tbody></table></div>
                <div class="ds-empty" data-empty>${shield}<h3>Your first backup starts here</h3><p>Create a local snapshot, or connect Google Drive for an off-site copy.</p><button class="ds-button ds-secondary" data-action="new">Create a backup</button></div>
                <footer class="ds-pagination"><span data-range></span><div><button class="ds-button ds-secondary" data-action="previous">← Previous</button><span data-page></span><button class="ds-button ds-secondary" data-action="next">Next →</button></div></footer></section>
                <aside class="ds-sidebar"><section class="ds-card"><div class="ds-card-title"><span class="ds-cloud">☁</span><h2>Google Drive</h2><span class="ds-pill" data-connection-label>Not connected</span></div><p data-account>Keep an encrypted copy outside your server.</p><p class="ds-muted" data-setup></p><button class="ds-button ds-secondary ds-full" data-action="connect">Connect Google Drive ↗</button><a class="ds-button ds-secondary ds-full" data-drive-folder target="_blank" rel="noopener noreferrer" hidden>Open Drive folder ↗</a><button class="ds-text-button" data-action="disconnect" hidden>Disconnect account</button></section>
                <section class="ds-card"><h2>Automatic backups</h2><p class="ds-muted" data-schedule-time></p><form data-settings><label class="ds-switch-row"><span>Enable daily backups</span><input type="checkbox" name="schedule_enabled" role="switch"></label><label class="ds-field" data-binary-field>Backup tool<select name="binary_key"></select><small>Only server-approved tools can be selected.</small></label><label class="ds-field">Keep backups for<select name="retention_days"><option value="7">7 days</option><option value="14">14 days</option><option value="30">30 days</option><option value="60">60 days</option><option value="90">90 days</option><option value="180">180 days</option><option value="365">365 days</option></select></label><p class="ds-footnote" data-settings-dirty role="status" hidden>Unsaved changes</p><p class="ds-footnote">The newest completed backup is kept when retention runs.</p><button class="ds-button ds-secondary ds-full" type="submit">Save settings</button></form></section>
                <div class="ds-assurance">${shield}<p><strong>Encrypted before upload</strong><br>Only encrypted archives are sent to Drive. Keep your encryption key somewhere safe.</p></div></aside></div>
        </div>
        <dialog class="ds-dialog" data-create aria-labelledby="ds-create-title"><form><div class="ds-dialog-body"><span class="ds-dialog-icon">${shield}</span><h2 id="ds-create-title">Create a backup</h2><p>Capture your database now. Your application stays available.</p><label class="ds-choice"><input type="radio" name="destination" value="local" checked><span><strong>Local only</strong><small>A private encrypted copy on this server.</small></span></label><label class="ds-choice"><input type="radio" name="destination" value="drive"><span><strong>Local + Google Drive</strong><small>An encrypted copy in both locations.</small></span></label><p class="ds-footnote">Includes database tables and data. Application files and uploads are separate.</p></div><footer><button type="button" class="ds-button ds-secondary" data-action="close">Cancel</button><button class="ds-button ds-primary" type="submit">Create backup</button></footer></form></dialog>
        <dialog class="ds-dialog" data-locations aria-labelledby="ds-locations-title"><div class="ds-dialog-body"><h2 id="ds-locations-title">Backup locations</h2><p data-location-name></p><section class="ds-location-card"><h3>On this server</h3><p data-local-status></p><div data-local-path hidden><label class="ds-field">Path from your Laravel project<input data-location-path readonly spellcheck="false"></label><button class="ds-button ds-secondary" data-action="copy-path" type="button">Copy path</button></div><p class="ds-footnote">This is server storage. Your browser cannot open it as a folder on your computer.</p></section><section class="ds-location-card"><h3>Google Drive</h3><p data-drive-status></p><div class="ds-location-actions"><a class="ds-button ds-secondary" data-drive-file target="_blank" rel="noopener noreferrer" hidden>Open Drive file ↗</a><a class="ds-button ds-secondary" data-location-folder target="_blank" rel="noopener noreferrer" hidden>Open Drive folder ↗</a></div><p class="ds-footnote">Use the Google account that owns this backup. Encrypted archives have no document preview.</p></section><p class="ds-footnote" data-copy-status role="status" aria-live="polite"></p></div><footer><button class="ds-button ds-secondary" data-action="close" type="button">Close</button><a class="ds-button ds-primary" data-location-download>Download encrypted</a></footer></dialog>
        <dialog class="ds-dialog" data-confirm aria-labelledby="ds-confirm-title"><div class="ds-dialog-body"><h2 id="ds-confirm-title">Delete this backup?</h2><p data-confirm-description></p></div><footer><button class="ds-button ds-secondary" data-action="close">Cancel</button><button class="ds-button ds-danger" data-action="confirm">Delete backup</button></footer></dialog>
    `;
    const find = (selector) => root.querySelector(selector);
    const createDialog = find('[data-create]'),
        confirmDialog = find('[data-confirm]'),
        locationsDialog = find('[data-locations]');
    const date = (value) =>
        value
            ? new Date(value).toLocaleString(undefined, {
                  dateStyle: 'medium',
                  timeStyle: 'short',
              })
            : 'Not yet';
    const notice = (message, error = false) => {
        if (disposed) return;
        const box = find('[data-notice]');
        box.textContent = message;
        box.hidden = !message;
        box.dataset.error = String(error);
    };
    const endpoint = (path) => safeEndpoint(path, window.location.origin);
    async function request(path, method = 'GET', data) {
        const csrf =
            document.cookie
                .split('; ')
                .find((item) => item.startsWith('XSRF-TOKEN='))
                ?.slice(11) ?? '';
        const response = await fetch(endpoint(path), {
            method,
            credentials: 'same-origin',
            signal: abort.signal,
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': decodeURIComponent(csrf),
            },
            body: data ? JSON.stringify(data) : undefined,
        });
        const payload = await response.json().catch(() => ({}));
        if (!response.ok)
            throw new Error(
                response.status >= 500
                    ? 'Could not complete this request. Please retry.'
                    : (Object.values(payload.errors ?? {}).flat()[0] ??
                          payload.message ??
                          'This request is not allowed.'),
            );
        return payload;
    }
    function renderReadiness() {
        const panel = find('[data-readiness]');
        panel.replaceChildren();
        for (const check of state.readiness.checks) {
            const item = document.createElement('article');
            item.className = 'ds-readiness-item';
            const header = document.createElement('div');
            const label = document.createElement('strong');
            label.textContent = check.label;
            const badge = document.createElement('span');
            badge.className = 'ds-readiness-badge';
            badge.dataset.status = check.status;
            badge.textContent =
                check.status === 'ok'
                    ? 'Ready'
                    : check.status === 'error'
                      ? 'Action needed'
                      : 'Check';
            header.append(label, badge);
            const message = document.createElement('p');
            message.textContent = check.message;
            item.append(header, message);
            if (check.last_seen_at) {
                const seen = document.createElement('small');
                seen.textContent = 'Last seen: ' + date(check.last_seen_at);
                item.append(seen);
            }
            panel.append(item);
        }
        const ready = state.readiness.checks.filter(
            (check) => check.status === 'ok',
        ).length;
        find('[data-readiness-summary]').textContent =
            ready + ' / ' + state.readiness.checks.length + ' ready';
        find('[data-doctor-checked]').textContent = state.readiness.checked_at
            ? 'Server checked: ' + date(state.readiness.checked_at)
            : 'Server checks have not run yet.';
    }
    function render() {
        if (disposed) return;
        find('[data-stat="completed"]').textContent = String(
            state.stats.completed,
        );
        find('[data-stat="bytes"]').textContent = formatBytes(
            state.stats.bytes,
        );
        find('[data-stat="last"]').textContent = date(
            state.stats.last_completed_at,
        );
        find('[data-stat="failed"]').textContent = state.stats.failed
            ? state.stats.failed + ' failed · retry from the history below'
            : 'No failed backups';
        renderReadiness();
        const configuration = state.configuration;
        find('[data-binary-field]').hidden = configuration.driver === 'sqlite';
        const connection = state.connection;
        const folderLink = find('[data-drive-folder]');
        folderLink.hidden = !connection.folder_url;
        if (connection.folder_url) folderLink.href = connection.folder_url;
        else folderLink.removeAttribute('href');
        find('[data-connection-label]').textContent = connection.connected
            ? 'Connected'
            : 'Not connected';
        find('[data-connection-label]').dataset.connected = String(
            connection.connected,
        );
        find('[data-account]').textContent =
            connection.email || 'Keep an encrypted copy outside your server.';
        find('[data-setup]').textContent = !connection.configured
            ? 'Google OAuth is not configured yet. Local backups are available.'
            : '';
        const connect = find('[data-action="connect"]');
        connect.hidden = connection.connected || !state.can.manage;
        connect.disabled = !connection.configured;
        find('[data-action="disconnect"]').hidden =
            !connection.connected || !state.can.manage;
        find('[data-schedule-time]').textContent =
            'Daily at ' +
            connection.daily_at +
            ' (' +
            connection.timezone +
            '). Uses Google Drive when connected; otherwise local storage.';
        if (!settingsDirty) {
            find('[name="schedule_enabled"]').checked =
                connection.schedule_enabled;
            const retention = find('[name="retention_days"]');
            if (
                ![...retention.options].some(
                    (option) =>
                        option.value === String(connection.retention_days),
                )
            )
                retention.add(
                    new Option(
                        connection.retention_days + ' days',
                        String(connection.retention_days),
                    ),
                );
            retention.value = String(connection.retention_days);
            const tools = find('[name="binary_key"]');
            tools.replaceChildren(
                ...configuration.binaries.map(
                    (tool) => new Option(tool.label, tool.key),
                ),
            );
            tools.value = configuration.binary_key;
        }
        find('[data-settings-dirty]').hidden = !settingsDirty;
        find('[data-settings]').hidden = !state.can.manage;
        find('[value="drive"]').disabled = !connection.connected;
        root.querySelectorAll('[data-action="new"]').forEach((button) => {
            button.hidden = !state.can.create;
        });
        const rows = find('[data-rows]');
        rows.replaceChildren();
        for (const backup of state.backups.data) {
            const row = document.createElement('tr');
            const name = document.createElement('td'),
                label = document.createElement('strong'),
                id = document.createElement('small');
            label.textContent =
                (backup.driver ?? 'Database').toUpperCase() +
                ' · ' +
                date(backup.created_at);
            id.textContent = backup.id.slice(0, 8);
            name.append(label, id);
            const status = document.createElement('td'),
                badge = document.createElement('span');
            badge.className = 'ds-status';
            badge.dataset.status = backup.status;
            badge.textContent = backup.status;
            status.append(badge);
            if (backup.status === 'uploading' && backup.size) {
                const progress = document.createElement('small');
                progress.textContent =
                    Math.min(
                        100,
                        Math.floor((backup.uploaded_bytes / backup.size) * 100),
                    ) + '% uploaded';
                status.append(progress);
            }
            if (backup.status === 'failed') {
                const hint = document.createElement('small');
                hint.textContent = 'Check database or Drive connection';
                status.append(hint);
            }
            const size = document.createElement('td');
            size.textContent = backup.size ? formatBytes(backup.size) : '—';
            const storage = document.createElement('td');
            storage.textContent =
                [backup.local && 'Local', backup.remote && 'Drive']
                    .filter(Boolean)
                    .join(' + ') ||
                (backup.status === 'completed'
                    ? 'Unavailable'
                    : backup.upload_to_drive
                      ? 'Local + Drive planned'
                      : 'Local planned');
            if (
                state.can.download &&
                backup.status === 'completed' &&
                (backup.local || backup.remote)
            ) {
                const locate = document.createElement('button');
                locate.type = 'button';
                locate.className = 'ds-location-trigger';
                locate.textContent = 'View locations';
                locate.setAttribute(
                    'aria-label',
                    'View locations for backup ' + backup.id.slice(0, 8),
                );
                locate.onclick = () => {
                    find('[data-location-name]').textContent =
                        backup.id.slice(0, 8) + ' · ' + date(backup.created_at);
                    find('[data-local-status]').textContent = backup.local
                        ? 'Private encrypted copy available.'
                        : 'No local copy. Download encrypted can retrieve the Drive copy.';
                    find('[data-local-path]').hidden = !backup.local_path;
                    find('[data-location-path]').value =
                        backup.local_path ?? '';
                    find('[data-drive-status]').textContent = backup.drive_url
                        ? 'An encrypted copy was uploaded to Google Drive.'
                        : 'No completed Drive copy for this backup.';
                    for (const [selector, url] of [
                        ['[data-drive-file]', backup.drive_url],
                        [
                            '[data-location-folder]',
                            backup.drive_url
                                ? state.connection.folder_url
                                : null,
                        ],
                    ]) {
                        const link = find(selector);
                        link.hidden = !url;
                        if (url) link.href = url;
                        else link.removeAttribute('href');
                    }
                    find('[data-location-download]').href = endpoint(
                        backup.download_url,
                    );
                    find('[data-copy-status]').textContent = '';
                    locationsDialog.showModal();
                };
                storage.append(locate);
            }
            const actions = document.createElement('td'),
                menu = document.createElement('details'),
                summary = document.createElement('summary');
            summary.textContent = '•••';
            summary.setAttribute(
                'aria-label',
                'Actions for backup ' + backup.id.slice(0, 8),
            );
            menu.append(summary);
            const panel = document.createElement('div');
            panel.className = 'ds-menu';
            if (
                state.can.download &&
                backup.status === 'completed' &&
                (backup.local || backup.remote)
            ) {
                const link = document.createElement('a');
                link.textContent = 'Download encrypted';
                link.href = endpoint(backup.download_url);
                panel.append(link);
            }
            if (state.can.create && backup.status === 'failed') {
                const retry = document.createElement('button');
                retry.textContent = 'Retry backup';
                retry.type = 'button';
                retry.onclick = () =>
                    perform(() => request(backup.retry_url, 'POST'));
                panel.append(retry);
            }
            if (state.can.delete) {
                const remove = document.createElement('button');
                remove.textContent = 'Delete backup';
                remove.type = 'button';
                remove.className = 'ds-delete';
                remove.onclick = () => {
                    find('#ds-confirm-title').textContent =
                        'Delete this backup?';
                    find('[data-action="confirm"]').textContent =
                        'Delete backup';
                    menu.open = false;
                    selected = backup;
                    find('[data-confirm-description]').textContent =
                        'Remove snapshot ' +
                        backup.id.slice(0, 8) +
                        ' from local storage and Google Drive. This cannot be undone.';
                    confirmDialog.showModal();
                };
                panel.append(remove);
            }
            if (!panel.children.length) {
                const none = document.createElement('span');
                none.textContent = 'No available actions';
                panel.append(none);
            }
            menu.append(panel);
            actions.append(menu);
            row.append(name, status, size, storage, actions);
            rows.append(row);
        }
        find('[data-empty]').hidden = state.backups.data.length > 0;
        find('[data-range]').textContent =
            'Showing ' +
            (state.backups.from ?? 0) +
            '–' +
            (state.backups.to ?? 0) +
            ' of ' +
            state.backups.total;
        find('[data-page]').textContent =
            state.backups.current_page + ' / ' + state.backups.last_page;
        find('[data-action="previous"]').disabled =
            state.backups.current_page <= 1;
        find('[data-action="next"]').disabled =
            state.backups.current_page >= state.backups.last_page;
    }
    async function refresh() {
        const url = new URL(state.endpoints.status, window.location.origin);
        url.searchParams.set('page', String(page));
        state = await request(url.href);
        if (!disposed) render();
    }
    async function perform(action) {
        if (busy || disposed) return;
        busy = true;
        root.setAttribute('aria-busy', 'true');
        root.querySelectorAll('button').forEach((button) => {
            button.disabled = true;
        });
        try {
            const result = await action();
            if (disposed) return;
            notice(result.message ?? 'Saved.');
            createDialog.close();
            confirmDialog.close();
            await refresh();
        } catch (error) {
            if (!disposed) {
                createDialog.close();
                confirmDialog.close();
                notice(error.message, true);
            }
        } finally {
            busy = false;
            if (!disposed) {
                root.removeAttribute('aria-busy');
                root.querySelectorAll('button').forEach((button) => {
                    button.disabled = false;
                });
                render();
            }
        }
    }
    const handlers = {
        new: () => {
            find('[value="local"]').checked = !state.connection.connected;
            find('[value="drive"]').checked = state.connection.connected;
            createDialog.showModal();
        },
        close: () => {
            createDialog.close();
            confirmDialog.close();
            locationsDialog.close();
        },
        'copy-path': async () => {
            const field = find('[data-location-path]');
            field.focus();
            field.select();
            try {
                await navigator.clipboard.writeText(field.value);
                find('[data-copy-status]').textContent = 'Path copied.';
            } catch {
                find('[data-copy-status]').textContent =
                    'Path selected. Copy it using your keyboard or selection menu.';
            }
        },
        refresh: () => {
            refresh().catch((error) => notice(error.message, true));
        },
        previous: () => {
            if (page > 1) {
                page--;
                refresh().catch((error) => notice(error.message, true));
            }
        },
        next: () => {
            if (page < state.backups.last_page) {
                page++;
                refresh().catch((error) => notice(error.message, true));
            }
        },
        connect: () => {
            window.location.assign(endpoint(state.endpoints.connect));
        },
        disconnect: () => {
            selected = null;
            find('[data-confirm-description]').textContent = state.connection
                .revoke_on_disconnect
                ? 'Revoke Google authorization for this project. All clients and integrations in the same Google project may lose access. Existing backup files are preserved.'
                : 'Disconnect DriveSnap on this server without revoking Google authorization. Other integrations keep their access. The Google grant remains active, and existing backup files are preserved.';
            find('#ds-confirm-title').textContent = 'Disconnect Google Drive?';
            find('[data-action="confirm"]').textContent = 'Disconnect';
            confirmDialog.showModal();
        },
        confirm: () =>
            void perform(() =>
                selected
                    ? request(selected.delete_url, 'DELETE')
                    : request(state.endpoints.disconnect, 'DELETE'),
            ),
    };
    root.querySelectorAll('[data-action]').forEach((button) =>
        button.addEventListener('click', () => {
            if (button.dataset.action === 'new') {
                find('#ds-confirm-title').textContent = 'Delete this backup?';
                find('[data-action="confirm"]').textContent = 'Delete backup';
            }
            handlers[button.dataset.action]?.();
        }),
    );
    createDialog.querySelector('form').addEventListener('submit', (event) => {
        event.preventDefault();
        void perform(() =>
            request(state.endpoints.store, 'POST', {
                upload_to_drive:
                    find('[name="destination"]:checked').value === 'drive',
            }),
        );
    });
    find('[data-settings]').addEventListener('input', () => {
        settingsDirty = true;
        find('[data-settings-dirty]').hidden = false;
    });
    find('[data-settings]').addEventListener('submit', (event) => {
        event.preventDefault();
        void perform(async () => {
            const result = await request(state.endpoints.settings, 'PUT', {
                schedule_enabled: find('[name="schedule_enabled"]').checked,
                retention_days: Number(find('[name="retention_days"]').value),
                ...(state.configuration.driver !== 'sqlite'
                    ? { binary_key: find('[name="binary_key"]').value }
                    : {}),
            });
            settingsDirty = false;
            return result;
        });
    });
    render();
    if (initial.feedback?.message) {
        notice(initial.feedback.message, initial.feedback.error);
    }
    const timer = setInterval(() => {
        if (
            !document.hidden &&
            !busy &&
            !root.querySelector('dialog[open], [data-rows] details[open]') &&
            !(
                root.contains(document.activeElement) &&
                ['INPUT', 'SELECT'].includes(document.activeElement.tagName)
            )
        )
            refresh().catch(() => {});
    }, 15000);
    return () => {
        disposed = true;
        clearInterval(timer);
        abort.abort();
        root.replaceChildren();
    };
}
