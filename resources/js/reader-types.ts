export type SettingsValues = {
    include_deuterocanonical: boolean;
    daily_reading_reminder_enabled: boolean;
    streak_warning_enabled: boolean;
    reading_timezone: string;
};

export type BrowserReminderConfig = {
    accountHasDevices: boolean;
    publicKey: string | null;
    subscriptionUrl: string;
    statusUrl: string;
    unsubscribeUrl: string;
    disconnectAllUrl: string;
};

export type SettingsProps = {
    values: SettingsValues;
    updateUrl: string;
    push: BrowserReminderConfig;
    readingTimezoneOptions: Record<string, string>;
};

export type ReaderUrls = {
    dashboard: string;
    plans: string;
    log: string;
    history: string;
    settings: string;
    achievements: string;
    recap: string;
    feedback: string;
    notifications: string;
    logout: string;
    admin: string | null;
};

export type ReaderProps = {
    appName: string;
    logoUrl: string;
    user: { name: string; email: string; avatar: string | null };
    csrfToken: string;
    unreadCount: number;
    urls: ReaderUrls;
};

export type ReaderPageProps = { reader: ReaderProps; status: string | null };
