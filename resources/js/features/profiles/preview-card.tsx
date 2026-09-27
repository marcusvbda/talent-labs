import { TriangleAlert } from 'lucide-react';
import { Fragment } from 'react';
import { Chip } from '@/components/ui/chip';
import { ErrorState } from '@/components/ui/error-state';
import { Skeleton } from '@/components/ui/skeleton';
import { useT } from '@/i18n/i18n-provider';
import type {
    ApplicationProfile,
    JobLanguage,
    TemplatePreview,
} from '@/types/contracts';

const JOB_URL_TOKEN = /(\{\{\s*job_url\s*\}\})/;

// The client never sees a job URL: the token is drawn as a non-editable chip.
const WithJobLink = ({ text }: { text: string }) => {
    const { t } = useT();

    return (
        <>
            {text.split(JOB_URL_TOKEN).map((part, index) =>
                index % 2 === 1 ? (
                    <Chip key={index} variant="plan" className="align-middle">
                        {t('profiles.job_link')}
                    </Chip>
                ) : (
                    <Fragment key={index}>{part}</Fragment>
                ),
            )}
        </>
    );
};

export const PreviewCard = ({
    language,
    preview,
    previewError,
    onRetry,
    missing,
    unlocked,
}: {
    language: JobLanguage;
    preview: TemplatePreview | undefined;
    previewError: boolean;
    onRetry: () => void;
    missing: ApplicationProfile['missing'];
    unlocked: number;
}) => {
    const { t, plural } = useT();

    return (
        <div className="flex flex-col gap-6">
            <div className="flex flex-col gap-3">
                <h3 className="text-label-sm text-muted">
                    {t('profiles.preview.title')}
                </h3>
                {previewError && !preview ? (
                    <ErrorState onRetry={onRetry} />
                ) : !preview ? (
                    <div
                        role="status"
                        aria-busy="true"
                        aria-label={t('common.loading')}
                        className="flex flex-col gap-3"
                    >
                        <Skeleton shape="line" className="h-6 w-2/3" />
                        <Skeleton shape="block" />
                    </div>
                ) : (
                    <>
                        <p className="text-chip text-muted">
                            {t('profiles.preview.sample', {
                                title: preview.sampleJob.title,
                                company: preview.sampleJob.company,
                            })}
                        </p>
                        <div className="flex flex-col gap-3 rounded-tile bg-tile p-4">
                            <p className="text-body font-medium wrap-break-word text-ink">
                                <WithJobLink text={preview.subject} />
                            </p>
                            <p className="text-body leading-relaxed wrap-break-word whitespace-pre-line text-ink">
                                <WithJobLink text={preview.body} />
                            </p>
                        </div>
                    </>
                )}
            </div>
            <div className="flex flex-col gap-2 border-t border-hairline pt-5">
                <h3 className="text-label-sm text-muted">
                    {t('profiles.unlocked.title')}
                </h3>
                <p className="text-body text-ink">
                    {plural('profiles.unlocked', unlocked, {
                        language: t(`locale.${language}`),
                    })}
                </p>
            </div>
            {missing.length > 0 ? (
                <div
                    role="note"
                    className="flex flex-col gap-2 rounded-tile bg-accent-soft p-4 text-accent-deep"
                >
                    <p className="flex items-center gap-2 text-label-sm">
                        <TriangleAlert
                            aria-hidden="true"
                            strokeWidth={1.8}
                            className="size-5 shrink-0"
                        />
                        {t('profiles.missing.title')}
                    </p>
                    <ul className="list-inside list-disc text-body">
                        {missing.map((item) => (
                            <li key={item}>{t(`profiles.missing.${item}`)}</li>
                        ))}
                    </ul>
                </div>
            ) : null}
        </div>
    );
};
