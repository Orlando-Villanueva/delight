import {
  fetchNativePushRegistration,
  fetchNativeReminderPreference,
  registerNativePush,
  unregisterNativePush,
  updateNativeReminderPreference,
} from '@/api/native-reminders';

const request = jest.fn();

describe('native reminder API', () => {
  beforeEach(() => {
    request.mockReset();
  });

  it('fetches the current preference', async () => {
    request.mockResolvedValue({
      data: { enabled: true, reading_timezone: 'America/Toronto' },
    });

    await expect(fetchNativeReminderPreference(request)).resolves.toEqual({
      enabled: true,
      reading_timezone: 'America/Toronto',
    });
    expect(request).toHaveBeenCalledWith('/api/v1/native-reminder-preferences');
  });

  it('updates the preference with the PUT contract', async () => {
    request.mockResolvedValue({
      data: { enabled: true, reading_timezone: 'America/Toronto' },
    });

    await updateNativeReminderPreference(request, true);

    expect(request).toHaveBeenCalledWith('/api/v1/native-reminder-preferences', {
      method: 'PUT',
      body: { enabled: true },
    });
  });

  it('fetches, registers, and unregisters the current push address', async () => {
    request
      .mockResolvedValueOnce({ data: { registered: false } })
      .mockResolvedValueOnce({ data: { registered: true } })
      .mockResolvedValueOnce(undefined);

    await expect(fetchNativePushRegistration(request)).resolves.toEqual({ registered: false });
    await expect(registerNativePush(request, 'ExpoPushToken[test-address]')).resolves.toEqual({
      registered: true,
    });
    await expect(unregisterNativePush(request)).resolves.toBeUndefined();

    expect(request).toHaveBeenNthCalledWith(1, '/api/v1/native-push-registration');
    expect(request).toHaveBeenNthCalledWith(2, '/api/v1/native-push-registration', {
      method: 'PUT',
      body: { expo_push_token: 'ExpoPushToken[test-address]' },
    });
    expect(request).toHaveBeenNthCalledWith(3, '/api/v1/native-push-registration', {
      method: 'DELETE',
    });
  });
});
