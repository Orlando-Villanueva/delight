export interface PlanDayOption {
    day: number;
    optionLabel: string;
}

export interface PlanSubscriptionSummary {
    active: boolean;
    dayNumber: number;
    lastDay: number;
    completedDays: number;
    trackedDays: number;
    progress: number;
    todayUrl: string;
}

export interface PlanCatalogueItem {
    slug: string;
    name: string;
    description: string;
    daysCount: number;
    canStart: boolean;
    firstDay: number;
    subscribeUrl: string | null;
    days: PlanDayOption[];
    subscription: PlanSubscriptionSummary | null;
}

export interface PlansPageProps {
    plans: PlanCatalogueItem[];
    hasActivePlan: boolean;
    flash: {
        info: string | null;
        error: string | null;
        success: string | null;
    };
}
