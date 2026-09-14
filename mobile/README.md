# Delight Mobile

Android-first Expo application for Delight. This directory is a standalone npm package; run all mobile commands from `mobile/`.

## Requirements

- Node.js 22.13 or newer (matches CI)
- npm
- Expo Go on an Android physical device, or an Android emulator with Expo Go installed
- Access to the same network from the development computer and physical device

No Laravel, signing, or EAS credentials belong in this directory.

## Setup

```bash
cd mobile
cp .env.example .env.local
npm ci
```

`EXPO_PUBLIC_API_URL` is public application configuration, never a secret. Development and preview default to `https://delight-staging.laravel.cloud`.

## Develop on an Android phone

Use a development build for the full native feature loop, including Google Sign-In. The `development`
profile uses the existing Preview package and staging EAS environment. Development and Preview installations
replace one another when signed with the same key; production Delight can stay installed separately.
Before building, verify the existing Preview signing credential and version counter. A newer installed
version code can prevent installing an older APK over it; avoid uninstalling merely to bypass that check
without considering the local session data it removes.

After separate build approval, the intended command is:

```bash
npx eas-cli build --platform android --profile development
```

Install that approved APK, then start the development server from this directory on macOS/Linux:

```bash
npm run start:dev-client
```

The command explicitly selects the development variant. Connect the phone to the development server using
its QR code on the same reachable network. JavaScript/TypeScript changes use Fast Refresh without another
APK build. Rebuild after changing native dependencies, Expo SDK, or native app/plugin configuration.
Generated native directories remain untracked under Continuous Native Generation.

### Backend and Google configuration

Development defaults to staging. Expo reads local variables from the ignored `.env.local`; the EAS profile's
`environment: preview` supplies cloud-build variables, not variables to the local development server.
Use the established staging values for `EXPO_PUBLIC_GOOGLE_WEB_CLIENT_ID` and, when applicable,
`EXPO_PUBLIC_GOOGLE_IOS_URL_SCHEME`. These public identifiers must match the approved Preview setup.
Do not use production OAuth settings or copy secrets into public variables. Google Sign-In is unavailable
without the web client ID. The installed build's certificate must also match the approved Android OAuth
client; selecting remote credentials alone does not prove that match.

For backend feature work, set `EXPO_PUBLIC_API_URL` in `.env.local` to a phone-reachable URL for your local
Laravel application. The computer's `localhost` and Herd `.test` name are not automatically reachable from
the phone. Use a reachable HTTPS endpoint with a certificate the phone trusts; do not weaken transport
security. The local backend must have the matching staging mobile Google audience configured if testing
Google login. Staging remains the simpler default when backend changes are unnecessary.

Restart the development server and reload the app after changing configuration. Restore the staging URL
when local backend testing is finished. Preview and production ignore API URL overrides and retain their fixed
backend targets.

### Standalone preview and Expo Go

Use the Preview APK for a fixed, staging-connected feature check without the computer or development server.
Build and installation remain separately approved. Verify cold startup, session restoration, and the feature
on the exact installed APK before considering it device-tested.

Expo Go remains available for limited UI work with `npm start` (or `npm run android`). It runs in the Expo Go
container even though development config reserves the Preview native identity. Native Google Sign-In is
intentionally hidden because Expo Go does not include its native library. If the installed Expo Go version
is incompatible, use [Expo's official selector](https://expo.dev/go) to obtain the matching Android version.

## Quality checks

```bash
npm run lint
npm run typecheck
npm test
npm run config:validate
```

From the repository root, also run:

```bash
git diff --check
```

CI runs the same install, lint, typecheck, and Jest checks with Node 22.13 when `/mobile/**` or the mobile workflow changes.

## Environment and build configuration

`app.config.js` defines three contexts:

| Context | App identity | Backend |
| --- | --- | --- |
| Development build | `com.orlandovillanueva.delight.preview` | Staging or explicit local override |
| Development / Expo Go | Expo Go container | Staging or explicit local override |
| Preview | `com.orlandovillanueva.delight.preview` | Staging |
| Production / Play | `com.orlandovillanueva.delight` | Production |

Development, preview, and play profiles are defined in `eas.json`. EAS cloud builds, signing changes, and releases require explicit approval and are not part of the normal development loop. After that approval, the intended Android commands are:

```bash
npx eas-cli build --platform android --profile preview
npx eas-cli build --platform android --profile play
```

The project uses Continuous Native Generation. Do not commit generated `android/` or `ios/` directories; both are ignored.

The `play` profile uses the `production` app variant and produces a Google Play AAB, with development mode
explicitly disabled. It replaces the former dogfood APK profile. Development and Preview remain APK workflows.
The production EAS environment supplies the established Google configuration; local staging values are not
the production authentication configuration.

Remote versioning and automatic increments remain enabled. Before any Play build, freeze the reviewed source
commit and included issues, verify the production package's current EAS version counter and Play version history,
and present the exact command, expected app version/version code, backend, and credential source for approval.
Do not assume the Preview counter determines the production counter. Remote credentials specify where EAS
obtains upload signing material; they do not select or verify the Play app-signing identity. Signing decisions,
credential changes, build execution, upload, candidate acceptance, and track rollout remain separate gates.
Internal Testing precedes validation of the Play-installed artifact and any promotion to Closed Testing.

### Candidate provenance and approval record

Keep each filled release record in the private release issue. This template defines what to capture; an
unavailable value stays explicitly pending and is resolved at its gate, never inferred from an older APK.

| Field | Evidence required |
| --- | --- |
| Source | Full reviewed `main` SHA after configuration changes merge; clean checkout |
| Included work | Reviewed issue/PR list, including the privacy/support/deletion and Play configuration work |
| Configuration | `play` profile, `production` variant and EAS environment, production backend, EAS project |
| Identity | `com.orlandovillanueva.delight`; resolved app version |
| Android version | Current production EAS counter, highest Play-uploaded version, approved next versionCode; actual build value afterward |
| Build approval | Exact command, source, expected versions, environment and credential source; approver/date |
| Upload signing | Selected remote credential identity and public SHA-1/SHA-256; confirm the resulting artifact signer |
| Play signing | Selected arrangement and all applicable Play app-signing certificate fingerprints; pending until exposed by Play |
| Google authentication | Production OAuth project/client references and package/certificate registration evidence for Play-delivered APKs |
| Artifact | EAS build ID/URL, actual source/version metadata, AAB URL and SHA-256 checksum; pending until build completes |
| Play processing | Uploaded versionCode, processing result and track; pending until separately approved upload |
| Device validation | Device/Android version, Play install source, installed version, date, observations and blockers |
| Acceptance | Explicit decision and approver/date for this exact candidate; promotion reuses it where feasible |

If EAS increments the counter before a failed upload/build, record the consumed value and refresh the expected
next value before retrying. Do not reset the counter to reuse an assumed available number. Compare EAS and Play
history again immediately before the approved build; concurrent builds can change the counter.

### Play-installed candidate validation

Run this checklist on the exact candidate installed through Play Internal Testing, after separate upload and
track approvals. A development APK, Expo Go session, or direct APK installation does not substitute for it.

- Confirm package, versionCode, Play installation source, correct app identity, and production backend.
- Verify email/password login, native Google login, cancellation/retry, logout and account switching.
- Force-close and reopen the app; verify the expected session and account are restored without Metro.
- Check Home, Today/Yesterday reading creation, History and pagination. Use an explicitly approved real reading
  or approved test account; verify each submitted reading appears exactly once on both mobile and web,
  including after refresh and reopening. Record actual observations without copying private reading content.
- Open account/settings, support, privacy and deletion resources. Verify the intended pages and navigation;
  do not submit an actual deletion request as a routine smoke test.
- Check light/dark modes and core navigation on the device. Record crashes, authentication failures and
  data-integrity issues as blockers.
- Confirm first installation works. Verify Play-to-Play update/session retention when a prior Play version is
  available; otherwise mark it not yet exercised. The retired private Firebase APK is outside compatibility scope.
- Record acceptance only after results are reviewed. Promote the same accepted version to Closed Testing when
  feasible. A replacement artifact or source change needs its own provenance and proportionate validation.
