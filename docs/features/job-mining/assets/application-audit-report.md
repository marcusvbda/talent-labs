# Application audit report (Stage 1)

- **Data:** local database, 11 `sent` applications of user 2 (ids 1-11, origin
  `manual`), sent 2026-10-28 16:42-16:45 (16:42:44 to 16:44:53), after the
  Stage 2 fixes. No `queued` applications and no other statuses exist (`select
status, count(*) from applications group by status` returned `sent` = 11). No
  posting was deleted.
- **Command:** `php artisan reports:application-audit --status=sent,queued`
  (read-only). Output:
  `storage/app/private/reports/application-audit-20261028-192129.csv` / `.md`
  (not committed).
- **Framing:** the poor applications that started this work are not in this
  database, so this report answers "do the Stage 2 fixes hold", not "how bad
  were the original applications".
- **Sample size:** 11 rows from one user and five sources. The percentages
  describe these 11 rows only and do not generalize.
- **User 2 preferences:** remote mode `any`, with no locations, titles or
  seniorities. That is the unfiltered case
  (`PreferenceCriteria::isUnfiltered()`, spec 2.7), so the match applies no
  restriction, title-word or role-profile rule. The audit heuristics still run.

## Summary

| Flag                                     | Count | %      | Ids  |
| ---------------------------------------- | ----- | ------ | ---- |
| a · Multiple roles                       | 0     | 0.0%   | -    |
| b · Aggregator/thread or missing job URL | 9     | 81.8%  | 3-11 |
| c · Location/authorization restriction   | 1     | 9.1%   | 2    |
| d · Out-of-profile role/seniority        | 0     | 0.0%   | -    |
| e · Generic recipient                    | 11    | 100.0% | 1-11 |
| 2+ flags                                 | 10    | 90.9%  | 2-11 |
| No flag                                  | 0     | 0.0%   | -    |

Sources: hacker_news 2 (ids 1, 2), we_work_remotely 1 (id 3), himalayas 1
(id 4), jobicy 4 (ids 5, 6, 8, 9), remote_ok 3 (ids 7, 10, 11).

## a · Multiple roles: 0 (0.0%)

- **Result:** no title, subject, HN header or body lists two or more roles.
- **Closest case:** id 1 (HN, Propel Labs), "Staff Full-Stack Engineer,
  Quality & Delivery". It splits on " & ", but "Delivery" is not a role word,
  so it reads correctly as one role. Its HN header
  (`Propel Labs | Staff Full-Stack Engineer, Quality & Delivery | Remote (North
America hours) | Full-time | ...`) has only one role segment.
- **Origin:** source parser. `HackerNewsAdapter` (`splitRoles()`,
  `hasAmbiguousRoles()`) turns a clearly delimited multi-role comment into
  `{id}#{n}` children and marks the parent not eligible. It marks an ambiguous
  multi-role title not eligible
  (`app/Collection/Adapters/HackerNewsAdapter.php`).
- **Verdict:** no defect observed.
- **Should a Stage 2 fix have prevented it:** yes, the per-role HN split and the
  ambiguous-title exclusion. The fix holds on this sample. Only 2 HN rows were
  audited, both single-role posts, so the split path itself was not exercised.

## b · Aggregator/thread or missing job URL: 9 (81.8%)

- **Ids:** 3 (WWR, "Senior Full Stack", Toggl), 4 (Himalayas, "Senior Angular
  Frontend Developer", MyTime), 5, 6, 8, 9 (Jobicy: "Senior Software Engineer,
  Internally Deployed Products" ClickUp, "Senior Software Engineer, Backend
  (Money Movement)" Phantom, "Senior Software Engineer, Product" RevenueCat,
  "Staff Backend Engineer" TeamSnap), 7, 10, 11 (RemoteOK: "Front End
  Developer" Acro Commerce, "Senior Frontend Engineer" B2Spin, "Software
  Engineer Showroom" Higharc).
- **Top sources:** jobicy 4, remote_ok 3, we_work_remotely 1, himalayas 1.
- **Top domains (as reported):** jobicy.com 4, remoteok.com 3,
  weworkremotely.com 1, himalayas.app 1.
- **What the emails contain:** none of the 9 emails contains an aggregator
  link. Each one has no job link at all, because the "Job posting:" line was
  removed. The emails carry only the client's LinkedIn, portfolio and GitHub
  links (checked on the bodies). All 9 are flagged for a **missing job URL**,
  not for a link that was sent.
- **Audit-reporting quirk (not a pipeline defect):**
  `ApplicationAudit::jobUrl()` falls back to the posting's `url` when the body
  holds neither `url` nor `apply_url`. So the "Reason" and domain columns show
  the aggregator listing (for example `remoteok.com`) instead of "no link". Read
  those columns as "where the posting came from", not "what was sent"
  (`app/Reports/ApplicationAudit.php`).
- **Origin:** source parser. These adapters only supply the aggregator's own
  listing page as both `url` and `apply_url`:
    - `JobicyAdapter` and `WeWorkRemotelyAdapter` set `applyUrl` to the same
      `url`.
    - `RemoteOkAdapter` falls back to `url` when `apply_url` is missing.
    - `HimalayasAdapter` stores an `applicationLink` that is itself a
      `himalayas.app/...?utm_source=api` page.
      `JobPosting::applicationUrl()` (`app/Models/JobPosting.php`) then returns
      null because every candidate is in `talent.outreach.aggregator_domains`
      (`config/talent.php`). The template drops the link line
      (`app/Outreach/Support/ApplicationTemplateRenderer.php`). For id 3 the WWR
      payload mentions the company's own `https://toggl.com/jobs/` in the
      description, but the parser does not extract it.
- **Verdict:** model limitation. These sources do not expose the company's own
  job page or ATS link. The Stage 2 behaviour (never send an aggregator link,
  drop the line) works as designed.
- **Should a Stage 2 fix have prevented it:** the Stage 2 fixes (`applicationUrl()`
  and the email link-line rule) should have prevented aggregator links in the
  email, and they did: 0 of 11 emails carry one. They were never meant to
  supply a company URL, so "missing job URL" on aggregator-sourced postings is
  expected. The two HN applications (ids 1, 2) send a company/ATS link
  (`propellabs.ai`, `eventfirst.jobs.personio.com`) rather than the HN thread,
  which confirms the original "job_url points to the HN thread" defect is
  fixed.

## c · Location/authorization restriction: 1 (9.1%)

- **Ids:** 2 (HN, eventfirst, "Senior Full-Stack Engineer", location "Berlin,
  Germany", header `REMOTE (CET)`, body "we are working remotely in the CET
  timezone").
- **Reason:** `timezone: cet`.
- **Top sources / domains:** hacker_news 1 / personio.com 1.
- **Origin:** normalization. `PostingRestrictions::detect()` records a
  `timezone` restriction (`app/Collection/Support/PostingRestrictions.php`).
  The match ignores timezone by design (spec 2.7: "`timezone` is recorded but
  never excludes"), and user 2's preferences are unfiltered anyway. The audit
  heuristic `ApplicationAuditFlags::geo()` deliberately counts any
  non-hybrid/onsite/region kind as incompatible (`app/Reports/ApplicationAuditFlags.php`).
- **Verdict:** model limitation. Preferences have no timezone dimension, and
  flag c is intentionally looser than the match. It is not a bug.
- **Should a Stage 2 fix have prevented it:** no. The Stage 2 match rules
  exclude region, work-authorization, onsite and hybrid conflicts, not
  timezone.
- **Detection notes (observed, not counted by the flag):**
    - ids 4, 5 and 9 have a bare location "United States" / "USA". id 7 is
      located in "Kelowna, ..., British Columbia, Canada".
      `PostingRestrictions` only recognizes "US-only", "remote (US)" and "US
      remote" phrasing, so a bare country location is not recorded as a region
      restriction.
    - id 1's "Remote (North America hours)" is not recorded as a timezone,
      because the pattern needs a zone token such as EST or PST.
      These are possible false negatives of the normalization step. With
      unfiltered preferences they would not have changed the match for user 2.

## d · Out-of-profile role/seniority: 0 (0.0%)

- **Result:** no title contains an excluded word (manager, director, head of,
  VP, chief, CTO, CEO, intern, trainee). Every `role_family` is a dev family:
  fullstack 3, frontend 3, software 3, backend 2. Seniority is senior on 10
  rows and unknown on id 7.
- **Origin if raised:** match (`MatchingJobPostings::forUser()`) and the AI
  profile (`role_family`, `seniority`). Heuristic in
  `ApplicationAuditFlags::profile()`.
- **Caveat:** user 2 has no preference titles or seniorities. So only the
  title-word check ran; the seniority and dev-profile role-family checks were
  inactive. The 0 holds for the title-word rule, and the role families happen
  to be in profile.
- **Verdict:** no defect observed.
- **Should a Stage 2 fix have prevented it:** yes, the title-word exclusion
  and the dev role-family rule. Nothing out of profile got through.

## e · Generic recipient: 11 (100.0%)

- **Ids:** all 11.
- **Local parts:** careers 4 (ids 1, 5, 6, 7), jobs 4 (ids 3, 4, 8, 9),
  recruiting 2 (ids 2, 11), hr 1 (id 10).
- **Top sources:** jobicy 4, remote_ok 3, hacker_news 2, we_work_remotely 1,
  himalayas 1.
- **Top domains:** jobicy.com 4, remoteok.com 3, propellabs.ai 1,
  personio.com 1, weworkremotely.com 1, himalayas.app 1.
- **Origin:** contact discovery. `DiscoverCompanyContacts` only probes the
  role aliases careers, jobs, hr, talent, recruiting and people over SMTP
  (spec 2.8), and `OutreachLimits::GENERIC_LOCAL_PARTS` classifies exactly
  those as generic.
- **Verdict:** model limitation, by design. Discovery never produces named
  contacts, and named contacts are out of scope.
- **Should a Stage 2 fix have prevented it:** no. Stage 2 only added the
  derived `Application::recipientKind()` badge and filter; it does not change
  who receives the email.

## Conclusion

- **Fixes that hold on this sample:**
    - no multi-role applications (a);
    - no aggregator or HN-thread link in any email (b: every flagged row has the
      link line removed);
    - no out-of-profile roles (d).
- **What remains is model limitations, not bugs:**
    - aggregator sources do not expose a company job URL (b, 9);
    - timezone is not a preference dimension (c, 1);
    - discovery yields role aliases only (e, 11).
- **Observed gaps, reported here without proposed fixes:**
    - the audit's b reason/domain shows the source listing rather than "no
      link";
    - normalization misses bare-country locations and "North America hours".
