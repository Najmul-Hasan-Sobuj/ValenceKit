/**
 * Safely decode common HTML entities in pagination labels without using an HTML sink.
 * When rendered via standard Vue text interpolation {{ decodePaginationLabel(label) }},
 * output is escaped by default, preventing XSS while displaying clean pagination labels.
 */
export function decodePaginationLabel(
    label: string | null | undefined,
): string {
    if (!label) {
        return '';
    }

    return label
        .replace(/&laquo;|&lsaquo;/g, '«')
        .replace(/&raquo;|&rsaquo;/g, '»')
        .replace(/&amp;/g, '&')
        .replace(/&quot;/g, '"')
        .replace(/&#039;/g, "'")
        .replace(/&lt;/g, '<')
        .replace(/&gt;/g, '>');
}
