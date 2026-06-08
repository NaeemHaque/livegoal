import axios from 'axios';

/**
 * Same-origin client for the Laravel JSON API. Every endpoint returns the
 * `{ data, meta }` envelope (see docs/API.md).
 */
const api = axios.create({
    baseURL: '/api',
    headers: { Accept: 'application/json' },
    timeout: 12000,
});

export default api;
