/**
 * Strict SVG sanitizer for QR code rendering.
 * Enforces an allowlist boundary: rejects non-SVG markup, dangerous tags,
 * inline event handlers, and active URIs.
 */
export function sanitizeSvg(svg: string | null | undefined): string {
    if (!svg || typeof svg !== 'string') {
        return '';
    }

    const trimmed = svg.trim();

    // Must be an SVG root element
    if (
        !trimmed.toLowerCase().startsWith('<svg') ||
        !trimmed.toLowerCase().endsWith('</svg>')
    ) {
        return '';
    }

    // Reject dangerous tags: script, foreignObject, iframe, object, embed, style, link, audio, video, image, use
    const forbiddenTags =
        /<\/?(?:script|foreignObject|iframe|object|embed|style|link|audio|video|image|use)\b/i;
    if (forbiddenTags.test(trimmed)) {
        return '';
    }

    // Reject inline event handlers (e.g. onload=, onerror=, onclick=)
    const eventHandlerRegex = /\son[a-z]+\s*=/i;
    if (eventHandlerRegex.test(trimmed)) {
        return '';
    }

    // Reject dangerous protocol URIs
    const dangerousUriRegex =
        /(?:href|src|xlink:href)\s*=\s*['"]?\s*(?:javascript:|data:)/i;
    if (dangerousUriRegex.test(trimmed)) {
        return '';
    }

    return trimmed;
}
