> Status: ARCHIVED (implemented). Historical record only. The code is the source of truth. DO NOT use this document as a reference to implement or change code.

# landing-page-content — landing page content changes

## Part 0 — Global rules (apply to every item below)

1. **Both languages.** Every piece of landing page content introduced or
   changed by an item must be resolved for **both** supported languages
   (English and Portuguese). No item is done with copy in only one language.
2. **Clean up legacy dictionary entries.** Whenever an item replaces,
   renames or removes landing content, the dictionary indexes (translation
   keys) it used to rely on and that are no longer referenced anywhere must
   be removed from **every** language dictionary. No orphaned keys, no
   leftover unused copy.

## Part 1 — Items

_Items are added below, one at a time._

### Item 1 — Hero headline

Screen: landing hero `<h1>` (`resources/js/features/landing/hero.tsx`).

Replace the headline copy:

| Language | Current                                          | New                                                        |
| -------- | ------------------------------------------------ | ---------------------------------------------------------- |
| EN       | "Applications sent by you, without opening 40 tabs." | "Applications sent automatically, without opening thousands of tabs." |
| PT       | "Candidaturas enviadas por você, sem abrir 40 abas." | "Candidaturas enviadas automaticamente, sem abrir milhares de abas." |

Notes:

- The headline is currently split into three keys
  (`landing.hero.title.a`, `.em`, `.b`) so that "you" / "você" renders in the
  accent color. The new copy has no "you", so the keys that no longer match
  the new structure must be reworked in both `lang/en.json` and
  `lang/pt.json` and the removed ones deleted (Part 0, rule 2).
- Which word (if any) keeps the accent color is **open — to be confirmed by
  the owner** before planning.

### Item 2 — Hero demo section is confusing

Screen: the product demo shown below the hero headline
(`resources/js/features/landing/hero-demo.tsx`): the orange "today's
sending" card, the dark "live sending" card, the "used today" tile and the
"recent activity" card.

**Problem (owner feedback):** the demo reads as ambiguous. Visitors can't
tell that it is part of the hero (an illustration of the product), and may
assume it is a broken or non-working feature.

**Goal:** improve the UX so it is immediately clear that this block is a
product preview that belongs to the hero, not a live or interactive part of
the page.

Notes:

- The exact solution (framing, labeling, layout, copy) is **open — to be
  defined by the owner** before planning. Do not decide it here.
- The demo currently reuses the `dashboard.*` translation keys (e.g.
  `dashboard.hero.*`, `dashboard.live.*`, `dashboard.stat.*`,
  `dashboard.activity.*`). Any new copy follows Part 0 (both languages,
  legacy keys cleaned up).

### Item 3 — Hero lead paragraph

Screen: landing hero paragraph under the headline
(`resources/js/features/landing/hero.tsx`, key `landing.hero.lead`).

Replace the copy:

| Language | Current | New |
| -------- | ------- | --- |
| EN | "We find development jobs, filter them by what you look for and send your application from your own Gmail, one company at a time, in the job's language." | "Optimize the process! We find tech jobs, filter them by what you look for and send your application from your own email." |
| PT | "Encontramos vagas de desenvolvimento, filtramos pelo que você procura e enviamos a candidatura do seu próprio Gmail, uma empresa por vez, no idioma da vaga." | "Otimize o processo! Encontramos vagas em tech, filtramos pelo que você procura e enviamos a candidatura do seu próprio Email." |

Notes:

- PT is the owner's text; the EN text is a direct translation of it.
- The new copy drops "Gmail" (now "email"), "one company at a time" and
  "in the job's language", and says "tech" instead of "development" jobs.
- The key stays the same (`landing.hero.lead`), so no key cleanup is
  expected beyond Part 0 if anything else becomes unused.

### Item 4 — Hero trust checklist

Screen: the three-check list under the hero buttons
(`resources/js/features/landing/hero.tsx`, keys `landing.hero.trust.1..3`).

Replace the copy:

| Key | Language | Current | New |
| --- | -------- | ------- | --- |
| `landing.hero.trust.1` | EN | "Sent from your Gmail" | "Sent from your email" |
| `landing.hero.trust.1` | PT | "Sai do seu Gmail" | "Enviado pelo seu email" |
| `landing.hero.trust.2` | EN | "One email per company" | "One to one" |
| `landing.hero.trust.2` | PT | "1 e-mail por empresa" | "1 a 1" |
| `landing.hero.trust.3` | EN | "You stay in control" | unchanged |
| `landing.hero.trust.3` | PT | "Você no controle" | unchanged |

Notes:

- PT is the owner's text; EN is a direct translation of it.
- The separate "Our guarantees" strip (`landing.guarantee.*`) is covered by
  Item 5.

### Item 5 — Guarantees strip, first card title

Screen: "Our guarantees" strip (`resources/js/features/landing/guarantees-strip.tsx`,
key `landing.guarantee.1.title`).

Replace the copy:

| Language | Current | New |
| -------- | ------- | --- |
| EN | "Sent from your Gmail" | "Sent from your email" |
| PT | "Sai do seu Gmail" | "Sai do seu email" |

Notes:

- Only the title changes. The description (`landing.guarantee.1.desc`,
  "You appear as yourself" / "Você aparece como você") and the other three
  cards stay as they are.
- PT is the owner's text; EN is a direct translation of it.

### Item 6 — Matching section text

Screen: landing "Preferences" section paragraph
(`resources/js/features/landing/feature-matching.tsx`, key
`landing.matching.text`).

Replace the copy:

| Language | Current | New |
| -------- | ------- | --- |
| EN | "Set role, seniority, stack and where you want to work. Any value inside a field matches; across fields, all must match. You see how many jobs fit right away." | "Set role, seniority, stack and where you want to work. Filter however you prefer; you instantly see the jobs that fit and send customized applications in bulk." |
| PT | "Defina cargo, senioridade, stack e onde quer trabalhar. Dentro de cada campo vale qualquer valor; entre os campos, todos precisam bater. Você vê na hora quantas vagas encaixam." | "Defina cargo, senioridade, stack e onde quer trabalhar. Filtre como preferir; você vê na hora as vagas que se encaixam e dispare aplicações customizadas em massa." |

Notes:

- The owner's PT text read "Você na hora as vagas que se encaixam" (verb
  missing); it is written here as "você vê na hora as vagas que se
  encaixam". **To be confirmed by the owner.**
- The new copy drops the explanation of the matching rule (any value
  inside a field, all fields must match) and the "how many jobs fit"
  count; it now mentions the jobs themselves and bulk sending.
- EN is a direct translation of the PT text.

### Item 7 — Caption under the matching filter chips

Screen: landing "Preferences" section, the row of filter chips
(`resources/js/features/landing/feature-matching.tsx`, keys
`landing.matching.chip.1..4`: Backend, Senior, Laravel, Remote or Lisbon).

Add a small caption **directly below the chips**, saying they are an
example of a tag filter. New key (suggested name:
`landing.matching.chips.caption`):

| Language | New |
| -------- | --- |
| EN | "Example of tag filter" |
| PT | "Exemplo de tag filter" |

Notes:

- The chips themselves do not change.
- PT is the owner's literal text ("tag filter" kept in English). **To be
  confirmed by the owner:** whether PT should be translated instead (e.g.
  "Exemplo de filtro por tags").
- Caption styling (size, color) is not specified; it should read as
  secondary text, consistent with the section.

### Item 8 — Languages section text

Screen: landing languages section paragraph
(`resources/js/features/landing/feature-languages.tsx`, key
`landing.languages.text`).

Replace the copy:

| Language | Current | New |
| -------- | ------- | --- |
| EN | "CV, subject and cover letter in English and Portuguese. A Portuguese job gets your Portuguese profile, and you see the email ready before anything is sent." | "CV, subject, cover letter and links in English and Portuguese. Our agent automatically decides the best way to make the application." |
| PT | "Currículo, assunto e carta de apresentação em inglês e português. A vaga em português recebe o seu perfil em português, e você vê o e-mail pronto antes de qualquer envio." | "Currículo, assunto, carta de apresentação e links em inglês e português. Nosso agente define automaticamente a melhor forma de fazer a aplicação." |

Notes:

- PT is the owner's text (with "automáticamente" spelled
  "automaticamente"); EN is a direct translation of it.
- New in the copy: "links" is added to the per-language items, and the
  "Portuguese job gets your Portuguese profile" and "you see the email
  ready before anything is sent" sentences are replaced by the "agent"
  sentence.
- The "agent" wording is the owner's. **No AI** exists in the product
  today (see `application-languages` spec); this item changes landing copy
  only and must not be read as a feature request.

### Item 9 — Live band title

Screen: landing live band heading
(`resources/js/features/landing/live-band.tsx`, key `landing.live.title`).

Replace the copy:

| Language | Current | New |
| -------- | ------- | --- |
| EN | "You see every step happen." | "Follow it step by step." |
| PT | "Você vê cada passo acontecer." | "Acompanhe passo-a-passo." |

Notes:

- PT is the owner's text; EN is a direct translation of it.
- The trailing period is kept to match the other section titles
  (e.g. "Só as vagas que combinam."). **To be confirmed by the owner.**
- Only the title changes; the live band eyebrow and text stay as they are.

### Item 10 — Live band text

Screen: landing live band paragraph
(`resources/js/features/landing/live-band.tsx`, key `landing.live.text`).

Replace the copy:

| Language | Current | New |
| -------- | ------- | --- |
| EN | "Every application shows the real stage it's in. If something fails, you see where and why, no black boxes. No refresh needed, everything updates on its own." | "Follow your sends in real time, or just dispatch and relax: we do everything for you, no worrying needed." |
| PT | "Cada candidatura mostra a etapa real em que está. Se algo falha, você vê onde e por quê, sem caixa-preta. Sem F5, tudo atualiza sozinho." | "Acompanhe em tempo real seus envios ou apenas dispare e relaxe, fazemos tudo para você sem precisar se preocupar." |

Notes:

- PT is the owner's text, with "dispache" corrected to "dispare" (same
  verb as in Item 6). **To be confirmed by the owner.** EN is a direct
  translation of it.
- The new copy drops the claims about the real stage per application,
  seeing where and why something fails, and updating without a refresh.

### Item 11 — Replace "Gmail" with "email" across the landing page

Rule: everywhere the landing page names **Gmail** specifically, say
**email** instead (PT: "email"). Reason: Gmail is the only provider today
because no other providers exist yet, but more will come, so the landing
must not be tied to one.

Landing copy that still says "Gmail" after Items 3, 4 and 5 (which already
cover `landing.hero.lead`, `landing.hero.trust.1` and
`landing.guarantee.1.title`):

| Key | Language | Current | New |
| --- | -------- | ------- | --- |
| `landing.meta.title` | EN | ":brand — Job applications sent from your own Gmail" | ":brand — Job applications sent from your own email" |
| `landing.meta.title` | PT | ":brand — Candidaturas enviadas pelo seu próprio Gmail" | ":brand — Candidaturas enviadas pelo seu próprio email" |
| `landing.meta.description` | EN | "... sends each application from your own Gmail, one company at a time, in the language of the job." | "... sends each application from your own email, one company at a time, in the language of the job." |
| `landing.meta.description` | PT | "... envia cada candidatura pelo seu próprio Gmail, uma empresa por vez, no idioma da vaga." | "... envia cada candidatura pelo seu próprio email, uma empresa por vez, no idioma da vaga." |
| `landing.plans.feature.gmail` | EN | "Sent from your own Gmail" | "Sent from your own email" |
| `landing.plans.feature.gmail` | PT | "Sai do seu próprio Gmail" | "Sai do seu próprio email" |
| `landing.gmail.note` | EN | "We only use your Gmail permission to send ..." | "We only use your email permission to send the applications you choose or allow. We never read your inbox." |
| `landing.gmail.note` | PT | "Usamos a permissão do seu Gmail apenas para enviar ..." | "Usamos a permissão do seu email apenas para enviar as candidaturas que você escolhe ou autoriza. Nunca lemos a sua caixa de entrada." |

Notes:

- Only the visible **copy** changes. `landing.meta.*` is page title and meta
  description (SEO / link previews), not visible body text.
- The keys `landing.plans.feature.gmail` and `landing.gmail.note` (and the
  `gmail` feature id in `landing-plan-card.tsx`) carry the old name. They
  should be renamed to `email` in both dictionaries and the code, with the
  old keys removed (Part 0, rule 2).
- **Out of scope:** the authenticated app (onboarding, account, dashboard,
  settings, `sending.*`, `plans.*`, etc.) keeps saying Gmail. **Heads-up:**
  the hero demo (Item 2) renders the live-sending sub-step
  `connecting_gmail`, whose label comes from the shared key
  `sending.sub.connecting_gmail` ("Conectando ao Gmail" / "Connecting to
  Gmail"). That key is also used by the app, so it is **not** changed here;
  **to be confirmed by the owner** whether the demo should show a
  provider-neutral label instead (which would need a landing-only key).
