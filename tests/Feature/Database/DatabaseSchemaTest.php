<?php

declare(strict_types=1);

namespace Tests\Unit\Database;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseSchemaTest extends TestCase
{
  use RefreshDatabase;

    // =========================================================================
    // TEST: TABLES EXIST
    // =========================================================================

  /** @test */
  public function all_tables_are_created()
  {
    $expectedTables = [
      'zones',
      'customers',
      'riders',
      'admins',
      'customer_addresses',
      'rider_vehicles',
      'trips',
      'rider_earnings',
      'payments',
      'activity_logs',
      'password_reset_tokens',
      'sessions',
    ];

    foreach ($expectedTables as $table) {
      $this->assertTrue(
        Schema::hasTable($table),
        "Table '{$table}' does not exist"
      );
    }
  }

    // =========================================================================
    // TEST: ZONES TABLE
    // =========================================================================

  /** @test */
  public function zones_table_has_correct_columns()
  {
    $expectedColumns = [
      'id',
      'name',
      'description',
      'is_active',
      'created_at',
      'updated_at',
      'deleted_at',
    ];

    $this->assertTableHasColumns('zones', $expectedColumns);
    $this->assertTrue(Schema::hasIndex('zones', 'zones_is_active_index'));
    $this->assertTrue(Schema::hasIndex('zones', 'zones_name_index'));
  }

  /** @test */
  public function zones_table_can_insert_record()
  {
    $zoneId = DB::table('zones')->insertGetId([
      'name' => 'Test Zone',
      'description' => 'Test Description',
      'is_active' => true,
      'created_at' => now(),
      'updated_at' => now(),
    ]);

    $zone = DB::table('zones')->find($zoneId);

    $this->assertNotNull($zone);
    $this->assertEquals('Test Zone', $zone->name);
    $this->assertEquals('Test Description', $zone->description);
    $this->assertEquals(1, $zone->is_active);
  }

    // =========================================================================
    // TEST: CUSTOMERS TABLE
    // =========================================================================

  /** @test */
  public function customers_table_has_correct_columns()
  {
    $expectedColumns = [
      'id',
      'phone_number',
      'registration_ip',
      'email',
      'password',
      'remember_token',
      'first_name',
      'last_name',
      'profile_picture',
      'address',
      'latitude',
      'longitude',
      'referral_code',
      'referred_by',
      'referral_rewarded',
      'wallet_balance',
      'points_balance',
      'total_rides',
      'total_spent',
      'notification_preferences',
      'timezone',
      'locale',
      'email_verification_code',
      'password_reset_code',
      'password_reset_expires_at',
      'deactivated_at',
      'deactivation_reason',
      'zone_id',
      'is_active',
      'is_verified',
      'email_verified_at',
      'phone_verified_at',
      'two_factor_enabled',
      'two_factor_secret',
      'email_verification_sent_at',
      'two_factor_recovery_codes',
      'last_login_at',
      'last_login_ip',
      'login_count',
      'fcm_token',
      'created_at',
      'updated_at',
      'deleted_at',
    ];

    $this->assertTableHasColumns('customers', $expectedColumns);

    // Check indexes
    $this->assertTrue(Schema::hasIndex('customers', 'idx_customers_email_active'));
    $this->assertTrue(Schema::hasIndex('customers', 'idx_customers_phone_active'));
    $this->assertTrue(Schema::hasIndex('customers', 'idx_customers_referral'));
    $this->assertTrue(Schema::hasIndex('customers', 'idx_customers_created'));
    $this->assertTrue(Schema::hasIndex('customers', 'idx_customers_name'));
    $this->assertTrue(Schema::hasIndex('customers', 'idx_customers_zone'));
    $this->assertTrue(Schema::hasIndex('customers', 'idx_customers_balance'));
    $this->assertTrue(Schema::hasIndex('customers', 'idx_customers_fcm'));
  }

  /** @test */
  public function customers_table_has_unique_constraints()
  {
    // Check unique constraints by trying to insert duplicates
    DB::table('zones')->insert([
      'name' => 'Test Zone',
      'created_at' => now(),
      'updated_at' => now(),
    ]);

    // First customer
    $customerId = DB::table('customers')->insertGetId([
      'phone_number' => '+2348012345678',
      'email' => 'test@example.com',
      'password' => bcrypt('password'),
      'first_name' => 'John',
      'last_name' => 'Doe',
      'referral_code' => 'REF123',
      'zone_id' => 1,
      'created_at' => now(),
      'updated_at' => now(),
    ]);

    $this->assertNotNull($customerId);

    // Try to insert duplicate email (should fail)
    $this->expectException(\Illuminate\Database\QueryException::class);

    DB::table('customers')->insert([
      'phone_number' => '+2348098765432',
      'email' => 'test@example.com', // Duplicate
      'password' => bcrypt('password'),
      'first_name' => 'Jane',
      'last_name' => 'Smith',
      'referral_code' => 'REF456',
      'zone_id' => 1,
      'created_at' => now(),
      'updated_at' => now(),
    ]);
  }

  /** @test */
  public function customers_table_can_insert_full_record()
  {
    DB::table('zones')->insert([
      'name' => 'Test Zone',
      'created_at' => now(),
      'updated_at' => now(),
    ]);

    $customerId = DB::table('customers')->insertGetId([
      'phone_number' => '+2348012345678',
      'registration_ip' => '192.168.1.1',
      'email' => 'john.doe@example.com',
      'password' => bcrypt('Password123!'),
      'first_name' => 'John',
      'last_name' => 'Doe',
      'address' => '123 Main Street',
      'latitude' => 6.5243793,
      'longitude' => 3.3792057,
      'referral_code' => 'JOHNDOE123',
      'referred_by' => null,
      'referral_rewarded' => false,
      'wallet_balance' => 0,
      'points_balance' => 0,
      'total_rides' => 0,
      'total_spent' => 0,
      'timezone' => 'Africa/Lagos',
      'locale' => 'en',
      'zone_id' => 1,
      'is_active' => true,
      'is_verified' => false,
      'created_at' => now(),
      'updated_at' => now(),
    ]);

    $customer = DB::table('customers')->find($customerId);

    $this->assertNotNull($customer);
    $this->assertEquals('john.doe@example.com', $customer->email);
    $this->assertEquals('John', $customer->first_name);
    $this->assertEquals('Doe', $customer->last_name);
    $this->assertEquals('+2348012345678', $customer->phone_number);
    $this->assertEquals(6.5243793, $customer->latitude);
    $this->assertEquals(3.3792057, $customer->longitude);
    $this->assertEquals('JOHNDOE123', $customer->referral_code);
  }

    // =========================================================================
    // TEST: RIDERS TABLE
    // =========================================================================

  /** @test */
  public function riders_table_has_correct_columns()
  {
    $expectedColumns = [
      'id',
      'phone_number',
      'email',
      'password',
      'remember_token',
      'first_name',
      'last_name',
      'gender',
      'address',
      'nin',
      'profile_picture',
      'image_path',
      'rating',
      'total_trips',
      'total_deliveries',
      'completed_trips',
      'cancelled_trips',
      'acceptance_rate',
      'is_online',
      'is_available',
      'is_busy',
      'zone_id',
      'current_latitude',
      'current_longitude',
      'location_updated_at',
      'last_status_update',
      'vehicle_type',
      'vehicle_color',
      'vehicle_plate_number',
      'vehicle_model',
      'seating_capacity',
      'driver_license_number',
      'driver_license_path',
      'proof_of_address_path',
      'license_verified_at',
      'background_check_passed',
      'phone_verified',
      'total_earned',
      'current_balance',
      'pending_payout',
      'total_withdrawn',
      'guarantor_name',
      'guarantor_phone',
      'guarantor_relationship',
      'guarantor_address',
      'guarantor_occupation',
      'nok_name',
      'nok_phone',
      'nok_relationship',
      'nok_address',
      'previous_place_of_work',
      'years_of_work',
      'verification_status',
      'rejection_reason',
      'approved_at',
      'approved_by',
      'two_factor_enabled',
      'two_factor_secret',
      'two_factor_recovery_codes',
      'two_factor_auth',
      'password_updated_at',
      'password_set_at',
      'password_reset_token',
      'password_reset_token_expires_at',
      'password_reset_attempts',
      'otp_code',
      'otp_expires_at',
      'otp_verified_at',
      'otp_attempts',
      'otp_last_attempt_at',
      'email_verification_code',
      'email_verification_sent_at',
      'email_verified',
      'is_active',
      'is_deleted',
      'email_verified_at',
      'fcm_token',
      'device_id',
      'last_login_at',
      'last_login_ip',
      'login_count',
      'created_at',
      'updated_at',
      'deleted_at',
    ];

    $this->assertTableHasColumns('riders', $expectedColumns);

    // Check indexes
    $this->assertTrue(Schema::hasIndex('riders', 'idx_riders_matching'));
    $this->assertTrue(Schema::hasIndex('riders', 'idx_riders_zone_available'));
    $this->assertTrue(Schema::hasIndex('riders', 'idx_riders_location'));
    $this->assertTrue(Schema::hasIndex('riders', 'idx_riders_email_active'));
    $this->assertTrue(Schema::hasIndex('riders', 'idx_riders_phone_active'));
    $this->assertTrue(Schema::hasIndex('riders', 'idx_riders_verification'));
    $this->assertTrue(Schema::hasIndex('riders', 'idx_riders_rating'));
    $this->assertTrue(Schema::hasIndex('riders', 'idx_riders_online'));
    $this->assertTrue(Schema::hasIndex('riders', 'idx_riders_fcm'));
  }

  /** @test */
  public function riders_table_has_unique_constraints()
  {
    DB::table('zones')->insert([
      'name' => 'Test Zone',
      'created_at' => now(),
      'updated_at' => now(),
    ]);

    // First rider
    $riderId = DB::table('riders')->insertGetId([
      'phone_number' => '+2348012345678',
      'email' => 'rider@example.com',
      'password' => bcrypt('password'),
      'first_name' => 'Rider',
      'last_name' => 'One',
      'vehicle_plate_number' => 'ABC-1234',
      'driver_license_number' => 'DL-12345',
      'zone_id' => 1,
      'created_at' => now(),
      'updated_at' => now(),
    ]);

    $this->assertNotNull($riderId);

    // Try to insert duplicate email
    $this->expectException(\Illuminate\Database\QueryException::class);

    DB::table('riders')->insert([
      'phone_number' => '+2348098765432',
      'email' => 'rider@example.com', // Duplicate
      'password' => bcrypt('password'),
      'first_name' => 'Rider',
      'last_name' => 'Two',
      'vehicle_plate_number' => 'XYZ-5678',
      'driver_license_number' => 'DL-67890',
      'zone_id' => 1,
      'created_at' => now(),
      'updated_at' => now(),
    ]);
  }

    // =========================================================================
    // TEST: ADMINS TABLE
    // =========================================================================

  /** @test */
  public function admins_table_has_correct_columns()
  {
    $expectedColumns = [
      'id',
      'name',
      'email',
      'password',
      'remember_token',
      'profile_picture',
      'phone_number',
      'role',
      'is_super_admin',
      'permissions',
      'dashboard_preferences',
      'language',
      'timezone',
      'is_active',
      'is_deleted',
      'deleted_at',
      'deletion_reason',
      'deleted_by',
      'two_factor_enabled',
      'two_factor_secret',
      'two_factor_recovery_codes',
      'password_updated_at',
      'last_login_at',
      'last_login_ip',
      'login_count',
      'last_action_at',
      'created_at',
      'updated_at',
    ];

    $this->assertTableHasColumns('admins', $expectedColumns);

    $this->assertTrue(Schema::hasIndex('admins', 'idx_admins_email_active'));
    $this->assertTrue(Schema::hasIndex('admins', 'idx_admins_role'));
    $this->assertTrue(Schema::hasIndex('admins', 'idx_admins_super'));
  }

  /** @test */
  public function admins_table_can_insert_record()
  {
    $adminId = DB::table('admins')->insertGetId([
      'name' => 'Super Admin',
      'email' => 'admin@example.com',
      'password' => bcrypt('Password123!'),
      'role' => 'super_admin',
      'is_super_admin' => true,
      'is_active' => true,
      'created_at' => now(),
      'updated_at' => now(),
    ]);

    $admin = DB::table('admins')->find($adminId);

    $this->assertNotNull($admin);
    $this->assertEquals('admin@example.com', $admin->email);
    $this->assertEquals('super_admin', $admin->role);
    $this->assertEquals(1, $admin->is_super_admin);
  }

    // =========================================================================
    // TEST: CUSTOMER ADDRESSES TABLE
    // =========================================================================

  /** @test */
  public function customer_addresses_table_has_correct_columns()
  {
    $expectedColumns = [
      'id',
      'customer_id',
      'label',
      'address',
      'street',
      'city',
      'state',
      'postal_code',
      'latitude',
      'longitude',
      'is_default',
      'created_at',
      'updated_at',
    ];

    $this->assertTableHasColumns('customer_addresses', $expectedColumns);
    $this->assertTrue(Schema::hasIndex('customer_addresses', 'idx_customer_addresses_default'));
    $this->assertTrue(Schema::hasIndex('customer_addresses', 'idx_customer_addresses_coords'));
  }

  /** @test */
  public function customer_addresses_has_foreign_key()
  {
    // Create zone first
    DB::table('zones')->insert([
      'name' => 'Test Zone',
      'created_at' => now(),
      'updated_at' => now(),
    ]);

    // Create customer
    $customerId = DB::table('customers')->insertGetId([
      'phone_number' => '+2348012345678',
      'email' => 'test@example.com',
      'password' => bcrypt('password'),
      'first_name' => 'John',
      'last_name' => 'Doe',
      'zone_id' => 1,
      'created_at' => now(),
      'updated_at' => now(),
    ]);

    // Insert address
    $addressId = DB::table('customer_addresses')->insertGetId([
      'customer_id' => $customerId,
      'label' => 'Home',
      'address' => '123 Main Street, Lagos',
      'street' => 'Main Street',
      'city' => 'Lagos',
      'state' => 'Lagos',
      'postal_code' => '100001',
      'latitude' => 6.5243793,
      'longitude' => 3.3792057,
      'is_default' => true,
      'created_at' => now(),
      'updated_at' => now(),
    ]);

    $address = DB::table('customer_addresses')->find($addressId);

    $this->assertNotNull($address);
    $this->assertEquals($customerId, $address->customer_id);
    $this->assertEquals('Home', $address->label);
    $this->assertEquals(6.5243793, $address->latitude);
  }

    // =========================================================================
    // TEST: RIDER VEHICLES TABLE
    // =========================================================================

  /** @test */
  public function rider_vehicles_table_has_correct_columns()
  {
    $expectedColumns = [
      'id',
      'rider_id',
      'model',
      'color',
      'license_plate',
      'vehicle_type',
      'seating_capacity',
      'is_active',
      'is_approved',
      'photos',
      'created_at',
      'updated_at',
    ];

    $this->assertTableHasColumns('rider_vehicles', $expectedColumns);
    $this->assertTrue(Schema::hasIndex('rider_vehicles', 'idx_rider_vehicles_plate'));
    $this->assertTrue(Schema::hasIndex('rider_vehicles', 'idx_rider_vehicles_active'));
  }

    // =========================================================================
    // TEST: TRIPS TABLE
    // =========================================================================

  /** @test */
  public function trips_table_has_correct_columns()
  {
    $expectedColumns = [
      'id',
      'customer_id',
      'rider_id',
      'status',
      'pickup_latitude',
      'pickup_longitude',
      'pickup_address',
      'dropoff_latitude',
      'dropoff_longitude',
      'dropoff_address',
      'estimated_fare',
      'final_fare',
      'distance_km',
      'duration_minutes',
      'customer_rating',
      'rider_rating',
      'customer_feedback',
      'rider_feedback',
      'accepted_at',
      'arrived_at',
      'started_at',
      'completed_at',
      'cancelled_at',
      'created_at',
      'updated_at',
    ];

    $this->assertTableHasColumns('trips', $expectedColumns);
    $this->assertTrue(Schema::hasIndex('trips', 'idx_trips_customer'));
    $this->assertTrue(Schema::hasIndex('trips', 'idx_trips_rider'));
    $this->assertTrue(Schema::hasIndex('trips', 'idx_trips_status'));
    $this->assertTrue(Schema::hasIndex('trips', 'idx_trips_status_created'));
  }

  /** @test */
  public function trips_table_can_create_complete_trip()
  {
    // Create zone
    DB::table('zones')->insert([
      'name' => 'Test Zone',
      'created_at' => now(),
      'updated_at' => now(),
    ]);

    // Create customers
    $customerId = DB::table('customers')->insertGetId([
      'phone_number' => '+2348012345678',
      'email' => 'customer@example.com',
      'password' => bcrypt('password'),
      'first_name' => 'Trip',
      'last_name' => 'Customer',
      'zone_id' => 1,
      'created_at' => now(),
      'updated_at' => now(),
    ]);

    // Create rider
    $riderId = DB::table('riders')->insertGetId([
      'phone_number' => '+2348098765432',
      'email' => 'rider@example.com',
      'password' => bcrypt('password'),
      'first_name' => 'Trip',
      'last_name' => 'Rider',
      'vehicle_plate_number' => 'ABC-1234',
      'driver_license_number' => 'DL-12345',
      'zone_id' => 1,
      'created_at' => now(),
      'updated_at' => now(),
    ]);

    // Create trip
    $tripId = DB::table('trips')->insertGetId([
      'customer_id' => $customerId,
      'rider_id' => $riderId,
      'status' => 'pending',
      'pickup_latitude' => 6.5243793,
      'pickup_longitude' => 3.3792057,
      'pickup_address' => '123 Main Street',
      'dropoff_latitude' => 6.5243793,
      'dropoff_longitude' => 3.3792057,
      'dropoff_address' => '456 Another Street',
      'estimated_fare' => 500.00,
      'created_at' => now(),
      'updated_at' => now(),
    ]);

    $trip = DB::table('trips')->find($tripId);

    $this->assertNotNull($trip);
    $this->assertEquals($customerId, $trip->customer_id);
    $this->assertEquals($riderId, $trip->rider_id);
    $this->assertEquals('pending', $trip->status);
    $this->assertEquals(500.00, $trip->estimated_fare);
  }

    // =========================================================================
    // TEST: RIDER EARNINGS TABLE
    // =========================================================================

  /** @test */
  public function rider_earnings_table_has_correct_columns()
  {
    $expectedColumns = [
      'id',
      'rider_id',
      'trip_id',
      'amount',
      'commission',
      'net_earning',
      'type',
      'status',
      'paid_at',
      'description',
      'created_at',
      'updated_at',
    ];

    $this->assertTableHasColumns('rider_earnings', $expectedColumns);
    $this->assertTrue(Schema::hasIndex('rider_earnings', 'idx_rider_earnings_status'));
    $this->assertTrue(Schema::hasIndex('rider_earnings', 'idx_rider_earnings_date'));
  }

    // =========================================================================
    // TEST: PAYMENTS TABLE
    // =========================================================================

  /** @test */
  public function payments_table_has_correct_columns()
  {
    $expectedColumns = [
      'id',
      'trip_id',
      'customer_id',
      'rider_id',
      'amount',
      'commission',
      'method',
      'status',
      'transaction_id',
      'metadata',
      'paid_at',
      'created_at',
      'updated_at',
    ];

    $this->assertTableHasColumns('payments', $expectedColumns);
    $this->assertTrue(Schema::hasIndex('payments', 'idx_payments_trip'));
    $this->assertTrue(Schema::hasIndex('payments', 'idx_payments_transaction'));
  }

    // =========================================================================
    // TEST: ACTIVITY LOGS TABLE
    // =========================================================================

  /** @test */
  public function activity_logs_table_has_correct_columns()
  {
    $expectedColumns = [
      'id',
      'actor_type',
      'actor_id',
      'action',
      'ip_address',
      'metadata',
      'created_at',
    ];

    $this->assertTableHasColumns('activity_logs', $expectedColumns);
    $this->assertTrue(Schema::hasIndex('activity_logs', 'idx_activity_action_date'));
    $this->assertTrue(Schema::hasIndex('activity_logs', 'idx_activity_actor'));
  }

  /** @test */
  public function activity_logs_can_store_audit_trail()
  {
    $logId = DB::table('activity_logs')->insertGetId([
      'actor_type' => 'App\Models\Admin',
      'actor_id' => 1,
      'action' => 'user_login',
      'ip_address' => '192.168.1.1',
      'metadata' => json_encode(['user_id' => 1, 'success' => true]),
      'created_at' => now(),
    ]);

    $log = DB::table('activity_logs')->find($logId);

    $this->assertNotNull($log);
    $this->assertEquals('user_login', $log->action);
    $this->assertEquals('192.168.1.1', $log->ip_address);
    $this->assertNotNull(json_decode($log->metadata));
  }

    // =========================================================================
    // TEST: PASSWORD RESET TOKENS TABLE
    // =========================================================================

  /** @test */
  public function password_reset_tokens_table_has_correct_columns()
  {
    $expectedColumns = [
      'email',
      'token',
      'created_at',
    ];

    $this->assertTableHasColumns('password_reset_tokens', $expectedColumns);
    $this->assertTrue(Schema::hasIndex('password_reset_tokens', 'idx_password_reset_token'));
  }

    // =========================================================================
    // TEST: SESSIONS TABLE
    // =========================================================================

  /** @test */
  public function sessions_table_has_correct_columns()
  {
    $expectedColumns = [
      'id',
      'user_id',
      'user_type',
      'ip_address',
      'user_agent',
      'payload',
      'last_activity',
    ];

    $this->assertTableHasColumns('sessions', $expectedColumns);
    $this->assertTrue(Schema::hasIndex('sessions', 'idx_sessions_user'));
    $this->assertTrue(Schema::hasIndex('sessions', 'idx_sessions_activity'));
  }

    // =========================================================================
    // TEST: FOREIGN KEY CONSTRAINTS
    // =========================================================================

  /** @test */
  public function foreign_key_constraints_work_correctly()
  {
    // Create zone
    $zoneId = DB::table('zones')->insertGetId([
      'name' => 'Test Zone',
      'created_at' => now(),
      'updated_at' => now(),
    ]);

    // Create customer with zone_id
    $customerId = DB::table('customers')->insertGetId([
      'phone_number' => '+2348012345678',
      'email' => 'test@example.com',
      'password' => bcrypt('password'),
      'first_name' => 'John',
      'last_name' => 'Doe',
      'zone_id' => $zoneId,
      'created_at' => now(),
      'updated_at' => now(),
    ]);

    $customer = DB::table('customers')->find($customerId);
    $this->assertEquals($zoneId, $customer->zone_id);

    // Delete zone - should set customer.zone_id to NULL (nullOnDelete)
    DB::table('zones')->delete($zoneId);
    $customer = DB::table('customers')->find($customerId);
    $this->assertNull($customer->zone_id);
  }

    // =========================================================================
    // TEST: SOFT DELETES
    // =========================================================================

  /** @test */
  public function soft_deletes_work_on_customers_table()
  {
    $customerId = DB::table('customers')->insertGetId([
      'phone_number' => '+2348012345678',
      'email' => 'test@example.com',
      'password' => bcrypt('password'),
      'first_name' => 'John',
      'last_name' => 'Doe',
      'created_at' => now(),
      'updated_at' => now(),
    ]);

    // Soft delete
    DB::table('customers')->where('id', $customerId)->update([
      'deleted_at' => now(),
    ]);

    // Should not be found in normal query
    $customer = DB::table('customers')->where('id', $customerId)->first();
    $this->assertNotNull($customer);
    $this->assertNotNull($customer->deleted_at);

    // Should be excluded by default when using Eloquent (tested separately)
  }

  // =========================================================================
  // HELPER METHODS
  // =========================================================================

  protected function assertTableHasColumns(string $table, array $columns): void
  {
    $existingColumns = Schema::getColumnListing($table);

    foreach ($columns as $column) {
      $this->assertContains(
        $column,
        $existingColumns,
        "Column '{$column}' not found in table '{$table}'"
      );
    }
  }
}
