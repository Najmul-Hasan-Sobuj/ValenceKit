import { watch } from 'vue';
import type { Ref } from 'vue';

/**
 * Keeps a slug in sync with a name source until the user manually edits the slug.
 * Once the slug is manually edited, auto-sync stops.
 *
 * @param name   - reactive ref for the source name
 * @param slug   - reactive ref for the slug (two-way)
 * @param locked - optional reactive ref; when true, auto-sync is permanently disabled
 */
export function useAutoSlug(name: Ref<string>, slug: Ref<string>, locked: Ref<boolean>): void {
    watch(name, (value) => {
        if (locked.value) return;
        slug.value = value
            .toLowerCase()
            .trim()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');
    });
}
