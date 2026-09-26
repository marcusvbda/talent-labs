import { useState } from 'react';
import type { ReactNode } from 'react';
import { Field } from '@/components/ui/field';
import { MultiSelect } from '@/components/ui/multi-select';
import { RadioGroup } from '@/components/ui/radio-group';
import { Segmented } from '@/components/ui/segmented';
import { Select } from '@/components/ui/select';
import { Tabs } from '@/components/ui/tabs';
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

export function ChoicesSection() {
    const { t } = useT();
    const [single, setSingle] = useState<string | null>(null);
    const [filled, setFilled] = useState<string | null>('remote');
    const [multi, setMulti] = useState<string[]>(['remote', 'hybrid']);
    const [emptyMulti, setEmptyMulti] = useState<string[]>([]);
    const [radio, setRadio] = useState('daily');
    const [segment, setSegment] = useState('week');

    const options = [
        { value: 'remote', label: t('styleguide.choices.opt_remote') },
        { value: 'hybrid', label: t('styleguide.choices.opt_hybrid') },
        { value: 'onsite', label: t('styleguide.choices.opt_onsite') },
    ];
    const error = t('styleguide.forms.error_sample');

    return (
        <StyleguideSection id="choices" title={t('styleguide.choices.title')}>
            <Group title={t('styleguide.choices.select')}>
                <Field
                    id="sg-select-default"
                    label={t('styleguide.forms.state_default')}
                    hint={t('styleguide.forms.hint_sample')}
                >
                    {(c) => (
                        <Select
                            {...c}
                            value={single}
                            onChange={setSingle}
                            options={options}
                        />
                    )}
                </Field>
                <Field
                    id="sg-select-selected"
                    label={t('styleguide.choices.state_selected')}
                >
                    {(c) => (
                        <Select
                            {...c}
                            value={filled}
                            onChange={setFilled}
                            options={options}
                        />
                    )}
                </Field>
                <Field
                    id="sg-select-open"
                    label={t('styleguide.choices.state_open')}
                >
                    {(c) => (
                        <Select
                            {...c}
                            open
                            value={filled}
                            onChange={setFilled}
                            options={options}
                        />
                    )}
                </Field>
                <Field
                    id="sg-select-empty"
                    label={t('styleguide.choices.state_empty')}
                >
                    {(c) => (
                        <Select
                            {...c}
                            open
                            value={null}
                            onChange={() => {}}
                            options={[]}
                        />
                    )}
                </Field>
                <Field
                    id="sg-select-error"
                    label={t('styleguide.forms.state_error')}
                    error={error}
                >
                    {(c) => (
                        <Select
                            {...c}
                            value={null}
                            onChange={() => {}}
                            options={options}
                        />
                    )}
                </Field>
                <Field
                    id="sg-select-disabled"
                    label={t('styleguide.forms.state_disabled')}
                >
                    {(c) => (
                        <Select
                            {...c}
                            disabled
                            value={filled}
                            onChange={() => {}}
                            options={options}
                        />
                    )}
                </Field>
            </Group>

            <Group title={t('styleguide.choices.multi')}>
                <Field
                    id="sg-multi-default"
                    label={t('styleguide.forms.state_default')}
                >
                    {(c) => (
                        <MultiSelect
                            {...c}
                            value={emptyMulti}
                            onChange={setEmptyMulti}
                            options={options}
                        />
                    )}
                </Field>
                <Field
                    id="sg-multi-selected"
                    label={t('styleguide.choices.state_selected')}
                >
                    {(c) => (
                        <MultiSelect
                            {...c}
                            value={multi}
                            onChange={setMulti}
                            options={options}
                        />
                    )}
                </Field>
                <Field
                    id="sg-multi-open"
                    label={t('styleguide.choices.state_open')}
                >
                    {(c) => (
                        <MultiSelect
                            {...c}
                            open
                            value={multi}
                            onChange={setMulti}
                            options={options}
                        />
                    )}
                </Field>
                <Field
                    id="sg-multi-error"
                    label={t('styleguide.forms.state_error')}
                    error={error}
                >
                    {(c) => (
                        <MultiSelect
                            {...c}
                            value={[]}
                            onChange={() => {}}
                            options={options}
                        />
                    )}
                </Field>
                <Field
                    id="sg-multi-disabled"
                    label={t('styleguide.forms.state_disabled')}
                >
                    {(c) => (
                        <MultiSelect
                            {...c}
                            disabled
                            value={multi}
                            onChange={() => {}}
                            options={options}
                        />
                    )}
                </Field>
            </Group>

            <Group title={t('styleguide.choices.radio')}>
                <RadioGroup
                    aria-label={t('styleguide.choices.radio')}
                    value={radio}
                    onChange={setRadio}
                    options={[
                        {
                            value: 'daily',
                            title: t('styleguide.choices.radio_daily'),
                            description: t('styleguide.choices.radio_daily_hint'),
                        },
                        {
                            value: 'weekly',
                            title: t('styleguide.choices.radio_weekly'),
                            description: t('styleguide.choices.radio_weekly_hint'),
                        },
                        {
                            value: 'never',
                            title: t('styleguide.choices.radio_never'),
                        },
                    ]}
                />
                <RadioGroup
                    disabled
                    aria-label={t('styleguide.forms.state_disabled')}
                    value="daily"
                    onChange={() => {}}
                    options={[
                        {
                            value: 'daily',
                            title: t('styleguide.choices.radio_daily'),
                            description: t('styleguide.choices.radio_daily_hint'),
                        },
                        {
                            value: 'weekly',
                            title: t('styleguide.choices.radio_weekly'),
                        },
                    ]}
                />
            </Group>

            <Group title={t('styleguide.choices.segmented')}>
                <Segmented
                    ariaLabel={t('styleguide.choices.segmented')}
                    value={segment}
                    onChange={setSegment}
                    options={[
                        { value: 'day', label: t('styleguide.choices.seg_day') },
                        {
                            value: 'week',
                            label: t('styleguide.choices.seg_week'),
                        },
                        {
                            value: 'month',
                            label: t('styleguide.choices.seg_month'),
                        },
                    ]}
                />
                <Segmented
                    disabled
                    ariaLabel={t('styleguide.forms.state_disabled')}
                    value="week"
                    onChange={() => {}}
                    options={[
                        { value: 'day', label: t('styleguide.choices.seg_day') },
                        {
                            value: 'week',
                            label: t('styleguide.choices.seg_week'),
                        },
                    ]}
                />
            </Group>

            <Group title={t('styleguide.choices.tabs')}>
                <Tabs
                    ariaLabel={t('styleguide.choices.tabs')}
                    className="md:col-span-2"
                    tabs={[
                        {
                            label: t('styleguide.choices.tab_overview'),
                            content: (
                                <p className="text-body text-muted">
                                    {t('styleguide.choices.tab_overview_body')}
                                </p>
                            ),
                        },
                        {
                            label: t('styleguide.choices.tab_activity'),
                            content: (
                                <p className="text-body text-muted">
                                    {t('styleguide.choices.tab_activity_body')}
                                </p>
                            ),
                        },
                        {
                            label: t('styleguide.forms.state_disabled'),
                            disabled: true,
                            content: null,
                        },
                    ]}
                />
            </Group>
        </StyleguideSection>
    );
}
