<?php

namespace Database\Seeders;

use App\Models\ReportReason;
use Illuminate\Database\Seeder;

class ReportReasonSeeder extends Seeder
{
    public function run(): void
    {
        $reasons = [
            'Rude behavior',
            'Late delivery',
            'Damaged package',
            'Wrong item delivered',
            'Driver unprofessional',
            'Other',
        ];

        foreach ($reasons as $reason) {
            ReportReason::firstOrCreate(['title' => $reason]);
        }
    }
}
