<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\User;
use ClickHouseDB\Client;
use ClickHouseDB\Statement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    public function testLoginReturnsATokenForValidCredentials(): void
    {
        User::factory()->create(['email' => 'test@example.com', 'password' => Hash::make('password')]);

        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $response->assertOk()->assertJsonStructure(['token']);
    }

    public function testTokenIssuedByLoginActuallyGrantsAccessToProtectedRoutes(): void
    {
        User::factory()->create(['email' => 'test@example.com', 'password' => Hash::make('password')]);

        $token = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'password',
        ])->json('token');

        $statement = Mockery::mock(Statement::class);
        $statement->shouldReceive('rows')->once()->andReturn([]);
        $client = Mockery::mock(Client::class);
        $client->shouldReceive('select')->once()->andReturn($statement);
        $this->app->instance(Client::class, $client);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/analytics/page-views')
            ->assertOk();
    }

    public function testLoginFailsForAWrongPassword(): void
    {
        User::factory()->create(['email' => 'test@example.com', 'password' => Hash::make('password')]);

        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    public function testLoginFailsForAnUnknownEmail(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'nobody@example.com',
            'password' => 'whatever',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    public function testLoginRequiresEmailAndPassword(): void
    {
        $this->postJson('/api/login', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function testLoginIsRateLimitedAfterFiveAttemptsPerMinute(): void
    {
        User::factory()->create(['email' => 'test@example.com', 'password' => Hash::make('password')]);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', ['email' => 'test@example.com', 'password' => 'wrong'])
                ->assertStatus(422);
        }

        $this->postJson('/api/login', ['email' => 'test@example.com', 'password' => 'wrong'])
            ->assertStatus(429);
    }

    public function testRateLimitIsKeyedByForwardedClientIpBehindATrustedProxy(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->withHeader('X-Forwarded-For', '203.0.113.1')
                ->postJson('/api/login', ['email' => 'nobody@example.com', 'password' => 'wrong'])
                ->assertStatus(422);
        }

        $this->withHeader('X-Forwarded-For', '203.0.113.1')
            ->postJson('/api/login', ['email' => 'nobody@example.com', 'password' => 'wrong'])
            ->assertStatus(429);

        $this->withHeader('X-Forwarded-For', '203.0.113.2')
            ->postJson('/api/login', ['email' => 'nobody@example.com', 'password' => 'wrong'])
            ->assertStatus(422);
    }
}
