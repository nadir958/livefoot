<?php
declare(strict_types=1);

namespace App\Enum;

enum MatchStatus: string
{
    case SCHEDULED = 'scheduled';
    case LIVE      = 'live';
    case FINISHED  = 'finished';
}
