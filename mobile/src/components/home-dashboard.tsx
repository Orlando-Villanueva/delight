import { useRouter } from 'expo-router';
import {
  ActivityIndicator,
  Pressable,
  RefreshControl,
  ScrollView,
  Text,
  View,
} from 'react-native';

import { type HomeDashboardData } from '@/api/bootstrap';
import { useBootstrap } from '@/hooks/use-bootstrap';
import { themeTokens, type ThemeColors } from '@/theme/tokens';
import { useTheme } from '@/theme/use-theme';

function formatDate(date: string, options: Intl.DateTimeFormatOptions): string {
  const [year, month, day] = date.split('-').map(Number);

  return new Intl.DateTimeFormat('en-CA', options).format(new Date(year, month - 1, day, 12));
}

function formatToday(date: string): string {
  return formatDate(date, { weekday: 'long', month: 'long', day: 'numeric' });
}

type TodayCardContent = {
  title: string;
  description: string;
  dateColor: string;
  titleColor: string;
  descriptionColor: string;
  borderColor: string;
  backgroundColor: string;
  showsLogAction: boolean;
};

function getTodayCardContent({
  hasReadToday,
  isStreakAtRisk,
  currentStreak,
  colors,
  mode,
}: Readonly<{
  hasReadToday: boolean;
  isStreakAtRisk: boolean;
  currentStreak: number;
  colors: ThemeColors;
  mode: 'light' | 'dark';
}>): TodayCardContent {
  if (hasReadToday) {
    return {
      title: 'Reading logged today',
      description: 'Your reading is safely recorded.',
      dateColor: colors.mutedText,
      titleColor: colors.success,
      descriptionColor: colors.mutedText,
      borderColor: colors.border,
      backgroundColor: colors.surface,
      showsLogAction: false,
    };
  }

  if (isStreakAtRisk) {
    const streakLabel = `${currentStreak}-day streak`;
    const warningTextColor = mode === 'dark' ? colors.text : colors.accentContrast;

    return {
      title: 'Your streak is at risk',
      description: `Log today’s reading before the day ends to keep your ${streakLabel}.`,
      dateColor: warningTextColor,
      titleColor: warningTextColor,
      descriptionColor: warningTextColor,
      borderColor: colors.accent,
      backgroundColor: colors.accentSubtle,
      showsLogAction: true,
    };
  }

  return {
    title: 'Ready when you are',
    description: 'Log today’s reading to keep your journey moving.',
    dateColor: colors.mutedText,
    titleColor: colors.text,
    descriptionColor: colors.mutedText,
    borderColor: colors.border,
    backgroundColor: colors.surface,
    showsLogAction: true,
  };
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
        borderWidth: 1,
        borderColor: colors.border,
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
  const { colors, mode } = useTheme();
  const router = useRouter();
  const { data, isPending, isRefetchError, isRefetching, refresh } = useBootstrap();

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
  const todayCard = getTodayCardContent({
    hasReadToday: dashboard.has_read_today,
    isStreakAtRisk: dashboard.streak_state === 'warning',
    currentStreak: dashboard.current_streak,
    colors,
    mode,
  });

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
          borderWidth: 1,
          borderColor: todayCard.borderColor,
          borderRadius: themeTokens.radius.card,
          backgroundColor: todayCard.backgroundColor,
        }}
      >
        <Text
          selectable
          style={{ color: todayCard.dateColor, fontWeight: '600' }}
        >
          {formatToday(dashboard.today)}
        </Text>
        <Text
          selectable
          style={{
            color: todayCard.titleColor,
            fontSize: 24,
            fontWeight: '700',
          }}
        >
          {todayCard.title}
        </Text>
        <Text selectable style={{ color: todayCard.descriptionColor }}>
          {todayCard.description}
        </Text>
        {todayCard.showsLogAction ? (
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
        <StreakCard
          currentStreak={dashboard.current_streak}
          longestStreak={dashboard.longest_streak}
        />
      </View>

      <View style={{ gap: 8 }}>
        <Text selectable style={{ color: colors.text, fontSize: 20, fontWeight: '700' }}>Reading rhythm</Text>
        <Text selectable style={{ color: colors.mutedText }}>Last 14 days</Text>
        <ActivityStrip activity={dashboard.activity} today={dashboard.today} />
      </View>
    </ScrollView>
  );
}
