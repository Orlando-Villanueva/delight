import type { AccessibilityState, GestureResponderEvent } from 'react-native';
import { Pressable, Text, View } from 'react-native';

import { useTheme } from '@/theme/use-theme';

type LogTabButtonProps = {
  accessibilityState?: AccessibilityState;
  onLongPress?: ((event: GestureResponderEvent) => void) | null;
  onPress?: ((event: GestureResponderEvent) => void) | null;
  testID?: string;
};

export function LogTabButton({ accessibilityState, onLongPress, onPress, testID }: Readonly<LogTabButtonProps>) {
  const { colors } = useTheme();
  const isSelected = accessibilityState?.selected === true;

  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel="Log a reading"
      accessibilityHint="Opens the reading log"
      accessibilityState={{ selected: isSelected }}
      onLongPress={onLongPress}
      onPress={onPress}
      testID={testID}
      style={{
        top: -20,
        flex: 1,
        minHeight: 64,
        alignItems: 'center',
        justifyContent: 'center',
      }}
    >
      {({ pressed }) => (
        <View
          pointerEvents="none"
          style={{
            position: 'absolute',
            top: 0,
            width: 56,
            height: 56,
            alignItems: 'center',
            justifyContent: 'center',
            borderRadius: 28,
            backgroundColor: colors.accentAction,
            boxShadow: pressed ? '0 1px 2px rgba(59, 26, 10, 0.24)' : '0 3px 6px rgba(249, 115, 22, 0.3)',
            opacity: pressed ? 0.88 : 1,
            transform: [{ scale: pressed ? 0.94 : 1 }],
          }}
        >
          <Text
            selectable
            style={{ color: colors.accentActionContrast, fontSize: 30, fontWeight: '700', lineHeight: 34 }}
          >
            +
          </Text>
        </View>
      )}
    </Pressable>
  );
}
