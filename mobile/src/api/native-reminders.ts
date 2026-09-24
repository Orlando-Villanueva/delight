type NativeReminderRequestOptions = {
  method?: 'GET' | 'PUT' | 'DELETE';
  body?: unknown;
};

export type NativeReminderRequest = <T>(
  path: string,
  options?: NativeReminderRequestOptions,
) => Promise<T>;

export type NativeReminderPreference = {
  enabled: boolean;
  reading_timezone: string;
};

export type NativePushRegistration = {
  registered: boolean;
};

type NativeReminderPreferenceResponse = {
  data: NativeReminderPreference;
};

type NativePushRegistrationResponse = {
  data: NativePushRegistration;
};

const nativeReminderPreferencesPath = '/api/v1/native-reminder-preferences';
const nativePushRegistrationPath = '/api/v1/native-push-registration';

export async function fetchNativeReminderPreference(
  request: NativeReminderRequest,
): Promise<NativeReminderPreference> {
  const response = await request<NativeReminderPreferenceResponse>(nativeReminderPreferencesPath);

  return response.data;
}

export async function updateNativeReminderPreference(
  request: NativeReminderRequest,
  enabled: boolean,
): Promise<NativeReminderPreference> {
  const response = await request<NativeReminderPreferenceResponse>(nativeReminderPreferencesPath, {
    method: 'PUT',
    body: { enabled },
  });

  return response.data;
}

export async function fetchNativePushRegistration(
  request: NativeReminderRequest,
): Promise<NativePushRegistration> {
  const response = await request<NativePushRegistrationResponse>(nativePushRegistrationPath);

  return response.data;
}

export async function registerNativePush(
  request: NativeReminderRequest,
  expoPushToken: string,
): Promise<NativePushRegistration> {
  const response = await request<NativePushRegistrationResponse>(nativePushRegistrationPath, {
    method: 'PUT',
    body: { expo_push_token: expoPushToken },
  });

  return response.data;
}

export async function unregisterNativePush(request: NativeReminderRequest): Promise<void> {
  await request<void>(nativePushRegistrationPath, { method: 'DELETE' });
}
