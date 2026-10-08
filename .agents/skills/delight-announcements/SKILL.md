---
name: delight-announcements
description: Draft, edit, preview, publish or schedule Delight announcements, explicitly authorize their email broadcasts, and inspect delivery results. Use for announcement operations, not implementation changes to the announcement system.
---

# Delight Announcements

Use the existing Artisan commands and shared services. Publication and email authorization are separate decisions. A request to prepare an announcement authorizes preparation; carry out publication and email authorization only within the user's explicitly approved scope. Do not infer email approval from publication approval. A user can explicitly approve both together for the reviewed announcement.

## Establish the target

Confirm the intended environment, deployed implementation, database attachment, and mail transport before writing records. A preview can run with `APP_ENV=production`; this does not identify its database. Compare the actual database/cluster attachments. `MAIL_MAILER=log` records messages rather than delivering to an inbox; Mailpit captures local SMTP, while a production provider sends externally.

For Laravel Cloud, use the available Cloud skill and discover command signatures with `--help`. Follow applicable local Cloud approval rules, including exact-command approval when required. Keep environment IDs and credentials out of this shared skill. Do not run `db:seed` as part of announcement publication.

## Prepare and review

Read the current command help before constructing an operation. Write the approved Markdown to a content file accessible in the execution environment. Use public image paths and check authored links and images; built-in structural validation does not prove that a URL is reachable or that an image renders.

```bash
php artisan announcements:draft --title="Announcement title" --slug=announcement-slug --content-file=/path/to/content.md --hero-image-path=images/updates/hero/example.png --json
php artisan announcements:edit announcement-slug --content-file=/path/to/revised.md --json
php artisan announcements:publish announcement-slug --dry-run --json
```

Filament announcement management is under `/admin/manage-announcements`. **New draft** saves a private draft; **Edit draft** updates only saved drafts. Image pickers browse committed hero images under `public/images/updates/hero/` and social previews under `public/images/updates/social/`. Their usage labels reflect saved announcement references, not proof that an asset is safe to delete. Existing image paths remain valid.

Draft creation persists `starts_at` (defaults to now). Use explicit dates with timezone offsets for scheduling. Draft output includes an authenticated admin preview URL. Review the actual content, image, publication time, and expiry with the user before publishing. Published announcements cannot be edited through the draft commands.

Optional draft email review:

```bash
php artisan announcements:test-email announcement-slug --dry-run
php artisan announcements:test-email announcement-slug --yes
```

This targets only the configured `mail.admin_address`, prefixes the subject with `[TEST]`, and creates no subscriber delivery records. Verify the recipient and transport before the send; it neither publishes nor authorizes a broadcast. Filament provides **Send test email** on draft editing and details pages. Confirmation shows the admin recipient and `[TEST]` subject. It uses the saved draft, so save edits first. Success means transport submission, not confirmed inbox delivery.

## Publish in-app

```bash
php artisan announcements:publish announcement-slug --yes --json
```

Publication sets `is_draft=false`, preserves a future `starts_at`, and otherwise uses the actual publication time. It does not authorize email. In Filament, open saved draft details and confirm **Publish announcement** or **Schedule announcement**. Creating or saving a draft does not publish it. Publication ends draft editing. Report the publication URL and saved timing. In a dry-run, `state=published` describes the proposed outcome, not a persisted transition.

## Authorize email separately

```bash
php artisan announcements:authorize-email announcement-slug --dry-run --json
php artisan announcements:authorize-email announcement-slug --yes --json
```

Filament provides **Authorize email** on published/scheduled announcement details with audience estimates in the confirmation. Review recipient estimates before authorization. The estimate uses valid addresses, opt-out filtering, and the publication-time account-creation cutoff; it is not a frozen audience or a sent count.

Authorization sets `email_broadcast_authorized_at=now()` under a transaction and row lock. It leaves `starts_at` unchanged and does not send synchronously. Drafts, initial authorization after expiry, missing publication times, malformed email content, and email history without an authorization timestamp are rejected. Do not bypass these guards or clear historical state to make a command succeed.

Repeated authorization reports `already_authorized`, preserving the original timestamp, audience, and delivery results. JSON/noninteractive mutation requires `--yes`; that flag is command confirmation, not a substitute for user authorization.

## Verify delivery

The existing scheduler runs `announcements:send-published-emails` every five minutes. Processing requires authorization and a due `starts_at`. A scheduled broadcast can be authorized now and wait until publication. Finalization snapshots eligible recipients using the publication-time cutoff; authorizing later does not shift that cutoff.

A manual processing run handles **all due authorized broadcasts in the environment**, not just the announcement being discussed:

```bash
php artisan announcements:send-published-emails
```

Inspect the announcement's authorization, audience-finalization and completion timestamps, plus its `AnnouncementEmailDelivery` rows and command/application logs. Preserve terminal failed and uncertain states. Do not automatically run `--retry-delivery`; inspect the failure and obtain approval for a deliberate retry. Completion means processing finished; inspect sent/skipped/failed/uncertain totals before claiming success.

Filament details show persisted milestones and recipient outcomes. Authorized incomplete announcements auto-refresh every 15 seconds while visible. The green **Auto-refresh** indicator and timestamp describe page updates, not scheduler health. Polling stops once completion is recorded. Recipient details expose recorded reasons; **Retry failed recipients** requires confirmation and applies only to eligible failures.

Report evidence precisely: authorized, awaiting publication, processed, captured by log/Mailpit, or accepted by the configured transport. Transport acceptance does not prove real inbox delivery. Do not change provider settings or add quota/MCP/UI functionality as part of preparing an announcement.

## Source of truth

Resolve these repository-relative paths from the checkout root:

- `app/Console/Commands/{CreateAnnouncementDraft,EditAnnouncementDraft,PublishAnnouncement,AuthorizeAnnouncementEmail,SendAnnouncementTestEmail,SendPublishedAnnouncementEmails}.php`: operation flags and output.
- `app/Services/AnnouncementService.php`: creation, publication, authorization guards and transaction.
- `app/Services/AnnouncementTestEmailService.php`: saved-draft test validation and transport submission.
- `app/Filament/Resources/Announcements/`: draft forms, publication, test-email and authorization actions, progress and recipient diagnostics.
- `app/Services/AnnouncementValidator.php` and `AnnouncementEmailLinkValidator.php`: input and email-reference validation.
- `app/Services/AnnouncementEmailDeliveryService.php`: audience selection, snapshots, retries and completion.
- `app/Models/Announcement.php`, `AnnouncementEmailDelivery.php`, and `routes/console.php`: visibility, persisted delivery state and schedule.
- `tests/Feature/Console/Commands/AuthorizeAnnouncementEmailTest.php`, `PublishAnnouncementTest.php`, and `tests/Feature/App/Services/AnnouncementServiceTest.php`: executable lifecycle examples.

Read the relevant implementation when help, deployed behavior, or historical records differ from this guide. Stop and report unexpected validation or transport failures rather than repeatedly retrying or modifying delivery history.
