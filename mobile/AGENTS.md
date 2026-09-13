# Delight Mobile Agent Guidance

These instructions apply to every file under `/mobile` and supplement the repository root `AGENTS.md`.

## Scope and package boundary

- Treat `/mobile` as a standalone npm package. Run npm commands here and never add npm workspace configuration to the Laravel root.
- Laravel is the sole backend and source of truth. Do not reproduce backend business rules or create a separate backend.
- Keep each implementation bounded to its Linear issue. Do not pull later V1 features into foundation work.

## Expo and native projects

- Use the current stable Expo SDK and version-compatible packages installed through Expo tooling where possible.
- Use a Delight development build for the full development loop. Native Google Sign-In requires a library that Expo Go does not include. Expo Go is an optional shortcut for compatible UI/JavaScript work, not evidence that native authentication works.
- Follow Continuous Native Generation. Express native configuration through Expo config and config plugins; never commit generated `android/` or `ios/` directories.
- Do not run EAS cloud builds, manage credentials, submit stores, publish updates, or release artifacts without explicit approval.
- Keep the app Android-first and iOS-compatible. The reserved production identifiers must continue to match.

## Testing feature branches on a phone

- Test from the intended feature checkout, including its uncommitted changes; a push or merge to `main` is not required. Confirm the checkout, branch, working-tree state, and API target before starting. Preserve the user's checkout choice and unrelated changes.
- From that checkout's `mobile/` directory, use `npm run start:dev-client` and connect the installed development app to that Metro server. If another server is running, verify its checkout before reusing it; do not silently test another branch or stop another task's server.
- A development APK supplies native code; Metro supplies the current checkout's JavaScript/TypeScript. Reuse a compatible installed development build for JavaScript changes. Native dependency, SDK, or native configuration changes require a separately approved rebuild and installation before those changes can be verified.
- Development uses the Preview package identity and defaults to staging. For Laravel changes, use an explicitly selected phone-reachable local backend or a separately approved staging deployment. Starting Metro does not run or deploy the backend. Never silently use production for unfinished feature testing.
- Local Metro configuration comes from the local environment, not automatically from EAS. Verify that the staging public Google client configuration is present without printing secrets. Follow `mobile/README.md` for backend reachability and environment setup.
- Development and standalone Preview share a package identity. Switching between them replaces the installed app and requires compatible signing and version codes. Do not uninstall or discard local session data merely to resolve an installation conflict without approval.
- Use a standalone Preview APK for staging verification without Metro. Record its source and artifact identity; it does not acquire later branch changes automatically. Producing a new APK remains separately approved.
- If the required development build, backend, or device connection is unavailable, report the specific missing prerequisite and finish the available automated checks. Do not substitute Expo Go evidence for native feature verification or claim phone testing from configuration checks alone.

## TypeScript, files, and imports

- Keep TypeScript strict and do not weaken compiler options to silence errors.
- Route files live only in `src/app`. Put components, hooks, configuration, state, types, and utilities outside the router tree.
- Use kebab-case file names and the `@/` path alias instead of deep relative imports.
- Prefer small reusable components and centralized tokens over duplicated inline values.
- Before creating a component, look at the rest of the mobile codebase for an existing one that already owns the
  same chrome, motion, interaction, or visual pattern.
- If an existing component is almost identical and only the body, title, or accessibility labels differ, extract
  the shared shell and reuse it. Do not duplicate that pattern in a new file, and do not ask first.
- If an existing candidate is the right shape but is below the quality you would otherwise ship — unclear API,
  weak accessibility, hard-coded values, or hard to extend — ask whether to improve and extract that component
  or keep a new single-use component for this case.
- Keep feature-specific content in the caller. Shared components should own the repeated shell, motion, and
  accessibility chrome.
- Keep source lines at or below 120 characters where practical. Expand dense JSX props and inline React Native
  style objects across multiple lines instead of compressing a component onto one line.
- Keep dynamic styles close to the component that owns them, but extract named helpers when conditional
  presentation logic would otherwise require nested ternaries.
- Hoist navigation option renderers such as `headerRight` outside the parent component when they do not require
  current props or state, so their references remain stable across renders.

## Routing

- Use stable Expo Router APIs: JavaScript `Tabs` with a nested `Stack` per tab.
- Do not use `expo-router/unstable-native-tabs` or other experimental navigation APIs.
- Home, Log, and History are authenticated V1 tabs; Log remains the central primary action.
- Every navigation branch must retain a valid `/` route and appropriate native headers/safe-area handling.

## API and state

- Use `expo/fetch` for later API work, TanStack Query for server state, React Hook Form and Zod for forms, and `expo-secure-store` for bearer tokens.
- Do not add Axios, Redux, NativeWind, Tailwind, persisted query caches, offline databases, queued writes, or background synchronization.
- Use `EXPO_PUBLIC_API_URL` only for non-sensitive public configuration. Never embed secrets, signing material, or plaintext tokens in source, logs, fixtures, or environment examples.
- Laravel remains authoritative. Refetch at the lifecycle points defined by the canonical contract; do not invent optimistic writes for reading creation.
- GET requests may follow the bounded retry policy. POST requests must never retry automatically.

## Security

- Store authentication tokens only in SecureStore and clear them on 401 or logout.
- Never use AsyncStorage for tokens.
- Preserve generic authentication errors and avoid logging credentials, tokens, notes, or personal reading data.
- Keep production and preview application identifiers distinct; dogfood uses production identity only through an explicitly approved build.

## Theme and accessibility

- Use centralized theme tokens and respect system light/dark mode.
- Maintain sufficient contrast and system font scaling; do not hard-code layouts that clip enlarged text.
- Give interactive controls accessible labels and useful hints, use practical 44x44-point touch targets, and announce meaningful loading, success, validation, and error state changes.
- Use native headers and safe-area-aware scroll containers. Test both color schemes on Android.

## Testing and verification

- Add focused Jest tests with `jest-expo` and React Native Testing Library for every behavior change.
- Prefer user-visible queries and behavior assertions over implementation details or snapshots.
- Run `npm ci` when dependency manifests or the lockfile change, dependencies are missing or the installation is suspect, or clean-install verification is required. Reuse a working installation for other changes.
- Before handoff, run `npm run lint`, `npm run typecheck`, `npm test`, `npm run config:validate`, and repository-level `git diff --check` for mobile code changes. For documentation-only changes, inspect the diff and run `git diff --check`.
- Validate development, preview, and dogfood public Expo configuration when identity or environment logic changes, including development API overrides and fixed preview/production targets.
- Complete an Android development-build smoke check for navigation or runtime changes. Expo Go may cover compatible changes, but explicitly record that limitation; native Google Sign-In requires the development build. Report the tested checkout, backend, device/build, and observed results. Keep automated checks, development-build testing, and standalone artifact testing distinct; never claim device verification without observed evidence.

## Releases

- Mobile CI may install, lint, typecheck, and run Jest only. It must not trigger EAS builds or releases.
- EAS builds, Laravel deployment, signing credentials, build-number changes, store work, and distribution require separate explicit approval.
- Keep non-sensitive run/test/build instructions in `mobile/README.md`; keep private product contracts in Linear rather than copying them into the repository.
