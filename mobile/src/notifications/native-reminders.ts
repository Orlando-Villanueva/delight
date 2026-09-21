import Constants from 'expo-constants';
import * as Notifications from 'expo-notifications';
import { Platform } from 'react-native';

export const nativeReminderNotificationChannelId = 'reading-reminders';

export type NativeNotificationPermission = Awaited<
  ReturnType<typeof Notifications.getPermissionsAsync>
>;

export async function getNativeNotificationPermission(): Promise<NativeNotificationPermission> {
  return Notifications.getPermissionsAsync();
}

export async function requestNativeNotificationPermission(): Promise<NativeNotificationPermission> {
  if (Platform.OS === 'android') {
    await Notifications.setNotificationChannelAsync(nativeReminderNotificationChannelId, {
      name: 'Reading reminders',
      importance: Notifications.AndroidImportance.DEFAULT,
    });
  }

  const existingPermission = await Notifications.getPermissionsAsync();

  if (existingPermission.granted) {
    return existingPermission;
  }

  return Notifications.requestPermissionsAsync();
}

export async function getNativeExpoPushToken(): Promise<string> {
  const projectId = Constants.expoConfig?.extra?.eas?.projectId ?? Constants.easConfig?.projectId;

  if (!projectId) {
    throw new Error('The Expo project is not configured for notifications.');
  }

  return (await Notifications.getExpoPushTokenAsync({ projectId })).data;
}
