<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

class EmailCheckController extends Controller
{
  use ApiResponseTrait;

  private const MAX_BULK_EMAILS = 50;
  private const RATE_LIMIT_PER_MINUTE = 30;

  public function __construct()
  {
    $this->middleware('throttle:' . self::RATE_LIMIT_PER_MINUTE . ',1');
  }

  // =========================================================
  // SINGLE EMAIL CHECK
  // =========================================================

  public function checkEmail(Request $request): JsonResponse
  {
    $requestId = $this->generateRequestId();
    $startTime = microtime(true);

    try {
      $email = $this->validateEmail($request, $requestId);
      if ($email instanceof JsonResponse) {
        return $email;
      }

      $user = Customer::where('email', $email)->first();
      $exists = (bool) $user;

      $responseData = $this->buildEmailResponse($email, $user, $exists);

      $this->logEmailCheck($requestId, $email, $exists, $request->ip(), $startTime);

      return $this->successResponse($responseData, $requestId);
    } catch (Throwable $e) {
      $this->logError($requestId, $e, $email ?? null);

      return $this->errorResponse(
        'EMAIL_CHECK_ERROR',
        'Unable to check email availability.',
        ['request_id' => $requestId],
        Response::HTTP_INTERNAL_SERVER_ERROR,
        $requestId
      );
    }
  }

  // =========================================================
  // EMAIL WITH USER DETAILS
  // =========================================================

  public function checkEmailWithUserDetails(Request $request): JsonResponse
  {
    $requestId = $this->generateRequestId();

    try {
      $email = $this->validateEmail($request, $requestId);
      if ($email instanceof JsonResponse) {
        return $email;
      }

      $user = Customer::where('email', $email)->first();

      if (!$user) {
        return $this->successResponse([
          'exists' => false,
          'message' => 'Email not found.',
          'action' => 'register',
        ], $requestId);
      }

      return $this->successResponse([
        'exists' => true,
        'message' => 'Email found.',
        'action' => 'login',
        'user' => $this->formatUser($user),
      ], $requestId);
    } catch (Throwable $e) {
      Log::error('Email detail check failed', [
        'request_id' => $requestId,
        'error' => $e->getMessage(),
      ]);

      return $this->errorResponse(
        'EMAIL_CHECK_ERROR',
        'Unable to process request.',
        null,
        Response::HTTP_INTERNAL_SERVER_ERROR,
        $requestId
      );
    }
  }

  // =========================================================
  // BULK EMAIL CHECK
  // =========================================================

  public function checkMultiple(Request $request): JsonResponse
  {
    $requestId = $this->generateRequestId();

    try {
      $emails = $request->input('emails', []);

      if (!is_array($emails) || empty($emails)) {
        return $this->errorResponse(
          'VALIDATION_ERROR',
          'Emails array is required.',
          null,
          Response::HTTP_UNPROCESSABLE_ENTITY,
          $requestId
        );
      }

      if (count($emails) > self::MAX_BULK_EMAILS) {
        return $this->errorResponse(
          'VALIDATION_ERROR',
          'Maximum ' . self::MAX_BULK_EMAILS . ' emails allowed.',
          null,
          Response::HTTP_UNPROCESSABLE_ENTITY,
          $requestId
        );
      }

      $results = [];

      foreach ($emails as $emailInput) {
        $email = strtolower(trim($emailInput));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
          $results[] = [
            'email' => $email,
            'exists' => false,
            'can_register' => false,
            'action' => null,
            'error' => 'Invalid email format'
          ];
          continue;
        }

        $exists = Customer::where('email', $email)->exists();

        $results[] = [
          'email' => $email,
          'exists' => $exists,
          'can_register' => !$exists,
          'action' => $exists ? 'login' : 'register',
        ];
      }

      $available = count(array_filter($results, fn($r) => $r['can_register']));

      Log::info('Bulk email check completed', [
        'request_id' => $requestId,
        'total' => count($results),
        'available' => $available,
      ]);

      return $this->successResponse([
        'total' => count($results),
        'available' => $available,
        'taken' => count($results) - $available,
        'results' => $results,
      ], $requestId);
    } catch (Throwable $e) {
      $this->logError($requestId, $e);

      return $this->errorResponse(
        'BULK_EMAIL_CHECK_ERROR',
        'Request failed.',
        ['request_id' => $requestId],
        Response::HTTP_INTERNAL_SERVER_ERROR,
        $requestId
      );
    }
  }

  // =========================================================
  // HELPERS
  // =========================================================

  private function validateEmail(Request $request, string $requestId): string|JsonResponse
  {
    $email = $request->input('email');

    if (!$email) {
      return $this->errorResponse(
        'VALIDATION_ERROR',
        'Email is required.',
        null,
        Response::HTTP_UNPROCESSABLE_ENTITY,
        $requestId
      );
    }

    $email = strtolower(trim($email));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      return $this->errorResponse(
        'VALIDATION_ERROR',
        'Invalid email format.',
        null,
        Response::HTTP_UNPROCESSABLE_ENTITY,
        $requestId
      );
    }

    return $email;
  }

  private function buildEmailResponse(string $email, ?Customer $user, bool $exists): array
  {
    if ($exists) {
      return [
        'email' => $email,
        'exists' => true,
        'can_register' => false,
        'message' => 'Email already registered.',
        'action' => 'login',
        'login_url' => '/api/v1/auth/public/login',
        'reset_password_url' => '/api/v1/auth/public/forgot-password',
        'user' => $this->formatUser($user),
      ];
    }

    return [
      'email' => $email,
      'exists' => false,
      'can_register' => true,
      'message' => 'Email available.',
      'action' => 'register',
      'register_url' => '/api/v1/auth/public/register',
      'send_otp_url' => '/api/v1/auth/public/send-otp',
    ];
  }

  private function formatUser(Customer $user): array
  {
    return [
      'first_name' => $user->first_name,
      'last_name' => $user->last_name,
      'full_name' => $user->full_name,
      'is_verified' => $user->is_verified,
      'is_active' => $user->is_active,
      'last_login_at' => $user->last_login_at?->toIso8601String(),
    ];
  }

  private function logEmailCheck(string $requestId, string $email, bool $exists, ?string $ip, float $start): void
  {
    Log::info('Email check', [
      'request_id' => $requestId,
      'email' => $email,
      'exists' => $exists,
      'ip' => $ip,
      'duration_ms' => round((microtime(true) - $start) * 1000, 2),
    ]);
  }

  private function logError(string $requestId, Throwable $e, ?string $email = null): void
  {
    Log::error('Email check error', [
      'request_id' => $requestId,
      'email' => $email,
      'error' => $e->getMessage(),
      'type' => get_class($e),
    ]);
  }
}
