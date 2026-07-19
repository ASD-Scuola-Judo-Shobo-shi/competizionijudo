<?php

declare(strict_types=1);

namespace App\Model;

enum EntryRegistrationResult
{
    case Registered;
    case AthleteRejected;
    case AlreadyRegistered;
    case CapacityExceeded;
    case Unsubscribed;
    case UnsubscribeFailed;
}
