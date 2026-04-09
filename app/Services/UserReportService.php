<?php

namespace App\Services;

use App\Models\UserReport;
use Illuminate\Validation\ValidationException;

class UserReportService
{
    /**
     * Create a user report
     */
    public function createReport(array $data, int $reporterId): UserReport
    {
        // Prevent self-report
        if ($data['reported_id'] == $reporterId) {
            throw ValidationException::withMessages([
                'reported_id' => ['You cannot report yourself.'],
            ]);
        }

        // Prevent duplicate report
        $exists = UserReport::where('reporter_id', $reporterId)
            ->where('reported_id', $data['reported_id'])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'reported_id' => ['You have already reported this user.'],
            ]);
        }

        // Assign reporter
        $data['reporter_id'] = $reporterId;

        return UserReport::create($data);
    }
}
