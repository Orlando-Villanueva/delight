import { MaterialCommunityIcons } from '@expo/vector-icons';
import { useQuery } from '@tanstack/react-query';
import { useEffect, useState } from 'react';
import { AccessibilityInfo, Image, Pressable, Text, View } from 'react-native';

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

type AccountAvatarProps = {
  color: string;
  initials: string | null;
  url?: string | null;
  size: number;
};

function AccountAvatar({ color, initials, url, size }: AccountAvatarProps) {
  const [isLoaded, setIsLoaded] = useState(false);
  const [hasFailed, setHasFailed] = useState(false);

  const borderRadius = size / 2;

  return (
    <View
      accessible={false}
      style={{
        width: size,
        height: size,
        alignItems: 'center',
        justifyContent: 'center',
        borderRadius,
        overflow: 'hidden',
      }}
    >
      {initials ? (
        <Text
          accessible={false}
          adjustsFontSizeToFit
          minimumFontScale={0.7}
          numberOfLines={1}
          style={{ color, fontSize: size * 0.4, fontWeight: '700' }}
        >
          {initials}
        </Text>
      ) : (
        <MaterialCommunityIcons
          accessible={false}
          color={color}
          name="account"
          size={size * 0.625}
        />
      )}
      {url && !hasFailed ? (
        <Image
          accessible={false}
          testID="account-avatar-image"
          source={{ uri: url }}
          resizeMode="cover"
          onLoad={() => setIsLoaded(true)}
          onError={() => setHasFailed(true)}
          style={{
            position: 'absolute',
            width: size,
            height: size,
            opacity: isLoaded ? 1 : 0,
          }}
        />
      ) : null}
    </View>
  );
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
          testID="header-account-avatar"
          accessible={false}
          style={{
            width: 32,
            height: 32,
            alignItems: 'center',
            justifyContent: 'center',
            borderRadius: 16,
            backgroundColor: colors.primarySubtle,
            overflow: 'hidden',
          }}
        >
          <AccountAvatar
            key={user?.avatar_url ?? 'fallback'}
            color={colors.primary}
            initials={initials}
            url={user?.avatar_url}
            size={32}
          />
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
              <View
                style={{
                  width: 56,
                  height: 56,
                  borderRadius: 28,
                  backgroundColor: colors.primarySubtle,
                  overflow: 'hidden',
                  marginBottom: 8,
                }}
              >
                <AccountAvatar
                  key={user.avatar_url ?? 'fallback'}
                  color={colors.primary}
                  initials={initials}
                  url={user.avatar_url}
                  size={56}
                />
              </View>
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
