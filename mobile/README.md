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
when local backend testing is finished. Preview and dogfood ignore API URL overrides and retain their fixed
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
| Dogfood | `com.orlandovillanueva.delight` | Production |

Development, preview, and dogfood profiles are defined in `eas.json`. EAS cloud builds, signing changes, and releases require explicit approval and are not part of the normal development loop. After that approval, the intended Android commands are:

```bash
npx eas-cli build --platform android --profile preview
npx eas-cli build --platform android --profile dogfood
```

The project uses Continuous Native Generation. Do not commit generated `android/` or `ios/` directories; both are ignored.
