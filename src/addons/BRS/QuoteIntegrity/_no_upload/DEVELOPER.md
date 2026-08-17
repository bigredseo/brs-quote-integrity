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
Listens to `entity_post_save` for `XF\\Entity\\Post` and runs only on inserts or when the `message` field changes. New findings are written with `source = live`.

### `XF/Entity/Post.php`
Adds `getBRSQuoteIntegrityFindings()` for the moderator-facing template. This is intentionally calculated from the **current message**, not the findings table. Therefore a corrected post stops showing a warning while the original detection row remains historically available.

### `Repository/Finding.php`
Small data-access class for the dedicated findings table. The unique key prevents the same reply/source/URL combination from being inserted repeatedly.

### `Job/HistoricalScan.php`
On-demand XenForo job. Processes a maximum of 250 quote-containing posts per finder batch and yields when its allotted job runtime is exhausted. Filters can restrict the scan by date, user, and forum node.

### `Admin/Controller/QuoteIntegrity.php`
Provides the running findings list and form used to enqueue a historical scan.

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

There is intentionally no review state.

## Historical behavior

The historical scanner should be started manually from the ACP. Recommended order on a large community:

1. Run against the known problem user with no or a generous date limit.
2. Review the number/pattern of findings.
3. If appropriate, run all users for the previous two years.
4. Expand further back only if the recent scan suggests a long-running pattern.

The table stores matches only, so a 100,000-post forum does not become a 100,000-row add-on table.

## False-positive strategy

This add-on intentionally avoids trying to decide whether ordinary quoted text was changed. Only URL insertion is considered. Common tracking parameters are stripped before comparison.

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
