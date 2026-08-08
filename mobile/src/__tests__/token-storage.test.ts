import * as SecureStore from 'expo-secure-store';

import { tokenStorage } from '@/auth/token-storage';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(),
  setItemAsync: jest.fn(),
  deleteItemAsync: jest.fn(),
}));

describe('secure token storage', () => {
  it('reads, writes, and removes the bearer token through SecureStore only', async () => {
    jest.mocked(SecureStore.getItemAsync).mockResolvedValue('stored-token');

    await expect(tokenStorage.get()).resolves.toBe('stored-token');
    await tokenStorage.set('new-token');
    await tokenStorage.clear();

    expect(SecureStore.setItemAsync).toHaveBeenCalledWith('delight.auth-token', 'new-token');
    expect(SecureStore.deleteItemAsync).toHaveBeenCalledWith('delight.auth-token');
  });
});
