export type AchievementCompletion = { completedOn: string | null; earnedOn: string };

export type EarnedAchievement = {
    id: string;
    key: string;
    name: string;
    description: string;
    icon: string;
    count: number;
    earnedOn: string;
    reading: { passage: string; date: string } | null;
    completions: AchievementCompletion[];
};

export type AchievementBookGoal = {
    key: string;
    name: string;
    icon: string;
    chaptersRead: number;
    totalChapters: number;
    chaptersRemaining: number;
    missingChapters: number[];
    completedCount: number;
    progressPercent: number;
};

export type AchievementProgressGoal = {
    key: string;
    name: string;
    description: string;
    icon: string;
    current: number;
    target: number;
    progressPercent: number;
    kind: 'longest_streak' | 'bible_progress' | 'reading_days';
    unit: 'days' | 'percent';
};

export type AchievementsProps = {
    badgeBaseUrl: string;
    latestAchievement: { name: string; icon: string; earnedOn: string } | null;
    categories: { key: string; label: string; achievements: EarnedAchievement[] }[];
    nextGoals: { books: AchievementBookGoal[]; progress: AchievementProgressGoal[] };
};
