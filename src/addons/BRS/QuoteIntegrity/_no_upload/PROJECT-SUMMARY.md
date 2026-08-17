# BRS Quote Integrity - Project Summary

**Developed by [Big Red SEO](https://www.bigredseo.com/)**

## The problem

XenForo users can edit the contents of a quote before posting it. That allows someone to insert a link inside another member's quoted words even though the original member never posted that link.

## The solution

BRS Quote Integrity compares links inside attributed XenForo quotes with the original post being quoted.

If the links match, nothing is shown and nothing is recorded.

If a link has been added to the quoted content:

- moderators see a warning directly on that reply;
- the inserted URL/domain is recorded in a simple ACP findings table;
- moderators can jump to the reply and original source post.

There is **no warning on normal posts** and no moderation status/workflow to maintain.

## Historical review

Administrators can manually run a historical scan by user, date range, or forum. The scan runs in batches and records only matches. This makes it practical to review a large, long-running forum without manually inspecting tens of thousands of posts.

A sensible first pass for a XenForo community would be the known user, followed by an all-user scan covering roughly the previous two years if needed.

## Ongoing impact

Once installed, new and edited posts are checked automatically. The historical scanner remains dormant unless an administrator explicitly starts it.

## Intended audience

The add-on is being designed for a specific clients' forum but is generic enough to be useful to other XenForo 2.3 communities dealing with quote manipulation, link spam, affiliate-link insertion, or misleading attribution.
