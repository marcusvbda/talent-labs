> Status: ARCHIVED (implemented). Historical record only. The code is the source of truth. DO NOT use this document as a reference to implement or change code.

# job-sources

## Daily batch and availability window

### Daily batch accumulates

Several collection runs on the same day (for example, started from the admin
at different times) add up to a single **daily batch**. A posting belongs to
the day of the run that first collected it.

Example: a run at 08:00 finds 500 postings and a run at 23:00 on the same day
finds 1,500 more. A client who filters for the day's postings and has not sent
anything yet today sees 2,000.

A posting seen again in a later run (same `source_id` + `external_id`) is not
duplicated and keeps the day of the run that first collected it.

### Availability window

Postings stay available to a client for a window of **5 calendar days**
(today plus the 4 previous days, in the app timezone, counted by the
`started_at` of the run that first collected each posting). The window size is
read from config (`config/talent.php`, overridable by env), not from the admin.

A client who is away does not lose postings while they are inside the window.

Example: the last 5 days each collected 1,000 postings and the client did not
use the system (did not send applications) for 10 days. The client sees the
postings of those 5 days as available to send, filtered by their preferences.
If all 5,000 match their criteria, all 5,000 are listed.

### What leaves the available list

Only postings the client has already sent an application for. Everything else
inside the window stays available.

## New job sources

Goal: add collection sources that surface postings with a **human contact**
(founder, hiring manager) or a **direct email**, so the applications sent
through the system can be strong rather than generic. Three sources are in
scope, in this priority order.

Exact endpoints, terms of use and attribution requirements are not verified
yet and are to be confirmed in the plan.

### 1. Hacker News "Ask HN: Who is hiring?"

- Reads the monthly thread through a public, unauthenticated API. No scraping
  and no login.
- Each top-level comment is one posting. Posters are often the founder or the
  hiring manager, and many include a direct email.
- Comments are free text. Email and links are extracted from the text. How
  title and company are extracted is to be defined in the plan.
- A direct email found in the comment is kept as a contact for the posting.

### 2. Y Combinator

- Covers the company directory and job listings.
- Keeps the founders' names when public, so a named contact can be built from
  the founder name and the company domain.
- Only publicly available data is collected. Content behind a login is out of
  scope.

### 3. Remote job boards with open API or RSS

- Himalayas, We Work Remotely and Working Nomads.
- Follow the same model as the existing aggregator sources: one source per
  board, original link kept and the board credited.
- These carry no human contact. They add company diversity.

### Out of scope

- X (Twitter), because scraping is too fragile and the official API is paid.
- Contact enrichment services (Hunter, Apollo) and any named-contact discovery
  beyond what the sources above already expose.
- Relocation and visa boards, funding-news leads and Reddit.

## Owner decisions (2026-10-02)

- **D1 — Posting day.** A posting's day is the `started_at` of the run that
  first collected it, in the app timezone (`config('app.timezone')`), not the
  client's timezone and not `first_seen_at`. The "today" filter, the
  "collected today" badge and the dashboard "new today" count use it.
- **D2 — What leaves the list.** The current rule stays: once the client has
  any application for a company (any status, failed included), every posting
  of that company leaves the client's pool. Applications are unique per
  client and company.
- **D3 — Window on top of the pool.** The availability window is an extra
  time limit on the existing pool rules (verified company, finished profile,
  recipient, target role family, language, preferences) and on the
  one-posting-per-company Jobs view. The counts in the examples above mean
  postings that are otherwise eligible.
- **D4 — HN emails.** Emails found in an HN comment are stored on the posting
  and shown in the admin only. They are not used as application recipients.
- **D5 — Y Combinator approach.** Read the public `/jobs` role pages and each
  posting's public company page, using the page data embedded in the HTML, at
  a low request rate. No login, no `/companies?` directory search (disallowed
  by robots.txt), no unofficial mirrors. The owner accepts the terms-of-use
  risk.
- **D6 — Founder contacts.** Keep founder names and titles (and the company
  website) on the posting. No email is guessed or verified from them.

## Acceptance criteria

- **AC1** Two runs on the same app-timezone day add up: the "today" view shows
  the postings first collected by either run.
- **AC2** A posting seen again in a later run is not duplicated and keeps its
  first run (and so its day).
- **AC3** The client pool only includes postings whose first run started
  within the last `talent.collection.window_days` days (default 5, env
  `COLLECTION_WINDOW_DAYS`), today included, in the app timezone.
- **AC4** A client away for N days or more still sees every eligible posting
  inside the window.
- **AC5** Applications remove postings from the pool per D2. Nothing
  time-related removes a posting inside the window.
- **AC6** The HN adapter extracts email addresses and links from each comment,
  and the emails are stored on the posting per D4.
- **AC7** A Y Combinator source (seeded, inactive by default) returns more
  than 0 postings live and keeps the founders' names and titles plus the
  company website when public. Public data only, credited "via Y Combinator"
  with the original link.
- **AC8** The Himalayas, We Work Remotely and Working Nomads sources each
  return more than 0 postings live and credit the board.
- **AC9** No X/Twitter, enrichment-service (Hunter, Apollo), Reddit,
  relocation/visa or funding-news source is added.
