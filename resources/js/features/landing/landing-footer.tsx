import { usePage } from "@inertiajs/react";
import { Logo } from "@/components/patterns/logo";
import { useT } from "@/i18n/i18n-provider";
import { dashboard, home, login } from "@/routes";
import type { SharedProps } from "@/types/shared";
import { BetaCta } from "./beta-cta";
import { anchorHash, scrollToHash } from "./scroll-to-hash";
import type { LandingProps } from "./types";

const SECTIONS = ["product", "how", "languages", "plans", "faq"] as const;

const LINK =
    "text-label-sm text-muted transition-colors hover:text-ink focus-visible:focus-ring";

function Column({
    title,
    children,
}: {
    title: string;
    children: React.ReactNode;
}) {
    return (
        <div className="flex flex-col gap-3">
            <h3 className="text-label font-medium text-ink">{title}</h3>
            <ul className="flex flex-col gap-2">{children}</ul>
        </div>
    );
}

export function LandingFooter({
    betaClosed,
    contactEmail,
    legal,
}: Pick<LandingProps, "betaClosed" | "contactEmail" | "legal">) {
    const { t } = useT();
    const { app, auth } = usePage<SharedProps>().props;

    const account =
        auth.user === null
            ? { href: login().url, label: t("landing.nav.signin") }
            : { href: dashboard().url, label: t("landing.nav.dashboard") };

    const links = [
        { key: "privacy", href: legal.privacy },
        { key: "terms", href: legal.terms },
        { key: "optout", href: legal.optOut },
    ].filter((link) => link.href !== null);

    return (
        <footer className="mx-auto w-full max-w-shell px-4 pb-6 md:px-8 desk:px-shell-x">
            <div className="rounded-card bg-tile px-6 py-10 md:px-10 md:py-12">
                <div className="grid gap-10 md:grid-cols-[1.4fr_repeat(3,1fr)]">
                    <div className="flex max-w-sm flex-col items-start gap-5">
                        <Logo href={home().url} />
                        <p className="text-label-sm text-muted">
                            {t("landing.footer.tagline")}
                        </p>
                        <BetaCta betaClosed={betaClosed} />
                    </div>

                    <nav
                        aria-label={t("landing.footer.product")}
                        onClick={(event) => {
                            const hash = anchorHash(event);

                            if (hash !== null && scrollToHash(hash)) {
                                event.preventDefault();
                            }
                        }}
                    >
                        <Column title={t("landing.footer.product")}>
                            {SECTIONS.map((section) => (
                                <li key={section}>
                                    <a href={`#${section}`} className={LINK}>
                                        {t(`landing.nav.${section}`)}
                                    </a>
                                </li>
                            ))}
                        </Column>
                    </nav>

                    <Column title={t("landing.footer.account")}>
                        <li>
                            <a href={account.href} className={LINK}>
                                {account.label}
                            </a>
                        </li>
                        {contactEmail !== null ? (
                            <li>
                                <a
                                    href={`mailto:${contactEmail}`}
                                    className={LINK}
                                >
                                    {t("landing.footer.contact")}
                                </a>
                            </li>
                        ) : null}
                    </Column>

                    {links.length > 0 ? (
                        <Column title={t("landing.footer.legal")}>
                            {links.map((link) => (
                                <li key={link.key}>
                                    <a
                                        href={link.href as string}
                                        className={LINK}
                                    >
                                        {t(`landing.footer.${link.key}`)}
                                    </a>
                                </li>
                            ))}
                        </Column>
                    ) : null}
                </div>

                <div className="mt-10 flex flex-col gap-2 border-t border-hairline pt-6 md:flex-row items-center justify-center">
                    <p className="text-label-sm text-muted">
                        &copy; {new Date().getFullYear()} {app.brand.name}.{" "}
                        {t("landing.footer.rights")}
                    </p>
                </div>
            </div>
        </footer>
    );
}
