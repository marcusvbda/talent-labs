import { Tab, TabGroup, TabList, TabPanel, TabPanels } from '@headlessui/react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

export function Tabs({
    tabs,
    selectedIndex,
    onChange,
    ariaLabel,
    className,
}: {
    tabs: {
        label: string;
        adornment?: ReactNode;
        disabled?: boolean;
        content: ReactNode;
    }[];
    selectedIndex?: number;
    onChange?: (index: number) => void;
    ariaLabel: string;
    className?: string;
}) {
    return (
        <TabGroup
            selectedIndex={selectedIndex}
            onChange={onChange}
            className={cn('flex min-w-0 flex-col gap-4', className)}
        >
            <TabList
                aria-label={ariaLabel}
                className="flex max-w-full flex-wrap gap-2 overflow-x-auto p-1"
            >
                {tabs.map((tab) => (
                    <Tab
                        key={tab.label}
                        disabled={tab.disabled}
                        className="inline-flex h-control-xs shrink-0 cursor-pointer items-center gap-2 rounded-full bg-tile px-6 text-label-sm whitespace-nowrap text-muted transition-colors hover:text-ink focus-visible:focus-ring data-disabled:cursor-not-allowed data-disabled:opacity-50 data-selected:bg-ink data-selected:text-white"
                    >
                        {tab.adornment}
                        {tab.label}
                    </Tab>
                ))}
            </TabList>
            <TabPanels>
                {tabs.map((tab) => (
                    <TabPanel
                        key={tab.label}
                        className="rounded-tile focus-visible:focus-ring"
                    >
                        {tab.content}
                    </TabPanel>
                ))}
            </TabPanels>
        </TabGroup>
    );
}
