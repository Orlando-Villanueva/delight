import type { AuthenticatedRequestOptions } from '@/auth/auth-context';

export type ReadingHistoryGroup = {
  logIds: number[];
  book: {
    id: number;
    name: string;
  };
  startChapter: number;
  endChapter: number | null;
  passage: string;
  notesText: string | null;
  dateRead: string;
  loggedAt: string | null;
};

export type ReadingHistoryDay = {
  dateRead: string;
  groups: ReadingHistoryGroup[];
};

export type ReadingHistoryPage = {
  days: ReadingHistoryDay[];
  currentPage: number;
  lastPage: number;
};

type ReadingHistoryResponse = {
  data: {
    date_read: string;
    groups: {
      log_ids: number[];
      book: { id: number; name: string };
      start_chapter: number;
      end_chapter: number | null;
      passage: string;
      notes_text: string | null;
      date_read: string;
      logged_at: string | null;
    }[];
  }[];
  meta: {
    current_page: number;
    last_page: number;
  };
};

type AuthenticatedApi = <T>(path: string, options?: AuthenticatedRequestOptions) => Promise<T>;

export async function fetchReadingHistoryPage(
  request: AuthenticatedApi,
  page: number,
): Promise<ReadingHistoryPage> {
  const response = await request<ReadingHistoryResponse>(`/api/v1/reading-logs?page=${page}`);

  return {
    days: response.data.map((day) => ({
      dateRead: day.date_read,
      groups: day.groups.map((group) => ({
        logIds: group.log_ids,
        book: group.book,
        startChapter: group.start_chapter,
        endChapter: group.end_chapter,
        passage: group.passage,
        notesText: group.notes_text,
        dateRead: group.date_read,
        loggedAt: group.logged_at,
      })),
    })),
    currentPage: response.meta.current_page,
    lastPage: response.meta.last_page,
  };
}

export function mergeReadingHistoryPages(pages: ReadingHistoryPage[]): ReadingHistoryDay[] {
  const days = new Map<string, ReadingHistoryDay>();
  const seenLogIds = new Set<number>();

  for (const page of pages) {
    for (const incomingDay of page.days) {
      const day = days.get(incomingDay.dateRead) ?? { dateRead: incomingDay.dateRead, groups: [] };

      for (const group of incomingDay.groups) {
        if (group.logIds.some((id) => seenLogIds.has(id))) {
          continue;
        }

        group.logIds.forEach((id) => seenLogIds.add(id));
        day.groups.push(group);
      }

      if (!days.has(incomingDay.dateRead)) {
        days.set(incomingDay.dateRead, day);
      }
    }
  }

  return [...days.values()];
}

export function hasPartialReadingHistoryOverlap(
  existingPages: ReadingHistoryPage[],
  incomingPage: ReadingHistoryPage,
): boolean {
  const seenLogIds = new Set<number>();

  for (const page of existingPages) {
    for (const day of page.days) {
      for (const group of day.groups) {
        group.logIds.forEach((id) => seenLogIds.add(id));
      }
    }
  }

  for (const day of incomingPage.days) {
    for (const group of day.groups) {
      const hasSeenLog = group.logIds.some((id) => seenLogIds.has(id));
      const hasUnseenLog = group.logIds.some((id) => !seenLogIds.has(id));

      if (hasSeenLog && hasUnseenLog) {
        return true;
      }

      group.logIds.forEach((id) => seenLogIds.add(id));
    }
  }

  return false;
}
