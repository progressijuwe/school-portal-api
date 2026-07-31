<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The throttle middleware shares a limiter store across tests.
        RateLimiter::clear('login');
    }

    public function test_a_student_can_log_in_and_receives_a_token(): void
    {
        $user = User::factory()->student()->create([
            'email' => 'ada@uni.edu',
            'password' => Hash::make('Correct-Horse-1!'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'ada@uni.edu',
            'password' => 'Correct-Horse-1!',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.role', 'student')
            ->assertJsonStructure(['data' => ['token', 'user' => ['id', 'name', 'email', 'role']]]);

        $this->assertNotEmpty($response->json('data.token'));
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_login_fails_with_the_wrong_password(): void
    {
        User::factory()->student()->create([
            'email' => 'ada@uni.edu',
            'password' => Hash::make('Correct-Horse-1!'),
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'ada@uni.edu',
            'password' => 'wrong-password',
        ])
            ->assertStatus(401)
            ->assertJsonPath('success', false);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_the_password_is_never_returned(): void
    {
        User::factory()->student()->create([
            'email' => 'ada@uni.edu',
            'password' => Hash::make('Correct-Horse-1!'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'ada@uni.edu',
            'password' => 'Correct-Horse-1!',
        ]);

        $this->assertArrayNotHasKey('password', $response->json('data.user'));
    }

    public function test_login_is_throttled_after_five_failed_attempts(): void
    {
        User::factory()->student()->create(['email' => 'ada@uni.edu']);

        foreach (range(1, 5) as $ignored) {
            $this->postJson('/api/auth/login', [
                'email' => 'ada@uni.edu',
                'password' => 'wrong-password',
            ])->assertStatus(401);
        }

        $this->postJson('/api/auth/login', [
            'email' => 'ada@uni.edu',
            'password' => 'wrong-password',
        ])->assertStatus(429);
    }

    public function test_throttling_is_scoped_to_the_email_not_only_the_ip(): void
    {
        User::factory()->student()->create(['email' => 'ada@uni.edu']);
        User::factory()->student()->create([
            'email' => 'grace@uni.edu',
            'password' => Hash::make('Correct-Horse-1!'),
        ]);

        // Exhaust the allowance for one account...
        foreach (range(1, 5) as $ignored) {
            $this->postJson('/api/auth/login', [
                'email' => 'ada@uni.edu',
                'password' => 'wrong-password',
            ]);
        }

        // ...a different account from the same IP must still be able to log in.
        // Keying the limiter on IP alone would lock out a whole campus gateway.
        $this->postJson('/api/auth/login', [
            'email' => 'grace@uni.edu',
            'password' => 'Correct-Horse-1!',
        ])->assertOk();
    }

    public function test_logout_revokes_only_the_current_token(): void
    {
        $user = User::factory()->student()->create();
        $keep = $user->createToken('other-device')->plainTextToken;
        $current = $user->createToken('this-device')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$current}")
            ->postJson('/api/auth/logout')
            ->assertOk();

        // The other device's token survives; only the presented one is revoked.
        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertSame(
            (int) explode('|', $keep)[0],
            $user->tokens()->sole()->id,
        );
    }

    public function test_unauthenticated_requests_are_rejected_as_json(): void
    {
        $this->getJson('/api/profile')
            ->assertStatus(401)
            ->assertJsonPath('success', false);
    }
}
