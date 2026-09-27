import type { NotificationItem } from '../../../types/contracts';
import { hoursAgo, minutesAgo } from './time';

// Newest first. Three unread; `data` carries only the params the i18n line
// needs (company, count, time) and never a URL or address.
export const NOTIFICATIONS: NotificationItem[] = [
    {
        id: 'n-1',
        type: 'jobs_collected',
        data: { count: 20 },
        readAt: null,
        createdAt: minutesAgo(25),
    },
    {
        id: 'n-2',
        type: 'application_failed',
        data: { company: 'Faro Data' },
        readAt: null,
        createdAt: hoursAgo(2),
    },
    {
        id: 'n-3',
        type: 'gmail_reauthorization_required',
        data: {},
        readAt: null,
        createdAt: hoursAgo(5),
    },
    {
        id: 'n-4',
        type: 'daily_limit_reached',
        data: { count: 25 },
        readAt: hoursAgo(20),
        createdAt: hoursAgo(22),
    },
    {
        id: 'n-5',
        type: 'jobs_collected',
        data: { count: 14 },
        readAt: hoursAgo(26),
        createdAt: hoursAgo(28),
    },
    {
        id: 'n-6',
        type: 'sending_auto_paused',
        data: { time: '09:00' },
        readAt: hoursAgo(30),
        createdAt: hoursAgo(33),
    },
    {
        id: 'n-7',
        type: 'application_failed',
        data: { company: 'Kiln' },
        readAt: hoursAgo(38),
        createdAt: hoursAgo(41),
    },
    {
        id: 'n-8',
        type: 'daily_limit_reached',
        data: { count: 25 },
        readAt: hoursAgo(44),
        createdAt: hoursAgo(46),
    },
];
