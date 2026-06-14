<?php

namespace App\Services\Order;

use App\Models\ReportReason;
use App\Models\CancellationReason;

class ReasonService
{
    public function getReportReasons(): array
    {
        return ReportReason::select('id', 'title')->orderBy('title')->get()->toArray();
    }

    public function getCancellationReasons(): array
    {
        return CancellationReason::select('id', 'title')->orderBy('title')->get()->toArray();
    }
}
