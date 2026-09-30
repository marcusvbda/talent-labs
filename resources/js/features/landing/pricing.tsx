import { useState } from "react";
import type { ReactNode } from "react";
import { Segmented } from "@/components/ui/segmented";
import { useT } from "@/i18n/i18n-provider";
import { cn } from "@/lib/utils";
import type { RegionKey } from "@/types/contracts";
import { LandingPlanCard } from "./landing-plan-card";
import type { LandingPlan } from "./types";
import { useReveal } from "./use-reveal";

const REGIONS: RegionKey[] = ["br", "eu", "row"];

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

export function Pricing({
    plans,
    defaultRegion,
    betaClosed,
}: {
    plans: LandingPlan[];
    defaultRegion: RegionKey;
    betaClosed: boolean;
}) {
    const { t } = useT();
    const [region, setRegion] = useState<RegionKey>(defaultRegion);

    return (
        <section aria-labelledby="plans-title">
            <div className="flex flex-col items-start gap-5">
                <Reveal index={0}>
                    <p className="text-label text-accent">
                        {t("landing.plans.eyebrow")}
                    </p>
                </Reveal>
                <Reveal index={1}>
                    <h2
                        id="plans-title"
                        className="text-landing-section-sm text-ink md:text-landing-section"
                    >
                        {t("landing.plans.title")}
                    </h2>
                </Reveal>
                <Reveal index={2} className="max-md:w-full">
                    <Segmented<RegionKey>
                        ariaLabel={t("landing.plans.region.label")}
                        value={region}
                        onChange={setRegion}
                        options={REGIONS.map((key) => ({
                            value: key,
                            label: t(`landing.plans.region.${key}`),
                        }))}
                        className="max-md:w-full"
                    />
                </Reveal>
            </div>
            <div className="mt-8 grid gap-gap lg:grid-cols-3 lg:gap-4 xl:gap-gap">
                {plans.map((plan) => (
                    <LandingPlanCard
                        key={plan.key}
                        plan={plan}
                        region={region}
                        betaClosed={betaClosed}
                    />
                ))}
            </div>
        </section>
    );
}
