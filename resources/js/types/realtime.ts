import type {
    AccountStatus,
    ApplicationItem,
    LiveSending,
    NotificationItem,
} from './contracts';

// Private channel `App.Models.User.{id}`; Echo listens with a leading dot, e.g. '.application.progressed'
export type UserChannelEvents = {
    'application.progressed': { application: ApplicationItem };
    'sending.updated': { sending: LiveSending };
    'account.updated': { status: AccountStatus };
    'notification.created': { notification: NotificationItem };
    'cover_letter.updated': {
        jobId: number;
        state: 'ready' | 'manual';
        reason: 'quota' | 'failed' | null;
        body: string | null;
        aiQuota: { used: number; limit: number };
    };
};
// Public channel `jobs`, event '.jobs.collected'
export type JobsChannelEvents = {
    'jobs.collected': { collectionRunId: number; newJobs: number };
};
