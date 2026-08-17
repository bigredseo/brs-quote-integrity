# BRS Quote Integrity - Help

**Developer:** [Big Red SEO](https://www.bigredseo.com/)  
**Add-on:** BRS Quote Integrity  
**Target:** XenForo 2.3+

## What this add-on does

BRS Quote Integrity detects a specific form of quote manipulation: a user quotes an existing XenForo post and adds a web link inside the quoted text even though that link did not exist in the original post.

The add-on does **not** attempt to police ordinary quote editing. Users may shorten quotes, remove paragraphs, fix spelling, or quote only part of a post without creating a finding. The trigger is intentionally narrow: **a URL appears inside an attributed quote and that URL cannot be found in the source post referenced by the quote.**

## Live monitoring

When a new post is created, or an existing post is edited, BRS Quote Integrity checks the submitted BBCode.

- No attributed quote: nothing happens.
- Attributed quote but no links: nothing happens.
- Link was already present in the original post: nothing happens.
- New link has been inserted into the quoted content: a finding is recorded.

Normal posts display nothing. Only moderators see a warning on a post that **currently** contains an inserted quote link.

## Findings table

The add-on maintains a simple running findings table in the Admin Control Panel. It is not a moderation workflow and has no Open, Reviewed, Confirmed, or Dismissed statuses.

Each finding records the reply, posting user, quoted source post, quoted user, inserted URL/domain, date detected, and whether it came from live monitoring or a historical scan.

Findings are intentionally retained if a moderator later edits or deletes the offending content. This provides a simple record that the behavior was detected without trying to track what moderation action was taken.

## Historical scanner

The historical scanner runs **only when an administrator starts it**. It can be limited by:

- posting user
- start date
- end date
- forum/node

It runs as a XenForo background job in batches and only writes actual findings. It does not create a second copy of every post it examines.

For a large forum, start with the known user. If a wider review is needed, scan a recent date range such as the previous two years.

## URL comparison

URLs are normalized before comparison to reduce obvious false positives. The current implementation:

- treats host names case-insensitively
- ignores URL fragments
- sorts query parameters
- removes common tracking parameters such as `utm_*`, `fbclid`, `gclid`, `dclid`, and `msclkid`

A genuinely different URL is still considered an inserted URL, even if it points to the same domain as a URL in the original post.

## Privacy and external services

BRS Quote Integrity performs its analysis locally against XenForo post data. It does not send post content or URLs to Big Red SEO or another external service.

## Support / developer

BRS Quote Integrity is developed by [Big Red SEO](https://www.bigredseo.com/).
