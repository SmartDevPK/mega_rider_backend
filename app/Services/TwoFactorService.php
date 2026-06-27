<?php

namespace App\Services;

use App\Models\Customer;
use Exception;
use Illuminate\Support\Facades\Cache;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorService
{
  protected Google2FA $google2fa;

  public function __construct()
  {
    $this->google2fa = new Google2FA();
  }

  public function enable(Customer $user): array
  {
    $secret = $this->google2fa->generateSecretKey();
    $qrCodeUrl = $this->google2fa->getQRCodeUrl(
      config('app.name'),
      $user->email,
      $secret
    );

    $user->update([
      'two_factor_secret' => $secret,
      'two_factor_enabled' => false,
    ]);

    return [
      'secret' => $secret,
      'qr_code_url' => $qrCodeUrl,
    ];
  }

  public function verify(Customer $user, string $code): bool
  {
    if (!$user->two_factor_secret) {
      return false;
    }

    $valid = $this->google2fa->verifyKey($user->two_factor_secret, $code);

    if ($valid && !$user->two_factor_enabled) {
      $user->update(['two_factor_enabled' => true]);
    }

    return $valid;
  }

  public function regenerateRecoveryCodes(Customer $user): array
  {
    $codes = [];
    for ($i = 0; $i < 8; $i++) {
      $codes[] = strtoupper(substr(md5(uniqid()), 0, 8));
    }

    $user->update([
      'two_factor_recovery_codes' => json_encode($codes)
    ]);

    return $codes;
  }
}
