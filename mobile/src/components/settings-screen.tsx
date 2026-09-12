import { MaterialCommunityIcons } from '@expo/vector-icons';
import * as Linking from 'expo-linking';
import { type ReactNode, useState } from 'react';
import { AccessibilityInfo, Pressable, ScrollView, Text, View } from 'react-native';

import { getWebBaseUrl } from '@/config/web-environment';
import { themeTokens } from '@/theme/tokens';
import { useTheme } from '@/theme/use-theme';

const supportEmail = 'orlando@mg.mydelight.app';
const externalResourceError = 'That resource could not be opened. Try again.';

type SettingsSectionProps = {
  title: string;
  children: ReactNode;
};

function SettingsSection({ title, children }: Readonly<SettingsSectionProps>) {
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

type SettingsLinkProps = {
  accessibilityHint: string;
  description: string;
  icon: keyof typeof MaterialCommunityIcons.glyphMap;
  label: string;
  onPress: () => void;
  isDanger?: boolean;
};

function SettingsLink({
  accessibilityHint,
  description,
  icon,
  label,
  onPress,
  isDanger = false,
}: Readonly<SettingsLinkProps>) {
  const { colors } = useTheme();
  const actionColor = isDanger ? colors.danger : colors.primary;

  return (
    <Pressable
      accessibilityRole="link"
      accessibilityLabel={label}
      accessibilityHint={accessibilityHint}
      onPress={onPress}
      style={({ pressed }) => ({
        minHeight: themeTokens.minimumTouchTarget,
        flexDirection: 'row',
        alignItems: 'center',
        gap: 12,
        padding: 16,
        backgroundColor: pressed ? colors.primarySubtle : colors.surface,
      })}
    >
      <MaterialCommunityIcons accessible={false} color={actionColor} name={icon} size={24} />
      <View style={{ flex: 1, gap: 3 }}>
        <Text selectable style={{ color: actionColor, fontSize: 16, fontWeight: '700' }}>
          {label}
        </Text>
        <Text selectable style={{ color: colors.mutedText, fontSize: 14, lineHeight: 20 }}>
          {description}
        </Text>
      </View>
      <MaterialCommunityIcons
        accessible={false}
        color={colors.mutedText}
        name="open-in-new"
        size={20}
      />
    </Pressable>
  );
}

export function SettingsScreen() {
  const { colors } = useTheme();
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const webUrl = getWebBaseUrl();

  async function openExternalResource(url: string): Promise<void> {
    setErrorMessage(null);

    try {
      await Linking.openURL(url);
    } catch {
      setErrorMessage(externalResourceError);
      AccessibilityInfo.announceForAccessibility(externalResourceError);
    }
  }

  return (
    <ScrollView
      contentInsetAdjustmentBehavior="automatic"
      contentContainerStyle={{
        gap: themeTokens.spacing.section,
        padding: themeTokens.spacing.screen,
        paddingBottom: 48,
      }}
      style={{ flex: 1, backgroundColor: colors.background }}
    >
      <Text selectable style={{ color: colors.mutedText, fontSize: 15, lineHeight: 22 }}>
        Manage your Delight preferences, support resources, and account.
      </Text>

      {errorMessage ? (
        <Text
          selectable
          accessibilityLiveRegion="assertive"
          style={{ color: colors.danger, fontSize: 15, lineHeight: 22 }}
        >
          {errorMessage}
        </Text>
      ) : null}

      <SettingsSection title="HELP & LEGAL">
        <SettingsLink
          accessibilityHint="Opens Delight's privacy policy in your browser"
          description="Learn how Delight collects, uses, and protects information."
          icon="shield-lock-outline"
          label="Privacy policy"
          onPress={() => void openExternalResource(`${webUrl}/privacy-policy`)}
        />
        <View style={{ height: 1, backgroundColor: colors.border, marginLeft: 52 }} />
        <SettingsLink
          accessibilityHint="Opens your email app to contact Delight support"
          description={supportEmail}
          icon="email-outline"
          label="Contact support"
          onPress={() => void openExternalResource(`mailto:${supportEmail}`)}
        />
      </SettingsSection>

      <SettingsSection title="ACCOUNT">
        <SettingsLink
          accessibilityHint="Opens the verified account deletion request form in your browser"
          description="Request deletion of your Delight account and associated reading data."
          icon="account-remove-outline"
          isDanger
          label="Request account deletion"
          onPress={() => void openExternalResource(`${webUrl}/account-deletion`)}
        />
      </SettingsSection>
    </ScrollView>
  );
}
