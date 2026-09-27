import { Check, CircleAlert, CircleHelp, Clock } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { Pill } from '@/components/ui/pill';
import { Spinner } from '@/components/ui/spinner';
import { useT } from '@/i18n/i18n-provider';
import type { ApplicationStatus } from '@/types/contracts';

const TINTS: Partial<Record<ApplicationStatus, string>> = {
    sent: 'bg-success-bg text-success-text',
    failed: 'bg-danger-bg text-danger-text',
    ambiguous: 'bg-accent-soft text-accent-deep',
};

const ICONS: Partial<Record<ApplicationStatus, LucideIcon>> = {
    queued: Clock,
    sent: Check,
    failed: CircleAlert,
    ambiguous: CircleHelp,
};

export const StatusBadge = ({ status }: { status: ApplicationStatus }) => {
    const { t } = useT();

    return (
        <Pill tone="tile" icon={ICONS[status]} className={TINTS[status]}>
            {status === 'sending' && <Spinner size="sm" />}
            {t(`applications.status.${status}`)}
        </Pill>
    );
};
