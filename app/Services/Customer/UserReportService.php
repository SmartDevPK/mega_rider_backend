<?php

namespace App\Services\Customer;

// Create UserRepository for database operations
namespace App\Repositories;

use App\Models\Customer;

class UserRepository
{
    public function findByEmail(string $email): ?Customer
    {
        return Customer::where('email', $email)->first();
    }

    public function findByPhone(string $phone): ?Customer
    {
        return Customer::where('phone_number', $phone)->first();
    }

    public function findByReferralCode(string $code): ?Customer
    {
        return Customer::where('referral_code', $code)->first();
    }

    public function create(array $data): Customer
    {
        return Customer::create($data);
    }

    public function update(Customer $user, array $data): bool
    {
        return $user->update($data);
    }

    public function emailExists(string $email, ?int $excludeId = null): bool
    {
        $query = Customer::where('email', $email);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    public function phoneExists(string $phone, ?int $excludeId = null): bool
    {
        $query = Customer::where('phone_number', $phone);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }
}
