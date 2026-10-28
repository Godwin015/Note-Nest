<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeaders([
            'Origin' => 'http://localhost:5173',
            'Referer' => 'http://localhost:5173',
        ]);
    }


    /**
     * Register a new user successfully.
     */
    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertCreated()
            ->assertJson([
                'message' => 'Registration successful.',
                'user' => [
                    'name' => 'Test User',
                    'email' => 'test@example.com',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $user = User::where('email', 'test@example.com')->first();

        $this->assertNotNull($user);
        $this->assertTrue(
            Hash::check('password123', $user->password)
        );

        $this->assertAuthenticatedAs($user);
    }

    /**
     * Registration requires valid input.
     */
    public function test_registration_requires_valid_input(): void
    {
        $response = $this->postJson('/api/register', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name',
                'email',
                'password',
            ]);

        $this->assertDatabaseCount('users', 0);
    }

    /**
     * Registration rejects duplicate email addresses.
     */
    public function test_registration_rejects_duplicate_email(): void
    {
        User::factory()->create([
            'email' => 'existing@example.com',
        ]);

        $response = $this->postJson('/api/register', [
            'name' => 'Another User',
            'email' => 'existing@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->assertDatabaseCount('users', 1);
    }

    /**
     * Registration requires matching password confirmation.
     */
    public function test_registration_requires_password_confirmation(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different123',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);

        $this->assertDatabaseCount('users', 0);
    }

    /**
     * User can log in with valid credentials.
     */
    public function test_user_can_login(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJson([
                'message' => 'Login successful.',
                'user' => [
                    'id' => $user->id,
                    'email' => 'test@example.com',
                ],
            ]);

        $this->assertAuthenticatedAs($user);
    }

    /**
     * Login rejects incorrect credentials.
     */
    public function test_login_rejects_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->assertGuest();
    }

    /**
     * Login requires email and password.
     */
    public function test_login_requires_email_and_password(): void
    {
        $response = $this->postJson('/api/login', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'email',
                'password',
            ]);
    }

    /**
     * Authenticated user can retrieve their profile.
     */
    public function test_authenticated_user_can_get_profile(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/user');

        $response->assertOk()
            ->assertJson([
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                ],
            ]);
    }

    /**
     * Guest cannot retrieve the authenticated profile.
     */
    public function test_guest_cannot_get_profile(): void
    {
        $response = $this->getJson('/api/user');

        $response->assertUnauthorized();
    }

    /**
     * Authenticated user can log out.
     */
    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/logout');

        $response->assertOk()
            ->assertJson([
                'message' => 'Logout successful.',
            ]);

        /**$this->assertGuest();  **/
        $this->assertGuest('web');
    }

    /**
     * Guest cannot log out through the protected endpoint.
     */
    public function test_guest_cannot_logout(): void
    {
        $response = $this->postJson('/api/logout');

        $response->assertUnauthorized();
    }


    /**
     * Registration rejects passwords shorter than eight characters.
     */
    public function test_registration_rejects_short_password(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Test User',
            'email' => 'shortpassword@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);

        $this->assertDatabaseCount('users', 0);
    }


    /**
     * Registration rejects invalid email addresses.
     */
    public function test_registration_rejects_invalid_email(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Test User',
            'email' => 'not-an-email',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->assertDatabaseCount('users', 0);
    }


    /**
     * Guest cannot create notes.
     */
    public function test_guest_cannot_create_note(): void
    {
        $response = $this->postJson('/api/notes', [
            'title' => 'Guest Note',
            'content' => 'This should not be saved.',
        ]);

        $response->assertUnauthorized();

        $this->assertDatabaseCount('notes', 0);
    }



}
