import { useQuery } from '@tanstack/react-query';
import { router } from 'expo-router';
import { useCallback, useMemo, useRef, useState } from 'react';
import { ActivityIndicator, Pressable, RefreshControl, ScrollView, Text, View } from 'react-native';

import {
  fetchReadingHistoryPage,
  mergeReadingHistoryPages,
  type ReadingHistoryDay,
  type ReadingHistoryGroup,
  type ReadingHistoryPage,
} from '@/api/reading-history';
import { useAuthenticatedApi } from '@/auth/auth-context';
import { themeTokens } from '@/theme/tokens';
import { useTheme } from '@/theme/use-theme';

function useReadingHistory() {
  const request = useAuthenticatedApi();
  const [loadedPages, setLoadedPages] = useState<ReadingHistoryPage[]>([]);
  const [isLoadingMore, setIsLoadingMore] = useState(false);
  const [loadMoreError, setLoadMoreError] = useState<Error | null>(null);
  const isLoadingMoreRef = useRef(false);

  const initialPage = useQuery({
    queryKey: ['reading-history', 1],
    queryFn: () => fetchReadingHistoryPage(request, 1),
  });

  const pages = useMemo(
    () => initialPage.data ? [initialPage.data, ...loadedPages] : [],
    [initialPage.data, loadedPages],
  );
  const days = useMemo(() => mergeReadingHistoryPages(pages), [pages]);
  const lastLoadedPage = loadedPages.at(-1)?.currentPage ?? initialPage.data?.currentPage ?? 0;
  const lastPage = loadedPages.at(-1)?.lastPage ?? initialPage.data?.lastPage ?? 0;
  const hasNextPage = initialPage.data !== undefined && lastLoadedPage < lastPage;

  const refresh = useCallback(async () => {
    setLoadedPages([]);
    setLoadMoreError(null);
    await initialPage.refetch();
  }, [initialPage]);

  const loadMore = useCallback(async () => {
    if (!hasNextPage || isLoadingMoreRef.current) {
      return;
    }

    isLoadingMoreRef.current = true;
    setIsLoadingMore(true);
    setLoadMoreError(null);

    try {
      const page = await fetchReadingHistoryPage(request, lastLoadedPage + 1);
      setLoadedPages((currentPages) => {
        if (currentPages.some((currentPage) => currentPage.currentPage === page.currentPage)) {
          return currentPages;
        }

        return [...currentPages, page];
      });
    } catch (error) {
      setLoadMoreError(error instanceof Error ? error : new Error('The next page could not be loaded.'));
    } finally {
      isLoadingMoreRef.current = false;
      setIsLoadingMore(false);
    }
  }, [hasNextPage, lastLoadedPage, request]);

  return {
    days,
    isInitialLoading: initialPage.isLoading,
    isRefreshing: initialPage.isRefetching,
    initialError: initialPage.error instanceof Error ? initialPage.error : null,
    isLoadingMore,
    loadMoreError,
    hasNextPage,
    refresh,
    retryInitial: initialPage.refetch,
    loadMore,
  };
}

function formatDate(date: string): string {
  return new Intl.DateTimeFormat(undefined, { dateStyle: 'full' }).format(new Date(`${date}T12:00:00`));
}

function formatTime(timestamp: string | null): string | null {
  if (!timestamp) {
    return null;
  }

  return new Intl.DateTimeFormat(undefined, { hour: 'numeric', minute: '2-digit' }).format(new Date(timestamp));
}

function chapterCount(group: ReadingHistoryGroup): string {
  const count = (group.endChapter ?? group.startChapter) - group.startChapter + 1;

  return `${count} ${count === 1 ? 'chapter' : 'chapters'}`;
}

function ActionButton({ label, onPress, tone = 'primary' }: { label: string; onPress: () => void; tone?: 'primary' | 'accent' }) {
  const { colors } = useTheme();
  const isAccent = tone === 'accent';

  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={label}
      onPress={onPress}
      style={({ pressed }) => ({
        alignItems: 'center',
        justifyContent: 'center',
        minHeight: themeTokens.minimumTouchTarget,
        paddingHorizontal: 16,
        borderRadius: themeTokens.radius.control,
        borderCurve: 'continuous',
        backgroundColor: isAccent ? colors.accentAction : colors.primary,
        opacity: pressed ? 0.8 : 1,
      })}
    >
      <Text
        selectable
        style={{ color: isAccent ? colors.accentActionContrast : colors.primaryContrast, fontSize: 16, fontWeight: '700' }}
      >
        {label}
      </Text>
    </Pressable>
  );
}

function HistoryDay({ day }: { day: ReadingHistoryDay }) {
  const { colors } = useTheme();

  return (
    <View accessibilityRole="header" style={{ gap: 12 }}>
      <Text selectable style={{ color: colors.text, fontSize: 19, fontWeight: '700' }}>
        {formatDate(day.dateRead)}
      </Text>
      {day.groups.map((group) => {
        const time = formatTime(group.loggedAt);

        return (
          <View
            key={group.logIds.join('-')}
            style={{
              gap: 8,
              padding: themeTokens.spacing.section,
              borderRadius: themeTokens.radius.card,
              borderCurve: 'continuous',
              borderWidth: 1,
              borderColor: colors.border,
              backgroundColor: colors.surface,
            }}
          >
            <Text selectable style={{ color: colors.text, fontSize: 18, fontWeight: '700' }}>
              {group.passage}
            </Text>
            <Text selectable style={{ color: colors.mutedText, fontSize: 15 }}>
              {group.book.name} · {chapterCount(group)}
            </Text>
            {group.notesText ? (
              <Text selectable style={{ color: colors.text, fontSize: 16, lineHeight: 24 }}>
                {group.notesText}
              </Text>
            ) : null}
            {time ? (
              <Text selectable style={{ color: colors.mutedText, fontSize: 14 }}>
                Logged at {time}
              </Text>
            ) : null}
          </View>
        );
      })}
    </View>
  );
}

export function ReadingHistory() {
  const { colors } = useTheme();
  const history = useReadingHistory();

  if (history.isInitialLoading) {
    return (
      <View accessibilityLiveRegion="polite" style={{ flex: 1, alignItems: 'center', justifyContent: 'center', gap: 12 }}>
        <ActivityIndicator accessibilityLabel="Loading reading history" color={colors.primary} />
        <Text selectable style={{ color: colors.mutedText, fontSize: 16 }}>Loading your reading history…</Text>
      </View>
    );
  }

  if (history.initialError && history.days.length === 0) {
    return (
      <View accessibilityLiveRegion="polite" style={{ flex: 1, justifyContent: 'center', gap: 16, padding: themeTokens.spacing.screen }}>
        <Text selectable style={{ color: colors.text, fontSize: 20, fontWeight: '700' }}>History is unavailable</Text>
        <Text selectable style={{ color: colors.mutedText, fontSize: 16, lineHeight: 24 }}>{history.initialError.message}</Text>
        <ActionButton label="Try again" onPress={() => void history.retryInitial()} />
      </View>
    );
  }

  return (
    <ScrollView
      contentInsetAdjustmentBehavior="automatic"
      refreshControl={(
        <RefreshControl
          refreshing={history.isRefreshing}
          onRefresh={history.refresh}
          testID="history-refresh-control"
        />
      )}
      style={{ backgroundColor: colors.background }}
      contentContainerStyle={{ gap: 24, padding: themeTokens.spacing.screen }}
    >
      {history.days.length === 0 ? (
        <View accessibilityLiveRegion="polite" style={{ gap: 16, paddingTop: 48 }}>
          <Text selectable style={{ color: colors.text, fontSize: 22, fontWeight: '700' }}>Your history is waiting</Text>
          <Text selectable style={{ color: colors.mutedText, fontSize: 16, lineHeight: 24 }}>
            Log a reading to begin building your record here.
          </Text>
          <ActionButton label="Log a reading" onPress={() => router.push('/(tabs)/log')} tone="accent" />
        </View>
      ) : (
        <>
          {history.days.map((day) => <HistoryDay key={day.dateRead} day={day} />)}
          {history.hasNextPage ? (
            <View accessibilityLiveRegion="polite" style={{ gap: 12 }}>
              {history.loadMoreError ? (
                <Text selectable style={{ color: colors.danger, fontSize: 16 }}>{history.loadMoreError.message}</Text>
              ) : null}
              {history.isLoadingMore ? (
                <ActivityIndicator accessibilityLabel="Loading more history" color={colors.primary} />
              ) : null}
              <ActionButton label="Load more" onPress={() => void history.loadMore()} />
            </View>
          ) : (
            <Text accessibilityLiveRegion="polite" selectable style={{ color: colors.mutedText, fontSize: 15, textAlign: 'center' }}>
              You have reached the beginning of your history.
            </Text>
          )}
        </>
      )}
    </ScrollView>
  );
}
