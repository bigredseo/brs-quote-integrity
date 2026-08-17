# BRS Quote Integrity

A XenForo 2.3 add-on by [Big Red SEO](https://www.bigredseo.com/) that detects links inserted into another member's attributed quoted content.

## Core principle

**Show nothing unless there is a problem.**

Normal posts and legitimate quotes are untouched. If a quoted block contains a URL that was not present in the source post referenced by XenForo's quote metadata, moderators receive an inline warning and the finding is added to a simple ACP table.

## Included

- live checking of new and edited posts
- moderator-only inline warning for current violations
- simple running findings table
- historical scanner that runs only on request
- filters for user/date/forum historical scans
- shared quote/URL analyzer
- in-plugin `HELP.md`
- developer documentation
- short administrator/project summary

## Package status

This archive is a **development source package** for XenForo 2.3. The PHP implementation and template source are included. XenForo development data must be registered in a XenForo 2.3 development installation and exported with XenForo's official build tools before production installation/distribution. See `DEV-REGISTRATION.md`.

The reason for this step is that XenForo itself exports listeners, class extensions, routes, navigation, templates, and template modifications into the release package.

## Files

- `src/addons/BRS/QuoteIntegrity/` - add-on source
- `src/addons/BRS/QuoteIntegrity/HELP.md` - administrator help shipped with the source
- `DEVELOPER.md` - internal technical reference
- `PROJECT-SUMMARY.md` - short explanation for site administrators
- `DEV-REGISTRATION.md` - XenForo development-data registration/build checklist
- `dev-definitions/templates/` - template source to register in the XenForo development environment

## Developer

[Big Red SEO](https://www.bigredseo.com/)
