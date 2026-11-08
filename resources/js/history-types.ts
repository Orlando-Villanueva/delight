export type HistoryGroup = {
    id: number;
    passage: string;
    notes: string | null;
    chapterCount: number;
    loggedAt: string;
    timeLabel: string;
    chapters: { id: number; chapter: number }[];
    noteUrl: string;
    deleteUrl: string;
    deleteChaptersUrl: string;
};

export type HistoryDay = { date: string; label: string; groups: HistoryGroup[] };
export type HistorySlice = { days: HistoryDay[]; nextUrl: string | null; hasLogs: boolean };
export type HistoryProps = HistorySlice & { today: string };
export type HistoryMutation = { day: HistoryDay | null; date: string; today: string; hasLogs: boolean };
export type HistoryAction = { kind: 'note' | 'delete'; group: HistoryGroup; date: string; dateLabel: string };
