/** Competitions surfaced across aggregated views (day fixtures, search index). */
export const FEATURED = ['WC', 'CL', 'PL', 'PD', 'SA', 'BL1', 'FL1', 'BSA'];

/** Stable key for a competition — its upstream code, falling back to its id. */
export const compKey = (c) => String(c?.code || c?.id || '');
