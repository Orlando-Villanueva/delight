import * as Notifications from 'expo-notifications';
import { Platform } from 'react-native';

import {
  getNativeExpoPushToken,
  getNativeNotificationPermission,
  nativeReminderNotificationChannelId,
  requestNativeNotificationPermission,
} from '@/notifications/native-reminders';

jest.mock('expo-constants', () => ({
  expoConfig: { extra: { eas: { projectId: 'eas-project-id' } } },
  easConfig: undefined,
}));
jest.mock('expo-notifications', () => ({
  AndroidImportance: { DEFAULT: 3 },
  getExpoPushTokenAsync: jest.fn(),
  getPermissionsAsync: jest.fn(),
  PermissionStatus: { DENIED: 'denied', GRANTED: 'granted' },
  requestPermissionsAsync: jest.fn(),
  setNotificationChannelAsync: jest.fn(),
}));

const mockedNotifications = jest.mocked(Notifications);

describe('native notification registration', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    Object.defineProperty(Platform, 'OS', { configurable: true, value: 'android' });
    mockedNotifications.setNotificationChannelAsync.mockResolvedValue(null);
    mockedNotifications.getPermissionsAsync.mockResolvedValue({
      granted: false,
      canAskAgain: true,
      expires: 'never',
      ios: undefined,
      android: undefined,
      status: Notifications.PermissionStatus.DENIED,
    });
    mockedNotifications.requestPermissionsAsync.mockResolvedValue({
      granted: true,
      canAskAgain: true,
      expires: 'never',
      ios: undefined,
      android: undefined,
      status: Notifications.PermissionStatus.GRANTED,
    });
    mockedNotifications.getExpoPushTokenAsync.mockResolvedValue({
      type: 'expo',
      data: 'ExpoPushToken[test-address]',
    });
  });

  afterEach(() => {
    Object.defineProperty(Platform, 'OS', { configurable: true, value: 'ios' });
  });

  it('reads permission without prompting', async () => {
    await expect(getNativeNotificationPermission()).resolves.toMatchObject({ granted: false });

    expect(mockedNotifications.requestPermissionsAsync).not.toHaveBeenCalled();
  });

  it('creates the Android channel and requests permission only when asked', async () => {
    await expect(requestNativeNotificationPermission()).resolves.toMatchObject({ granted: true });

    expect(mockedNotifications.setNotificationChannelAsync).toHaveBeenCalledWith(
      nativeReminderNotificationChannelId,
      expect.objectContaining({ name: 'Reading reminders' }),
    );
    expect(mockedNotifications.requestPermissionsAsync).toHaveBeenCalledTimes(1);
  });

  it('uses the configured EAS project when obtaining an Expo address', async () => {
    await expect(getNativeExpoPushToken()).resolves.toBe('ExpoPushToken[test-address]');

    expect(mockedNotifications.getExpoPushTokenAsync).toHaveBeenCalledWith({
      projectId: 'eas-project-id',
    });
  });
});
