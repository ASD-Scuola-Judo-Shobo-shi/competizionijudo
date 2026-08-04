<?php

declare(strict_types=1);

namespace App\Model;

enum EntryRegistrationResult
{
    case Registered;
    case AthleteRejected;
    case AthleteWeightMissing;
    case AlreadyRegistered;
    case CapacityExceeded;
    case QuotaExceeded;
    case Unsubscribed;
    case UnsubscribeFailed;
}
