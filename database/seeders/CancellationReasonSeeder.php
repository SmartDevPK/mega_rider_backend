<?php

namespace Database\Seeders;

use App\Models\CancellationReason;
use Illuminate\Database\Seeder;

class CancellationReasonSeeder extends Seeder
{
    public function run(): void
    {
        $reasons = [
            // Cancellation Reasons
            ['type' => 'cancellation', 'reason' => 'Changed my mind', 'sort_order' => 1],
            ['type' => 'cancellation', 'reason' => 'Order took too long', 'sort_order' => 2],
            ['type' => 'cancellation', 'reason' => 'Found cheaper alternative', 'sort_order' => 3],
            ['type' => 'cancellation', 'reason' => 'Wrong address entered', 'sort_order' => 4, 'requires_comment' => true],

            // Report Reasons
            ['type' => 'report', 'reason' => 'Inappropriate behavior', 'sort_order' => 1, 'auto_action' => 'warning', 'auto_action_days' => 0],
            ['type' => 'report', 'reason' => 'Fraudulent activity', 'sort_order' => 2, 'auto_action' => 'suspend', 'auto_action_days' => 7, 'requires_evidence' => true],
            ['type' => 'report', 'reason' => 'Harassment', 'sort_order' => 3, 'auto_action' => 'suspend', 'auto_action_days' => 14, 'requires_evidence' => true],
            ['type' => 'report', 'reason' => 'Spam', 'sort_order' => 4, 'auto_action' => 'warning', 'auto_action_days' => 0],

            // Return Reasons
            ['type' => 'return', 'reason' => 'Damaged item', 'sort_order' => 1, 'requires_evidence' => true],
            ['type' => 'return', 'reason' => 'Wrong item delivered', 'sort_order' => 2, 'requires_evidence' => true],
            ['type' => 'return', 'reason' => 'Item not as described', 'sort_order' => 3],

            // Complaint Reasons
            ['type' => 'complaint', 'reason' => 'Late delivery', 'sort_order' => 1],
            ['type' => 'complaint', 'reason' => 'Rude rider', 'sort_order' => 2, 'requires_comment' => true],
            ['type' => 'complaint', 'reason' => 'Overcharged', 'sort_order' => 3],
        ];

        foreach ($reasons as $reason) {
            CancellationReason::updateOrCreate(
                ['type' => $reason['type'], 'reason' => $reason['reason']],
                $reason
            );
        }
    }
}
