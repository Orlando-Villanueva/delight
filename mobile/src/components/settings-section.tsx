import { type ReactNode } from 'react';
import { Text, View } from 'react-native';

import { themeTokens } from '@/theme/tokens';
import { useTheme } from '@/theme/use-theme';

type SettingsSectionProps = {
  title: string;
  children: ReactNode;
};

export function SettingsSection({ title, children }: Readonly<SettingsSectionProps>) {
  const { colors } = useTheme();

  return (
    <View style={{ gap: 8 }}>
      <Text selectable style={{ color: colors.mutedText, fontSize: 13, fontWeight: '700' }}>
        {title}
      </Text>
      <View
        style={{
          overflow: 'hidden',
          borderWidth: 1,
          borderColor: colors.border,
          borderRadius: themeTokens.radius.card,
          borderCurve: 'continuous',
          backgroundColor: colors.surface,
        }}
      >
        {children}
      </View>
    </View>
  );
}
