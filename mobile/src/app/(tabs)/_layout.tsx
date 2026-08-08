import { Tabs } from 'expo-router';

import { AuthGate } from '@/auth/auth-gate';
import { themeTokens } from '@/theme/tokens';
import { useTheme } from '@/theme/use-theme';

export default function TabsLayout() {
  const { colors } = useTheme();

  return (
    <AuthGate>
      <Tabs
        initialRouteName="home"
        screenOptions={{
          headerShown: false,
          tabBarActiveTintColor: colors.primary,
          tabBarInactiveTintColor: colors.mutedText,
          tabBarStyle: {
            minHeight: themeTokens.minimumTouchTarget,
            backgroundColor: colors.surface,
            borderTopColor: colors.border,
          },
        }}
      >
        <Tabs.Screen
          name="home"
          options={{ title: 'Home', tabBarAccessibilityLabel: 'Home tab' }}
        />
        <Tabs.Screen
          name="log"
          options={{ title: 'Log', tabBarAccessibilityLabel: 'Log a reading tab' }}
        />
        <Tabs.Screen
          name="history"
          options={{ title: 'History', tabBarAccessibilityLabel: 'Reading history tab' }}
        />
      </Tabs>
    </AuthGate>
  );
}
