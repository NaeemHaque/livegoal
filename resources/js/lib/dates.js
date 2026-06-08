/** Local-date helpers working in `YYYY-MM-DD` strings (no UTC drift). */

export function isoDate(d = new Date()) {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');

    return `${y}-${m}-${day}`;
}

export function parseIso(iso) {
    const [y, m, d] = iso.split('-').map(Number);

    return new Date(y, m - 1, d);
}

export function addDays(iso, n) {
    const d = parseIso(iso);
    d.setDate(d.getDate() + n);

    return isoDate(d);
}

export const today = () => isoDate(new Date());
