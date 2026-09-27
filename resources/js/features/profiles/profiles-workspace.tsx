import { router } from '@inertiajs/react';
import { Pause, Plus } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { PageHeader } from '@/components/patterns/page-header';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { EmptyState } from '@/components/ui/empty-state';
import { Menu } from '@/components/ui/menu';
import { Modal } from '@/components/ui/modal';
import { StatusDisc } from '@/components/ui/status-disc';
import { Tabs } from '@/components/ui/tabs';
import { toast } from '@/components/ui/toast';
import {
    useCreateProfile,
    useDeleteProfile,
    useSaveProfile,
} from '@/data/hooks/use-profiles';
import { useTemplatePreview } from '@/data/hooks/use-template-preview';
import { PreviewCard } from '@/features/profiles/preview-card';
import {
    ProfileForm,
    draftFrom,
    isDirty,
} from '@/features/profiles/profile-form';
import type { ProfileDraft } from '@/features/profiles/profile-form';
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

type Pending =
    | { kind: 'tab'; language: JobLanguage }
    | { kind: 'visit'; url: string };

const StatusDot = ({ profile }: { profile: ApplicationProfile }) => {
    const { t } = useT();
    const status = !profile.active
        ? 'inactive'
        : profile.complete
          ? 'complete'
          : 'incomplete';

    return (
        <StatusDisc
            status={status === 'complete' ? 'done' : 'icon'}
            icon={status === 'inactive' ? Pause : undefined}
            tint={
                status === 'complete'
                    ? 'green'
                    : status === 'incomplete'
                      ? 'orange'
                      : 'neutral'
            }
            size="sm"
            label={t(`profiles.status.${status}`)}
            className="-ml-3 size-6"
        />
    );
};

// The preview trails the draft by 500 ms; keyed by language so a tab switch
// starts from the new profile instead of a stale debounce.
const LivePreview = ({
    profile,
    draft,
    unlocked,
}: {
    profile: ApplicationProfile;
    draft: ProfileDraft;
    unlocked: number;
}) => {
    const subject = useDebouncedValue(draft.subject, PREVIEW_DEBOUNCE_MS);
    const body = useDebouncedValue(draft.body, PREVIEW_DEBOUNCE_MS);
    const coverLetter = useDebouncedValue(
        draft.coverLetter,
        PREVIEW_DEBOUNCE_MS,
    );
    const preview = useTemplatePreview({
        language: profile.language,
        subject,
        body,
        coverLetter,
    });

    return (
        <PreviewCard
            language={profile.language}
            preview={preview.data}
            previewError={preview.isError}
            onRetry={() => void preview.refetch()}
            missing={profile.missing}
            unlocked={unlocked}
        />
    );
};

const EmptyProfiles = ({
    unlockCounts,
    creating,
    onCreate,
}: {
    unlockCounts: ProfilesData['unlockCounts'];
    creating: JobLanguage | null;
    onCreate: (language: JobLanguage) => void;
}) => {
    const { t, plural } = useT();

    return (
        <Card tone="light">
            <EmptyState
                icon={Plus}
                title={t('profiles.empty.title')}
                description={t('profiles.empty.description')}
                action={
                    <ul className="flex flex-col gap-3 sm:flex-row">
                        {LANGUAGES.map((language) => (
                            <li key={language} className="flex flex-col gap-2">
                                <Button
                                    variant="secondary-tile"
                                    loading={creating === language}
                                    disabled={creating !== null}
                                    onClick={() => onCreate(language)}
                                >
                                    {t(`locale.${language}`)}
                                </Button>
                                <span className="text-chip text-muted">
                                    {plural(
                                        'profiles.empty.unlock',
                                        unlockCounts[language],
                                        { language: t(`locale.${language}`) },
                                    )}
                                </span>
                            </li>
                        ))}
                    </ul>
                }
            />
        </Card>
    );
};

export const ProfilesWorkspace = ({ data }: { data: ProfilesData }) => {
    const { t, plural } = useT();
    const create = useCreateProfile();
    const save = useSaveProfile();
    const remove = useDeleteProfile();
    const [selected, setSelected] = useState<JobLanguage | null>(null);
    // Unsaved edits per language; compared against the latest saved profile,
    // so a refetch never overwrites what the user is typing.
    const [drafts, setDrafts] = useState<
        Partial<Record<JobLanguage, ProfileDraft>>
    >({});
    const [pending, setPending] = useState<Pending | null>(null);
    const bypassGuard = useRef(false);

    const { profiles } = data;
    const current =
        profiles.find((profile) => profile.language === selected) ??
        profiles[0];
    const draft = current
        ? (drafts[current.language] ?? draftFrom(current))
        : undefined;
    const dirty = current && draft ? isDirty(current, draft) : false;
    const missingLanguages = LANGUAGES.filter(
        (language) =>
            !profiles.some((profile) => profile.language === language),
    );
    const activeCount = profiles.filter((profile) => profile.active).length;

    // Leaving with unsaved edits (Inertia visit or tab close) asks first.
    useEffect(() => {
        if (!dirty) {
            return;
        }

        const off = router.on('before', (event) => {
            const { visit } = event.detail;

            if (
                bypassGuard.current ||
                visit.method !== 'get' ||
                visit.url.pathname === window.location.pathname
            ) {
                return;
            }

            setPending({ kind: 'visit', url: visit.url.href });

            return false;
        });
        const onBeforeUnload = (event: BeforeUnloadEvent) =>
            event.preventDefault();

        window.addEventListener('beforeunload', onBeforeUnload);

        return () => {
            off();
            window.removeEventListener('beforeunload', onBeforeUnload);
        };
    }, [dirty]);

    const discardCurrent = () => {
        if (current) {
            setDrafts((all) => ({ ...all, [current.language]: undefined }));
        }
    };

    const requestSelect = (language: JobLanguage) => {
        if (language === current?.language) {
            return;
        }

        if (dirty) {
            setPending({ kind: 'tab', language });
        } else {
            setSelected(language);
        }
    };

    const onDiscard = () => {
        const target = pending;

        setPending(null);
        discardCurrent();

        if (target?.kind === 'tab') {
            setSelected(target.language);
        } else if (target?.kind === 'visit') {
            bypassGuard.current = true;
            router.visit(target.url, {
                onFinish: () => {
                    bypassGuard.current = false;
                },
            });
        }
    };

    const onCreate = (language: JobLanguage) =>
        create.mutate(
            { language },
            {
                onSuccess: () => requestSelect(language),
                onError: () => toast.error(t('profiles.create_failed')),
            },
        );

    const onSave = () => {
        if (!current || !draft) {
            return;
        }

        save.mutate(
            { language: current.language, ...draft },
            {
                onSuccess: () => {
                    discardCurrent();
                    toast.success(t('profiles.saved'));
                },
                onError: () => toast.error(t('profiles.save_failed')),
            },
        );
    };

    const onDelete = () => {
        if (!current) {
            return;
        }

        remove.mutate(
            { language: current.language },
            {
                onSuccess: () => {
                    discardCurrent();
                    setSelected(null);
                    toast.success(t('profiles.deleted'));
                },
                onError: () => toast.error(t('profiles.delete_failed')),
            },
        );
    };

    return (
        <>
            <PageHeader
                title={t('profiles.title')}
                summary={plural('profiles.summary', activeCount)}
                actions={
                    missingLanguages.length > 0 && profiles.length > 0 ? (
                        <Menu
                            trigger={
                                <Button
                                    variant="secondary-tile"
                                    iconLeft={Plus}
                                    loading={create.isPending}
                                >
                                    {t('profiles.add')}
                                </Button>
                            }
                            items={missingLanguages.map((language) => ({
                                label: t(`locale.${language}`),
                                onSelect: () => onCreate(language),
                            }))}
                        />
                    ) : undefined
                }
            />
            {!current || !draft ? (
                <EmptyProfiles
                    unlockCounts={data.unlockCounts}
                    creating={
                        create.isPending
                            ? (create.variables?.language ?? null)
                            : null
                    }
                    onCreate={onCreate}
                />
            ) : (
                <Tabs
                    ariaLabel={t('profiles.tabs_label')}
                    selectedIndex={profiles.indexOf(current)}
                    onChange={(index) =>
                        requestSelect(profiles[index].language)
                    }
                    tabs={profiles.map((profile) => ({
                        label: t(`locale.${profile.language}`),
                        adornment: <StatusDot profile={profile} />,
                        content:
                            profile === current ? (
                                <AppGrid key={profile.language}>
                                    <Card
                                        tone="light"
                                        className="min-w-0 lg:col-span-7"
                                    >
                                        <ProfileForm
                                            profile={profile}
                                            draft={draft}
                                            onDraftChange={(next) =>
                                                setDrafts((all) => ({
                                                    ...all,
                                                    [profile.language]: next,
                                                }))
                                            }
                                            variables={data.variables}
                                            dirty={dirty}
                                            saving={save.isPending}
                                            onSave={onSave}
                                            deleting={remove.isPending}
                                            onDelete={onDelete}
                                        />
                                    </Card>
                                    <aside className="min-w-0 lg:col-span-5">
                                        <Card
                                            tone="light"
                                            className="lg:sticky lg:top-6"
                                        >
                                            <LivePreview
                                                profile={profile}
                                                draft={draft}
                                                unlocked={
                                                    data.unlockCounts[
                                                        profile.language
                                                    ]
                                                }
                                            />
                                        </Card>
                                    </aside>
                                </AppGrid>
                            ) : null,
                    }))}
                />
            )}
            <Modal
                open={pending !== null}
                onClose={() => setPending(null)}
                size="sm"
                title={t('profiles.unsaved.title')}
                description={t('profiles.unsaved.body')}
                footer={
                    <>
                        <Button
                            variant="secondary-tile"
                            onClick={() => setPending(null)}
                        >
                            {t('profiles.unsaved.stay')}
                        </Button>
                        <Button onClick={onDiscard}>
                            {t('profiles.unsaved.discard')}
                        </Button>
                    </>
                }
            />
        </>
    );
};
