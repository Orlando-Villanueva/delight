import type { ReadingCelebration } from '@/components/reading-log-types';

export type PlanTodayChapter = { bookId: number; bookName: string; chapter: number; completed: boolean };
export type PlanTodayState = {
    plan: { name: string; slug: string };
    subscription: { startDay: number };
    reading: { chapters: PlanTodayChapter[]; completedCount: number; totalCount: number; allCompleted: boolean } | null;
    dayNumber: number;
    currentDay: number;
    totalDays: number;
    previousDay: number | null;
    nextDay: number | null;
    progress: number;
    isComplete: boolean;
    isActive: boolean;
    isBeforeTracking: boolean;
    hasOtherActivePlan: boolean;
    completedDaysCount: number;
    trackedDaysCount: number;
    unlinkedTodayChaptersCount: number;
    unlinkedTodayChaptersTotal: number;
    urls: { today: string; catalogue: string; activate: string; unsubscribe: string; logChapter: string; logAll: string; applyReadings: string };
    flash: { success: string | null; info: string | null; error: string | null };
};

export type PlanTodayPageProps = PlanTodayState & { achievementsUrl: string; achievementBadgeBaseUrl: string };
export type PlanTodayMutation = { today: PlanTodayState; celebration: ReadingCelebration | null };
