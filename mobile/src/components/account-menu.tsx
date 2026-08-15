import { MaterialCommunityIcons } from '@expo/vector-icons';
import { useQuery } from '@tanstack/react-query';
import { useEffect, useState } from 'react';
import { AccessibilityInfo, Pressable, Text, View } from 'react-native';

import { fetchBootstrap } from '@/api/bootstrap';
import { useAuth, useAuthenticatedApi, type AuthUser } from '@/auth/auth-context';
import { BottomSheet } from '@/components/bottom-sheet';
import { LogoutButton } from '@/components/logout-button';
import { themeTokens } from '@/theme/tokens';
import { useTheme } from '@/theme/use-theme';

export function accountInitials(name: string | undefined): string | null {
  if (!name) {
    return null;
  }

  const parts = name.trim().split(/\s+/).filter(Boolean);

  if (parts.length === 0) {
    return null;
  }

  const first = [...parts[0]][0];

  if (!first) {
    return null;
  }

  if (parts.length === 1) {
    return first.toLocaleUpperCase('en-CA');
  }

  const last = [...parts[parts.length - 1]][0];

  if (!last) {
    return null;
  }

  return `${first}${last}`.toLocaleUpperCase('en-CA');
}

function accountAccessibilityLabel(user: AuthUser | null): string {
  return user?.name ? `Account for ${user.name}` : 'Account';
}

function identityAnnouncement(user: AuthUser | null, isLoading: boolean): string {
  if (user) {
    return `Signed in as ${user.name}, ${user.email}.`;
  }

  if (isLoading) {
    return 'Loading account.';
  }

  return 'Account details are unavailable.';
}

export function AccountMenu() {
  const { user: sessionUser } = useAuth();
  const request = useAuthenticatedApi();
  const { colors } = useTheme();
  const [visible, setVisible] = useState(false);
  const { data, isLoading } = useQuery({
    queryKey: ['bootstrap'],
    queryFn: () => fetchBootstrap(request),
    enabled: sessionUser === null,
  });
  const user = sessionUser ?? data?.user ?? null;
  const isIdentityLoading = !user && isLoading;
  const initials = accountInitials(user?.name);

  useEffect(() => {
    if (!visible) {
      return;
    }

    AccessibilityInfo.announceForAccessibility(identityAnnouncement(user, isIdentityLoading));
  }, [isIdentityLoading, user, visible]);

  function close() {
    setVisible(false);
  }

  return (
    <>
      <Pressable
        accessibilityRole="button"
        accessibilityLabel={accountAccessibilityLabel(user)}
        accessibilityHint="Shows the signed-in account and sign out"
        accessibilityState={{ expanded: visible }}
        onPress={() => setVisible(true)}
        style={{
          minWidth: themeTokens.minimumTouchTarget,
          minHeight: themeTokens.minimumTouchTarget,
          alignItems: 'center',
          justifyContent: 'center',
          paddingHorizontal: 8,
        }}
      >
        <View
          accessible={false}
          style={{
            minWidth: 32,
            minHeight: 32,
            alignItems: 'center',
            justifyContent: 'center',
            paddingHorizontal: 6,
            borderRadius: 16,
            backgroundColor: colors.primarySubtle,
          }}
        >
          {initials ? (
            <Text
              accessible={false}
              adjustsFontSizeToFit
              minimumFontScale={0.7}
              numberOfLines={1}
              style={{
                color: colors.primary,
                fontSize: 13,
                fontWeight: '700',
              }}
            >
              {initials}
            </Text>
          ) : (
            <MaterialCommunityIcons
              accessible={false}
              color={colors.primary}
              name="account"
              size={20}
            />
          )}
        </View>
      </Pressable>
      <BottomSheet
        visible={visible}
        title="Account"
        onClose={close}
        padBottomSafeArea
        dismissAccessibilityLabel="Dismiss account"
        dismissAccessibilityHint="Closes the account details"
        closeAccessibilityLabel="Close account"
        closeAccessibilityHint="Closes the account details"
      >
        <View accessibilityLiveRegion="polite" style={{ gap: 4 }}>
          {user ? (
            <>
              <Text
                selectable
                style={{ color: colors.text, fontSize: 18, fontWeight: '700' }}
              >
                {user.name}
              </Text>
              <Text selectable style={{ color: colors.mutedText, fontSize: 16 }}>
                {user.email}
              </Text>
            </>
          ) : (
            <Text selectable style={{ color: colors.mutedText, fontSize: 16 }}>
              {isIdentityLoading
                ? 'Loading account…'
                : 'Account details are unavailable.'}
            </Text>
          )}
        </View>
        <View
          style={{
            borderTopWidth: 1,
            borderTopColor: colors.border,
            paddingTop: 8,
          }}
        >
          <LogoutButton />
        </View>
      </BottomSheet>
    </>
  );
}
