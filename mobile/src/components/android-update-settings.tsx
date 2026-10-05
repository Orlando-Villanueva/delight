import * as Linking from 'expo-linking';
import { useState } from 'react';
import { AccessibilityInfo, ActivityIndicator, Pressable, Text, View } from 'react-native';

import { SettingsSection } from '@/components/settings-section';
import { useManualAndroidUpdateCheck, type ManualAndroidUpdateState } from '@/hooks/use-manual-android-update-check';
import { themeTokens } from '@/theme/tokens';
import { useTheme } from '@/theme/use-theme';

const androidPageUrl = 'https://mydelight.app/android';
const playListingUrl = 'https://play.google.com/store/apps/details?id=com.orlandovillanueva.delight';
const linkErrorMessage = 'That resource could not be opened. Try again.';

type UpdateActionProps = {
  label: string;
  hint: string;
  onPress: () => void;
  disabled?: boolean;
  isLink?: boolean;
};

function UpdateAction({ label, hint, onPress, disabled = false, isLink = false }: Readonly<UpdateActionProps>) {
  const { colors } = useTheme();

  return (
    <Pressable
      accessibilityRole={isLink ? 'link' : 'button'}
      accessibilityLabel={label}
      accessibilityHint={hint}
      accessibilityState={{ disabled }}
      disabled={disabled}
      onPress={onPress}
      style={({ pressed }) => ({
        maxWidth: '100%',
        minHeight: themeTokens.minimumTouchTarget,
        justifyContent: 'center',
        paddingHorizontal: 12,
        borderRadius: themeTokens.radius.control,
        backgroundColor: pressed ? colors.primarySubtle : colors.surface,
        opacity: disabled ? 0.5 : 1,
      })}
    >
      <Text style={{ color: colors.primary, fontSize: 14, fontWeight: '700' }}>
        {label}
      </Text>
    </Pressable>
  );
}

function statusMessage(state: ManualAndroidUpdateState): string {
  switch (state.status) {
    case 'idle':
      return 'Find the latest Delight version.';
    case 'checking':
      return 'Checking for updates…';
    case 'up-to-date':
      return 'Delight is up to date.';
    case 'update-available':
      return `Delight ${state.release.version} is available.`;
    case 'play-managed':
      return 'Updates are managed by Google Play.';
    case 'unable-to-check':
      if (state.reason === 'unknown-installer') {
        return 'Unable to determine how Delight was installed. Visit the official Android page for update options.';
      }

      if (state.reason === 'unknown-version') {
        return 'Unable to determine the installed version. Visit the official Android page for update options.';
      }

      return 'Unable to check for updates. Try again.';
    case 'unsupported':
      return '';
  }
}

function checkLabel(state: ManualAndroidUpdateState): string {
  if (state.status === 'unable-to-check') {
    return 'Try again';
  }

  if (state.status === 'up-to-date' || state.status === 'update-available') {
    return 'Check again';
  }

  return 'Check for updates';
}

export function AndroidUpdateSettings() {
  const { colors } = useTheme();
  const { state, checkForUpdate } = useManualAndroidUpdateCheck();
  const [linkError, setLinkError] = useState<string | null>(null);

  async function openResource(url: string): Promise<void> {
    setLinkError(null);

    try {
      await Linking.openURL(url);
    } catch {
      setLinkError(linkErrorMessage);
      AccessibilityInfo.announceForAccessibility(linkErrorMessage);
    }
  }

  function check(): void {
    setLinkError(null);
    void checkForUpdate();
  }

  if (state.status === 'unsupported') {
    return null;
  }

  const canCheck = state.status === 'idle'
    || state.status === 'checking'
    || state.status === 'up-to-date'
    || (state.status === 'unable-to-check' && state.reason === 'request-failed');

  return (
    <SettingsSection title="UPDATES">
      <View style={{ gap: 8, padding: 16 }}>
        <View style={{ flexDirection: 'row', flexWrap: 'wrap', alignItems: 'center', gap: 12 }}>
          <View style={{ flexBasis: 160, flexGrow: 1, flexDirection: 'row', alignItems: 'center', gap: 8 }}>
            {state.status === 'checking' ? (
              <ActivityIndicator accessible={false} color={colors.primary} />
            ) : null}
            <Text
              selectable
              accessibilityLiveRegion={state.status === 'unable-to-check' ? 'assertive' : 'polite'}
              style={{
                flex: 1,
                color: state.status === 'unable-to-check' ? colors.danger : colors.mutedText,
                fontSize: 14,
                lineHeight: 20,
              }}
            >
              {statusMessage(state)}
            </Text>
          </View>

          {state.status === 'play-managed' ? (
            <UpdateAction
              isLink
              label="Open Google Play"
              hint="Opens Delight's Google Play listing"
              onPress={() => void openResource(playListingUrl)}
            />
          ) : null}

          {state.status === 'update-available' ? (
            <UpdateAction
              isLink
              label="View update"
              hint="Opens the official Delight Android page to review and download the update"
              onPress={() => void openResource(state.release.update_url)}
            />
          ) : null}

          {state.status === 'unable-to-check' && state.reason !== 'request-failed' ? (
            <UpdateAction
              isLink
              label="Visit Android page"
              hint="Opens the official Delight Android page for update options"
              onPress={() => void openResource(androidPageUrl)}
            />
          ) : null}

          {canCheck ? (
            <UpdateAction
              label={checkLabel(state)}
              hint="Checks the latest Android release against your installed version"
              disabled={state.status === 'checking'}
              onPress={check}
            />
          ) : null}

        </View>

        {linkError ? (
          <Text
            selectable
            accessibilityLiveRegion="assertive"
            style={{ color: colors.danger, fontSize: 14, lineHeight: 20 }}
          >
            {linkError}
          </Text>
        ) : null}
      </View>
    </SettingsSection>
  );
}
