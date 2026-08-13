import { Tabs, usePathname, useRouter } from 'expo-router';
import type { BottomTabBarButtonProps } from 'expo-router/build/react-navigation/bottom-tabs';
import type { ColorValue } from 'react-native';
import { View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { AuthGate } from '@/auth/auth-gate';
import { BottomNavigationIcon, bottomNavigationIcons } from '@/components/bottom-navigation-icon';
import { LogTabButton } from '@/components/log-tab-button';
import { StandardTabButton } from '@/components/standard-tab-button';
import { useTheme } from '@/theme/use-theme';

function renderLogTabSpacer({ style }: BottomTabBarButtonProps) {
  return <View pointerEvents="none" style={[style, { minHeight: 64 }]} />;
}

function renderStandardTabButton(props: BottomTabBarButtonProps) {
  return <StandardTabButton {...props} />;
}

function renderHomeTabIcon({ color, size }: { color: ColorValue; size: number }) {
  return <BottomNavigationIcon color={color} name={bottomNavigationIcons.home} size={size} />;
}

function renderHistoryTabIcon({ color, size }: { color: ColorValue; size: number }) {
  return <BottomNavigationIcon color={color} name={bottomNavigationIcons.history} size={size} />;
}

export default function TabsLayout() {
  const { colors } = useTheme();
  const insets = useSafeAreaInsets();
  const router = useRouter();
  const isLogRoute = usePathname() === '/log';

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
            options={{
              title: 'Home',
              tabBarAccessibilityLabel: 'Home tab',
              tabBarButton: renderStandardTabButton,
              tabBarIcon: renderHomeTabIcon,
            }}
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
              tabBarIcon: renderHistoryTabIcon,
            }}
          />
        </Tabs>
        <LogTabButton
          bottom={insets.bottom + 20}
          isSelected={isLogRoute}
          onPress={() => router.navigate('/(tabs)/log')}
        />
      </View>
    </AuthGate>
  );
}
