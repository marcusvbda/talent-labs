import { devEmitter, registerSimulation } from '@/data/realtime/dev-emitter';
import { getDevState, subscribeDevState } from '@/data/fixtures/dev-state';
import { SEND_STEPS, STEP_DELAY_MS } from '@/data/fixtures/send-steps';
import { fixtureState } from '@/data/fixtures/state';
import { useFixtures } from '@/data/source';
import type { ApplicationItem } from '@/types/contracts';

export { STEP_DELAY_MS };

// Simulated sender. Driven only by setTimeout; it never fetches. All progress
// lives in fixtureState, so pause() can drop the timer and resume() continues
// from the exact sub-step.

const CV_ERROR = 'Your CV file could not be read.';
const FAIL_STAGE = 'attaching_cv';
const SOON_MS = 2000;

let timer: ReturnType<typeof setTimeout> | null = null;
let wanted = false; // start() was called and the queue has not drained yet
let failingApplicationId: number | null = null;
let collectionRunId = 100;
let limitNotified = false;

const halted = (): boolean =>
    fixtureState.get().paused || getDevState().gmail === 'needs_reconnection';

const clear = (): void => {
    if (timer !== null) {
        clearTimeout(timer);
        timer = null;
    }
};

const emitSending = (): void =>
    devEmitter.emit('sending.updated', { sending: fixtureState.liveSending() });

const emitAccount = (): void =>
    devEmitter.emit('account.updated', {
        status: fixtureState.accountStatus(),
    });

const emitNotification = (
    type: 'application_failed' | 'daily_limit_reached',
    data: Record<string, string | number>,
): void =>
    devEmitter.emit('notification.created', {
        notification: fixtureState.addNotification(type, data),
    });

const applicationById = (id: number): ApplicationItem | undefined =>
    fixtureState.get().applications.find((row) => row.item.id === id)?.item;

const later = (ms: number, run: () => void): void => {
    clear();
    timer = setTimeout(() => {
        timer = null;
        run();
    }, ms);
};

const randomSpacingMs = (): number => (45 + Math.random() * 75) * 1000;

// Overdue queue heads start within SOON_MS; the rest are re-spaced 45-120 s
// apart from there.
function respaceIfOverdue(soon: boolean): void {
    const queued = fixtureState.queuedItems();
    const head = queued[0];

    if (!head?.scheduledFor) {
        return;
    }

    if (!soon && new Date(head.scheduledFor).getTime() > Date.now()) {
        return;
    }

    let slot = Date.now() + Math.random() * SOON_MS;

    queued.forEach((item, index) => {
        if (index > 0) {
            slot += randomSpacingMs();
        }

        fixtureState.patchApplication(item.id, {
            scheduledFor: new Date(slot).toISOString(),
        });
    });
}

// Auto mode: keep the queue fed with the next matching job.
function autoQueue(): boolean {
    if (getDevState().plan !== 'free') {
        return false;
    }

    for (const job of fixtureState.matches()) {
        if (fixtureState.queue([job.id], 'auto').queued.length > 0) {
            return true;
        }
    }

    return false;
}

function queueOneMatch(): boolean {
    for (const job of fixtureState.matches()) {
        if (fixtureState.queue([job.id], 'manual').queued.length > 0) {
            return true;
        }
    }

    console.info('[fixtures] no matching job to queue');

    return false;
}

function begin(item: ApplicationItem): void {
    const first = SEND_STEPS[0];

    if (fixtureState.get().failNextSend) {
        failingApplicationId = item.id;
        fixtureState.set((s) => ({ ...s, failNextSend: false }));
    }

    fixtureState.set((s) => ({
        ...s,
        sendingApplicationId: item.id,
        currentStage: first.stage,
        currentSubStep: first.subStep,
        waitStartedAt: null,
    }));

    const application = fixtureState.patchApplication(item.id, {
        status: 'sending',
        stage: first.stage,
        subStep: first.subStep,
        scheduledFor: null,
    });

    devEmitter.emit('application.progressed', { application });
    emitSending();
    later(STEP_DELAY_MS, advance);
}

function finish(id: number, outcome: 'sent' | 'failed'): void {
    const failed = outcome === 'failed';
    const data = fixtureState.get();
    const application = fixtureState.patchApplication(id, {
        status: outcome,
        stage: outcome,
        // A failed item keeps the sub-step it stopped at.
        subStep: failed ? data.currentSubStep : null,
        lastError: failed ? CV_ERROR : null,
        sentAt: failed ? null : new Date().toISOString(),
    });

    fixtureState.set((s) => ({
        ...s,
        sentToday: failed ? s.sentToday : s.sentToday + 1,
        sendingApplicationId: null,
        currentStage: null,
        currentSubStep: null,
    }));

    if (failed) {
        failingApplicationId = null;
    }

    devEmitter.emit('application.progressed', { application });
    emitSending();
    emitAccount();

    if (failed && application) {
        emitNotification('application_failed', {
            company: application.company.name,
        });
    }

    const { quota } = fixtureState.accountStatus();

    if (quota.remaining === 0 && !limitNotified) {
        limitNotified = true;
        emitNotification('daily_limit_reached', { count: quota.limit });
    }

    schedule();
}

// One sub-step elapsed: move to the next one, or finish the item.
function advance(): void {
    const data = fixtureState.get();
    const id = data.sendingApplicationId;

    if (id === null || halted()) {
        return;
    }

    const index = SEND_STEPS.findIndex(
        (step) => step.subStep === data.currentSubStep,
    );

    if (failingApplicationId === id && data.currentStage === FAIL_STAGE) {
        finish(id, 'failed');

        return;
    }

    const next = SEND_STEPS[index + 1];

    if (!next) {
        finish(id, 'sent');

        return;
    }

    const stageChanged = next.stage !== data.currentStage;

    fixtureState.set((s) => ({
        ...s,
        currentStage: next.stage,
        currentSubStep: next.subStep,
    }));

    const application = fixtureState.patchApplication(id, {
        stage: next.stage,
        subStep: next.subStep,
    });

    devEmitter.emit('application.progressed', { application });

    if (stageChanged) {
        emitSending();
    }

    later(STEP_DELAY_MS, advance);
}

// Decides what happens next: continue the in-flight item, wait for the next
// queued one, top up the queue (auto mode) or stop.
function schedule(): void {
    if (!useFixtures || !wanted || halted() || timer !== null) {
        return;
    }

    if (fixtureState.get().sendingApplicationId !== null) {
        later(STEP_DELAY_MS, advance);

        return;
    }

    if (fixtureState.quota().remaining === 0) {
        wanted = false;

        return;
    }

    let head = fixtureState.queuedItems()[0];

    if (!head && autoQueue()) {
        head = fixtureState.queuedItems()[0];
        fixtureState.set((s) => ({
            ...s,
            waitStartedAt: new Date().toISOString(),
        }));
        emitSending();
    }

    if (!head) {
        wanted = false;
        emitSending();

        return;
    }

    if (fixtureState.get().waitStartedAt === null) {
        fixtureState.set((s) => ({
            ...s,
            waitStartedAt: new Date().toISOString(),
        }));
    }

    const wait = Math.max(
        0,
        new Date(head.scheduledFor ?? Date.now()).getTime() - Date.now(),
    );

    later(wait, () => {
        // The plan may have been lowered while this wait was running.
        if (fixtureState.quota().remaining === 0) {
            wanted = false;
            emitSending();

            return;
        }

        begin(head);
    });
}

function start(options?: { soon?: boolean }): void {
    if (!useFixtures) {
        return;
    }

    wanted = true;
    limitNotified = fixtureState.quota().remaining === 0;

    if (fixtureState.get().paused) {
        fixtureState.set((s) => ({ ...s, paused: false }));
    }

    if (fixtureState.get().sendingApplicationId === null) {
        respaceIfOverdue(options?.soon ?? false);
        clear();
    }

    emitSending();
    schedule();
}

function pause(): void {
    clear();
    fixtureState.set((s) => ({ ...s, paused: true }));
    emitSending();
    emitAccount();
}

function resume(): void {
    wanted = true;
    limitNotified = fixtureState.quota().remaining === 0;
    fixtureState.set((s) => ({ ...s, paused: false }));

    if (fixtureState.get().sendingApplicationId === null) {
        // The wait restarts now; schedule() stamps waitStartedAt again.
        fixtureState.set((s) => ({ ...s, waitStartedAt: null }));
        clear();
        respaceIfOverdue(false);
    }

    emitSending();
    emitAccount();
    schedule();
}

export const engine = {
    start,
    pause,
    resume,
    isRunning: (): boolean => wanted && !halted(),
};

// Gmail needing reauthorization halts sending; reconnecting picks it up again.
const unsubscribe = useFixtures
    ? subscribeDevState(() => {
          if (halted()) {
              clear();
          } else {
              schedule();
          }

          emitSending();
      })
    : () => {};

if (useFixtures) {
    registerSimulation('send', () => {
        queueOneMatch();
        start({ soon: true });
    });

    registerSimulation('failure', () => {
        if (fixtureState.queuedItems().length === 0) {
            queueOneMatch();
        }

        fixtureState.set((s) => ({ ...s, failNextSend: true }));
        start({ soon: true });
    });

    registerSimulation('newJobs', () => {
        const used = new Set(
            fixtureState.get().applications.map((row) => row.jobId),
        );
        // Prefer jobs nobody applied to; history covers most of the pool.
        const fresh = fixtureState
            .get()
            .jobs.filter((job) => !job.collectedToday)
            .sort((a, b) => Number(used.has(a.id)) - Number(used.has(b.id)))
            .slice(0, 3);
        const ids = new Set(fresh.map((job) => job.id));

        fixtureState.set((s) => ({
            ...s,
            jobs: s.jobs.map((job) =>
                ids.has(job.id)
                    ? {
                          ...job,
                          collectedToday: true,
                          firstSeenAt: new Date().toISOString(),
                      }
                    : job,
            ),
        }));

        collectionRunId += 1;
        devEmitter.emit('jobs.collected', {
            collectionRunId,
            newJobs: fresh.length,
        });
    });
}

if (import.meta.hot) {
    import.meta.hot.dispose(() => {
        clear();
        unsubscribe();
    });
}
