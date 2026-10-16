<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CookieAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_sets_httponly_cookie()
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200);
        $response->assertCookie('access_token');

        $cookies = $response->headers->getCookies();
        $accessTokenCookie = collect($cookies)->first(fn($c) => $c->getName() === 'access_token');
        $this->assertTrue($accessTokenCookie->isHttpOnly());

        $response->assertJsonMissing(['access_token']);
    }

    public function test_protected_route_works_with_cookie()
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $token = $user->createToken('test_token')->plainTextToken;

        $response = $this->call('POST', '/api/auth/me', [], ['access_token' => $token]);

        $response->assertStatus(200);
        $response->assertJsonPath('email', $user->email);
    }

    public function test_logout_clears_cookie()
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $token = $user->createToken('test_token')->plainTextToken;

        $response = $this->call('POST', '/api/auth/logout', [], ['access_token' => $token]);

        $response->assertStatus(200);
        $response->assertCookieExpired('access_token');
    }
}
