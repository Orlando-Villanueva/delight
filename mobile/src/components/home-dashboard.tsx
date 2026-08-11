import { useQuery } from '@tanstack/react-query';
import { useCallback, useEffect } from 'react';
import {
  AppState,
  type AppStateStatus,
  ActivityIndicator,
  Pressable,
  RefreshControl,
  ScrollView,
  Text,
  View,
} from 'react-native';

import { fetchBootstrap, type HomeDashboardData } from '@/api/bootstrap';
import { useAuthenticatedApi } from '@/auth/auth-context';
import { themeTokens } from '@/theme/tokens';
import { useTheme } from '@/theme/use-theme';

function StatCard({ label, value }: Readonly<{ label: string; value: string }>) {
  const { colors } = useTheme();

  return (
    <View
      style={{
        flex: 1,
        minWidth: 130,
        gap: 4,
        padding: themeTokens.spacing.section,
        borderWidth: 1,
        borderColor: colors.border,
        borderRadius: themeTokens.radius.card,
        backgroundColor: colors.surface,
      }}
    >
      <Text selectable style={{ color: colors.mutedText }}>{label}</Text>
      <Text
        selectable
        style={{ color: colors.primary, fontSize: 24, fontWeight: '700', fontVariant: ['tabular-nums'] }}
      >
        {value}
      </Text>
    </View>
  );
}

function ActivityStrip({ activity }: Readonly<{ activity: HomeDashboardData['activity'] }>) {
  const { colors } = useTheme();

  return (
    <View accessibilityLabel="Reading activity for the last 14 days" style={{ flexDirection: 'row', gap: 6 }}>
      {activity.map((entry) => (
        <View
          key={entry.date}
          accessible
          accessibilityLabel={`${entry.date}: ${entry.count} reading${entry.count === 1 ? '' : 's'}`}
          style={{
            flex: 1,
            minHeight: themeTokens.minimumTouchTarget,
            borderRadius: themeTokens.radius.control,
            backgroundColor: entry.count > 0 ? colors.primary : colors.border,
          }}
        />
      ))}
    </View>
  );
}

function LoadingState() {
  const { colors } = useTheme();

  return (
    <View accessibilityLiveRegion="polite" style={{ flex: 1, alignItems: 'center', justifyContent: 'center', gap: 12 }}>
      <ActivityIndicator color={colors.primary} />
      <Text selectable style={{ color: colors.mutedText }}>Loading your reading dashboard…</Text>
    </View>
  );
}

export function HomeDashboard() {
  const { colors } = useTheme();
  const request = useAuthenticatedApi();
  const { data, isPending, isRefetchError, isRefetching, refetch } = useQuery({
    queryKey: ['bootstrap'],
    queryFn: () => fetchBootstrap(request),
  });

  const refresh = useCallback(async () => {
    await refetch();
  }, [refetch]);

  useEffect(() => {
    function refreshWhenForegrounded(nextAppState: AppStateStatus) {
      if (nextAppState === 'active') {
        void refresh();
      }
    }

    return AppState.addEventListener('change', refreshWhenForegrounded).remove;
  }, [refresh]);

  if (isPending) {
    return <LoadingState />;
  }

  if (!data) {
    return (
      <View
        accessibilityLiveRegion="polite"
        style={{ flex: 1, alignItems: 'center', justifyContent: 'center', gap: themeTokens.spacing.section, padding: themeTokens.spacing.screen }}
      >
        <Text selectable style={{ color: colors.text, fontSize: 20, fontWeight: '700', textAlign: 'center' }}>
          Your dashboard could not be loaded
        </Text>
        <Text selectable style={{ color: colors.mutedText, textAlign: 'center' }}>
          Check your connection and try again.
        </Text>
        <Pressable
          accessibilityRole="button"
          accessibilityLabel="Retry loading dashboard"
          accessibilityHint="Requests your latest reading dashboard"
          onPress={() => void refresh()}
          style={{
            minHeight: themeTokens.minimumTouchTarget,
            justifyContent: 'center',
            paddingHorizontal: themeTokens.spacing.section,
            borderRadius: themeTokens.radius.control,
            backgroundColor: colors.primary,
          }}
        >
          <Text style={{ color: colors.primaryContrast, fontWeight: '700' }}>Try again</Text>
        </Pressable>
      </View>
    );
  }

  const dashboard = data;
  const isEmpty = dashboard.activity.every((entry) => entry.count === 0);

  return (
    <ScrollView
      testID="home-dashboard"
      contentInsetAdjustmentBehavior="automatic"
      contentContainerStyle={{ gap: themeTokens.spacing.screen, padding: themeTokens.spacing.screen }}
      refreshControl={
        <RefreshControl
          testID="home-refresh-control"
          refreshing={isRefetching}
          onRefresh={() => void refresh()}
          tintColor={colors.primary}
        />
      }
      style={{ flex: 1, backgroundColor: colors.background }}
    >
      <View
        style={{
          gap: 8,
          padding: themeTokens.spacing.screen,
          borderRadius: themeTokens.radius.card,
          backgroundColor: colors.surface,
        }}
      >
        <Text selectable style={{ color: colors.mutedText, fontWeight: '600' }}>Today · {dashboard.today}</Text>
        <Text
          selectable
          style={{ color: dashboard.has_read_today ? colors.success : colors.text, fontSize: 24, fontWeight: '700' }}
        >
          {dashboard.has_read_today ? 'Reading logged today' : 'Ready when you are'}
        </Text>
        <Text selectable style={{ color: colors.mutedText }}>
          {dashboard.has_read_today ? 'Your reading is safely recorded.' : 'Log today’s reading to keep your journey moving.'}
        </Text>
      </View>

      {isRefetchError ? (
        <View
          accessibilityLiveRegion="polite"
          style={{
            gap: 8,
            padding: themeTokens.spacing.section,
            borderWidth: 1,
            borderColor: colors.danger,
            borderRadius: themeTokens.radius.card,
            backgroundColor: colors.surface,
          }}
        >
          <Text selectable style={{ color: colors.danger, fontWeight: '700' }}>
            We couldn’t refresh your dashboard
          </Text>
          <Text selectable style={{ color: colors.mutedText }}>
            Showing your most recently loaded reading activity.
          </Text>
          <Pressable
            accessibilityRole="button"
            accessibilityLabel="Retry refreshing dashboard"
            accessibilityHint="Requests your latest reading dashboard"
            onPress={() => void refresh()}
            style={{
              alignSelf: 'flex-start',
              minHeight: themeTokens.minimumTouchTarget,
              justifyContent: 'center',
              paddingHorizontal: themeTokens.spacing.section,
              borderRadius: themeTokens.radius.control,
              backgroundColor: colors.primary,
            }}
          >
            <Text selectable style={{ color: colors.primaryContrast, fontWeight: '700' }}>
              Try again
            </Text>
          </Pressable>
        </View>
      ) : null}

      {isEmpty ? (
        <View accessibilityLiveRegion="polite" style={{ gap: 8 }}>
          <Text selectable style={{ color: colors.text, fontSize: 20, fontWeight: '700' }}>No recent reading activity</Text>
          <Text selectable style={{ color: colors.mutedText }}>
            Log a reading to start a new streak and fill your activity strip.
          </Text>
        </View>
      ) : null}

      <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: themeTokens.spacing.section }}>
        <StatCard label="Current streak" value={`${dashboard.current_streak} days`} />
        <StatCard label="Longest streak" value={`${dashboard.longest_streak} days`} />
        <StatCard label="Days read this week" value={`${dashboard.this_week_days}`} />
        <StatCard label="Days read this month" value={`${dashboard.this_month_days}`} />
      </View>

      <View style={{ gap: 8 }}>
        <Text selectable style={{ color: colors.text, fontSize: 20, fontWeight: '700' }}>Recent activity</Text>
        <ActivityStrip activity={dashboard.activity} />
      </View>
    </ScrollView>
  );
}
