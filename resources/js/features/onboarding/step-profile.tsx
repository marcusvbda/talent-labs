import { useRef, useState } from 'react';
import { DataCard } from '@/components/patterns/data-card';
import { Button } from '@/components/ui/button';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Segmented } from '@/components/ui/segmented';
import { Textarea } from '@/components/ui/textarea';
import { toast } from '@/components/ui/toast';
import { useDefaultTemplates } from '@/data/hooks/use-default-templates';
import {
    useCreateProfile,
    useProfiles,
    useSaveProfile,
} from '@/data/hooks/use-profiles';
import { useTemplatePreview } from '@/data/hooks/use-template-preview';
import { StepFooter } from '@/features/onboarding/step-footer';
import { CvCard } from '@/features/profiles/cv-card';
import { PreviewCard } from '@/features/profiles/preview-card';
import { VariableBar } from '@/features/profiles/variable-bar';
import { useT } from '@/i18n/i18n-provider';
import { AppGrid } from '@/layouts/app-layout';
import { useDebouncedValue } from '@/lib/use-debounced-value';
import type {
    ApplicationProfile,
    JobLanguage,
    ProfilesData,
} from '@/types/contracts';

const LANGUAGES: JobLanguage[] = ['en', 'pt'];
const PREVIEW_DEBOUNCE_MS = 500;

type Draft = { subject: string; body: string; coverLetter: string };

const ProfileEditor = ({
    language,
    profile,
    data,
    onBack,
    onDone,
}: {
    language: JobLanguage;
    profile: ApplicationProfile | undefined;
    data: ProfilesData;
    onBack: () => void;
    onDone: () => void;
}) => {
    const { t } = useT();
    const defaults = useDefaultTemplates(language);
    const create = useCreateProfile();
    const save = useSaveProfile();
    const [draft, setDraft] = useState<Draft>(
        profile
            ? {
                  subject: profile.emailSubject,
                  body: profile.emailBody,
                  coverLetter: profile.coverLetter,
              }
            : { ...defaults, coverLetter: '' },
    );
    const focused = useRef<'subject' | 'body'>('body');
    const subject = useDebouncedValue(draft.subject, PREVIEW_DEBOUNCE_MS);
    const body = useDebouncedValue(draft.body, PREVIEW_DEBOUNCE_MS);
    const coverLetter = useDebouncedValue(
        draft.coverLetter,
        PREVIEW_DEBOUNCE_MS,
    );
    const preview = useTemplatePreview({
        language,
        subject,
        body,
        coverLetter,
    });

    const insert = (token: string) =>
        setDraft((current) => ({
            ...current,
            [focused.current]: current[focused.current] + token,
        }));

    const persist = () => ({ language, ...draft, active: true });
    const onFailure = () => toast.error(t('onboarding.profile.save_failed'));

    const onCreate = () =>
        create.mutate(
            { language },
            {
                onSuccess: (created) => {
                    const next: Draft = {
                        subject: draft.subject.trim()
                            ? draft.subject
                            : created.emailSubject,
                        body: draft.body.trim()
                            ? draft.body
                            : created.emailBody,
                        coverLetter: draft.coverLetter,
                    };

                    setDraft(next);
                    save.mutate(
                        { language, ...next, active: true },
                        { onError: onFailure },
                    );
                },
                onError: onFailure,
            },
        );

    const onContinue = () =>
        save.mutate(persist(), { onSuccess: onDone, onError: onFailure });

    const complete = Boolean(
        profile && profile.cv && draft.subject.trim() && draft.body.trim(),
    );

    return (
        <AppGrid>
            <div className="flex min-w-0 flex-col gap-6 md:col-span-2 lg:col-span-7">
                {profile ? (
                    <CvCard language={language} cv={profile.cv} />
                ) : (
                    <div className="flex flex-col gap-3">
                        <p className="text-body text-muted">
                            {t('onboarding.profile.create_hint')}
                        </p>
                        <div>
                            <Button
                                variant="secondary-tile"
                                loading={create.isPending}
                                onClick={onCreate}
                            >
                                {t('onboarding.profile.create')}
                            </Button>
                        </div>
                    </div>
                )}
                <div className="flex flex-col gap-4">
                    <Field label={t('profiles.email.subject')}>
                        {(control) => (
                            <Input
                                {...control}
                                value={draft.subject}
                                onFocus={() => (focused.current = 'subject')}
                                onChange={(event) =>
                                    setDraft({
                                        ...draft,
                                        subject: event.target.value,
                                    })
                                }
                            />
                        )}
                    </Field>
                    <Field label={t('profiles.email.body')}>
                        {(control) => (
                            <Textarea
                                {...control}
                                rows={8}
                                value={draft.body}
                                onFocus={() => (focused.current = 'body')}
                                onChange={(event) =>
                                    setDraft({
                                        ...draft,
                                        body: event.target.value,
                                    })
                                }
                            />
                        )}
                    </Field>
                    <VariableBar variables={data.variables} onInsert={insert} />
                </div>
                <Field
                    label={t('profiles.cover_letter.title')}
                    hint={t('profiles.cover_letter.help')}
                    optional
                >
                    {(control) => (
                        <Textarea
                            {...control}
                            rows={5}
                            value={draft.coverLetter}
                            onChange={(event) =>
                                setDraft({
                                    ...draft,
                                    coverLetter: event.target.value,
                                })
                            }
                        />
                    )}
                </Field>
                <StepFooter
                    onBack={onBack}
                    onContinue={onContinue}
                    disabled={!complete}
                    loading={save.isPending}
                />
            </div>
            <aside className="min-w-0 md:col-span-2 lg:col-span-5">
                <PreviewCard
                    language={language}
                    preview={preview.data}
                    previewError={preview.isError}
                    onRetry={() => void preview.refetch()}
                    missing={profile?.missing ?? ['cv']}
                    unlocked={data.unlockCounts[language]}
                />
            </aside>
        </AppGrid>
    );
};

export const StepProfile = ({
    initialLanguage,
    onBack,
    onDone,
}: {
    initialLanguage: JobLanguage;
    onBack: () => void;
    onDone: () => void;
}) => {
    const { t } = useT();
    const { data, isError, refetch } = useProfiles();
    const [language, setLanguage] = useState<JobLanguage>(initialLanguage);

    return (
        <DataCard
            title={t('onboarding.profile.title')}
            subtitle={t('onboarding.profile.subtitle')}
            state={isError ? 'error' : !data ? 'loading' : 'ready'}
            onRetry={() => void refetch()}
        >
            {data && (
                <div className="flex flex-col gap-6">
                    <Segmented
                        value={language}
                        onChange={setLanguage}
                        ariaLabel={t('onboarding.profile.language')}
                        options={LANGUAGES.map((value) => ({
                            value,
                            label: t(`locale.${value}`),
                        }))}
                    />
                    <ProfileEditor
                        key={language}
                        language={language}
                        profile={data.profiles.find(
                            (item) => item.language === language,
                        )}
                        data={data}
                        onBack={onBack}
                        onDone={onDone}
                    />
                </div>
            )}
        </DataCard>
    );
};
