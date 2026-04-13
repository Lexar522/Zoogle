<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class GoogleAuthCallbackTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_callback_creates_user_and_logs_in_when_google_returns_profile(): void
    {
        config([
            'services.google.client_id' => 'test-id',
            'services.google.client_secret' => 'test-secret',
            'services.google.redirect' => 'http://localhost/callback',
        ]);

        $socialUser = (new SocialiteUser)->map([
            'id' => 'google-sub-99',
            'nickname' => null,
            'name' => 'Test Buyer',
            'email' => 'buyer@example.test',
            'avatar' => 'https://example.test/a.png',
            'avatar_original' => 'https://example.test/a.png',
        ]);

        Socialite::shouldReceive('driver')
            ->once()
            ->with('google')
            ->andReturnSelf();
        Socialite::shouldReceive('stateless')
            ->once()
            ->andReturnSelf();
        Socialite::shouldReceive('redirectUrl')
            ->once()
            ->andReturnSelf();
        Socialite::shouldReceive('user')
            ->once()
            ->andReturn($socialUser);

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('account.index'));

        $this->assertAuthenticated();
        $user = User::query()->where('email', 'buyer@example.test')->first();
        $this->assertNotNull($user);
        $this->assertSame('google-sub-99', $user->google_id);
        $this->assertSame('Test Buyer', $user->name);
    }
}
