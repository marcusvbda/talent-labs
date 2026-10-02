import { Plus, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { IconButton } from '@/components/ui/icon-button';
import { Input } from '@/components/ui/input';
import { useT } from '@/i18n/i18n-provider';
import type { ProfileLink } from '@/types/contracts';

const MAX_ROWS = 10;
const MAX_LABEL = 60;
const MAX_URL = 2048;
const URL_PATTERN = /^https?:\/\/\S+$/i;
const FORBIDDEN = /\{\{|\}\}/;

export const linkRowState = (
    row: ProfileLink,
): 'blank' | 'valid' | 'invalid' => {
    const label = row.label.trim();
    const url = row.url.trim();

    if (label === '' && url === '') {
        return 'blank';
    }

    const valid =
        label !== '' &&
        url !== '' &&
        label.length <= MAX_LABEL &&
        url.length <= MAX_URL &&
        URL_PATTERN.test(url) &&
        !FORBIDDEN.test(label) &&
        !FORBIDDEN.test(url);

    return valid ? 'valid' : 'invalid';
};

export const LinksField = ({
    links,
    onChange,
}: {
    links: ProfileLink[];
    onChange: (next: ProfileLink[]) => void;
}) => {
    const { t } = useT();
    const hasInvalid = links.some((row) => linkRowState(row) === 'invalid');

    const updateRow = (index: number, next: Partial<ProfileLink>) => {
        onChange(
            links.map((row, i) => (i === index ? { ...row, ...next } : row)),
        );
    };

    const removeRow = (index: number) => {
        onChange(links.filter((_, i) => i !== index));
    };

    const addRow = () => {
        onChange([...links, { label: '', url: '' }]);
    };

    return (
        <div className="flex flex-col gap-3">
            <label className="flex items-baseline justify-between gap-2 text-label-sm text-ink">
                {t('profiles.links.title')}
                <span className="text-chip text-muted">
                    {t('forms.optional')}
                </span>
            </label>
            <p className="text-chip text-muted">{t('profiles.links.help')}</p>
            <div className="flex flex-col gap-3">
                {links.map((row, index) => {
                    const state = linkRowState(row);
                    const label = row.label.trim();
                    const url = row.url.trim();
                    const labelInvalid =
                        state === 'invalid' &&
                        (label === '' ||
                            label.length > MAX_LABEL ||
                            FORBIDDEN.test(label));
                    const urlInvalid =
                        state === 'invalid' &&
                        (url === '' ||
                            url.length > MAX_URL ||
                            !URL_PATTERN.test(url) ||
                            FORBIDDEN.test(url));

                    return (
                        <div
                            key={index}
                            className="flex flex-col gap-2 sm:flex-row sm:items-center"
                        >
                            <Input
                                aria-label={t('profiles.links.label')}
                                placeholder={t(
                                    'profiles.links.label_placeholder',
                                )}
                                maxLength={MAX_LABEL}
                                value={row.label}
                                aria-invalid={labelInvalid || undefined}
                                onChange={(event) =>
                                    updateRow(index, {
                                        label: event.target.value,
                                    })
                                }
                                className="sm:flex-1"
                            />
                            <Input
                                type="url"
                                inputMode="url"
                                aria-label={t('profiles.links.url')}
                                placeholder="https://"
                                maxLength={MAX_URL}
                                value={row.url}
                                aria-invalid={urlInvalid || undefined}
                                onChange={(event) =>
                                    updateRow(index, {
                                        url: event.target.value,
                                    })
                                }
                                className="sm:flex-1"
                            />
                            <IconButton
                                icon={X}
                                label={t('profiles.links.remove')}
                                onClick={() => removeRow(index)}
                            />
                        </div>
                    );
                })}
            </div>
            {hasInvalid && (
                <p role="alert" className="text-chip text-danger-text">
                    {t('profiles.links.invalid')}
                </p>
            )}
            <Button
                variant="secondary-tile"
                iconLeft={Plus}
                disabled={links.length >= MAX_ROWS}
                onClick={addRow}
            >
                {t('profiles.links.add')}
            </Button>
        </div>
    );
};
