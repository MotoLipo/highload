<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AuthServiceTest extends TestCase
{
    use RefreshDatabase;

    public function testLoginReturnsATokenForValidCredentials(): void
    {
        $user = User::factory()->create(['password' => Hash::make('secret-password')]);

        $token = (new AuthService)->login($user->email, 'secret-password');

        $this->assertNotEmpty($token);
    }

    public function testLoginThrowsForAnUnknownEmail(): void
    {
        $this->expectException(ValidationException::class);

        (new AuthService)->login('nobody@example.com', 'whatever');
    }

    public function testLoginThrowsForAWrongPassword(): void
    {
        $user = User::factory()->create(['password' => Hash::make('secret-password')]);

        $this->expectException(ValidationException::class);

        (new AuthService)->login($user->email, 'wrong-password');
    }
}
