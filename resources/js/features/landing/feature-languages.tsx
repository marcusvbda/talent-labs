import { FileText } from 'lucide-react';
import { Fragment, useState } from 'react';
import type { ReactNode } from 'react';
import { Card } from '@/components/ui/card';
import { Chip } from '@/components/ui/chip';
import { Tabs } from '@/components/ui/tabs';
import { useT } from '@/i18n/i18n-provider';
import type { JobLanguage } from '@/types/contracts';
import { cn } from '@/lib/utils';
import { JOB_LINK_TOKEN, SAMPLE_CV_FILE, SAMPLE_EMAILS } from './demo-data';
import { useReveal } from './use-reveal';

const SAMPLE_LANGUAGES: JobLanguage[] = ['en', 'pt'];

const Reveal = ({
    index,
    className,
    children,
}: {
    index: number;
    className?: string;
    children: ReactNode;
}) => {
    const reveal = useReveal<HTMLDivElement>(index);

    return (
        <div
            ref={reveal.ref}
            style={reveal.style}
            className={cn(reveal.className, className)}
        >
            {children}
        </div>
    );
};

const WithJobLink = ({ text }: { text: string }) => {
    const { t } = useT();

    return (
        <>
            {text.split(JOB_LINK_TOKEN).map((part, index) => (
                <Fragment key={index}>
                    {index > 0 ? (
                        <Chip variant="plan" className="align-middle">
                            {t('profiles.job_link')}
                        </Chip>
                    ) : null}
                    {part}
                </Fragment>
            ))}
        </>
    );
};

const EmailPreview = ({ language }: { language: JobLanguage }) => {
    const email = SAMPLE_EMAILS[language];

    return (
        <div
            lang={language}
            className="flex flex-col gap-3 rounded-tile bg-tile p-4"
        >
            <p className="text-body font-medium wrap-break-word text-ink">
                {email.subject}
            </p>
            {email.lines.map((line) => (
                <p
                    key={line}
                    className="text-body leading-relaxed wrap-break-word text-ink"
                >
                    <WithJobLink text={line} />
                </p>
            ))}
        </div>
    );
};

const CvRow = () => (
    <div className="flex min-w-0 items-center gap-3 rounded-tile bg-tile p-4">
        <FileText
            aria-hidden="true"
            strokeWidth={1.8}
            className="size-5 shrink-0 text-muted"
        />
        <p className="truncate text-body text-ink">{SAMPLE_CV_FILE}</p>
    </div>
);

export function FeatureLanguages() {
    const { t } = useT();
    const [selected, setSelected] = useState(0);

    return (
        <section aria-labelledby="languages-title">
            <div className="grid grid-cols-1 items-center gap-gap xl:grid-cols-2">
                <div className="flex flex-col items-start gap-5 self-baseline">
                    <Reveal index={0}>
                        <p className="text-label text-accent">
                            {t('landing.languages.eyebrow')}
                        </p>
                    </Reveal>
                    <Reveal index={1}>
                        <h2
                            id="languages-title"
                            className="text-landing-section-sm text-ink md:text-landing-section"
                        >
                            {t('landing.languages.title')}
                        </h2>
                    </Reveal>
                    <Reveal index={2}>
                        <p className="max-w-2xl text-landing-body text-muted">
                            {t('landing.languages.text')}
                        </p>
                    </Reveal>
                </div>
                <Reveal index={2}>
                    <Card tone="light">
                        <Tabs
                            ariaLabel={t('landing.languages.title')}
                            selectedIndex={selected}
                            onChange={setSelected}
                            tabs={SAMPLE_LANGUAGES.map((language) => ({
                                label: t(`locale.${language}`),
                                content: (
                                    <div className="flex flex-col gap-3">
                                        <CvRow />
                                        <EmailPreview language={language} />
                                    </div>
                                ),
                            }))}
                        />
                    </Card>
                </Reveal>
            </div>
        </section>
    );
}
