import type { QueryClient } from '@tanstack/react-query';
import {
    accountUpdated,
    applicationProgressed,
    jobsCollected,
    notificationCreated,
    notifyToast,
    sendingUpdated,
} from '@/data/realtime/handlers';
import { useUserChannel } from '@/data/realtime/use-user-channel';
import { useT } from '@/i18n/i18n-provider';
import type { JobsChannelEvents, UserChannelEvents } from '@/types/realtime';

// Mounted once in the app layout. `jobs.collected` rides the same map: in
// fixtures mode the dev emitter delivers it; in real mode it belongs to the
// public `jobs` channel, which the backend spec wires to this same handler.
export function useRealtimeCache(): void {
    const { t } = useT();

    useUserChannel<UserChannelEvents & JobsChannelEvents>({
        'application.progressed': applicationProgressed,
        'sending.updated': sendingUpdated,
        'account.updated': accountUpdated,
        'notification.created': (
            payload: UserChannelEvents['notification.created'],
            qc: QueryClient,
        ) => {
            notificationCreated(payload, qc);
            notifyToast(payload.notification, t);
        },
        'jobs.collected': jobsCollected,
    });
}
