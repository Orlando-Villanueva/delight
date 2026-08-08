import * as SecureStore from 'expo-secure-store';

const tokenKey = 'delight.auth-token';

export const tokenStorage = {
  get: (): Promise<string | null> => SecureStore.getItemAsync(tokenKey),
  set: (token: string): Promise<void> => SecureStore.setItemAsync(tokenKey, token),
  clear: (): Promise<void> => SecureStore.deleteItemAsync(tokenKey),
};
