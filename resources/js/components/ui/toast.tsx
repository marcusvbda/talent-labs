import { CircleAlert, CircleCheck, Info, X } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useSyncExternalStore } from 'react';
import { useT } from '@/i18n/i18n-provider';
import { cn } from '@/lib/utils';

export type ToastTone = 'success' | 'error' | 'info';

export type ToastItem = { id: number; tone: ToastTone; message: string };

const ICONS: Record<ToastTone, LucideIcon> = {
    success: CircleCheck,
    error: CircleAlert,
    info: Info,
};

const ICON_TONES: Record<ToastTone, string> = {
    success: 'bg-success-bg text-success-text',
    error: 'bg-danger-bg text-danger-text',
    info: 'bg-tile text-ink',
};

const DISMISS_AFTER_MS = 5000;

let items: ToastItem[] = [];
let nextId = 1;
const listeners = new Set<() => void>();

const emit = () => {
    listeners.forEach((listener) => listener());
};

const subscribe = (listener: () => void) => {
    listeners.add(listener);

    return () => {
        listeners.delete(listener);
    };
};

const getSnapshot = () => items;

const dismiss = (id: number) => {
    items = items.filter((item) => item.id !== id);
    emit();
};

const push = (tone: ToastTone, message: string) => {
    const id = nextId++;

    items = [...items, { id, tone, message }];
    emit();
    // Display-only timer: it only removes the toast.
    setTimeout(() => dismiss(id), DISMISS_AFTER_MS);
};

export const toast = {
    success: (message: string) => push('success', message),
    error: (message: string) => push('error', message),
    info: (message: string) => push('info', message),
};

export function Toast({
    tone,
    message,
    dismissLabel,
    onDismiss,
    className,
}: {
    tone: ToastTone;
    message: string;
    dismissLabel: string;
    onDismiss?: () => void;
    className?: string;
}) {
    const Icon = ICONS[tone];

    return (
        <div
            className={cn(
                'pointer-events-auto flex items-center gap-3 rounded-row bg-card p-3 text-ink shadow-shell',
                className,
            )}
        >
            <span
                aria-hidden="true"
                className={cn(
                    'grid size-control-sm shrink-0 place-items-center rounded-full',
                    ICON_TONES[tone],
                )}
            >
                <Icon size={20} strokeWidth={1.8} />
            </span>
            <p className="min-w-0 flex-1 text-body">{message}</p>
            {onDismiss ? (
                <button
                    type="button"
                    aria-label={dismissLabel}
                    onClick={onDismiss}
                    className="grid size-control-sm shrink-0 place-items-center rounded-full text-muted hover:bg-tile focus-visible:focus-ring"
                >
                    <X size={20} strokeWidth={1.8} aria-hidden="true" />
                </button>
            ) : null}
        </div>
    );
}

export function Toaster() {
    const { t } = useT();
    const current = useSyncExternalStore(subscribe, getSnapshot, getSnapshot);

    return (
        <div
            aria-live="polite"
            className="pointer-events-none fixed inset-x-4 top-4 z-50 flex flex-col gap-3 md:inset-x-auto md:top-auto md:right-8 md:bottom-8 md:w-96"
        >
            {current.map((item) => (
                <Toast
                    key={item.id}
                    tone={item.tone}
                    message={item.message}
                    dismissLabel={t('common.dismiss')}
                    onDismiss={() => dismiss(item.id)}
                />
            ))}
        </div>
    );
}
