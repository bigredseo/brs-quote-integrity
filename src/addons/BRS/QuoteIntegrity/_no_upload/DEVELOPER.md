# BRS Quote Integrity - Developer Reference

Developed by [Big Red SEO](https://www.bigredseo.com/).

## Purpose

Detect links inserted into attributed XenForo quotes when those links were not present in the quoted source post. The add-on is deliberately an exception detector, not a general quote-diff or moderation case-management system.

## Architecture

### `Service/QuoteAnalyzer.php`

Shared analysis engine used by both live monitoring and historical scans.

1. Fast pre-check for `[QUOTE` plus URL-like content.
2. Parse attributed quote blocks and obtain their `post:` IDs.
3. Load referenced source posts.
4. Extract URLs from quoted content and source BBCode.
5. Normalize URLs.
6. Return only URLs present in the quote but absent from the source post.

The parser uses a stack for quote boundaries instead of a single recursive regex so manually nested quote blocks do not immediately break matching.

### `Listener.php`

Listens to `entity_post_save` for `XF\Entity\Post` and runs only on inserts or when the `message` field changes.

Live analysis uses the shared analyzer and reconciles the post's current findings against previously stored findings. New findings are written with `source = live`. Existing open findings that are no longer detected are marked `resolved`. A previously resolved finding is reopened if the same condition is detected again. Findings marked `ignored` are not automatically reopened.

### `XF/Entity/Post.php`

Adds `getBRSQuoteIntegrityFindings()` for the moderator-facing template. This is intentionally calculated from the **current message**, not the findings table. Therefore a corrected post stops showing a moderator warning immediately, while its stored detection record remains available in the findings history.

### `Repository/Finding.php`

Small data-access class for the dedicated findings table. The unique key prevents the same reply/source/URL combination from being inserted repeatedly.

The repository also manages finding state. Findings default to `open`. Administrators can manually mark a finding as `ignored`, while live and historical re-analysis can automatically mark an open finding as `resolved` when the condition is no longer present. A resolved finding is reopened if the same condition is detected again. Ignored findings are not automatically reopened.

### `Job/HistoricalScan.php`

On-demand XenForo job. Processes a maximum of 250 quote-containing posts per finder batch and yields when its allotted job runtime is exhausted. Filters can restrict the scan by date, user, and forum node.

Each post that is actually analyzed is reconciled against its existing stored findings. New findings are recorded as `historical`, open findings that are no longer detected are marked `resolved`, and previously resolved findings are reopened if detected again. Findings outside the scope of the scan are not changed.

### `Admin/Controller/QuoteIntegrity.php`

Provides the running findings list and form used to enqueue a historical scan.

The ACP findings workflow supports filtering by status, manually ignoring open findings, and restoring ignored findings to the open list. Open and ignored counts are also exposed for navigation between actionable and ignored findings.

## Table

`xf_brs_quote_integrity`

Key fields:

- `finding_id`
- `post_id`
- `thread_id`
- `user_id` / `username`
- `quoted_post_id`
- `quoted_user_id` / `quoted_username`
- `detected_date`
- `added_url`
- `added_domain`
- `url_hash`
- `source` (`live` or `historical`)
- `status` (`open`, `ignored`, or `resolved`)
- `status_date`
- `status_user_id`

`source` and `status` represent different concepts:

- `source` records how the finding was originally detected: `live` or `historical`.
- `status` records its current disposition: `open`, `ignored`, or `resolved`.

Status behavior:

- `open` - the finding is currently actionable.
- `ignored` - an administrator reviewed the finding and intentionally removed it from the actionable list.
- `resolved` - subsequent analysis confirmed that the previously detected condition is no longer present.

Ignored findings are retained so subsequent scans do not recreate them as actionable findings. Resolved findings are also retained as historical records and reopen automatically if the same condition is detected again.

`status_date` records when a finding was ignored or resolved. `status_user_id` records the administrator who ignored a finding; automatic resolution uses `0`.

## Historical behavior

The historical scanner should be started manually from the ACP. Recommended order on a large community:

1. Run against the known problem user with no or a generous date limit.
2. Review the number/pattern of findings.
3. If appropriate, run all users for the previous two years.
4. Expand further back only if the recent scan suggests a long-running pattern.

The table stores matches only, so a 100,000-post forum does not become a 100,000-row add-on table.

Historical scans reconcile only posts they actually analyze. A date-, user-, or node-limited scan does not resolve findings belonging to posts outside that scan's scope.

## False-positive strategy

This add-on intentionally avoids trying to decide whether ordinary quoted text was changed. Only URL insertion is considered. Common tracking parameters are stripped before comparison.

Findings that are legitimate exceptions can be marked `ignored` in the ACP rather than deleted. This preserves the audit record and prevents the same finding from becoming actionable again during a later scan.

Possible cases to test before general distribution:

- redirected/shortened URLs
- XenForo media auto-embedding
- `[URL]` and `[URL=...]` variants
- punctuation immediately after a bare URL
- nested quotes
- source post subsequently edited after the quote was made
- source post deleted or unavailable

### Important historical limitation

The scanner compares an old quote to the **current stored version** of its source post. If the source post itself was edited after the reply was made, XenForo's current post record may not exactly represent what existed at the time of quoting. If XenForo edit history is enabled and retained, a future version could optionally compare against source edit history by timestamp.

## Release process

The supplied code is a development source package. Register the XenForo development data described in `DEV-REGISTRATION.md` inside a XenForo 2.3 development installation, test against the site's actual 2.3 point release and styles, and use XenForo's own `xf-addon:build-release` command to generate the distributable archive.

This is intentional: XenForo stores listeners, routes, templates, template modifications, and navigation as add-on development data and exports these definitions when the add-on is built.
