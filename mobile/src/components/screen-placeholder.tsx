import { ScrollView, Text, View } from 'react-native';

import { themeTokens } from '@/theme/tokens';
import { useTheme } from '@/theme/use-theme';

type ScreenPlaceholderProps = {
  message: string;
};

export function ScreenPlaceholder({ message }: ScreenPlaceholderProps) {
  const { colors } = useTheme();

  return (
    <ScrollView
      contentInsetAdjustmentBehavior="automatic"
      style={{ backgroundColor: colors.background }}
      contentContainerStyle={{
        flexGrow: 1,
        justifyContent: 'center',
        padding: themeTokens.spacing.screen,
      }}
    >
      <View
        style={{
          gap: themeTokens.spacing.section,
          padding: themeTokens.spacing.screen,
          borderRadius: themeTokens.radius.card,
          borderCurve: 'continuous',
          borderWidth: 1,
          borderColor: colors.border,
          backgroundColor: colors.surface,
        }}
      >
        <Text selectable style={{ color: colors.text, fontSize: 20, fontWeight: '600' }}>
          {message}
        </Text>
        <Text selectable style={{ color: colors.mutedText, fontSize: 16, lineHeight: 24 }}>
          This foundation is ready for the next focused mobile issue.
        </Text>
      </View>
    </ScrollView>
  );
}
