<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Transaction;
use App\Models\Zone;
use App\Models\LoginAttempt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CustomerModelTest extends TestCase
{
  use RefreshDatabase;

  // =========================================================================
  // TEST: MODEL INSTANTIATION
  // =========================================================================

  #[Test]
  public function it_can_create_a_customer(): void
  {
    $zone = Zone::factory()->create();

    $customer = Customer::create([
      'phone_number' => '+2348012345678',
      'email' => 'test@example.com',
      'password' => 'Password123!',
      'first_name' => 'John',
      'last_name' => 'Doe',
      'zone_id' => $zone->id,
    ]);

    $this->assertDatabaseHas('customers', [
      'email' => 'test@example.com',
      'first_name' => 'John',
      'last_name' => 'Doe',
    ]);

    $this->assertTrue(Hash::check('Password123!', $customer->password));
  }

  #[Test]
  public function it_automatically_generates_referral_code(): void
  {
    $customer = Customer::factory()->create();

    $this->assertNotNull($customer->referral_code);
    $this->assertEquals(8, strlen($customer->referral_code));
  }

  #[Test]
  public function it_has_fillable_attributes(): void
  {
    $customer = new Customer();
    $fillable = $customer->getFillable();

    $expectedFillable = [
      'phone_number',
      'email',
      'password',
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
      'registration_ip',
      'total_rides',
      'total_spent',
      'notification_preferences',
      'timezone',
      'locale',
      'zone_id',
      'is_active',
      'is_verified',
      'email_verified_at',
      'phone_verified_at',
      'two_factor_enabled',
      'two_factor_secret',
      'two_factor_recovery_codes',
      'last_login_at',
      'last_login_ip',
      'login_count',
      'fcm_token',
    ];

    foreach ($expectedFillable as $field) {
      $this->assertContains($field, $fillable, "Field '{$field}' should be fillable");
    }
  }

  #[Test]
  public function it_hides_sensitive_attributes(): void
  {
    $customer = Customer::factory()->create([
      'password' => Hash::make('secret'),
      'two_factor_secret' => 'secret_key',
    ]);

    $array = $customer->toArray();

    $this->assertArrayNotHasKey('password', $array);
    $this->assertArrayNotHasKey('two_factor_secret', $array);
    $this->assertArrayNotHasKey('two_factor_recovery_codes', $array);
    $this->assertArrayNotHasKey('remember_token', $array);
  }

  #[Test]
  public function it_has_correct_casts(): void
  {
    $customer = Customer::factory()->create([
      'wallet_balance' => '100.50',
      'points_balance' => 50,
      'total_rides' => 10,
      'login_count' => 5,
      'is_verified' => 1,
      'is_active' => 1,
      'two_factor_enabled' => 0,
      'referral_rewarded' => 0,
      'notification_preferences' => ['email' => true, 'sms' => false],
    ]);

    $customer->refresh();

    // SQLite returns decimal as string, so check both possibilities
    $this->assertTrue(
      is_float($customer->wallet_balance) || is_string($customer->wallet_balance),
      'Wallet balance should be float or string'
    );

    // Convert to float for comparison if it's a string
    $this->assertEquals(100.50, (float) $customer->wallet_balance);

    $this->assertIsInt($customer->points_balance);
    $this->assertEquals(50, $customer->points_balance);

    $this->assertIsInt($customer->total_rides);
    $this->assertEquals(10, $customer->total_rides);

    $this->assertIsBool($customer->is_verified);
    $this->assertTrue($customer->is_verified);

    $this->assertIsArray($customer->notification_preferences);
    $this->assertTrue($customer->notification_preferences['email']);
  }

  #[Test]
  public function it_returns_full_name(): void
  {
    $customer = Customer::factory()->create([
      'first_name' => 'John',
      'last_name' => 'Doe',
    ]);

    $this->assertEquals('John Doe', $customer->full_name);
  }

  #[Test]
  public function it_returns_initials(): void
  {
    $customer = Customer::factory()->create([
      'first_name' => 'John',
      'last_name' => 'Doe',
    ]);

    $this->assertEquals('JD', $customer->initials);
  }

  #[Test]
  public function it_returns_formatted_wallet_balance(): void
  {
    $customer = Customer::factory()->create([
      'wallet_balance' => 1500.50,
    ]);

    $this->assertEquals('₦1,500.50', $customer->formatted_wallet_balance);
  }

  #[Test]
  public function it_capitalizes_first_name(): void
  {
    $customer = Customer::factory()->create([
      'first_name' => 'john',
    ]);

    $this->assertEquals('John', $customer->first_name);
  }

  #[Test]
  public function it_capitalizes_last_name(): void
  {
    $customer = Customer::factory()->create([
      'last_name' => 'doe',
    ]);

    $this->assertEquals('Doe', $customer->last_name);
  }

  #[Test]
  public function it_belongs_to_zone(): void
  {
    $zone = Zone::factory()->create();
    $customer = Customer::factory()->create(['zone_id' => $zone->id]);

    $this->assertInstanceOf(Zone::class, $customer->zone);
    $this->assertEquals($zone->id, $customer->zone->id);
  }

  #[Test]
  public function it_scopes_verified_customers(): void
  {
    Customer::factory()->create(['is_verified' => true, 'email_verified_at' => now()]);
    Customer::factory()->create(['is_verified' => false, 'email_verified_at' => null]);

    $verified = Customer::verified()->get();

    $this->assertEquals(1, $verified->count());
    $this->assertTrue($verified->first()->is_verified);
  }

  #[Test]
  public function it_scopes_active_customers(): void
  {
    Customer::factory()->create(['is_active' => true]);
    Customer::factory()->create(['is_active' => false]);

    $active = Customer::active()->get();

    $this->assertEquals(1, $active->count());
    $this->assertTrue($active->first()->is_active);
  }

  #[Test]
  public function it_scopes_phone_verified_customers(): void
  {
    Customer::factory()->create(['phone_verified_at' => now()]);
    Customer::factory()->create(['phone_verified_at' => null]);

    $verified = Customer::phoneVerified()->get();

    $this->assertEquals(1, $verified->count());
    $this->assertNotNull($verified->first()->phone_verified_at);
  }

  #[Test]
  public function it_scopes_customers_in_zone(): void
  {
    $zone1 = Zone::factory()->create();
    $zone2 = Zone::factory()->create();

    Customer::factory()->create(['zone_id' => $zone1->id]);
    Customer::factory()->create(['zone_id' => $zone2->id]);

    $customers = Customer::inZone($zone1->id)->get();

    $this->assertEquals(1, $customers->count());
    $this->assertEquals($zone1->id, $customers->first()->zone_id);
  }

  #[Test]
  public function it_checks_if_email_is_verified(): void
  {
    $customer = Customer::factory()->create([
      'is_verified' => false,
      'email_verified_at' => null,
    ]);

    $this->assertFalse($customer->hasVerifiedEmail());

    $customer->markEmailAsVerified();
    $customer->refresh();

    $this->assertTrue($customer->hasVerifiedEmail());
    $this->assertNotNull($customer->email_verified_at);
    $this->assertTrue($customer->is_verified);
  }

  #[Test]
  public function it_checks_if_phone_is_verified(): void
  {
    $customer = Customer::factory()->create([
      'phone_verified_at' => null,
    ]);

    $this->assertFalse($customer->hasVerifiedPhone());

    $customer->markPhoneAsVerified();
    $customer->refresh();

    $this->assertTrue($customer->hasVerifiedPhone());
    $this->assertNotNull($customer->phone_verified_at);
  }

  #[Test]
  public function it_adds_to_wallet(): void
  {
    $customer = Customer::factory()->create(['wallet_balance' => 100]);

    $result = $customer->addToWallet(50.50);

    $this->assertTrue($result);
    $this->assertEquals(150.50, $customer->wallet_balance);
  }

  #[Test]
  public function it_deducts_from_wallet(): void
  {
    $customer = Customer::factory()->create(['wallet_balance' => 100]);

    $result = $customer->deductFromWallet(40.75);

    $this->assertTrue($result);
    $this->assertEquals(59.25, $customer->wallet_balance);
  }

  #[Test]
  public function it_cannot_deduct_more_than_balance(): void
  {
    $customer = Customer::factory()->create(['wallet_balance' => 100]);

    $result = $customer->deductFromWallet(150);

    $this->assertFalse($result);
    $this->assertEquals(100, $customer->wallet_balance);
  }

  #[Test]
  public function it_updates_total_spent(): void
  {
    $customer = Customer::factory()->create([
      'total_spent' => 0,
      'total_rides' => 0,
    ]);

    $customer->updateTotalSpent(250.75);

    $this->assertEquals(250.75, $customer->total_spent);
    $this->assertEquals(1, $customer->total_rides);

    $customer->updateTotalSpent(100.50);

    $this->assertEquals(351.25, $customer->total_spent);
    $this->assertEquals(2, $customer->total_rides);
  }

  #[Test]
  public function it_identifies_as_customer(): void
  {
    $customer = Customer::factory()->create();

    $this->assertTrue($customer->isCustomer());
    $this->assertFalse($customer->isAdmin());
  }

  #[Test]
  public function it_implements_jwt_subject(): void
  {
    $customer = Customer::factory()->create();

    $this->assertEquals($customer->id, $customer->getJWTIdentifier());
    $this->assertIsArray($customer->getJWTCustomClaims());
    $this->assertEmpty($customer->getJWTCustomClaims());
  }

  #[Test]
  public function it_uses_soft_deletes(): void
  {
    $customer = Customer::factory()->create();

    $customer->delete();

    $this->assertNotNull($customer->deleted_at);
    $this->assertDatabaseHas('customers', ['id' => $customer->id]);
    $this->assertNull(Customer::find($customer->id));
    $this->assertNotNull(Customer::withTrashed()->find($customer->id));
  }
}
