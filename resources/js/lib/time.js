/**
 * Timezone-aware time formatting. `tz` is an IANA name, or 'local' for the
 * browser's zone. Falls back to an empty string on bad input.
 */

const zone = (tz) => (tz && tz !== 'local' ? tz : undefined);

export function formatTime(iso, tz = 'local') {
    if (!iso) {
        return '';
    }

    try {
        return new Intl.DateTimeFormat(undefined, {
            hour: '2-digit',
            minute: '2-digit',
            timeZone: zone(tz),
        }).format(new Date(iso));
    } catch {
        return '';
    }
}

export function formatDate(iso, tz = 'local') {
    if (!iso) {
        return '';
    }

    try {
        return new Intl.DateTimeFormat(undefined, {
            weekday: 'short',
            day: 'numeric',
            month: 'short',
            timeZone: zone(tz),
        }).format(new Date(iso));
    } catch {
        return '';
    }
}

export function formatDateTime(iso, tz = 'local') {
    const date = formatDate(iso, tz);
    const time = formatTime(iso, tz);

    return date && time ? `${date} · ${time}` : date || time;
}
