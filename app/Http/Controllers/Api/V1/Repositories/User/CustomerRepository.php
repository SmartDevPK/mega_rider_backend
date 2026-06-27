<?php
// App\Repositories\User\CustomerRepository.php

namespace App\Repositories\User;

use App\Models\Customer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Collection;

class CustomerRepository
{
  /**
   * Create a new customer
   */
  public function create(array $data): Customer
  {
    try {
      Log::info('CustomerRepository create called with:', $data);

      $customer = Customer::create($data);

      Log::info('Customer created successfully:', ['id' => $customer->id]);

      return $customer;
    } catch (\Exception $e) {
      Log::error('Error in CustomerRepository create: ' . $e->getMessage(), [
        'data' => $data,
        'trace' => $e->getTraceAsString()
      ]);
      throw $e;
    }
  }

  /**
   * Update a customer
   */
  public function update(Customer $customer, array $data): bool
  {
    try {
      Log::info('CustomerRepository update called:', [
        'customer_id' => $customer->id,
        'data' => $data
      ]);

      $result = $customer->update($data);

      Log::info('Customer updated successfully:', ['customer_id' => $customer->id]);

      return $result;
    } catch (\Exception $e) {
      Log::error('Error in CustomerRepository update: ' . $e->getMessage(), [
        'customer_id' => $customer->id,
        'data' => $data,
        'trace' => $e->getTraceAsString()
      ]);
      throw $e;
    }
  }

  /**
   * Delete a customer
   */
  public function delete(Customer $customer): bool
  {
    try {
      Log::info('CustomerRepository delete called:', ['customer_id' => $customer->id]);

      $result = $customer->delete();

      Log::info('Customer deleted successfully:', ['customer_id' => $customer->id]);

      return $result;
    } catch (\Exception $e) {
      Log::error('Error in CustomerRepository delete: ' . $e->getMessage(), [
        'customer_id' => $customer->id,
        'trace' => $e->getTraceAsString()
      ]);
      throw $e;
    }
  }

  /**
   * Find customer by ID
   */
  public function findById(int $id): ?Customer
  {
    try {
      return Customer::find($id);
    } catch (\Exception $e) {
      Log::error('Error in CustomerRepository findById: ' . $e->getMessage(), [
        'id' => $id,
        'trace' => $e->getTraceAsString()
      ]);
      throw $e;
    }
  }

  /**
   * Find customer by email
   */
  public function findByEmail(string $email): ?Customer
  {
    try {
      return Customer::where('email', $email)->first();
    } catch (\Exception $e) {
      Log::error('Error in CustomerRepository findByEmail: ' . $e->getMessage(), [
        'email' => $email,
        'trace' => $e->getTraceAsString()
      ]);
      throw $e;
    }
  }

  /**
   * Find customer by email and verification code
   */
  public function findByEmailAndVerificationCode(string $email, string $code): ?Customer
  {
    try {
      return Customer::where('email', $email)
        ->where('email_verification_code', $code)
        ->first();
    } catch (\Exception $e) {
      Log::error('Error in CustomerRepository findByEmailAndVerificationCode: ' . $e->getMessage(), [
        'email' => $email,
        'code' => $code,
        'trace' => $e->getTraceAsString()
      ]);
      throw $e;
    }
  }

  /**
   * Find customer by email and reset code
   */
  public function findByEmailAndResetCode(string $email, string $code): ?Customer
  {
    try {
      return Customer::where('email', $email)
        ->where('password_reset_code', $code)
        ->first();
    } catch (\Exception $e) {
      Log::error('Error in CustomerRepository findByEmailAndResetCode: ' . $e->getMessage(), [
        'email' => $email,
        'code' => $code,
        'trace' => $e->getTraceAsString()
      ]);
      throw $e;
    }
  }

  /**
   * Find customer by email or phone
   */
  public function findByEmailOrPhone(string $email, string $phone): ?Customer
  {
    try {
      return Customer::where('email', $email)
        ->orWhere('phone_number', $phone)
        ->first();
    } catch (\Exception $e) {
      Log::error('Error in CustomerRepository findByEmailOrPhone: ' . $e->getMessage(), [
        'email' => $email,
        'phone' => $phone,
        'trace' => $e->getTraceAsString()
      ]);
      throw $e;
    }
  }

  /**
   * Find customer by referral code
   */
  public function findByReferralCode(string $code): ?Customer
  {
    try {
      return Customer::where('referral_code', $code)->first();
    } catch (\Exception $e) {
      Log::error('Error in CustomerRepository findByReferralCode: ' . $e->getMessage(), [
        'code' => $code,
        'trace' => $e->getTraceAsString()
      ]);
      throw $e;
    }
  }

  /**
   * Check if email exists
   */
  public function emailExists(string $email): bool
  {
    try {
      return Customer::where('email', $email)->exists();
    } catch (\Exception $e) {
      Log::error('Error in CustomerRepository emailExists: ' . $e->getMessage(), [
        'email' => $email,
        'trace' => $e->getTraceAsString()
      ]);
      return false;
    }
  }

  /**
   * Check if phone number exists
   */
  public function phoneExists(string $phone): bool
  {
    try {
      return Customer::where('phone_number', $phone)->exists();
    } catch (\Exception $e) {
      Log::error('Error in CustomerRepository phoneExists: ' . $e->getMessage(), [
        'phone' => $phone,
        'trace' => $e->getTraceAsString()
      ]);
      return false;
    }
  }

  /**
   * Check if referral code exists
   */
  public function referralCodeExists(string $code): bool
  {
    try {
      return Customer::where('referral_code', $code)->exists();
    } catch (\Exception $e) {
      Log::error('Error in CustomerRepository referralCodeExists: ' . $e->getMessage(), [
        'code' => $code,
        'trace' => $e->getTraceAsString()
      ]);
      return true; // Return true to avoid duplicate codes
    }
  }

  /**
   * Mark email as verified
   */
  public function markEmailAsVerified(Customer $customer): bool
  {
    try {
      return $customer->update([
        'email_verified_at' => now(),
        'is_verified' => true,
        'email_verification_code' => null,
      ]);
    } catch (\Exception $e) {
      Log::error('Error in CustomerRepository markEmailAsVerified: ' . $e->getMessage(), [
        'customer_id' => $customer->id,
        'trace' => $e->getTraceAsString()
      ]);
      throw $e;
    }
  }

  /**
   * Get total customer count
   */
  public function getCustomerCount(): int
  {
    try {
      return Customer::count();
    } catch (\Exception $e) {
      Log::error('Error in CustomerRepository getCustomerCount: ' . $e->getMessage(), [
        'trace' => $e->getTraceAsString()
      ]);
      return 0;
    }
  }

  /**
   * Get active customer count
   */
  public function getActiveCustomerCount(): int
  {
    try {
      return Customer::where('is_active', true)->count();
    } catch (\Exception $e) {
      Log::error('Error in CustomerRepository getActiveCustomerCount: ' . $e->getMessage(), [
        'trace' => $e->getTraceAsString()
      ]);
      return 0;
    }
  }

  /**
   * Get customers by registration date range
   */
  public function getCustomersByDateRange(string $startDate, string $endDate): Collection
  {
    try {
      return Customer::whereBetween('created_at', [$startDate, $endDate])->get();
    } catch (\Exception $e) {
      Log::error('Error in CustomerRepository getCustomersByDateRange: ' . $e->getMessage(), [
        'start_date' => $startDate,
        'end_date' => $endDate,
        'trace' => $e->getTraceAsString()
      ]);
      return new Collection();
    }
  }

  /**
   * Update last login information
   */
  public function updateLastLogin(Customer $customer, string $ip): bool
  {
    try {
      return $customer->update([
        'last_login_at' => now(),
        'last_login_ip' => $ip,
        'login_count' => DB::raw('login_count + 1'),
      ]);
    } catch (\Exception $e) {
      Log::error('Error in CustomerRepository updateLastLogin: ' . $e->getMessage(), [
        'customer_id' => $customer->id,
        'ip' => $ip,
        'trace' => $e->getTraceAsString()
      ]);
      throw $e;
    }
  }

  /**
   * Update FCM token
   */
  public function updateFcmToken(Customer $customer, ?string $fcmToken): bool
  {
    try {
      return $customer->update(['fcm_token' => $fcmToken]);
    } catch (\Exception $e) {
      Log::error('Error in CustomerRepository updateFcmToken: ' . $e->getMessage(), [
        'customer_id' => $customer->id,
        'trace' => $e->getTraceAsString()
      ]);
      throw $e;
    }
  }

  /**
   * Get customers with pending email verification
   */
  public function getUnverifiedCustomers(): Collection
  {
    try {
      return Customer::where('is_verified', false)
        ->whereNull('email_verified_at')
        ->get();
    } catch (\Exception $e) {
      Log::error('Error in CustomerRepository getUnverifiedCustomers: ' . $e->getMessage(), [
        'trace' => $e->getTraceAsString()
      ]);
      return new \Illuminate\Database\Eloquent\Collection();
    }
  }

  /**
   * Search customers by name or email
   */
  public function searchCustomers(string $searchTerm): \Illuminate\Database\Eloquent\Collection
  {
    try {
      return Customer::where('first_name', 'LIKE', "%{$searchTerm}%")
        ->orWhere('last_name', 'LIKE', "%{$searchTerm}%")
        ->orWhere('email', 'LIKE', "%{$searchTerm}%")
        ->orWhere('phone_number', 'LIKE', "%{$searchTerm}%")
        ->get();
    } catch (\Exception $e) {
      Log::error('Error in CustomerRepository searchCustomers: ' . $e->getMessage(), [
        'search_term' => $searchTerm,
        'trace' => $e->getTraceAsString()
      ]);
      return new Collection();
    }
  }
}
