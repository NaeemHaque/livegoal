import {
    formatDate,
    formatDateRange,
    formatDateTime,
    formatLongDate,
    formatShortDateTime,
    formatTime,
} from '@/lib/time';
import { useSettingsStore } from '@/stores/settings';

/**
 * Time formatters bound to the user's timezone setting. Reading `settings.timezone`
 * inside each formatter keeps templates reactive to a timezone change.
 */
export function useTimeFormat() {
    const settings = useSettingsStore();

    return {
        time: (iso) => formatTime(iso, settings.timezone),
        date: (iso) => formatDate(iso, settings.timezone),
        longDate: (iso) => formatLongDate(iso, settings.timezone),
        dateRange: (start, end) => formatDateRange(start, end),
        shortDateTime: (iso) => formatShortDateTime(iso, settings.timezone),
        dateTime: (iso) => formatDateTime(iso, settings.timezone),
    };
}
