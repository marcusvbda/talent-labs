import {
    Dialog,
    DialogBackdrop,
    DialogPanel,
    DialogTitle,
} from '@headlessui/react';
import { X } from 'lucide-react';
import type { ReactNode } from 'react';
import { useT } from '@/i18n/i18n-provider';
import { cn } from '@/lib/utils';
import { IconButton } from './icon-button';

type SheetSide = 'right' | 'bottom';

const PLACEMENT: Record<SheetSide, string> = {
    right: 'inset-y-0 right-0 w-full max-w-md rounded-l-card pt-[max(var(--spacing-card),env(safe-area-inset-top))] pr-[max(var(--spacing-card),env(safe-area-inset-right))] pb-[max(var(--spacing-card),env(safe-area-inset-bottom))] data-closed:translate-x-full',
    bottom: 'inset-x-0 bottom-0 max-h-[85dvh] rounded-t-card pt-card pr-[max(var(--spacing-card),env(safe-area-inset-right))] pb-[max(var(--spacing-card),env(safe-area-inset-bottom))] pl-[max(var(--spacing-card),env(safe-area-inset-left))] data-closed:translate-y-full',
};

export function Sheet({
    open,
    onClose,
    side,
    title,
    children,
}: {
    open: boolean;
    onClose: () => void;
    side: SheetSide;
    title: string;
    children?: ReactNode;
}) {
    const { t } = useT();

    return (
        <Dialog open={open} onClose={onClose} className="relative z-50">
            <DialogBackdrop
                transition
                className="fixed inset-0 bg-scrim transition duration-200 data-closed:opacity-0"
            />
            <DialogPanel
                transition
                className={cn(
                    'fixed flex flex-col gap-6 overflow-y-auto bg-card pl-card shadow-shell transition duration-200 ease-out',
                    PLACEMENT[side],
                )}
            >
                <div className="flex items-start justify-between gap-4">
                    <DialogTitle className="min-w-0 text-card-title-sm">
                        {title}
                    </DialogTitle>
                    <IconButton
                        icon={X}
                        label={t('common.close')}
                        onClick={onClose}
                    />
                </div>
                {children}
            </DialogPanel>
        </Dialog>
    );
}
