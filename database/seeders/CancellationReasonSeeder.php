<?php

namespace Database\Seeders;

use App\Models\CancellationReason;
use Illuminate\Database\Seeder;

class CancellationReasonSeeder extends Seeder
{
    public function run(): void
    {
        $reasons = [
            'Changed my mind',
            'Late delivery',
            'Incorrect order',
            'Other',
        ];

        foreach ($reasons as $reason) {
            CancellationReason::firstOrCreate(['title' => $reason]);
        }
    }
}

