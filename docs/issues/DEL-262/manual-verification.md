# DEL-262 Manual Verification

## Browser Support

- Desktop Chrome, Edge, Firefox, and Safari: verify the Settings flow can request permission, subscribe, receive a test reminder, and open the expected target when clicked.
- Android Chrome: verify the same flow from the browser or installed PWA context.
- iPhone/iPad supported path: Safari -> Add to Home Screen -> open Delight from the Home Screen icon -> enable notifications from Settings.
- Normal iPhone/iPad Safari tabs, normal iOS Chrome tabs, and in-app browsers are not supported notification contexts for this MVP.

## Scenarios

- Dashboard prompt appears when reminders are off, links to /settings#reading-reminders, dismisses without showing the native permission prompt, and stays dismissed.
- Settings shows iPhone/iPad install guidance instead of the normal enable button when iOS/iPadOS is not running as a Home Screen PWA.
- Daily reminder at 09:00 local time sends only when the user has not logged reading for that local date.
- Streak-at-risk warning at 18:00 local time sends only when the user has an active streak at risk and has not logged reading today.
- Notification click opens today's active plan when the user has an active reading plan; otherwise it opens /logs/create.
- Disabling reminders or deleting the browser subscription prevents later notifications.
