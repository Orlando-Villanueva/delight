import type { ReactNode } from 'react';
import type {
  AccessibilityState,
  GestureResponderEvent,
  StyleProp,
  ViewStyle,
} from 'react-native';
import { Pressable, View } from 'react-native';

import { useTheme } from '@/theme/use-theme';

type StandardTabButtonProps = {
  accessibilityLabel?: string;
  accessibilityState?: AccessibilityState;
  children?: ReactNode;
  onLongPress?: ((event: GestureResponderEvent) => void) | null;
  onPress?: ((event: GestureResponderEvent) => void) | null;
  style?: StyleProp<ViewStyle>;
  testID?: string;
};

export function StandardTabButton({
  accessibilityLabel,
  accessibilityState,
  children,
  onLongPress,
  onPress,
  style,
  testID,
}: Readonly<StandardTabButtonProps>) {
  const { colors } = useTheme();

  return (
    <Pressable
      accessibilityLabel={accessibilityLabel}
      accessibilityRole="button"
      accessibilityState={accessibilityState}
      android_ripple={undefined}
      onLongPress={onLongPress}
      onPress={onPress}
      testID={testID}
      style={({ pressed }) => [
        style,
        {
          flex: 1,
          minHeight: 64,
          alignItems: 'center',
          justifyContent: 'center',
          opacity: pressed ? 0.82 : 1,
        },
      ]}
    >
      {({ pressed }) => (
        <>
          <View
            pointerEvents="none"
            style={{
              position: 'absolute',
              width: 52,
              height: 42,
              borderRadius: 21,
              backgroundColor: pressed ? colors.primarySubtle : 'transparent',
            }}
          />
          {children}
        </>
      )}
    </Pressable>
  );
}
