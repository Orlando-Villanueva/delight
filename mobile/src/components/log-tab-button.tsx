import type { GestureResponderEvent } from 'react-native';
import { Pressable } from 'react-native';

import { BottomNavigationIcon, bottomNavigationIcons } from '@/components/bottom-navigation-icon';
import { useTheme } from '@/theme/use-theme';

type LogTabButtonProps = {
  bottom: number;
  isSelected?: boolean;
  onLongPress?: ((event: GestureResponderEvent) => void) | null;
  onPress?: ((event: GestureResponderEvent) => void) | null;
  testID?: string;
};

export function LogTabButton({
  bottom,
  isSelected = false,
  onLongPress,
  onPress,
  testID,
}: Readonly<LogTabButtonProps>) {
  const { colors } = useTheme();

  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel="Log a reading"
      accessibilityHint="Opens the reading log"
      accessibilityState={{ selected: isSelected }}
      onLongPress={onLongPress}
      onPress={onPress}
      testID={testID}
      style={({ pressed }) => ({
        position: 'absolute',
        right: '50%',
        bottom,
        width: 56,
        height: 56,
        alignItems: 'center',
        justifyContent: 'center',
        borderRadius: 28,
        backgroundColor: colors.accentAction,
        boxShadow: pressed ? '0 1px 2px rgba(59, 26, 10, 0.24)' : '0 3px 6px rgba(249, 115, 22, 0.3)',
        opacity: pressed ? 0.88 : 1,
        transform: [{ translateX: 28 }, { scale: pressed ? 0.94 : 1 }],
        zIndex: 1,
      })}
    >
      <BottomNavigationIcon
        color={colors.accentActionContrast}
        name={bottomNavigationIcons.log}
        size={30}
        testID="log-tab-icon"
      />
    </Pressable>
  );
}
