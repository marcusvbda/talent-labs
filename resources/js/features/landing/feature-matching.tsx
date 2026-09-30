import type { ReactNode } from "react";
import { JobRow } from "@/components/patterns/job-row";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { useT } from "@/i18n/i18n-provider";
import { cn } from "@/lib/utils";
import { DEMO_JOBS, DEMO_MATCH_STACK } from "./demo-data";
import { useReveal } from "./use-reveal";

const CHIPS = [1, 2, 3, 4] as const;
const PANEL_JOBS = DEMO_JOBS.slice(0, 3);

const noop = () => {};

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

const Panel = () => {
    const { t } = useT();

    return (
        <div aria-hidden="true" inert>
            <Card tone="light">
                <div className="mb-5 flex flex-wrap items-baseline justify-between gap-2">
                    <h3 className="text-row-title text-ink">
                        {t("landing.matching.panel.title")}
                    </h3>
                    <span className="text-body text-muted">
                        {t("landing.matching.panel.count")}
                    </span>
                </div>
                <div className="flex flex-col gap-2.5">
                    {PANEL_JOBS.map((job, index) => (
                        <JobRow
                            key={job.id}
                            selected={index === 0}
                            onSelectedChange={noop}
                            company={job.company}
                            title={job.title}
                            meta={job.company}
                            stack={DEMO_MATCH_STACK[job.id] ?? []}
                            language={job.language}
                        />
                    ))}
                </div>
                <div className="mt-5 flex flex-wrap items-center justify-between gap-3">
                    <span className="text-body text-muted">
                        {t("landing.matching.panel.selected")}
                    </span>
                    <Button inert>{t("landing.matching.panel.send")}</Button>
                </div>
            </Card>
        </div>
    );
};

export function FeatureMatching() {
    const { t } = useT();

    return (
        <section aria-labelledby="product-title" className="py-12 md:py-16">
            <div className="grid grid-cols-1 items-center gap-gap xl:grid-cols-2">
                <div className="flex flex-col items-start gap-5 self-baseline">
                    <Reveal index={0}>
                        <p className="text-label text-accent">
                            {t("landing.matching.eyebrow")}
                        </p>
                    </Reveal>
                    <Reveal index={1}>
                        <h2
                            id="product-title"
                            className="text-landing-section-sm text-ink md:text-landing-section"
                        >
                            {t("landing.matching.title")}
                        </h2>
                    </Reveal>
                    <Reveal index={2}>
                        <p className="max-w-2xl text-landing-body text-muted">
                            {t("landing.matching.text")}
                        </p>
                    </Reveal>
                    <Reveal index={3}>
                        <ul className="flex flex-wrap gap-2.5">
                            {CHIPS.map((n) => (
                                <li
                                    key={n}
                                    className="rounded-full bg-card px-4 py-2.5 text-label-sm text-ink"
                                >
                                    {t(`landing.matching.chip.${n}`)}
                                </li>
                            ))}
                        </ul>
                    </Reveal>
                </div>
                <Reveal index={2}>
                    <Panel />
                </Reveal>
            </div>
        </section>
    );
}
