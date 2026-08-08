import { Redirect, useSegments } from 'expo-router';
import type { PropsWithChildren } from 'react';
import { ActivityIndicator, Text, View } from 'react-native';

import { useAuth } from '@/auth/auth-context';
import { themeTokens } from '@/theme/tokens';
import { useTheme } from '@/theme/use-theme';

export function AuthGate({ children }: PropsWithChildren) {
  const { status } = useAuth();
  const segments = useSegments();
  const { colors } = useTheme();
  const isLoginRoute = segments[0] === '(auth)';

  if (status === 'loading') {
    return (
      <View
        accessibilityLiveRegion="polite"
        style={{ flex: 1, alignItems: 'center', justifyContent: 'center', gap: 12, backgroundColor: colors.background }}
      >
        <ActivityIndicator accessibilityLabel="Restoring your session" color={colors.primary} />
        <Text selectable style={{ color: colors.mutedText, fontSize: 16 }}>
          Opening Delight…
        </Text>
      </View>
    );
  }

  if (status === 'unauthenticated' && !isLoginRoute) {
    return <Redirect href="/(auth)/login" />;
  }

  if (status === 'authenticated' && isLoginRoute) {
    return <Redirect href="/(tabs)/home" />;
  }

  return <View style={{ flex: 1, minHeight: themeTokens.minimumTouchTarget }}>{children}</View>;
}
