<?php

namespace App\Enums;

enum ReportReason: string
{
    case Spoiler = 'spoiler';
    case Spam = 'spam';
    case Abuse = 'abuse';
    case Other = 'other';
}
