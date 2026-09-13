import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { DarkTheme, DefaultTheme, ThemeProvider } from 'expo-router';
import { Stack } from 'expo-router/stack';
import { StatusBar } from 'expo-status-bar';
import * as SystemUI from 'expo-system-ui';
import { useEffect } from 'react';
import { View } from 'react-native';

import { AuthProvider } from '@/auth/auth-context';
import { shouldRetryQuery } from '@/api/retry-policy';
import { useTheme } from '@/theme/use-theme';

const queryClient = new QueryClient({
  defaultOptions: {
    queries: { retry: shouldRetryQuery },
    mutations: { retry: false },
  },
});

export default function RootLayout() {
  const { colors, mode } = useTheme();

  useEffect(() => {
    void SystemUI.setBackgroundColorAsync(colors.background).catch(() => {
      if (__DEV__) {
        console.warn('Could not update the native window background.');
      }
    });
  }, [colors.background]);

  const baseNavigationTheme = mode === 'dark' ? DarkTheme : DefaultTheme;
  const navigationTheme = {
    ...baseNavigationTheme,
    colors: {
      ...baseNavigationTheme.colors,
      background: colors.background,
      border: colors.border,
      card: colors.surface,
      primary: colors.primary,
      text: colors.text,
    },
  };

  return (
    <QueryClientProvider client={queryClient}>
      <AuthProvider>
        <ThemeProvider value={navigationTheme}>
          <View testID="app-background" style={{ flex: 1, backgroundColor: colors.background }}>
            <Stack
              screenOptions={{
                contentStyle: { backgroundColor: colors.background },
                headerShown: false,
              }}
            >
              <Stack.Screen name="index" />
              <Stack.Screen name="(auth)" />
              <Stack.Screen name="(tabs)" />
              <Stack.Screen name="settings" options={{ animation: 'fade' }} />
            </Stack>
            <StatusBar style="auto" />
          </View>
        </ThemeProvider>
      </AuthProvider>
    </QueryClientProvider>
  );
}
