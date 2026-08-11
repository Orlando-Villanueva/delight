import { Tabs } from 'expo-router';
import type { BottomTabBarButtonProps } from 'expo-router/build/react-navigation/bottom-tabs';
import { View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { AuthGate } from '@/auth/auth-gate';
import { LogTabButton } from '@/components/log-tab-button';
import { StandardTabButton } from '@/components/standard-tab-button';
import { useTheme } from '@/theme/use-theme';

function renderLogTabButton(props: BottomTabBarButtonProps) {
  return <LogTabButton {...props} />;
}

function renderStandardTabButton(props: BottomTabBarButtonProps) {
  return <StandardTabButton {...props} />;
}

export default function TabsLayout() {
  const { colors } = useTheme();
  const insets = useSafeAreaInsets();

  return (
    <AuthGate>
      <Tabs
        initialRouteName="home"
        screenOptions={{
          headerShown: false,
          tabBarActiveTintColor: colors.primary,
          tabBarInactiveTintColor: colors.mutedText,
          tabBarStyle: {
            height: 84 + insets.bottom,
            paddingTop: 20,
            paddingBottom: insets.bottom,
            backgroundColor: 'transparent',
            borderTopWidth: 0,
          },
          tabBarBackground: () => (
            <View
              pointerEvents="none"
              style={{
                position: 'absolute',
                top: 20,
                right: 0,
                bottom: 0,
                left: 0,
                borderTopWidth: 1,
                borderTopColor: colors.border,
                backgroundColor: colors.surface,
              }}
            />
          ),
        }}
      >
        <Tabs.Screen
          name="home"
          options={{ title: 'Home', tabBarAccessibilityLabel: 'Home tab', tabBarButton: renderStandardTabButton }}
        />
        <Tabs.Screen
          name="log"
          options={{
            title: 'Log',
            tabBarAccessibilityLabel: 'Log a reading tab',
            tabBarButton: renderLogTabButton,
          }}
        />
        <Tabs.Screen
          name="history"
          options={{
            title: 'History',
            tabBarAccessibilityLabel: 'Reading history tab',
            tabBarButton: renderStandardTabButton,
          }}
        />
      </Tabs>
    </AuthGate>
  );
}
