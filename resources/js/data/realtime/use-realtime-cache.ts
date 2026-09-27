import {
    accountUpdated,
    applicationProgressed,
    jobsCollected,
    notificationCreated,
    sendingUpdated,
} from '@/data/realtime/handlers';
import { useUserChannel } from '@/data/realtime/use-user-channel';
import type { JobsChannelEvents, UserChannelEvents } from '@/types/realtime';

// Mounted once in the app layout. `jobs.collected` rides the same map: in
// fixtures mode the dev emitter delivers it; in real mode it belongs to the
// public `jobs` channel, which the backend spec wires to this same handler.
export function useRealtimeCache(): void {
    useUserChannel<UserChannelEvents & JobsChannelEvents>({
        'application.progressed': applicationProgressed,
        'sending.updated': sendingUpdated,
        'account.updated': accountUpdated,
        'notification.created': notificationCreated,
        'jobs.collected': jobsCollected,
    });
}
