import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { FileDrop } from '@/components/ui/file-drop';
import { Toast, toast } from '@/components/ui/toast';
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

const Labeled = ({
    label,
    children,
}: {
    label: string;
    children: ReactNode;
}) => (
    <div className="flex flex-col gap-2">
        <span className="text-label-sm text-muted">{label}</span>
        {children}
    </div>
);

export function FeedbackSection() {
    const { t } = useT();
    const noop = () => {};

    return (
        <StyleguideSection id="feedback" title={t('styleguide.feedback.title')}>
            <Group title={t('styleguide.feedback.file_drop')}>
                <Labeled label={t('styleguide.feedback.state_idle')}>
                    <FileDrop
                        accept="application/pdf"
                        maxSizeMb={5}
                        onFile={noop}
                    />
                </Labeled>
                <Labeled label={t('styleguide.feedback.state_interactive')}>
                    <FileDrop
                        accept="application/pdf"
                        maxSizeMb={1}
                        onFile={(file) => toast.info(file.name)}
                    />
                </Labeled>
                <Labeled label={t('styleguide.feedback.state_uploading')}>
                    <FileDrop
                        accept="application/pdf"
                        maxSizeMb={5}
                        progress={60}
                        onFile={noop}
                    />
                </Labeled>
                <Labeled label={t('styleguide.forms.state_error')}>
                    <FileDrop
                        accept="application/pdf"
                        maxSizeMb={5}
                        error={t('forms.file.wrong_type')}
                        onFile={noop}
                    />
                </Labeled>
            </Group>

            <Group title={t('styleguide.feedback.toast')}>
                <Labeled label={t('styleguide.feedback.toast_static')}>
                    <div className="flex flex-col gap-3">
                        <Toast
                            tone="success"
                            message={t('styleguide.feedback.msg_success')}
                            dismissLabel={t('common.dismiss')}
                            onDismiss={noop}
                        />
                        <Toast
                            tone="error"
                            message={t('styleguide.feedback.msg_error')}
                            dismissLabel={t('common.dismiss')}
                            onDismiss={noop}
                        />
                        <Toast
                            tone="info"
                            message={t('styleguide.feedback.msg_info')}
                            dismissLabel={t('common.dismiss')}
                            onDismiss={noop}
                        />
                    </div>
                </Labeled>
                <Labeled label={t('styleguide.feedback.toast_fire')}>
                    <div className="flex flex-wrap gap-3">
                        <Button
                            onClick={() =>
                                toast.success(
                                    t('styleguide.feedback.msg_success'),
                                )
                            }
                        >
                            {t('styleguide.feedback.fire_success')}
                        </Button>
                        <Button
                            onClick={() =>
                                toast.error(t('styleguide.feedback.msg_error'))
                            }
                        >
                            {t('styleguide.feedback.fire_error')}
                        </Button>
                        <Button
                            onClick={() =>
                                toast.info(t('styleguide.feedback.msg_info'))
                            }
                        >
                            {t('styleguide.feedback.fire_info')}
                        </Button>
                    </div>
                </Labeled>
            </Group>
        </StyleguideSection>
    );
}
