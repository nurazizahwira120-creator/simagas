<?php

namespace Database\Factories;

use App\Enums\StatusAkun;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => UserRole::WaliMurid,
            // Eksplisit, bukan mengandalkan default kolom di database: model
            // hasil factory->create() tidak otomatis memuat ulang nilai default
            // dari DB, sehingga tanpa baris ini $user->status bisa null dan
            // memanggil ->bolehLogin() padanya akan error.
            'status' => StatusAkun::Active,
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function superAdmin(): static
    {
        return $this->state(fn () => ['role' => UserRole::SuperAdmin]);
    }

    public function kepsek(): static
    {
        return $this->state(fn () => ['role' => UserRole::Kepsek]);
    }

    public function waliKelas(): static
    {
        return $this->state(fn () => ['role' => UserRole::WaliKelas]);
    }

    public function guru(): static
    {
        return $this->state(fn () => ['role' => UserRole::Guru]);
    }

    public function staff(): static
    {
        return $this->state(fn () => ['role' => UserRole::Staff]);
    }

    public function adminTu(): static
    {
        return $this->state(fn () => ['role' => UserRole::AdminTu]);
    }

    public function waliMurid(): static
    {
        return $this->state(fn () => ['role' => UserRole::WaliMurid]);
    }

    public function guruPiket(): static
    {
        return $this->state(fn () => ['role' => UserRole::GuruPiket]);
    }
}
