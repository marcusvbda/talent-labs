import { useState } from 'react';
import type { ReactNode } from 'react';
import { Checkbox } from '@/components/ui/checkbox';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { TagsInput } from '@/components/ui/tags-input';
import { Textarea } from '@/components/ui/textarea';
import { useT } from '@/i18n/i18n-provider';
import { StyleguideSection } from './styleguide-section';

const Group = ({ title, children }: { title: string; children: ReactNode }) => (
    <div className="flex flex-col gap-3">
        <h3 className="text-label-sm text-muted">{title}</h3>
        <div className="grid gap-6 rounded-card-sm bg-card p-card-sm md:grid-cols-2">
            {children}
        </div>
    </div>
);

export function FormsSection() {
    const { t } = useT();
    const [tags, setTags] = useState(['Laravel', 'React']);
    const [emptyTags, setEmptyTags] = useState<string[]>([]);
    const [errorTags, setErrorTags] = useState<string[]>([]);
    const [on, setOn] = useState(true);
    const [off, setOff] = useState(false);
    const [checked, setChecked] = useState(true);
    const [plain, setPlain] = useState(false);

    const error = t('styleguide.forms.error_sample');
    const hint = t('styleguide.forms.hint_sample');

    return (
        <StyleguideSection id="forms" title={t('styleguide.forms.title')}>
            <Group title={t('styleguide.forms.input')}>
                <Field
                    id="sg-input-default"
                    label={t('styleguide.forms.state_default')}
                    hint={hint}
                >
                    {(c) => (
                        <Input
                            {...c}
                            placeholder={t('styleguide.forms.placeholder')}
                        />
                    )}
                </Field>
                <Field
                    id="sg-input-focus"
                    label={t('styleguide.forms.state_focus')}
                    hint={t('styleguide.forms.focus_hint')}
                >
                    {(c) => (
                        <Input
                            {...c}
                            placeholder={t('styleguide.forms.placeholder')}
                        />
                    )}
                </Field>
                <Field
                    id="sg-input-error"
                    label={t('styleguide.forms.state_error')}
                    error={error}
                    optional
                >
                    {(c) => <Input {...c} defaultValue="name@" />}
                </Field>
                <Field
                    id="sg-input-disabled"
                    label={t('styleguide.forms.state_disabled')}
                >
                    {(c) => (
                        <Input
                            {...c}
                            disabled
                            placeholder={t('styleguide.forms.placeholder')}
                        />
                    )}
                </Field>
                <Field
                    id="sg-input-filled"
                    label={t('styleguide.forms.state_filled')}
                >
                    {(c) => (
                        <Input
                            {...c}
                            defaultValue={t('styleguide.forms.filled_sample')}
                        />
                    )}
                </Field>
                <Field id="sg-input-lg" label={t('styleguide.forms.size_lg')}>
                    {(c) => (
                        <Input
                            {...c}
                            size="lg"
                            placeholder={t('styleguide.forms.placeholder')}
                        />
                    )}
                </Field>
                <Field id="sg-input-sm" label={t('styleguide.forms.size_sm')}>
                    {(c) => (
                        <Input
                            {...c}
                            size="sm"
                            placeholder={t('styleguide.forms.placeholder')}
                        />
                    )}
                </Field>
            </Group>

            <Group title={t('styleguide.forms.textarea')}>
                <Field
                    id="sg-ta-default"
                    label={t('styleguide.forms.state_default')}
                    hint={hint}
                >
                    {(c) => (
                        <Textarea
                            {...c}
                            placeholder={t('styleguide.forms.placeholder')}
                        />
                    )}
                </Field>
                <Field
                    id="sg-ta-error"
                    label={t('styleguide.forms.state_error')}
                    error={error}
                >
                    {(c) => <Textarea {...c} defaultValue="…" />}
                </Field>
                <Field
                    id="sg-ta-disabled"
                    label={t('styleguide.forms.state_disabled')}
                >
                    {(c) => (
                        <Textarea
                            {...c}
                            disabled
                            placeholder={t('styleguide.forms.placeholder')}
                        />
                    )}
                </Field>
                <Field
                    id="sg-ta-filled"
                    label={t('styleguide.forms.state_filled')}
                >
                    {(c) => (
                        <Textarea
                            {...c}
                            defaultValue={t('styleguide.forms.filled_sample')}
                        />
                    )}
                </Field>
            </Group>

            <Group title={t('styleguide.forms.tags')}>
                <Field
                    id="sg-tags-default"
                    label={t('styleguide.forms.state_default')}
                    hint={t('styleguide.forms.tags_hint')}
                >
                    {(c) => (
                        <TagsInput
                            {...c}
                            value={emptyTags}
                            onChange={setEmptyTags}
                            placeholder={t('styleguide.forms.tags_placeholder')}
                        />
                    )}
                </Field>
                <Field
                    id="sg-tags-filled"
                    label={t('styleguide.forms.state_filled')}
                >
                    {(c) => (
                        <TagsInput
                            {...c}
                            value={tags}
                            onChange={setTags}
                            placeholder={t('styleguide.forms.tags_placeholder')}
                        />
                    )}
                </Field>
                <Field
                    id="sg-tags-error"
                    label={t('styleguide.forms.state_error')}
                    error={error}
                >
                    {(c) => (
                        <TagsInput
                            {...c}
                            value={errorTags}
                            onChange={setErrorTags}
                            placeholder={t('styleguide.forms.tags_placeholder')}
                        />
                    )}
                </Field>
                <Field
                    id="sg-tags-disabled"
                    label={t('styleguide.forms.state_disabled')}
                >
                    {(c) => (
                        <TagsInput
                            {...c}
                            disabled
                            value={['Laravel']}
                            onChange={() => {}}
                        />
                    )}
                </Field>
            </Group>

            <Group title={t('styleguide.forms.checkbox')}>
                <label className="flex items-center gap-3 text-body">
                    <Checkbox
                        checked={plain}
                        onChange={(e) => setPlain(e.target.checked)}
                    />
                    {t('styleguide.forms.state_default')}
                </label>
                <label className="flex items-center gap-3 text-body">
                    <Checkbox
                        checked={checked}
                        onChange={(e) => setChecked(e.target.checked)}
                    />
                    {t('styleguide.forms.state_filled')}
                </label>
                <label className="flex items-center gap-3 text-body">
                    <Checkbox indeterminate checked={false} readOnly />
                    {t('styleguide.forms.indeterminate')}
                </label>
                <label className="flex items-center gap-3 text-body">
                    <Checkbox aria-invalid defaultChecked={false} />
                    {t('styleguide.forms.state_error')}
                </label>
                <label className="flex items-center gap-3 text-body">
                    <Checkbox disabled />
                    {t('styleguide.forms.state_disabled')}
                </label>
                <label className="flex items-center gap-3 text-body">
                    <Checkbox disabled defaultChecked />
                    {t('styleguide.forms.disabled_checked')}
                </label>
            </Group>

            <Group title={t('styleguide.forms.switch')}>
                <label className="flex items-center gap-3 text-body">
                    <Switch checked={off} onChange={setOff} />
                    {t('styleguide.forms.state_default')}
                </label>
                <label className="flex items-center gap-3 text-body">
                    <Switch checked={on} onChange={setOn} />
                    {t('styleguide.forms.state_filled')}
                </label>
                <label className="flex items-center gap-3 text-body">
                    <Switch checked={false} onChange={() => {}} disabled />
                    {t('styleguide.forms.state_disabled')}
                </label>
                <label className="flex items-center gap-3 text-body">
                    <Switch checked onChange={() => {}} disabled />
                    {t('styleguide.forms.disabled_checked')}
                </label>
            </Group>
        </StyleguideSection>
    );
}
