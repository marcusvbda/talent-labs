import { Download, Pencil, Share2, Trash2 } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { Menu } from '@/components/ui/menu';
import { Modal } from '@/components/ui/modal';
import { Popover } from '@/components/ui/popover';
import { Sheet } from '@/components/ui/sheet';
import { Tooltip } from '@/components/ui/tooltip';
import { useT } from '@/i18n/i18n-provider';
import { StyleguideSection } from './styleguide-section';

const Group = ({ title, children }: { title: string; children: ReactNode }) => (
    <div className="flex flex-col gap-3">
        <h3 className="text-label-sm text-muted">{title}</h3>
        <div className="flex flex-wrap items-center gap-4 rounded-card-sm bg-card p-card-sm">
            {children}
        </div>
    </div>
);

export function OverlaysSection() {
    const { t } = useT();
    const [modal, setModal] = useState(false);
    const [right, setRight] = useState(false);
    const [bottom, setBottom] = useState(false);

    return (
        <StyleguideSection id="overlays" title={t('styleguide.overlays.title')}>
            <Group title={t('styleguide.overlays.modal')}>
                <Button variant="secondary-tile" onClick={() => setModal(true)}>
                    {t('styleguide.overlays.open_modal')}
                </Button>
                <Modal
                    open={modal}
                    onClose={() => setModal(false)}
                    title={t('styleguide.overlays.modal_title')}
                    description={t('styleguide.overlays.modal_description')}
                    footer={
                        <>
                            <Button
                                variant="ghost"
                                onClick={() => setModal(false)}
                            >
                                {t('styleguide.overlays.cancel')}
                            </Button>
                            <Button onClick={() => setModal(false)}>
                                {t('styleguide.overlays.confirm')}
                            </Button>
                        </>
                    }
                >
                    <p className="text-body text-muted">
                        {t('styleguide.overlays.body')}
                    </p>
                </Modal>
            </Group>

            <Group title={t('styleguide.overlays.sheet')}>
                <Button variant="secondary-tile" onClick={() => setRight(true)}>
                    {t('styleguide.overlays.open_sheet_right')}
                </Button>
                <Button
                    variant="secondary-tile"
                    onClick={() => setBottom(true)}
                >
                    {t('styleguide.overlays.open_sheet_bottom')}
                </Button>
                <Sheet
                    side="right"
                    open={right}
                    onClose={() => setRight(false)}
                    title={t('styleguide.overlays.sheet_title')}
                >
                    <p className="text-body text-muted">
                        {t('styleguide.overlays.body')}
                    </p>
                </Sheet>
                <Sheet
                    side="bottom"
                    open={bottom}
                    onClose={() => setBottom(false)}
                    title={t('styleguide.overlays.sheet_title')}
                >
                    <p className="text-body text-muted">
                        {t('styleguide.overlays.body')}
                    </p>
                </Sheet>
            </Group>

            <Group title={t('styleguide.overlays.popover')}>
                <Popover
                    trigger={
                        <Button variant="secondary-tile">
                            {t('styleguide.overlays.open_popover')}
                        </Button>
                    }
                >
                    <p className="text-body">
                        {t('styleguide.overlays.popover_body')}
                    </p>
                </Popover>
            </Group>

            <Group title={t('styleguide.overlays.menu')}>
                <Menu
                    trigger={
                        <Button variant="secondary-tile">
                            {t('styleguide.overlays.open_menu')}
                        </Button>
                    }
                    items={[
                        {
                            label: t('styleguide.overlays.menu_edit'),
                            icon: Pencil,
                            onSelect: () => {},
                        },
                        {
                            label: t('styleguide.overlays.menu_share'),
                            icon: Share2,
                            href: '#',
                        },
                        {
                            label: t('styleguide.overlays.menu_export'),
                            icon: Download,
                            onSelect: () => {},
                        },
                        {
                            label: t('styleguide.overlays.menu_delete'),
                            icon: Trash2,
                            danger: true,
                            onSelect: () => {},
                        },
                    ]}
                />
            </Group>

            <Group title={t('styleguide.overlays.tooltip')}>
                <Tooltip content={t('styleguide.overlays.tooltip_body')}>
                    <Button variant="secondary-tile">
                        {t('styleguide.overlays.tooltip_trigger')}
                    </Button>
                </Tooltip>
            </Group>
        </StyleguideSection>
    );
}
