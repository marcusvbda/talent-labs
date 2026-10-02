import { useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Modal } from '@/components/ui/modal';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { useT } from '@/i18n/i18n-provider';
import { CvCard } from '@/features/profiles/cv-card';
import { LinksField, linkRowState } from '@/features/profiles/links-field';
import { VariableBar } from '@/features/profiles/variable-bar';
import type {
    ApplicationProfile,
    ProfileLink,
    TemplateVariable,
} from '@/types/contracts';

export type ProfileDraft = {
    subject: string;
    body: string;
    coverLetter: string;
    links: ProfileLink[];
    active: boolean;
};

export const draftFrom = (profile: ApplicationProfile): ProfileDraft => ({
    subject: profile.emailSubject,
    body: profile.emailBody,
    coverLetter: profile.coverLetter,
    links: profile.links,
    active: profile.active,
});

export const isDirty = (profile: ApplicationProfile, draft: ProfileDraft) =>
    JSON.stringify(draftFrom(profile)) !== JSON.stringify(draft);

type TemplateField = 'subject' | 'body';

export const ProfileForm = ({
    profile,
    draft,
    onDraftChange,
    variables,
    dirty,
    saving,
    onSave,
    deleting,
    onDelete,
}: {
    profile: ApplicationProfile;
    draft: ProfileDraft;
    onDraftChange: (next: ProfileDraft) => void;
    variables: TemplateVariable[];
    dirty: boolean;
    saving: boolean;
    onSave: () => void;
    deleting: boolean;
    onDelete: () => void;
}) => {
    const { t } = useT();
    const [confirmingDelete, setConfirmingDelete] = useState(false);
    const subjectRef = useRef<HTMLInputElement>(null);
    const bodyRef = useRef<HTMLTextAreaElement>(null);
    const focused = useRef<TemplateField>('body');

    // Inserts at the caret (or replaces the selection) of the last focused
    // template field and leaves the caret right after the token.
    const insert = (token: string) => {
        const field = focused.current;
        const element =
            field === 'subject' ? subjectRef.current : bodyRef.current;

        if (!element) {
            return;
        }

        const start = element.selectionStart ?? draft[field].length;
        const end = element.selectionEnd ?? start;
        const value = draft[field];

        onDraftChange({
            ...draft,
            [field]: value.slice(0, start) + token + value.slice(end),
        });

        const caret = start + token.length;

        requestAnimationFrame(() => {
            element.focus();
            element.setSelectionRange(caret, caret);
        });
    };

    return (
        <div className="flex flex-col gap-8">
            <CvCard language={profile.language} cv={profile.cv} />
            <div className="flex flex-col gap-4">
                <h3 className="text-label-sm text-ink">
                    {t('profiles.email.title')}
                </h3>
                <Field label={t('profiles.email.subject')}>
                    {(control) => (
                        <Input
                            {...control}
                            ref={subjectRef}
                            value={draft.subject}
                            onFocus={() => (focused.current = 'subject')}
                            onChange={(event) =>
                                onDraftChange({
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
                            ref={bodyRef}
                            rows={10}
                            value={draft.body}
                            onFocus={() => (focused.current = 'body')}
                            onChange={(event) =>
                                onDraftChange({
                                    ...draft,
                                    body: event.target.value,
                                })
                            }
                        />
                    )}
                </Field>
                <VariableBar variables={variables} onInsert={insert} />
            </div>
            <Field
                label={t('profiles.cover_letter.title')}
                hint={t('profiles.cover_letter.help')}
                optional
            >
                {(control) => (
                    <Textarea
                        {...control}
                        rows={6}
                        value={draft.coverLetter}
                        onChange={(event) =>
                            onDraftChange({
                                ...draft,
                                coverLetter: event.target.value,
                            })
                        }
                    />
                )}
            </Field>
            <LinksField
                links={draft.links}
                onChange={(links) => onDraftChange({ ...draft, links })}
            />
            <div className="flex items-start justify-between gap-4">
                <div className="flex min-w-0 flex-col gap-1">
                    <label
                        htmlFor={`profile-active-${profile.language}`}
                        className="text-label-sm text-ink"
                    >
                        {t('profiles.active.label')}
                    </label>
                    <p className="text-chip text-muted">
                        {t('profiles.active.help')}
                    </p>
                </div>
                <Switch
                    id={`profile-active-${profile.language}`}
                    checked={draft.active}
                    onChange={(active) => onDraftChange({ ...draft, active })}
                />
            </div>
            <div className="flex flex-wrap items-center justify-between gap-3 border-t border-hairline pt-6">
                <Button
                    variant="ghost"
                    className="text-danger-text"
                    onClick={() => setConfirmingDelete(true)}
                >
                    {t('profiles.delete')}
                </Button>
                <Button
                    loading={saving}
                    disabled={
                        !dirty ||
                        draft.links.some(
                            (row) => linkRowState(row) === 'invalid',
                        )
                    }
                    onClick={onSave}
                >
                    {t('profiles.save')}
                </Button>
            </div>
            <Modal
                open={confirmingDelete}
                onClose={() => setConfirmingDelete(false)}
                size="sm"
                title={t('profiles.delete_title')}
                description={t('profiles.delete_body')}
                footer={
                    <>
                        <Button
                            variant="secondary-tile"
                            onClick={() => setConfirmingDelete(false)}
                        >
                            {t('profiles.cancel')}
                        </Button>
                        <Button
                            className="bg-danger text-white hover:bg-danger-text"
                            loading={deleting}
                            onClick={onDelete}
                        >
                            {t('profiles.delete')}
                        </Button>
                    </>
                }
            />
        </div>
    );
};
