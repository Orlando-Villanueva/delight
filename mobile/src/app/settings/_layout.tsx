import { Stack } from 'expo-router/stack';

import { AuthGate } from '@/auth/auth-gate';
import { useTheme } from '@/theme/use-theme';

export default function SettingsLayout() {
  const { colors } = useTheme();

  return (
    <AuthGate>
      <Stack
        screenOptions={{
          contentStyle: { backgroundColor: colors.background },
          headerStyle: { backgroundColor: colors.surface },
          headerTintColor: colors.text,
          headerShadowVisible: false,
        }}
      >
        <Stack.Screen name="index" options={{ title: 'Settings' }} />
      </Stack>
    </AuthGate>
  );
}
