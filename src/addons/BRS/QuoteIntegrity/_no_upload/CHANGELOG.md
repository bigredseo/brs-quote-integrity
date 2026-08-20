# Changelog

## 1.1.0 - 2026-08-20

### Added
- Added finding statuses: `open`, `ignored`, and `resolved`.
- Added an ACP action to manually ignore reviewed findings.
- Added an Undo ignore action to restore ignored findings to the open list.
- Added status filtering in the ACP findings screen.
- Added Open and Ignored finding counts for easier navigation between actionable and ignored findings.
- Added automatic resolution when a previously recorded finding is no longer present after a post is reanalyzed.
- Added automatic reopening when a previously resolved finding is detected again.

### Changed
- Live post analysis now reconciles existing findings after post edits instead of only recording new findings.
- Historical scans now reconcile existing findings for posts they actually analyze instead of only recording new findings.
- Ignored findings remain ignored during subsequent live and historical analysis.
- Findings default to the Open status so ignored and resolved findings no longer clutter the actionable findings list.

## 1.0.1 - 2026-08-17

### Fixed
- Fixed the ACP findings history display so scan results use XenForo's native data table formatting.
- Fixed "View reply" and "View original" links in the ACP to correctly open the associated public forum posts.
- Fixed the moderator/admin quote integrity warning so "View original post" correctly links to the quoted source post.

## 1.0.0 - 2026-08-10

- Initial BRS Quote Integrity development build.
- Detects URLs inserted into attributed XenForo quotes.
- Adds live post-save analysis for new and edited posts.
- Adds moderator-only current-post warning design.
- Adds simple findings table with no review-status workflow.
- Adds on-demand historical scanning by date, user, and forum node.
- Adds administrator help, developer reference, and project summary.
- Developer attribution links to Big Red SEO: https://www.bigredseo.com/
