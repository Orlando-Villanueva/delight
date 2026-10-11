import { type ReactNode, useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
  Animated,
  type DimensionValue,
  Modal,
  KeyboardAvoidingView,
  PanResponder,
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
const sheetExitMs = 220;
const upwardTravelLimit = 24;
export type SheetDismissReason = 'back' | 'close' | 'backdrop' | 'drag';

type BottomSheetProps = {
  visible: boolean;
  title: string;
  onClose: () => void;
  children: ReactNode;
  dismissAccessibilityLabel: string;
  dismissAccessibilityHint: string;
  closeAccessibilityLabel: string;
  closeAccessibilityHint: string;
  closeLabel?: string;
  maxHeight?: DimensionValue;
  /**
   * Reserve extra space below chrome for the system gesture inset.
   * Leave off for tall scrollable sheets so the list can sit closer to the bottom.
   */
  padBottomSafeArea?: boolean;
  paddingBottom?: number;
  draggable?: boolean;
  dismissDisabled?: boolean;
  avoidKeyboard?: boolean;
  onBeforeDismiss?: (reason: SheetDismissReason) => boolean | Promise<boolean>;
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
  closeLabel = 'Close',
  maxHeight,
  padBottomSafeArea = false,
  paddingBottom,
  draggable = false,
  dismissDisabled = false,
  avoidKeyboard = false,
  onBeforeDismiss,
}: Readonly<BottomSheetProps>) {
  const { colors } = useTheme();
  const insets = useSafeAreaInsets();
  const { height } = useWindowDimensions();
  const [overlayOpacity] = useState(() => new Animated.Value(0));
  const [sheetTranslateY] = useState(() => new Animated.Value(height));

  const closingRef = useRef(false);
  const checkingDismissRef = useRef(false);
  const animationRef = useRef<Animated.CompositeAnimation | null>(null);
  const onCloseRef = useRef(onClose);
  useEffect(() => {
    onCloseRef.current = onClose;
  }, [onClose]);

  const dismiss = useCallback(async (reason: SheetDismissReason) => {
    if (closingRef.current || checkingDismissRef.current || dismissDisabled) {
      return;
    }

    if (onBeforeDismiss) {
      checkingDismissRef.current = true;
      Animated.spring(sheetTranslateY, {
        toValue: 0, useNativeDriver: true, overshootClamping: true,
      }).start();
      try {
        if (!await onBeforeDismiss(reason)) {
          return;
        }
      } finally {
        checkingDismissRef.current = false;
      }
    }

    closingRef.current = true;
    animationRef.current?.stop();
    const animation = Animated.parallel([
      Animated.timing(overlayOpacity, {
        toValue: 0, duration: sheetExitMs, useNativeDriver: true,
      }),
      Animated.timing(sheetTranslateY, {
        toValue: height, duration: sheetExitMs, useNativeDriver: true,
      }),
    ]);
    animationRef.current = animation;
    animation.start(({ finished }) => {
      if (finished) {
        onCloseRef.current();
      }
    });
  }, [dismissDisabled, height, onBeforeDismiss, overlayOpacity, sheetTranslateY]);

  const returnToOpen = useCallback(() => {
    if (closingRef.current) {
      return;
    }

    const animation = Animated.spring(sheetTranslateY, {
      toValue: 0, useNativeDriver: true, overshootClamping: true,
    });
    animationRef.current = animation;
    animation.start();
  }, [sheetTranslateY]);

  const shouldClaimHandle = useCallback(() => (
    !closingRef.current && !checkingDismissRef.current && !dismissDisabled
  ), [dismissDisabled]);
  const shouldStartDrag = useCallback((_: unknown, gesture: { dy: number; dx: number }) => (
    !closingRef.current && !checkingDismissRef.current && !dismissDisabled
      && Math.abs(gesture.dy) > 5 && Math.abs(gesture.dy) > Math.abs(gesture.dx)
  ), [dismissDisabled]);
  const startDrag = useCallback(() => {
    animationRef.current?.stop();
    sheetTranslateY.setValue(0);
    overlayOpacity.setValue(1);
  }, [overlayOpacity, sheetTranslateY]);
  const moveDrag = useCallback((_: unknown, gesture: { dy: number }) => {
    if (!closingRef.current) {
      const translation = gesture.dy < 0
        ? gesture.dy / (4 + Math.abs(gesture.dy) / upwardTravelLimit)
        : gesture.dy;
      sheetTranslateY.setValue(translation);
    }
  }, [sheetTranslateY]);
  const releaseDrag = useCallback((_: unknown, gesture: { dy: number; vy: number }) => {
    if (gesture.dy > 80 || (gesture.dy > 12 && gesture.vy > 0.7)) {
      void dismiss('drag');
    } else {
      returnToOpen();
    }
  }, [dismiss, returnToOpen]);
  // PanResponder stores these callbacks without invoking them during render.
  // eslint-disable-next-line react-hooks/refs
  const dragResponder = useMemo(() => PanResponder.create({
    onStartShouldSetPanResponder: shouldClaimHandle,
    onMoveShouldSetPanResponder: shouldStartDrag,
    onPanResponderGrant: startDrag,
    onPanResponderMove: moveDrag,
    onPanResponderRelease: releaseDrag,
    onPanResponderTerminate: returnToOpen,
  }), [moveDrag, releaseDrag, returnToOpen, shouldClaimHandle, shouldStartDrag, startDrag]);

  useEffect(() => {
    closingRef.current = false;
    if (!visible) {
      return;
    }

    overlayOpacity.setValue(0);
    sheetTranslateY.setValue(height);

    const animation = Animated.parallel([
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
    ]);
    animationRef.current = animation;
    animation.start();

    return () => animationRef.current?.stop();
  }, [height, overlayOpacity, sheetTranslateY, visible]);

  return (
    <Modal
      testID="bottom-sheet-modal"
      animationType="none"
      transparent
      visible={visible}
      onRequestClose={() => { void dismiss('back'); }}
      accessibilityViewIsModal
    >
      <KeyboardAvoidingView
        enabled={avoidKeyboard}
        behavior="padding"
        style={{ flex: 1, justifyContent: 'flex-end' }}
      >
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
          onPress={() => { void dismiss('backdrop'); }}
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
          {draggable ? (
            <View
              pointerEvents="none"
              style={{
                position: 'absolute',
                left: 0,
                right: 0,
                bottom: -upwardTravelLimit,
                height: upwardTravelLimit,
                backgroundColor: colors.surface,
              }}
            />
          ) : null}
          {draggable ? (
            <View
              {...dragResponder.panHandlers}
              collapsable={false}
              testID="bottom-sheet-drag-handle"
              accessible={false}
              style={{
                minHeight: themeTokens.minimumTouchTarget,
                alignItems: 'center',
                justifyContent: 'center',
                marginTop: -themeTokens.spacing.screen,
                marginBottom: -themeTokens.spacing.section,
              }}
            >
              <View
                pointerEvents="none"
                style={{ width: 36, height: 4, borderRadius: 2, backgroundColor: colors.mutedText }}
              />
            </View>
          ) : null}
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
              onPress={() => { void dismiss('close'); }}
              disabled={dismissDisabled}
              accessibilityState={{ disabled: dismissDisabled }}
              style={{
                minHeight: themeTokens.minimumTouchTarget,
                minWidth: themeTokens.minimumTouchTarget,
                justifyContent: 'center',
                alignItems: 'flex-end',
              }}
            >
              <Text style={{ color: colors.primary, fontSize: 16, fontWeight: '600' }}>
                {closeLabel}
              </Text>
            </Pressable>
          </View>
          {children}
        </Animated.View>
      </KeyboardAvoidingView>
    </Modal>
  );
}
