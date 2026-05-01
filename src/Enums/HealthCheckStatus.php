<?php

namespace Elvinaqalarov99\StatusPage\Enums;

enum HealthCheckStatus: string
{
    case Ok       = 'ok';
    case Failed   = 'failed';
    case Warning  = 'warning';
    case Skipped  = 'skipped';
    case Resolved = 'resolved'; // synthetic — used for event-stream transitions, not stored in DB
    case Unknown  = 'unknown';   // synthetic — used for event-stream transitions, not stored in DB
}
