---
name: delight-announcements
description: Draft, revise, preview, and prepare publication of Delight announcements from completed feature work using the guarded Artisan commands.
---

# Delight Announcements

Guide one human and one agent through evidence → draft → edit → browser preview → publication dry run → explicit approval. Keep announcement operations in the existing Artisan interface. Do not implement database writes, mail delivery, credentials, MCP tools, or HTTP clients in this skill.

## Establish the message

Inspect the completed issue, accepted implementation, relevant tests, and any user-observed behavior. Prefer primary project evidence over issue promises. Identify what changed for users, who benefits, and what they can do next. A useful new capability, meaningful usability improvement, or change requiring user action can merit an announcement; internal refactoring alone usually does not. Recommend combining small related improvements when that makes a clearer message.

Distinguish implemented and tested behavior from verified availability in the intended release. Merge, passing tests, and local demonstrations do not prove production deployment. If release evidence is missing, prepare a draft with the uncertainty in the private handoff and ask only for evidence that materially affects the claim or publication decision. Do not present unsupported availability claims as established facts or silently deploy to make the copy true.

Prepare a concise benefit-led title and Markdown body: explain the improvement, any relevant limits, and a public CTA linked to a verified destination. Keep internal issue IDs and verification notes in the handoff unless readers need them. The CTA is a Markdown link in the body, not a separate command field.

Gather the required public hero image path, optional social image path, proposed start time, and optional expiry. Check that imagery resolves in the intended environment; an accepted path string does not prove an asset exists. Use supplied or existing suitable imagery, and ask for missing imagery inputs when needed. Drafting does not authorize uploading or replacing production assets.

State timing with an explicit timezone/UTC offset. For immediate publication, explain that the actual publication time is set when the action runs. For scheduling, use the intended future time. Resolve material ambiguity about audience-facing availability, imagery, CTA, or timing without asking the user to repeat information already supplied.

## Use the delivered commands

Run from the Delight repository in the intended, authorized environment. Establish the environment from the task context and permitted configuration inspection before a persisted operation; do not assume a local shell implies a local database. Production access and deployment retain their separate approval requirements. Do not embed machine-specific credentials or Cloud commands here.

Use `php artisan announcements:draft --help`, `announcements:edit --help`, and `announcements:publish --help` to confirm the available interface when needed. Source contracts live in `app/Console/Commands/{CreateAnnouncementDraft,EditAnnouncementDraft,PublishAnnouncement}.php` and `app/Console/AnnouncementDraftOutput.php`. Prefer their current output over stale issue wording. There is no `type` option, standalone `announcements:preview` command, pre-publication email preview, or test-recipient sending path.

Write the prepared Markdown to a local UTF-8 file, such as an ignored file under `storage/app/`, using a file-writing tool or safely quoted input that preserves Markdown literally. Pass its path through `--content-file`; do not interpolate copy into shell code. Quote all command values safely.

Create a persisted draft using this command shape, replacing example values with the prepared inputs:

```sh
php artisan announcements:draft --title='Announcement title' --content-file='storage/app/announcement.md' --hero-image-path='images/announcement.png' --json --no-interaction
```

Optional creation flags are `--slug`, `--social-image-path`, `--starts-at`, and `--ends-at`. An omitted slug is derived from the title; an omitted start time defaults to now. Draft creation remains unpublished even with a future proposed start time and does not authorize email delivery.

Read the exit status and JSON. Successful creation and editing return `id`, `slug`, `state: draft`, `preview_url`, `publication_url`, `proposed_starts_at`, and `proposed_ends_at`. Retain the returned identity and URLs. `publication_url` is the eventual public destination, not evidence that the draft is public.

For a requested revision, edit the existing draft by its current slug, supplying only changed fields:

```sh
php artisan announcements:edit 'current-draft-slug' --content-file='storage/app/announcement.md' --json --no-interaction
```

Editing also accepts `--title`, `--slug`, `--hero-image-path`, `--social-image-path`, `--starts-at`, and `--ends-at`. Use `--clear-social-image` or `--clear-ends-at` to remove those optional values; do not set and clear the same value together. At least one change is required. Use the returned slug and preview URL after a slug change. Non-drafts cannot be edited through this workflow; do not work around that guard for published or scheduled records.

Validation failures return a nonzero exit status and an `errors` object keyed by field. Explain the actionable error, correct inputs within the requested scope, and retry only when the cause is understood. If execution has an uncertain outcome, establish what happened through supported inspection before repeating a persisted action; do not blindly create duplicates or retry publication.

## Preview and present the decision

Present the returned `preview_url` and use the available browser workflow to inspect its rendered web content, imagery, and CTA. The route requires an authenticated admin session. If access is unavailable, report that browser verification is incomplete and request the necessary user participation; do not claim to have viewed it or bypass authentication. Do not substitute the public URL for a protected draft preview. Follow the repository's browser handoff rule when verification leaves a useful review state.

Run the non-mutating publication dry run for the current draft:

```sh
php artisan announcements:publish 'current-draft-slug' --dry-run --json --no-interaction
```

The JSON contains `id`, `slug`, `title`, `state`, `publication_url`, `starts_at`, `ends_at`, `eligible_recipients`, `excluded_recipients`, `audience_note`, and `dry_run: true`. Here `state` is the proposed `published` or `scheduled` outcome: the persisted record is still a draft. Recipient numbers are current estimates, finalized when delivery becomes due; do not reinterpret eligibility or bypass marketing opt-outs.

Present one concrete approval handoff: environment, persisted ID and current slug, current title/body and preview link, browser verification result, immediate or scheduled timing with timezone, expiry if any, eligible/excluded estimates, and material release-evidence gaps. Explain that publication or scheduling authorizes the resulting eligible-user email broadcast through the existing delivery system. Show the exact publication command to be authorized.

Handle user-requested revisions in the same linear conversation: edit the draft, inspect the updated preview, run the dry run for the revised proposal, and present it for approval. Do not apply an earlier approval to content or timing the user has since asked to change. No review tokens, fingerprints, speculative concurrency checks, or automatic refresh loops are needed.

## Execute the approved publication

Publish or schedule only after explicit user approval of this identified draft, proposed timing, and resulting email broadcast. A request to draft, revise, preview, implement, merge, build, test, or deploy is not publication approval. If the user has already explicitly approved the concrete action and broadcast, do not ask for the same approval again.

After approval, execute:

```sh
php artisan announcements:publish 'current-draft-slug' --yes --json --no-interaction
```

There is no schedule flag on this command: a future persisted `starts_at` schedules publication; otherwise it publishes at the actual current time. Prepare timing through draft creation or editing before the approval handoff. `--yes` records command confirmation; it cannot replace the user's authorization.

Report success only from a successful command result, including the returned state, actual start time, and publication URL. Explain that email delivery is authorized, not necessarily completed. Do not invoke a separate broad-send command, introduce an autonomous follow-up, or claim recipient delivery from publication output alone. Pre-publication email preview and separate test-recipient sending remain deferred; published editorial revisions belong to DEL-304.
