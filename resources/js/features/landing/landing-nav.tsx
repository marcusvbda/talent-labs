import { usePage } from "@inertiajs/react";
import { Menu } from "lucide-react";
import { useState } from "react";
import { LanguageSwitcher } from "@/components/patterns/language-switcher";
import { Logo } from "@/components/patterns/logo";
import { NavPills } from "@/components/patterns/nav-pills";
import type { NavPillItem } from "@/components/patterns/nav-pills";
import { Button } from "@/components/ui/button";
import { IconButton } from "@/components/ui/icon-button";
import { Sheet } from "@/components/ui/sheet";
import { useT } from "@/i18n/i18n-provider";
import { dashboard, home, login } from "@/routes";
import type { SharedProps } from "@/types/shared";
import { BetaCta } from "./beta-cta";
import { anchorHash, scrollToHash } from "./scroll-to-hash";

const SECTIONS = ["product", "how", "languages", "plans", "faq"] as const;

const ITEM =
    "flex h-control-sm w-full items-center gap-3 rounded-full px-5 text-label text-ink transition-colors hover:bg-tile focus-visible:focus-ring";

export function LandingNav({ betaClosed }: { betaClosed: boolean }) {
    const { t } = useT();
    const { auth } = usePage<SharedProps>().props;
    const [open, setOpen] = useState(false);

    const items: NavPillItem[] = SECTIONS.map((section) => ({
        key: section,
        label: t(`landing.nav.${section}`),
        href: `#${section}`,
        anchor: true,
    }));

    const account =
        auth.user === null
            ? { href: login().url, label: t("landing.nav.signin") }
            : { href: dashboard().url, label: t("landing.nav.dashboard") };

    return (
        <header className="sticky top-0 z-40 bg-shell">
            <div className="mx-auto flex w-full  max-w-shell items-center justify-between gap-3 px-4 py-3 md:px-8 desk:px-shell-x">
                <Logo href={home().url} />
                <div
                    className="hidden lg:block"
                    onClick={(event) => {
                        const hash = anchorHash(event);

                        if (hash !== null && scrollToHash(hash)) {
                            event.preventDefault();
                        }
                    }}
                >
                    <NavPills items={items} />
                </div>
                <div className="flex items-center gap-2">
                    <LanguageSwitcher />
                    <div className="hidden lg:block">
                        <Button variant="secondary-tile" href={account.href}>
                            {account.label}
                        </Button>
                    </div>
                    <div className="hidden sm:block">
                        <BetaCta betaClosed={betaClosed} />
                    </div>
                    <div className="lg:hidden">
                        <IconButton
                            icon={Menu}
                            label={t("topbar.menu")}
                            onClick={() => setOpen(true)}
                        />
                    </div>
                </div>
            </div>
            <Sheet
                open={open}
                onClose={() => setOpen(false)}
                side="right"
                title={t("topbar.menu_title")}
            >
                <nav
                    aria-label={t("topbar.menu_title")}
                    onClick={(event) => {
                        const hash = anchorHash(event);

                        if (hash === null) {
                            return;
                        }

                        if (document.getElementById(hash.slice(1))) {
                            event.preventDefault();
                            setOpen(false);
                            requestAnimationFrame(() => scrollToHash(hash));
                        } else {
                            setOpen(false);
                        }
                    }}
                >
                    <ul className="flex flex-col gap-1">
                        {items.map((item) => (
                            <li key={item.key}>
                                <a href={item.href} className={ITEM}>
                                    {item.label}
                                </a>
                            </li>
                        ))}
                        <li className="lg:hidden">
                            <a href={account.href} className={ITEM}>
                                {account.label}
                            </a>
                        </li>
                    </ul>
                </nav>
            </Sheet>
        </header>
    );
}
