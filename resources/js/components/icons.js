import { h } from 'vue';

/**
 * Build a stroke-style line icon (inherits currentColor, sized via the `size` prop).
 * Ported from the design export (docs/design-ref/project/icons.jsx).
 */
function lineIcon(children, { vb = 24, sw = 1.7 } = {}) {
    const Icon = (props) =>
        h(
            'svg',
            {
                width: props.size ?? 20,
                height: props.size ?? 20,
                viewBox: `0 0 ${vb} ${vb}`,
                fill: 'none',
                stroke: 'currentColor',
                'stroke-width': sw,
                'stroke-linecap': 'round',
                'stroke-linejoin': 'round',
                'aria-hidden': 'true',
            },
            children.map(([tag, attrs]) => h(tag, attrs)),
        );

    Icon.props = ['size'];

    return Icon;
}

export const IcLive = lineIcon([
    [
        'circle',
        { cx: 12, cy: 12, r: 3.2, fill: 'currentColor', stroke: 'none' },
    ],
    [
        'path',
        { d: 'M5.5 5.5a9 9 0 0 0 0 13M18.5 5.5a9 9 0 0 1 0 13', opacity: 0.7 },
    ],
]);

export const IcCalendar = lineIcon([
    ['rect', { x: 3.5, y: 5, width: 17, height: 15, rx: 2.5 }],
    ['path', { d: 'M3.5 9.5h17M8 3.5v3M16 3.5v3' }],
]);

export const IcTrophy = lineIcon([
    ['path', { d: 'M7 4h10v4a5 5 0 0 1-10 0V4Z' }],
    [
        'path',
        {
            d: 'M7 6H4.5a2.5 2.5 0 0 0 2.7 2.9M17 6h2.5a2.5 2.5 0 0 1-2.7 2.9M9.5 14.5 9 18h6l-.5-3.5M7.5 20.5h9',
        },
    ],
]);

export const IcStar = lineIcon([
    [
        'path',
        {
            d: 'm12 3.6 2.5 5.2 5.7.8-4.1 4 1 5.7-5.1-2.7-5.1 2.7 1-5.7-4.1-4 5.7-.8Z',
        },
    ],
]);

export const IcChart = lineIcon([
    ['path', { d: 'M4 20V4M4 20h16M8 20v-6M12 20V9M16 20v-9M20 20V6' }],
]);

export const IcSearch = lineIcon([
    ['circle', { cx: 11, cy: 11, r: 6.5 }],
    ['path', { d: 'm16 16 4.5 4.5' }],
]);

export const IcBell = lineIcon([
    ['path', { d: 'M6 9a6 6 0 0 1 12 0c0 5 2 6 2 6H4s2-1 2-6' }],
    ['path', { d: 'M9.5 20a2.5 2.5 0 0 0 5 0' }],
]);

export const IcSun = lineIcon([
    ['circle', { cx: 12, cy: 12, r: 4.2 }],
    [
        'path',
        {
            d: 'M12 2.5v2.5M12 19v2.5M4.6 4.6l1.8 1.8M17.6 17.6l1.8 1.8M2.5 12H5M19 12h2.5M4.6 19.4l1.8-1.8M17.6 6.4l1.8-1.8',
        },
    ],
]);

export const IcMoon = lineIcon([
    ['path', { d: 'M20 14.5A8 8 0 0 1 9.5 4 7 7 0 1 0 20 14.5Z' }],
]);

export const IcSettings = lineIcon([
    ['circle', { cx: 12, cy: 12, r: 3 }],
    [
        'path',
        {
            d: 'M19.1 13.4a7.6 7.6 0 0 0 0-2.8l1.8-1.4-1.8-3.1-2.1.85a7.5 7.5 0 0 0-2.45-1.4L12.1 3.2H8.9l-.45 2.35A7.5 7.5 0 0 0 6 6.95L3.9 6.1 2.1 9.2l1.8 1.4a7.6 7.6 0 0 0 0 2.8L2.1 14.8l1.8 3.1L6 17.05a7.5 7.5 0 0 0 2.45 1.4L8.9 20.8h3.2l.45-2.35a7.5 7.5 0 0 0 2.45-1.4l2.1.85 1.8-3.1-1.8-1.4Z',
        },
    ],
]);

export const IcMore = lineIcon([
    ['circle', { cx: 5, cy: 12, r: 1.4, fill: 'currentColor', stroke: 'none' }],
    [
        'circle',
        { cx: 12, cy: 12, r: 1.4, fill: 'currentColor', stroke: 'none' },
    ],
    [
        'circle',
        { cx: 19, cy: 12, r: 1.4, fill: 'currentColor', stroke: 'none' },
    ],
]);

export const IcInbox = lineIcon([
    [
        'path',
        {
            d: 'M3.5 13.5 6 6.5a2 2 0 0 1 1.9-1.4h8.2A2 2 0 0 1 18 6.5l2.5 7v5a1.5 1.5 0 0 1-1.5 1.5H5a1.5 1.5 0 0 1-1.5-1.5v-5Z',
        },
    ],
    ['path', { d: 'M3.5 13.5h5l1.5 2.5h4l1.5-2.5h5' }],
]);

export const IcAlert = lineIcon([
    ['path', { d: 'M12 3.5 21 19H3l9-15.5Z' }],
    ['path', { d: 'M12 9.5v4M12 16.5v.4' }],
]);

export const IcWifiOff = lineIcon([
    [
        'path',
        {
            d: 'M2 8.8a16 16 0 0 1 6-3.4M22 8.8a16 16 0 0 0-5-3.2M5.5 12.4a11 11 0 0 1 3-1.8M18.5 12.4a11 11 0 0 0-3-1.8M9 16a6 6 0 0 1 6 0',
        },
    ],
    [
        'circle',
        { cx: 12, cy: 19.5, r: 1, fill: 'currentColor', stroke: 'none' },
    ],
    ['path', { d: 'm3 3 18 18' }],
]);

export const IcRefresh = lineIcon([
    ['path', { d: 'M20 11a8 8 0 1 0-.5 4' }],
    ['path', { d: 'M20 4.5V11h-6.5' }],
]);

export const IcClock = lineIcon([
    ['circle', { cx: 12, cy: 12, r: 8.5 }],
    ['path', { d: 'M12 7.5V12l3 2' }],
]);

export const IcCheck = lineIcon([['path', { d: 'm4.5 12.5 5 5 10-11' }]]);

export const IcArrowR = lineIcon([['path', { d: 'M4 12h15m-6-6 6 6-6 6' }]]);

export const IcPin = lineIcon([
    ['path', { d: 'M12 21s7-6.2 7-11a7 7 0 1 0-14 0c0 4.8 7 11 7 11Z' }],
    ['circle', { cx: 12, cy: 10, r: 2.6 }],
]);

export const IcWhistle = lineIcon([
    [
        'path',
        {
            d: 'M3 11.5h7.5L13 9.5h5.5A3.5 3.5 0 0 1 22 13a4 4 0 0 1-7.9.9H3a1.5 1.5 0 0 1-1.5-1.5v0A1.4 1.4 0 0 1 3 11.5Z',
        },
    ],
    ['circle', { cx: 18, cy: 13, r: 1.4 }],
    ['path', { d: 'M6 8.5 4.5 6' }],
]);

/** Football glyph used in the logo mark. */
export const IcBall = (props) =>
    h(
        'svg',
        {
            width: props.size ?? 20,
            height: props.size ?? 20,
            viewBox: '0 0 24 24',
            'aria-hidden': 'true',
        },
        [
            h('circle', {
                cx: 12,
                cy: 12,
                r: 9,
                fill: 'none',
                stroke: 'currentColor',
                'stroke-width': 1.6,
            }),
            h('path', {
                d: 'M12 7.2 9 9.4l1.1 3.5h3.8L15 9.4 12 7.2Z',
                fill: 'currentColor',
            }),
            h('path', {
                d: 'M12 7.2V4M9 9.4 6.2 8M15 9.4 17.8 8M10.1 12.9 8.4 15.8M13.9 12.9l1.7 2.9',
                stroke: 'currentColor',
                'stroke-width': 1.3,
                'stroke-linecap': 'round',
                fill: 'none',
            }),
        ],
    );

IcBall.props = ['size'];
