<?php

namespace App\Enums;

enum OnboardingStep: string
{
    case LogFlowReached = 'log_flow_reached';
    case PlanBrowserReached = 'plan_browser_reached';
    case PlanSelected = 'plan_selected';
    case ReminderRequested = 'reminder_requested';
    case Dismissed = 'dismissed';
    case FirstReadingCompleted = 'first_reading_completed';
}
