import { useMemo } from 'react';
import {
  ActivityIndicator,
  Pressable,
  Switch,
  Text,
  View,
} from 'react-native';

import { SettingsSection } from '@/components/settings-section';
import { useNativeReminderSettings } from '@/hooks/use-native-reminder-settings';
import { themeTokens } from '@/theme/tokens';
import { useTheme } from '@/theme/use-theme';

export function NativeReminderSettings() {
  const { colors } = useTheme();
  const {
    cleanupPending,
    enabled,
    error,
    hasQueryError,
    isBusy,
    isLoading,
    permissionWarning,
    openNotificationSettings,
    retryCleanup,
    retryLoading,
    toggle,
  } = useNativeReminderSettings();
  const buttonStyle = useMemo(() => ({
    alignSelf: 'flex-start' as const,
    minHeight: themeTokens.minimumTouchTarget,
    justifyContent: 'center' as const,
    paddingHorizontal: 12,
    borderRadius: themeTokens.radius.control,
  }), []);

  return (
    <SettingsSection title="NOTIFICATIONS">
      <View style={{ gap: 12, padding: 16 }}>
        <View style={{ flexDirection: 'row', alignItems: 'center', gap: 12 }}>
          <View style={{ flex: 1, gap: 4 }}>
            <Text selectable style={{ color: colors.text, fontSize: 16, fontWeight: '700' }}>
              Reading reminders
            </Text>
            <Text selectable style={{ color: colors.mutedText, fontSize: 14, lineHeight: 20 }}>
              Get a daily reminder and a warning when your active streak is at risk.
            </Text>
          </View>
          {isLoading ? (
            <ActivityIndicator accessibilityLabel="Checking reading reminder settings" color={colors.primary} />
          ) : (
            <Switch
              accessibilityLabel="Reading reminders"
              accessibilityHint="Enables or disables native reading reminders on this device"
              accessibilityState={{ disabled: isBusy || hasQueryError, checked: enabled }}
              disabled={isBusy || hasQueryError}
              onValueChange={toggle}
              trackColor={{ false: colors.border, true: colors.primarySubtle }}
              thumbColor={enabled ? colors.primary : colors.mutedText}
              value={enabled}
            />
          )}
        </View>

        {permissionWarning ? (
          <View style={{ gap: 8 }}>
            <Text
              selectable
              accessibilityLiveRegion="polite"
              style={{ color: colors.accent, fontSize: 14, lineHeight: 20 }}
            >
              {permissionWarning}
            </Text>
            <Pressable
              accessibilityRole="button"
              accessibilityLabel="Open notification settings"
              accessibilityHint="Opens system settings so you can allow Delight notifications"
              onPress={() => void openNotificationSettings()}
              style={({ pressed }) => ({
                ...buttonStyle,
                backgroundColor: pressed ? colors.primarySubtle : colors.surface,
              })}
            >
              <Text selectable style={{ color: colors.primary, fontSize: 14, fontWeight: '700' }}>
                Open notification settings
              </Text>
            </Pressable>
          </View>
        ) : null}

        {cleanupPending ? (
          <View style={{ gap: 8 }}>
            <Text
              selectable
              accessibilityLiveRegion="assertive"
              style={{ color: colors.danger, fontSize: 14, lineHeight: 20 }}
            >
              Reminders are off, but the notification address could not be removed. Retry cleanup.
            </Text>
            <Pressable
              accessibilityRole="button"
              accessibilityLabel="Retry notification cleanup"
              accessibilityHint="Attempts to remove the saved notification address again"
              disabled={isBusy}
              onPress={retryCleanup}
              style={({ pressed }) => ({
                ...buttonStyle,
                backgroundColor: pressed ? colors.primarySubtle : colors.surface,
                opacity: isBusy ? 0.5 : 1,
              })}
            >
              <Text selectable style={{ color: colors.primary, fontSize: 14, fontWeight: '700' }}>
                Retry notification cleanup
              </Text>
            </Pressable>
          </View>
        ) : null}

        {hasQueryError ? (
          <Pressable
            accessibilityRole="button"
            accessibilityLabel="Retry loading notification settings"
            accessibilityHint="Loads your current reading reminder settings again"
            onPress={retryLoading}
            style={({ pressed }) => ({
              ...buttonStyle,
              backgroundColor: pressed ? colors.primarySubtle : colors.surface,
            })}
          >
            <Text selectable style={{ color: colors.primary, fontSize: 14, fontWeight: '700' }}>
              Retry loading notification settings
            </Text>
          </Pressable>
        ) : null}

        {error && !cleanupPending ? (
          <Text
            selectable
            accessibilityLiveRegion="assertive"
            style={{ color: colors.danger, fontSize: 14, lineHeight: 20 }}
          >
            {error}
          </Text>
        ) : null}
      </View>
    </SettingsSection>
  );
}
