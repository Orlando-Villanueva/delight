import { useQuery } from '@tanstack/react-query';
import { useRouter } from 'expo-router';
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

function formatDate(date: string, options: Intl.DateTimeFormatOptions): string {
  const [year, month, day] = date.split('-').map(Number);

  return new Intl.DateTimeFormat('en-CA', options).format(new Date(year, month - 1, day, 12));
}

function formatToday(date: string): string {
  return formatDate(date, { weekday: 'long', month: 'long', day: 'numeric' });
}

function StreakCard({
  currentStreak,
  longestStreak,
}: Readonly<{ currentStreak: number; longestStreak: number }>) {
  const { colors } = useTheme();

  return (
    <View
      accessible
      accessibilityLabel={`Current streak: ${currentStreak} ${currentStreak === 1 ? 'day' : 'days'}. Best: ${
        longestStreak
      } ${longestStreak === 1 ? 'day' : 'days'}.`}
      style={{
        gap: 4,
        padding: themeTokens.spacing.section,
        borderRadius: themeTokens.radius.card,
        backgroundColor: colors.surface,
      }}
    >
      <Text selectable style={{ color: colors.mutedText, fontWeight: '600' }}>Current streak</Text>
      <Text
        selectable
        style={{ color: colors.primary, fontSize: 32, fontWeight: '700', fontVariant: ['tabular-nums'] }}
      >
        {currentStreak} {currentStreak === 1 ? 'day' : 'days'}
      </Text>
      <View style={{ flexDirection: 'row', gap: 4 }}>
        <Text selectable style={{ color: colors.mutedText }}>Best</Text>
        <Text
          selectable
          style={{ color: colors.text, fontVariant: ['tabular-nums'], fontWeight: '600' }}
        >
          {longestStreak} {longestStreak === 1 ? 'day' : 'days'}
        </Text>
      </View>
    </View>
  );
}

function SupportingStat({ label, value }: Readonly<{ label: string; value: number }>) {
  const { colors } = useTheme();

  return (
    <View
      accessible
      accessibilityLabel={`${label}: ${value}`}
      style={{ flex: 1, minWidth: 130, gap: 4, padding: themeTokens.spacing.section }}
    >
      <Text selectable style={{ color: colors.mutedText }}>{label}</Text>
      <Text
        selectable
        style={{ color: colors.text, fontSize: 20, fontWeight: '700', fontVariant: ['tabular-nums'] }}
      >
        {value}
      </Text>
    </View>
  );
}

function ActivityStrip({
  activity,
  today,
}: Readonly<{ activity: HomeDashboardData['activity']; today: string }>) {
  const { colors } = useTheme();

  return (
    <View
      accessibilityLabel="Reading rhythm for the last 14 days. Read and no reading are shown."
      style={{ gap: 12 }}
    >
      <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 8 }}>
        {activity.map((entry) => (
          <View
            key={entry.date}
            accessible
            accessibilityLabel={`${formatDate(entry.date, { weekday: 'long', month: 'long', day: 'numeric' })}: ${
              entry.count > 0 ? 'Read' : 'No reading'
            }${entry.date === today ? '. Today' : ''}`}
            style={{
              flexBasis: '12%',
              flexGrow: 1,
              minHeight: themeTokens.minimumTouchTarget,
              alignItems: 'center',
              justifyContent: 'center',
              gap: 2,
              paddingVertical: 6,
              borderWidth: entry.date === today ? 2 : 1,
              borderColor: entry.date === today ? colors.accent : colors.border,
              borderRadius: themeTokens.radius.control,
              backgroundColor: entry.count > 0 ? colors.primary : colors.primarySubtle,
            }}
          >
            <Text
              selectable
              style={{ color: entry.count > 0 ? colors.primaryContrast : colors.mutedText, fontSize: 12 }}
            >
              {formatDate(entry.date, { weekday: 'narrow' })}
            </Text>
            <Text
              selectable
              style={{
                color: entry.count > 0 ? colors.primaryContrast : colors.text,
                fontWeight: '700',
                fontVariant: ['tabular-nums'],
              }}
            >
              {formatDate(entry.date, { day: 'numeric' })}
            </Text>
          </View>
        ))}
      </View>
      <View accessibilityLabel="Legend: Read and No reading" style={{ flexDirection: 'row', gap: 16 }}>
        <View style={{ alignItems: 'center', flexDirection: 'row', gap: 6 }}>
          <View style={{ width: 12, height: 12, borderRadius: 6, backgroundColor: colors.primary }} />
          <Text selectable style={{ color: colors.mutedText }}>Read</Text>
        </View>
        <View style={{ alignItems: 'center', flexDirection: 'row', gap: 6 }}>
          <View
            style={{
              width: 12,
              height: 12,
              borderWidth: 1,
              borderColor: colors.border,
              borderRadius: 6,
              backgroundColor: colors.primarySubtle,
            }}
          />
          <Text selectable style={{ color: colors.mutedText }}>No reading</Text>
        </View>
      </View>
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
  const router = useRouter();
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
        <Text selectable style={{ color: colors.mutedText, fontWeight: '600' }}>
          {formatToday(dashboard.today)}
        </Text>
        <Text
          selectable
          style={{ color: dashboard.has_read_today ? colors.success : colors.text, fontSize: 24, fontWeight: '700' }}
        >
          {dashboard.has_read_today ? 'Reading logged today' : 'Ready when you are'}
        </Text>
        <Text selectable style={{ color: colors.mutedText }}>
          {dashboard.has_read_today ? 'Your reading is safely recorded.' : 'Log today’s reading to keep your journey moving.'}
        </Text>
        {!dashboard.has_read_today ? (
          <Pressable
            accessibilityRole="button"
            accessibilityLabel="Log today’s reading"
            accessibilityHint="Opens the reading log for today"
            onPress={() => router.navigate('/(tabs)/log')}
            style={{
              alignSelf: 'flex-start',
              minHeight: themeTokens.minimumTouchTarget,
              justifyContent: 'center',
              paddingHorizontal: themeTokens.spacing.section,
              borderRadius: themeTokens.radius.control,
              backgroundColor: colors.accentAction,
            }}
          >
            <Text selectable style={{ color: colors.accentActionContrast, fontWeight: '700' }}>
              Log today’s reading
            </Text>
          </Pressable>
        ) : null}
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
            Log a reading to start a new streak and begin your reading rhythm.
          </Text>
        </View>
      ) : null}

      <View style={{ gap: 8 }}>
        <StreakCard currentStreak={dashboard.current_streak} longestStreak={dashboard.longest_streak} />
        <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: themeTokens.spacing.section }}>
          <SupportingStat label="Days read this week" value={dashboard.this_week_days} />
          <SupportingStat label="Days read this month" value={dashboard.this_month_days} />
        </View>
      </View>

      <View style={{ gap: 8 }}>
        <Text selectable style={{ color: colors.text, fontSize: 20, fontWeight: '700' }}>Reading rhythm</Text>
        <Text selectable style={{ color: colors.mutedText }}>Last 14 days</Text>
        <ActivityStrip activity={dashboard.activity} today={dashboard.today} />
      </View>
    </ScrollView>
  );
}
