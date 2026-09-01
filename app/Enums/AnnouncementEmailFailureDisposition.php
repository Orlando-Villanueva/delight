<?php

namespace App\Enums;

enum AnnouncementEmailFailureDisposition: string
{
    case Retryable = 'retryable';
    case Terminal = 'terminal';
    case Uncertain = 'uncertain';
}
