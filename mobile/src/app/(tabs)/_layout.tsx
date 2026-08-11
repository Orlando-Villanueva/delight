import { Tabs, useRouter, useSegments } from 'expo-router';
import type { BottomTabBarButtonProps } from 'expo-router/build/react-navigation/bottom-tabs';
import { View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { AuthGate } from '@/auth/auth-gate';
import { LogTabButton } from '@/components/log-tab-button';
import { StandardTabButton } from '@/components/standard-tab-button';
import { useTheme } from '@/theme/use-theme';

function renderLogTabSpacer({ style }: BottomTabBarButtonProps) {
  return <View pointerEvents="none" style={[style, { minHeight: 64 }]} />;
}

function renderStandardTabButton(props: BottomTabBarButtonProps) {
  return <StandardTabButton {...props} />;
}

export default function TabsLayout() {
  const { colors } = useTheme();
  const insets = useSafeAreaInsets();
  const router = useRouter();
  const segments = useSegments();
  const isLogRoute = segments[1] === 'log';

  return (
    <AuthGate>
      <View style={{ flex: 1 }}>
        <Tabs
          initialRouteName="home"
          screenOptions={{
            headerShown: false,
            tabBarActiveTintColor: colors.primary,
            tabBarInactiveTintColor: colors.mutedText,
            tabBarStyle: {
              height: 64 + insets.bottom,
              paddingBottom: insets.bottom,
              backgroundColor: colors.surface,
              borderTopColor: colors.border,
            },
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
              tabBarButton: renderLogTabSpacer,
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
        <LogTabButton
          bottom={insets.bottom + 36}
          isSelected={isLogRoute}
          onPress={() => router.navigate('/(tabs)/log')}
        />
      </View>
    </AuthGate>
  );
}
