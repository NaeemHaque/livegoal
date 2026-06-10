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

export function formatLongDate(iso, tz = 'local') {
    if (!iso) {
        return '';
    }

    try {
        return new Intl.DateTimeFormat(undefined, {
            day: 'numeric',
            month: 'long',
            year: 'numeric',
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

export function formatShortDateTime(iso, tz = 'local') {
    if (!iso) {
        return '';
    }

    try {
        return new Intl.DateTimeFormat(undefined, {
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            timeZone: zone(tz),
        }).format(new Date(iso));
    } catch {
        return '';
    }
}

export function formatDateRange(startIso, endIso) {
    if (!startIso || !endIso) {
        return '';
    }

    try {
        // Tournament windows are calendar dates (date-only), so format in UTC —
        // a local timezone must not shift the day. The year sits on the end only.
        const format = (iso, opts) =>
            new Intl.DateTimeFormat(undefined, {
                ...opts,
                timeZone: 'UTC',
            }).format(new Date(iso));

        const start = format(startIso, { day: 'numeric', month: 'short' });
        const end = format(endIso, {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
        });

        return `${start} – ${end}`;
    } catch {
        return '';
    }
}
