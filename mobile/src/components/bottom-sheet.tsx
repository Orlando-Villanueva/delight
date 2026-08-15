import { type ReactNode, useEffect, useState } from 'react';
import {
  Animated,
  type DimensionValue,
  Modal,
  Pressable,
  Text,
  useWindowDimensions,
  View,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { themeTokens } from '@/theme/tokens';
import { useTheme } from '@/theme/use-theme';

const overlayColor = 'rgba(15, 23, 42, 0.48)';
const overlayEnterMs = 200;
const sheetEnterMs = 280;

type BottomSheetProps = {
  visible: boolean;
  title: string;
  onClose: () => void;
  children: ReactNode;
  dismissAccessibilityLabel: string;
  dismissAccessibilityHint: string;
  closeAccessibilityLabel: string;
  closeAccessibilityHint: string;
  maxHeight?: DimensionValue;
  /**
   * Reserve extra space below chrome for the system gesture inset.
   * Leave off for tall scrollable sheets so the list can sit closer to the bottom.
   */
  padBottomSafeArea?: boolean;
  paddingBottom?: number;
};

export function sheetPaddingBottom({
  padBottomSafeArea,
  paddingBottom,
  insetBottom,
}: {
  padBottomSafeArea: boolean;
  paddingBottom?: number;
  insetBottom: number;
}): number {
  if (paddingBottom !== undefined) {
    return paddingBottom;
  }

  if (!padBottomSafeArea) {
    return themeTokens.spacing.screen;
  }

  return Math.max(insetBottom, themeTokens.spacing.screen);
}

export function BottomSheet({
  visible,
  title,
  onClose,
  children,
  dismissAccessibilityLabel,
  dismissAccessibilityHint,
  closeAccessibilityLabel,
  closeAccessibilityHint,
  maxHeight,
  padBottomSafeArea = false,
  paddingBottom,
}: Readonly<BottomSheetProps>) {
  const { colors } = useTheme();
  const insets = useSafeAreaInsets();
  const { height } = useWindowDimensions();
  const [overlayOpacity] = useState(() => new Animated.Value(0));
  const [sheetTranslateY] = useState(() => new Animated.Value(height));

  useEffect(() => {
    if (!visible) {
      return;
    }

    overlayOpacity.setValue(0);
    sheetTranslateY.setValue(height);

    Animated.parallel([
      Animated.timing(overlayOpacity, {
        toValue: 1,
        duration: overlayEnterMs,
        useNativeDriver: true,
      }),
      Animated.timing(sheetTranslateY, {
        toValue: 0,
        duration: sheetEnterMs,
        useNativeDriver: true,
      }),
    ]).start();
  }, [height, overlayOpacity, sheetTranslateY, visible]);

  return (
    <Modal
      animationType="none"
      transparent
      visible={visible}
      onRequestClose={onClose}
      accessibilityViewIsModal
    >
      <View style={{ flex: 1, justifyContent: 'flex-end' }}>
        <Animated.View
          pointerEvents="none"
          style={{
            position: 'absolute',
            top: 0,
            right: 0,
            bottom: 0,
            left: 0,
            backgroundColor: overlayColor,
            opacity: overlayOpacity,
          }}
        />
        <Pressable
          accessibilityRole="button"
          accessibilityLabel={dismissAccessibilityLabel}
          accessibilityHint={dismissAccessibilityHint}
          onPress={onClose}
          style={{ position: 'absolute', top: 0, right: 0, bottom: 0, left: 0 }}
        />
        <Animated.View
          style={{
            maxHeight,
            padding: themeTokens.spacing.screen,
            paddingBottom: sheetPaddingBottom({
              padBottomSafeArea,
              paddingBottom,
              insetBottom: insets.bottom,
            }),
            borderTopLeftRadius: themeTokens.radius.card,
            borderTopRightRadius: themeTokens.radius.card,
            borderCurve: 'continuous',
            backgroundColor: colors.surface,
            gap: themeTokens.spacing.section,
            transform: [{ translateY: sheetTranslateY }],
          }}
        >
          <View
            style={{
              flexDirection: 'row',
              alignItems: 'center',
              justifyContent: 'space-between',
              gap: 12,
            }}
          >
            <Text
              selectable
              style={{
                color: colors.text,
                fontSize: 20,
                fontWeight: '700',
                flex: 1,
              }}
            >
              {title}
            </Text>
            <Pressable
              accessibilityRole="button"
              accessibilityLabel={closeAccessibilityLabel}
              accessibilityHint={closeAccessibilityHint}
              onPress={onClose}
              style={{
                minHeight: themeTokens.minimumTouchTarget,
                minWidth: themeTokens.minimumTouchTarget,
                justifyContent: 'center',
                alignItems: 'flex-end',
              }}
            >
              <Text style={{ color: colors.primary, fontSize: 16, fontWeight: '600' }}>
                Close
              </Text>
            </Pressable>
          </View>
          {children}
        </Animated.View>
      </View>
    </Modal>
  );
}
