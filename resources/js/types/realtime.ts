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
};
// Public channel `jobs`, event '.jobs.collected'
export type JobsChannelEvents = {
    'jobs.collected': { collectionRunId: number; newJobs: number };
};
