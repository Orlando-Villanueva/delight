export type ReadingValues = {
    book_id: string;
    start_chapter: string;
    end_chapter: string;
    date_read: string;
    notes_text: string;
};

export type ReadingDates = { today: string; yesterday: string };
export type BibleBook = { id: number; name: string; chapters: number; testament: string };
export type ReadingErrors = Partial<Record<keyof ReadingValues | 'chapter_range', string[]>>;
export type ReadingErrorScopes = Partial<Record<keyof ReadingValues, string>>;
export type ReadingSnapshot = { values: ReadingValues; collection: string; notesOpen: boolean };

export type CelebrationAchievement = {
    id: number;
    display_name: string;
    description: string;
    icon: string;
    is_repeat_book_completion: boolean;
};

export type CelebrationProgress = {
    display_name: string;
    icon: string;
    current: number;
    target: number;
    progress_percent: number;
    unit: 'percent' | 'days';
};

export type ReadingCelebration = {
    earned: CelebrationAchievement[];
    progress: CelebrationProgress[];
    record: { eyebrow: string; title: string; description: string; icon: string } | null;
    reading: { passage: string; date: string };
};

export type ReadingSuccess = {
    message: string;
    reading: { passage_text: string; date_read: string };
    form: { values: ReadingValues; dates: ReadingDates; books: BibleBook[]; recent_books: BibleBook[] };
    celebration: ReadingCelebration;
};

export type ReadingOutcome =
    | { kind: 'success'; data: ReadingSuccess }
    | { kind: 'validation'; errors: ReadingErrors; errorScopes: ReadingErrorScopes }
    | { kind: 'session' | 'uncertain' | 'failure' };

export type ReadingSession = {
    snapshot: ReadingSnapshot;
    pending: Promise<ReadingOutcome> | null;
    outcome: ReadingOutcome | null;
    dateContext: ReadingDates | null;
    uncertain: boolean;
};

export type ReadingMessages = Record<
    'when' | 'today' | 'yesterday' | 'date_help' | 'book' | 'select_book' | 'book_label' | 'recent' | 'start' | 'end'
    | 'start_placeholder' | 'end_placeholder' | 'add_note' | 'note' | 'note_placeholder' | 'save' | 'saving' | 'saved'
    | 'dismiss_success' | 'session_error' | 'request_error' | 'uncertain_error' | 'check_history' | 'retry_checked', string
> & {
    collections: Record<string, string>;
    chapter_notes: { esther_additions: string; daniel_additions: string };
};

export type ReadingFormConfig = {
    userId: string;
    locale: string;
    submitUrl: string;
    historyUrl: string;
    achievementsUrl: string;
    achievementBadgeBaseUrl: string;
    csrfToken: string;
    books: BibleBook[];
    recentBooks: BibleBook[];
    dates: ReadingDates;
    values: ReadingValues;
    errors: ReadingErrors;
    errorScopes: ReadingErrorScopes;
    successMessage: string;
    messages: ReadingMessages;
};
