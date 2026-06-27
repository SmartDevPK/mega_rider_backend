<?php

declare(strict_types=1);

namespace App\Repositories\User;

use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * CustomerRepository
 * 
 * Handles all database operations for Customer model
 * Following the repository pattern for better separation of concerns
 */
class CustomerRepository
{
  /**
   * Find customer by ID
   */
  public function findById(int $id): ?Customer
  {
    return Customer::find($id);
  }

  /**
   * Find customer by email
   */
  public function findByEmail(string $email): ?Customer
  {
    return Customer::where('email', $email)->first();
  }

  /**
   * Find customer by phone number
   */
  public function findByPhone(string $phone): ?Customer
  {
    return Customer::where('phone_number', $phone)->first();
  }

  /**
   * Find customer by email or phone
   */
  public function findByEmailOrPhone(string $email, string $phone): ?Customer
  {
    return Customer::where('email', $email)
      ->orWhere('phone_number', $phone)
      ->first();
  }

  /**
   * Find customer by referral code
   */
  public function findByReferralCode(string $code): ?Customer
  {
    return Customer::where('referral_code', $code)->first();
  }

  /**
   * Find customer by email and verification code
   */
  public function findByEmailAndVerificationCode(string $email, string $code): ?Customer
  {
    return Customer::where('email', $email)
      ->where('email_verification_code', $code)
      ->first();
  }

  /**
   * Find customer by email and reset code
   */
  public function findByEmailAndResetCode(string $email, string $code): ?Customer
  {
    return Customer::where('email', $email)
      ->where('password_reset_code', $code)
      ->first();
  }

  /**
   * Create new customer
   */
  public function create(array $data): Customer
  {
    return DB::transaction(function () use ($data) {
      return Customer::create($data);
    });
  }

  /**
   * Update customer
   */
  public function update(Customer $customer, array $data): bool
  {
    return DB::transaction(function () use ($customer, $data) {
      return $customer->update($data);
    });
  }

  /**
   * Delete customer (soft delete)
   */
  public function delete(Customer $customer): bool
  {
    return DB::transaction(function () use ($customer) {
      return $customer->delete();
    });
  }

  /**
   * Force delete customer
   */
  public function forceDelete(Customer $customer): bool
  {
    return DB::transaction(function () use ($customer) {
      return $customer->forceDelete();
    });
  }

  /**
   * Check if email exists
   */
  public function emailExists(string $email, ?int $excludeId = null): bool
  {
    $query = Customer::where('email', $email);

    if ($excludeId) {
      $query->where('id', '!=', $excludeId);
    }

    return $query->exists();
  }

  /**
   * Check if phone number exists
   */
  public function phoneExists(string $phone, ?int $excludeId = null): bool
  {
    $query = Customer::where('phone_number', $phone);

    if ($excludeId) {
      $query->where('id', '!=', $excludeId);
    }

    return $query->exists();
  }

  /**
   * Check if referral code exists
   */
  public function referralCodeExists(string $code): bool
  {
    return Customer::where('referral_code', $code)->exists();
  }

  /**
   * Mark email as verified
   */
  public function markEmailAsVerified(Customer $customer): bool
  {
    return $customer->update([
      'is_verified' => true,
      'email_verification_code' => null,
      'email_verified_at' => now(),
    ]);
  }

  /**
   * Get all active customers
   */
  public function getActiveCustomers(int $limit = 100)
  {
    return Customer::where('is_active', true)
      ->where('is_deleted', false)
      ->limit($limit)
      ->get();
  }

  /**
   * Get customers registered in date range
   */
  public function getCustomersRegisteredBetween(string $startDate, string $endDate)
  {
    return Customer::whereBetween('created_at', [$startDate, $endDate])
      ->get();
  }

  /**
   * Get customer count
   */
  public function getCustomerCount(): int
  {
    return Customer::count();
  }

  /**
   * Get active customer count
   */
  public function getActiveCustomerCount(): int
  {
    return Customer::where('is_active', true)
      ->where('is_deleted', false)
      ->count();
  }
}
