<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class AuditService
{

  // Constructor logic if needed

  public function log(string $message, array $context = [])
  {
    // Log the audit trail
    // Example: save to database, file, etc.

    Log::info('AUDIT: ' . $message, $context);

    // Or save to database
    // AuditLog::create(['message' => $message, 'context' => $context]);
  }

  public function info(string $message)
  {
    return $this->log($message, ['level' => 'info']);
  }

  public function warning(string $message)
  {
    return $this->log($message, ['level' => 'warning']);
  }

  public function error(string $message)
  {
    return $this->log($message, ['level' => 'error']);
  }
}
