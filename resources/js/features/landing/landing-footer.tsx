import { usePage } from "@inertiajs/react";
import { useT } from "@/i18n/i18n-provider";
import type { SharedProps } from "@/types/shared";
import type { LandingProps } from "./types";

const LINK =
    "text-label-sm text-muted transition-colors hover:text-ink focus-visible:focus-ring";

export function LandingFooter({
    contactEmail,
    legal,
}: Pick<LandingProps, "contactEmail" | "legal">) {
    const { t } = useT();
    const { app } = usePage<SharedProps>().props;

    const links = [
        { key: "privacy", href: legal.privacy },
        { key: "terms", href: legal.terms },
        { key: "optout", href: legal.optOut },
    ].filter((link) => link.href !== null);

    return (
        <footer className="mx-auto flex w-full max-w-shell flex-col gap-3 px-4 py-8 md:flex-row md:items-center md:justify-between md:px-8 desk:px-shell-x">
            <p className="text-label-sm text-muted">&copy; {app.brand.name}</p>
            <ul className="flex flex-wrap items-center gap-x-5 gap-y-2">
                {contactEmail !== null ? (
                    <li>
                        <a href={`mailto:${contactEmail}`} className={LINK}>
                            {t("landing.footer.contact")}
                        </a>
                    </li>
                ) : null}
                {links.map((link) => (
                    <li key={link.key}>
                        <a href={link.href as string} className={LINK}>
                            {t(`landing.footer.${link.key}`)}
                        </a>
                    </li>
                ))}
            </ul>
        </footer>
    );
}
