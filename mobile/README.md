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

## Run with Expo Go

```bash
npm start
```

Scan the QR code with Expo Go. If LAN discovery is unavailable, retry with `npx expo start --tunnel`. For an already-running Android emulator, use `npm run android`.

If Expo Go reports that the project SDK is incompatible, install the matching Android Expo Go release from [Expo's official selector](https://expo.dev/go) and retry. This remains Expo Go; it is not a custom development client.

The expected shell has stable Home, Log, and History tabs. Expo Go is the default loop; do not create a development client unless a required V1 dependency is unsupported and its native requirement is documented first.

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
| Development / Expo Go | Expo Go container | Staging |
| Preview | `com.orlandovillanueva.delight.preview` | Staging |
| Dogfood | `com.orlandovillanueva.delight` | Production |

Preview and dogfood profiles are reserved in `eas.json`. EAS cloud builds, signing changes, and releases require explicit approval and are not part of the normal development loop. After that approval, the intended Android commands are:

```bash
npx eas-cli build --platform android --profile preview
npx eas-cli build --platform android --profile dogfood
```

The project uses Continuous Native Generation. Do not commit generated `android/` or `ios/` directories; both are ignored.
