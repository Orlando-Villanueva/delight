import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';
import { Linking } from 'react-native';

import { NativeReminderSettings } from '@/components/native-reminder-settings';
import { useAuth, useAuthenticatedApi } from '@/auth/auth-context';
import {
  getNativeExpoPushToken,
  getNativeNotificationPermission,
  requestNativeNotificationPermission,
  type NativeNotificationPermission,
} from '@/notifications/native-reminders';

jest.mock('expo-router', () => {
  const React = jest.requireActual('react');

  return {
    useFocusEffect: (effect: () => void) => {
      React.useEffect(effect, [effect]);
    },
  };
});
jest.mock('@/auth/auth-context', () => ({
  useAuth: jest.fn(),
  useAuthenticatedApi: jest.fn(),
}));
jest.mock('@/notifications/native-reminders', () => ({
  getNativeExpoPushToken: jest.fn(),
  getNativeNotificationPermission: jest.fn(),
  requestNativeNotificationPermission: jest.fn(),
}));

const mockedUseAuth = jest.mocked(useAuth);
const mockedUseAuthenticatedApi = jest.mocked(useAuthenticatedApi);
const mockedGetNativeExpoPushToken = jest.mocked(getNativeExpoPushToken);
const mockedGetNativeNotificationPermission = jest.mocked(getNativeNotificationPermission);
const mockedRequestNativeNotificationPermission = jest.mocked(requestNativeNotificationPermission);

let preferenceEnabled = false;
let registrationPresent = false;
let failPreferenceUpdate = false;
let failRegistration = false;
let failUnregistration = false;
const request = jest.fn();

function renderNativeReminderSettings() {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: { retry: false, gcTime: Infinity },
      mutations: { retry: false, gcTime: Infinity },
    },
  });

  return render(
    <QueryClientProvider client={queryClient}>
      <NativeReminderSettings />
    </QueryClientProvider>,
  );
}

describe('native reminder settings', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    preferenceEnabled = false;
    registrationPresent = false;
    failPreferenceUpdate = false;
    failRegistration = false;
    failUnregistration = false;
    mockedUseAuth.mockReturnValue({ status: 'authenticated' } as ReturnType<typeof useAuth>);
    mockedUseAuthenticatedApi.mockReturnValue(request);
    request.mockImplementation(async (path: string, options?: { method?: string; body?: unknown }) => {
      if (path === '/api/v1/native-reminder-preferences' && !options?.method) {
        return { data: { enabled: preferenceEnabled, reading_timezone: 'America/Toronto' } };
      }

      if (path === '/api/v1/native-push-registration' && !options?.method) {
        return { data: { registered: registrationPresent } };
      }

      if (path === '/api/v1/native-reminder-preferences' && options?.method === 'PUT') {
        if (failPreferenceUpdate && (options.body as { enabled: boolean }).enabled) {
          throw new Error('Preference update failed.');
        }

        preferenceEnabled = Boolean((options.body as { enabled: boolean }).enabled);
        return { data: { enabled: preferenceEnabled, reading_timezone: 'America/Toronto' } };
      }

      if (path === '/api/v1/native-push-registration' && options?.method === 'PUT') {
        if (failRegistration) {
          throw new Error('Registration failed.');
        }

        registrationPresent = true;
        return { data: { registered: true } };
      }

      if (path === '/api/v1/native-push-registration' && options?.method === 'DELETE') {
        if (failUnregistration) {
          throw new Error('Unregistration failed.');
        }

        registrationPresent = false;
        return undefined;
      }

      throw new Error(`Unexpected request: ${path}`);
    });
    mockedGetNativeNotificationPermission.mockResolvedValue({
      granted: false,
      canAskAgain: true,
      expires: 'never',
      ios: undefined,
      android: undefined,
      status: 'denied' as NativeNotificationPermission['status'],
    });
    mockedRequestNativeNotificationPermission.mockResolvedValue({
      granted: true,
      canAskAgain: true,
      expires: 'never',
      ios: undefined,
      android: undefined,
      status: 'granted' as NativeNotificationPermission['status'],
    });
    mockedGetNativeExpoPushToken.mockResolvedValue('ExpoPushToken[test-address]');
  });

  it('does not prompt for permission while loading the Settings screen', async () => {
    renderNativeReminderSettings();

    await waitFor(() => expect(screen.getByLabelText('Reading reminders')).toBeOnTheScreen());

    expect(mockedRequestNativeNotificationPermission).not.toHaveBeenCalled();
    expect(mockedGetNativeNotificationPermission).toHaveBeenCalled();
  });

  it('registers the address before enabling the backend preference', async () => {
    renderNativeReminderSettings();
    await waitFor(() => expect(screen.getByLabelText('Reading reminders')).toBeOnTheScreen());

    await fireEvent(screen.getByLabelText('Reading reminders'), 'valueChange', true);

    await waitFor(() => expect(request).toHaveBeenCalledWith(
      '/api/v1/native-reminder-preferences',
      { method: 'PUT', body: { enabled: true } },
    ));

    const registrationCall = request.mock.calls.findIndex(([, options]) => (
      options?.method === 'PUT'
      && (options.body as { expo_push_token?: string })?.expo_push_token
    ));
    const preferenceCall = request.mock.calls.findIndex(([, options]) => (
      options?.method === 'PUT'
      && (options.body as { enabled?: boolean })?.enabled === true
    ));

    expect(registrationCall).toBeGreaterThanOrEqual(0);
    expect(registrationCall).toBeLessThan(preferenceCall);
    expect(mockedRequestNativeNotificationPermission).toHaveBeenCalledTimes(1);
    expect(mockedGetNativeExpoPushToken).toHaveBeenCalledTimes(1);
  });

  it('leaves the backend disabled when permission is denied', async () => {
    mockedRequestNativeNotificationPermission.mockResolvedValueOnce({
      granted: false,
      canAskAgain: false,
      expires: 'never',
      ios: undefined,
      android: undefined,
      status: 'denied' as NativeNotificationPermission['status'],
    });
    renderNativeReminderSettings();
    await waitFor(() => expect(screen.getByLabelText('Reading reminders')).toBeOnTheScreen());

    await fireEvent(screen.getByLabelText('Reading reminders'), 'valueChange', true);

    await waitFor(() => expect(screen.getByText(
      'Notifications were not enabled. Allow notifications in system settings and try again.',
    )).toBeOnTheScreen());
    expect(request).not.toHaveBeenCalledWith(
      '/api/v1/native-reminder-preferences',
      expect.objectContaining({ method: 'PUT' }),
    );
    expect(mockedGetNativeExpoPushToken).not.toHaveBeenCalled();
  });

  it('leaves the backend disabled when address registration fails', async () => {
    failRegistration = true;
    renderNativeReminderSettings();
    await waitFor(() => expect(screen.getByLabelText('Reading reminders')).toBeOnTheScreen());

    await fireEvent(screen.getByLabelText('Reading reminders'), 'valueChange', true);

    await waitFor(() => expect(screen.getByText(
      'Notification settings could not be updated. Try again.',
    )).toBeOnTheScreen());
    expect(request).not.toHaveBeenCalledWith(
      '/api/v1/native-reminder-preferences',
      expect.objectContaining({ method: 'PUT' }),
    );
    expect(request).toHaveBeenCalledWith(
      '/api/v1/native-push-registration',
      { method: 'DELETE' },
    );
    expect(screen.getByLabelText('Reading reminders')).toHaveProp('value', false);
  });

  it('keeps intent visible when permission is later revoked', async () => {
    preferenceEnabled = true;
    registrationPresent = true;
    renderNativeReminderSettings();

    await waitFor(() => expect(screen.getByText(
      'Reminders are enabled, but system notifications are blocked. Open system settings to restore delivery.',
    )).toBeOnTheScreen());
    expect(screen.getByLabelText('Reading reminders')).toHaveProp('value', true);

    jest.spyOn(Linking, 'openSettings').mockResolvedValue(undefined);
    await fireEvent.press(screen.getByLabelText('Open notification settings'));
    expect(Linking.openSettings).toHaveBeenCalledTimes(1);
    jest.restoreAllMocks();
  });

  it('surfaces cleanup when a disabled preference still has a registration', async () => {
    preferenceEnabled = false;
    registrationPresent = true;
    renderNativeReminderSettings();

    await waitFor(() => expect(screen.getByLabelText('Retry notification cleanup')).toBeOnTheScreen());
    expect(screen.getByText(
      'Reminders are off, but the notification address could not be removed. Retry cleanup.',
    )).toBeOnTheScreen();
  });

  it('disables the preference before unregistering on explicit opt-out', async () => {
    preferenceEnabled = true;
    registrationPresent = true;
    mockedGetNativeNotificationPermission.mockResolvedValue({
      granted: true,
      canAskAgain: true,
      expires: 'never',
      ios: undefined,
      android: undefined,
      status: 'granted' as NativeNotificationPermission['status'],
    });
    renderNativeReminderSettings();
    await waitFor(() => expect(screen.getByLabelText('Reading reminders')).toHaveProp('value', true));

    await fireEvent(screen.getByLabelText('Reading reminders'), 'valueChange', false);
    await waitFor(() => expect(request).toHaveBeenCalledWith(
      '/api/v1/native-push-registration',
      { method: 'DELETE' },
    ));

    const preferenceCall = request.mock.calls.findIndex(([, options]) => (
      options?.method === 'PUT'
      && (options.body as { enabled?: boolean })?.enabled === false
    ));
    const unregisterCall = request.mock.calls.findIndex(([, options]) => options?.method === 'DELETE');

    expect(preferenceCall).toBeGreaterThanOrEqual(0);
    expect(preferenceCall).toBeLessThan(unregisterCall);
  });

  it('keeps the preference disabled when address cleanup fails', async () => {
    preferenceEnabled = true;
    registrationPresent = true;
    failUnregistration = true;
    mockedGetNativeNotificationPermission.mockResolvedValue({
      granted: true,
      canAskAgain: true,
      expires: 'never',
      ios: undefined,
      android: undefined,
      status: 'granted' as NativeNotificationPermission['status'],
    });
    renderNativeReminderSettings();
    await waitFor(() => expect(screen.getByLabelText('Reading reminders')).toHaveProp('value', true));

    await fireEvent(screen.getByLabelText('Reading reminders'), 'valueChange', false);

    await waitFor(() => expect(screen.getByText(
      'Reminders are off, but the notification address could not be removed. Retry cleanup.',
    )).toBeOnTheScreen());
    expect(preferenceEnabled).toBe(false);

    failUnregistration = false;
    await fireEvent.press(screen.getByLabelText('Retry notification cleanup'));
    await waitFor(() => expect(screen.queryByLabelText('Retry notification cleanup')).toBeNull());
  });

  it('surfaces cleanup when enable rollback cannot remove the address', async () => {
    failPreferenceUpdate = true;
    failUnregistration = true;
    renderNativeReminderSettings();
    await waitFor(() => expect(screen.getByLabelText('Reading reminders')).toBeOnTheScreen());

    await fireEvent(screen.getByLabelText('Reading reminders'), 'valueChange', true);

    await waitFor(() => expect(screen.getByLabelText('Retry notification cleanup')).toBeOnTheScreen());
    expect(screen.getByText(
      'Reminders are off, but the notification address could not be removed. Retry cleanup.',
    )).toBeOnTheScreen();
  });
});
