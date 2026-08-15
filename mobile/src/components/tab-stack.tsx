import { Stack } from 'expo-router/stack';

import { AccountMenu } from '@/components/account-menu';
import { useTheme } from '@/theme/use-theme';

type TabStackProps = {
  title: string;
};

function renderAccountButton() {
  return <AccountMenu />;
}

export function TabStack({ title }: Readonly<TabStackProps>) {
  const { colors } = useTheme();

  return (
    <Stack
      screenOptions={{
        contentStyle: { backgroundColor: colors.background },
        headerStyle: { backgroundColor: colors.surface },
        headerTintColor: colors.text,
        headerShadowVisible: false,
      }}
    >
      <Stack.Screen name="index" options={{ title, headerRight: renderAccountButton }} />
    </Stack>
  );
}
