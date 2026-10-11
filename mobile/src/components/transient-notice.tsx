import { MaterialCommunityIcons } from '@expo/vector-icons';
import { useCallback, useEffect, useRef, useState } from 'react';
import { AccessibilityInfo, Animated, Platform, Pressable, Text } from 'react-native';

import { themeTokens } from '@/theme/tokens';
import { useTheme } from '@/theme/use-theme';

export function TransientNotice({ message, duration = 5000, bottom = 40, onDismiss }: {
  message: string;
  duration?: number;
  bottom?: number;
  onDismiss: () => void;
}) {
  const { colors } = useTheme();
  const [progress] = useState(() => new Animated.Value(0));
  const reduceMotion = useRef(false);
  const closing = useRef(false);
  const dismiss = useCallback(() => {
    if (closing.current) return;
    closing.current = true;
    if (reduceMotion.current) {
      onDismiss();
      return;
    }
    Animated.timing(progress, {
      toValue: 0, duration: reduceMotion.current ? 0 : 160, useNativeDriver: true,
    }).start(({ finished }) => { if (finished) onDismiss(); });
  }, [onDismiss, progress]);

  useEffect(() => {
    let cancelled = false;
    closing.current = false;
    AccessibilityInfo.announceForAccessibility(message);
    async function enter() {
      reduceMotion.current = await Promise.resolve(AccessibilityInfo.isReduceMotionEnabled()).catch(() => false);
      if (cancelled) return;
      progress.setValue(reduceMotion.current ? 1 : 0);
      if (reduceMotion.current) return;
      Animated.timing(progress, {
        toValue: 1, duration: reduceMotion.current ? 0 : 180, useNativeDriver: true,
      }).start();
    }
    void enter();
    return () => { cancelled = true; progress.stopAnimation(); };
  }, [message, progress]);

  useEffect(() => {
    let cancelled = false;
    let timer: ReturnType<typeof setTimeout>;

    async function scheduleDismissal() {
      let timeout = duration;
      if (Platform.OS === 'android') {
        try {
          const recommended = await AccessibilityInfo.getRecommendedTimeoutMillis(duration);
          timeout = Math.max(duration, recommended || duration);
        } catch {
          // Use the default duration when the native accessibility preference is unavailable.
        }
      }
      if (!cancelled) timer = setTimeout(dismiss, timeout);
    }

    void scheduleDismissal();
    return () => {
      cancelled = true;
      clearTimeout(timer);
    };
  }, [duration, message, dismiss]);

  return (
    <Animated.View
      style={{
        position: 'absolute', bottom,
        left: themeTokens.spacing.screen, right: themeTokens.spacing.screen,
        flexDirection: 'row', alignItems: 'center', gap: 8,
        paddingLeft: 16, paddingRight: 4, paddingVertical: 8,
        backgroundColor: colors.noticeSurface,
        borderRadius: themeTokens.radius.control, elevation: 4,
        opacity: progress,
        transform: [{ translateY: progress.interpolate({ inputRange: [0, 1], outputRange: [12, 0] }) }],
      }}
    >
      <Text style={{ flex: 1, color: colors.noticeText, fontSize: 16, lineHeight: 24 }}>
        {message}
      </Text>
      <Pressable
        accessibilityRole="button"
        accessibilityLabel="Dismiss notification"
        onPress={dismiss}
        style={{ minWidth: 44, minHeight: 44, alignItems: 'center', justifyContent: 'center' }}
      >
        <MaterialCommunityIcons name="close" size={20} color={colors.noticeText} />
      </Pressable>
    </Animated.View>
  );
}
