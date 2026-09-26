import {
    Dialog,
    DialogBackdrop,
    DialogPanel,
    DialogTitle,
    Description,
} from '@headlessui/react';
import { X } from 'lucide-react';
import type { ReactNode } from 'react';
import { useT } from '@/i18n/i18n-provider';
import { cn } from '@/lib/utils';
import { IconButton } from './icon-button';

type ModalSize = 'sm' | 'md' | 'lg';

const SIZES: Record<ModalSize, string> = {
    sm: 'md:max-w-sm',
    md: 'md:max-w-lg',
    lg: 'md:max-w-2xl',
};

export function Modal({
    open,
    onClose,
    title,
    description,
    footer,
    size = 'md',
    children,
}: {
    open: boolean;
    onClose: () => void;
    title: string;
    description?: string;
    footer?: ReactNode;
    size?: ModalSize;
    children?: ReactNode;
}) {
    const { t } = useT();

    return (
        <Dialog open={open} onClose={onClose} className="relative z-50">
            <DialogBackdrop
                transition
                className="fixed inset-0 bg-scrim transition duration-150 data-closed:opacity-0"
            />
            <div className="fixed inset-0 grid place-items-center overflow-y-auto p-4">
                <DialogPanel
                    transition
                    className={cn(
                        'flex max-h-full w-full flex-col gap-6 rounded-card bg-card p-card shadow-shell transition duration-150 data-closed:scale-95 data-closed:opacity-0',
                        SIZES[size],
                    )}
                >
                    <div className="flex items-start justify-between gap-4">
                        <div className="flex min-w-0 flex-col gap-2">
                            <DialogTitle className="text-card-title-sm md:text-card-title">
                                {title}
                            </DialogTitle>
                            {description ? (
                                <Description className="text-body text-muted">
                                    {description}
                                </Description>
                            ) : null}
                        </div>
                        <IconButton
                            icon={X}
                            label={t('common.close')}
                            onClick={onClose}
                        />
                    </div>
                    {children ? (
                        <div className="overflow-y-auto">{children}</div>
                    ) : null}
                    {footer ? (
                        <div className="flex flex-wrap justify-end gap-3">
                            {footer}
                        </div>
                    ) : null}
                </DialogPanel>
            </div>
        </Dialog>
    );
}
