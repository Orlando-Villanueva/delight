export type ReadingActivityPoint = { date: string; count: number };
export type DashboardStreak = {
    currentStreak: number;
    longestStreak: number;
    activity14DayCounts: ReadingActivityPoint[];
    currentStreakCounts: ReadingActivityPoint[];
    presentation: {
        state: string;
        classes: { showIcon: boolean };
        message: string;
        tone: string;
        label: string | null;
        showCta: boolean;
        recordStatus: string;
        recordJustBroken: boolean;
    };
};
export type DashboardSummary = { daysRead: number; totalChapters: number; bookCompletions: number; averageChaptersPerDay: number; elapsedDays: number };
export type CalendarDay = { date: string; day: number; readingCount: number; hasRead: boolean; isToday: boolean; isFuture: boolean };
export type DashboardCalendarData = { monthName: string; days: (CalendarDay | null)[]; thisMonthReadings: number; thisMonthChapters: number; daysPassedInMonth: number };
export type BookProgress = {
    book_id: number; name: string; chapter_count: number; chapters_read: number; percentage: number;
    completed_count: number; next_completion_chapters: number; next_completion_missing_chapters: number[];
    next_completion_progress_percent: number; status: 'completed' | 'in-progress' | 'not-started';
};
export type TestamentProgress = { processed_books: BookProgress[]; completed_books: number; in_progress_books: number; not_started_books: number };
export type BibleProgress = {
    bible_completions: number; first_coverage_percent: number; next_completion_progress_percent: number;
    old_testament: TestamentProgress; new_testament: TestamentProgress; deuterocanonical: TestamentProgress | null;
};
export type DashboardGoal = { key: string; contextKey: string; displayName: string; description: string; icon: string; style: string; current: number; target: number; progressPercent: number };
export type DashboardMilestone = { latest: { displayName: string; description: string; icon: string; earnedAt: string | null } | null; milestone: DashboardGoal | null };
export type DashboardOnboarding = { show: boolean; canRemind: boolean; dismissUrl: string; remindUrl: string; plansUrl: string };
export type DashboardProps = {
    hasReadToday: boolean;
    badgeBaseUrl: string;
    streak: DashboardStreak;
    summary: DashboardSummary;
    calendar: DashboardCalendarData;
    bookProgress: BibleProgress;
    recentActivity: { id: number; passageText: string; dateRead: string; timeAgo: string }[];
    milestone: DashboardMilestone;
    recap: { show: boolean; year: number; endLabel: string; isFinal: boolean; url: string };
    onboarding: DashboardOnboarding;
};
