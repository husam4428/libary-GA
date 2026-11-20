<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LibraryApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_staff_login_successful_and_me(): void
    {
        $response = $this->postJson('/api/staff/login', [
            'email' => 'ali2@gmail.com',
            'password' => '12345678',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['token', 'message']);

        $token = $response->json('token');

        // Staff profile
        $me = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/staff-me');

        $me->assertStatus(200)
            ->assertJsonPath('staff.email', 'ali2@gmail.com');
    }

    public function test_staff_login_invalid_credentials(): void
    {
        $response = $this->postJson('/api/staff/login', [
            'email' => 'ali2@gmail.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401);
    }

    public function test_customer_login_and_token_separation(): void
    {
        $response = $this->postJson('/api/customer/login', [
            'phone_number' => '0911111111',
            'password' => '12345678',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['token']);

        $customerToken = $response->json('token');

        // Customer access customer-me
        $me = $this->withHeader('Authorization', 'Bearer ' . $customerToken)
            ->getJson('/api/customer-me');
        $me->assertStatus(200);

        // Customer cannot access staff routes (Multi-guard test)
        $staffRoute = $this->withHeader('Authorization', 'Bearer ' . $customerToken)
            ->getJson('/api/staff');
        $staffRoute->assertStatus(401);
    }

    public function test_customer_registration_returns_token(): void
    {
        $response = $this->postJson('/api/customer', [
            'name' => 'Sara New',
            'email' => 'saranew@example.com',
            'phone_number' => '0933333333',
            'password' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['customer', 'token']);
    }

    public function test_book_search_and_availability(): void
    {
        $staff = Staff::first();
        $token = $staff->createToken('test')->plainTextToken;

        // Search book
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/book?search=Clean');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Clean Code')
            ->assertJsonPath('data.0.is_available', false); // Seeded as borrowed
    }

    public function test_borrowings_overlap_prevention(): void
    {
        $customer = Customer::where('phone_number', '0922222222')->first();
        $token = $customer->createToken('test')->plainTextToken;

        // Book 1 is already borrowed today + 5 days
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/borrowings', [
                'book_id' => 1,
                'borrowing_date' => now()->addDays(1)->toDateString(),
                'return_date' => now()->addDays(3)->toDateString(),
            ]);

        $response->assertStatus(422)
            ->assertJson(['message' => 'This book is already borrowed on these dates']);
    }

    public function test_staff_mark_as_returned(): void
    {
        $staff = Staff::first();
        $staffToken = $staff->createToken('test')->plainTextToken;

        // Mark book 1 as returned for customer 1
        $response = $this->withHeader('Authorization', 'Bearer ' . $staffToken)
            ->putJson('/api/borrowings/return', [
                'customer_id' => 1,
                'book_id' => 1,
            ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Book marked as returned']);

        // Marking again gives 404
        $second = $this->withHeader('Authorization', 'Bearer ' . $staffToken)
            ->putJson('/api/borrowings/return', [
                'customer_id' => 1,
                'book_id' => 1,
            ]);

        $second->assertStatus(404);
    }

    public function test_cannot_delete_category_with_books(): void
    {
        $staff = Staff::first();
        $staffToken = $staff->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $staffToken)
            ->deleteJson('/api/category/1');

        $response->assertStatus(409)
            ->assertJson(['message' => 'Cannot delete category that has books']);
    }
}
