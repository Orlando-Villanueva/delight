import { Stack } from 'expo-router/stack';

import { useTheme } from '@/theme/use-theme';

type TabStackProps = {
  title: string;
};

export function TabStack({ title }: TabStackProps) {
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
      <Stack.Screen name="index" options={{ title }} />
    </Stack>
  );
}
