/**
 * Extract the leading numeric id from a (possibly slugged) route param, so a
 * keyword URL like "524-arsenal-vs-chelsea" still drives the API call for 524.
 * Falls back to the raw value when there's no numeric prefix.
 */
export function numericId(param) {
    const match = String(param ?? '').match(/^\d+/);

    return match ? match[0] : String(param ?? '');
}
