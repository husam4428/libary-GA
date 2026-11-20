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

    /*
    |--------------------------------------------------------------------------
    | Try this: Login Table (Page 28)
    |--------------------------------------------------------------------------
    */

    public function test_staff_login_successful_and_me(): void
    {
        // 1. Login
        $response = $this->postJson('/api/staff/login', [
            'email' => 'ali2@gmail.com',
            'password' => '12345678',
        ]);
        $response->assertStatus(200)->assertJsonStructure(['token', 'message']);
        $token = $response->json('token');

        // 3. Who am I
        $me = $this->withHeader('Authorization', 'Bearer ' . $token)->getJson('/api/staff-me');
        $me->assertStatus(200)->assertJsonPath('staff.email', 'ali2@gmail.com');
    }

    public function test_staff_wrong_password(): void
    {
        // 2. Wrong password
        $response = $this->postJson('/api/staff/login', [
            'email' => 'ali2@gmail.com',
            'password' => 'wrongpass',
        ]);
        $response->assertStatus(401)->assertJson(['message' => 'Invalid credentials']);
    }

    public function test_no_token_gives_401(): void
    {
        // 4. No token (no Bearer)
        $response = $this->getJson('/api/staff');
        $response->assertStatus(401);
    }

    public function test_staff_logout(): void
    {
        // 5. Logout
        $login = $this->postJson('/api/staff/login', [
            'email' => 'ali2@gmail.com',
            'password' => '12345678',
        ]);
        $token = $login->json('token');

        $logout = $this->withHeader('Authorization', 'Bearer ' . $token)->postJson('/api/staff-logout');
        $logout->assertStatus(200)->assertJson(['message' => 'Logged out successfully']);

        auth()->forgetGuards();

        // Then the same token gives 401
        $me = $this->withHeader('Authorization', 'Bearer ' . $token)->getJson('/api/staff-me');
        $me->assertStatus(401);
    }

    public function test_customer_login_and_logout(): void
    {
        // 6. Customer login
        $response = $this->postJson('/api/customer/login', [
            'phone_number' => '0911111111',
            'password' => '12345678',
        ]);
        $response->assertStatus(200)->assertJsonStructure(['token']);
        $token = $response->json('token');

        // 7. Customer me
        $me = $this->withHeader('Authorization', 'Bearer ' . $token)->getJson('/api/customer-me');
        $me->assertStatus(200)->assertJsonPath('customer.phone_number', '0911111111');

        // 8. Wrong token type (Customer accessing Staff route)
        $staffRoute = $this->withHeader('Authorization', 'Bearer ' . $token)->getJson('/api/staff');
        $staffRoute->assertStatus(401);

        // 9. Customer logout
        $logout = $this->withHeader('Authorization', 'Bearer ' . $token)->postJson('/api/customer-logout');
        $logout->assertStatus(200);

        auth()->forgetGuards();

        // Then that token gives 401
        $meAgain = $this->withHeader('Authorization', 'Bearer ' . $token)->getJson('/api/customer-me');
        $meAgain->assertStatus(401);
    }

    public function test_customer_wrong_password(): void
    {
        $response = $this->postJson('/api/customer/login', [
            'phone_number' => '0911111111',
            'password' => 'wrongpass',
        ]);
        $response->assertStatus(401);
    }

    /*
    |--------------------------------------------------------------------------
    | Try this: Staff Table (Page 28)
    |--------------------------------------------------------------------------
    */

    public function test_staff_crud_actions(): void
    {
        $staff = Staff::first();
        $token = $staff->createToken('test')->plainTextToken;
        $headers = ['Authorization' => 'Bearer ' . $token];

        // List all (GET /api/staff)
        $list = $this->withHeaders($headers)->getJson('/api/staff');
        $list->assertStatus(200)->assertJsonStructure(['data']);

        // Create (POST /api/staff)
        $create = $this->withHeaders($headers)->postJson('/api/staff', [
            'name' => 'Fatima',
            'email' => 'fatima@gmail.com',
            'password' => '12345678',
        ]);
        $create->assertStatus(201);
        $newId = $create->json('data.id');

        // Show one (GET /api/staff/{id})
        $show = $this->withHeaders($headers)->getJson("/api/staff/{$newId}");
        $show->assertStatus(200)->assertJsonPath('data.name', 'Fatima');

        // Show invalid id -> 404
        $this->withHeaders($headers)->getJson("/api/staff/99999")->assertStatus(404);

        // Update (PUT /api/staff/{id})
        $update = $this->withHeaders($headers)->putJson("/api/staff/{$newId}", [
            'name' => 'Fatima Updated',
        ]);
        $update->assertStatus(200)->assertJsonPath('data.name', 'Fatima Updated');

        // Delete (DELETE /api/staff/{id})
        $delete = $this->withHeaders($headers)->deleteJson("/api/staff/{$newId}");
        $delete->assertStatus(200)->assertJson(['message' => 'Staff deleted successfully']);

        // Validation (POST /api/staff with empty body -> 422)
        $val = $this->withHeaders($headers)->postJson('/api/staff', []);
        $val->assertStatus(422)->assertJsonStructure(['errors']);
    }

    /*
    |--------------------------------------------------------------------------
    | Try this: Categories & Books Table (Page 29) + Tasks 8 & 9
    |--------------------------------------------------------------------------
    */

    public function test_categories_and_books_lifecycle(): void
    {
        $staff = Staff::first();
        $staffToken = $staff->createToken('test')->plainTextToken;
        $staffHeaders = ['Authorization' => 'Bearer ' . $staffToken];

        $customer = Customer::first();
        $custToken = $customer->createToken('cust')->plainTextToken;
        $custHeaders = ['Authorization' => 'Bearer ' . $custToken];

        // Create category (POST /api/category) -> 201
        $cat = $this->withHeaders($staffHeaders)->postJson('/api/category', [
            'name' => 'Science',
            'description' => 'Science and nature',
        ]);
        $cat->assertStatus(201);
        $catId = $cat->json('data.id');

        auth()->forgetGuards();

        // List categories (GET /api/category) -> 200 (any token)
        $this->withHeaders($custHeaders)->getJson('/api/category')->assertStatus(200);

        auth()->forgetGuards();

        // Create book (POST /api/book) -> 201
        $book = $this->withHeaders($staffHeaders)->postJson('/api/book', [
            'name' => 'Astrophysics',
            'author' => 'Neil deGrasse Tyson',
            'ISBN' => '9780393609394',
            'category_id' => $catId,
        ]);
        $book->assertStatus(201);
        $bookId = $book->json('data.id');

        // Bad category -> 422
        $badBook = $this->withHeaders($staffHeaders)->postJson('/api/book', [
            'name' => 'Bad Book',
            'author' => 'Author',
            'ISBN' => '1112223334',
            'category_id' => 9999,
        ]);
        $badBook->assertStatus(422);

        auth()->forgetGuards();

        // Show book (GET /api/book/1) -> 200
        $this->withHeaders($custHeaders)->getJson("/api/book/{$bookId}")
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'Astrophysics');

        auth()->forgetGuards();

        // Update book (PUT /api/book/{id}) -> 200
        $this->withHeaders($staffHeaders)->putJson("/api/book/{$bookId}", [
            'name' => 'Astrophysics for Young People',
        ])->assertStatus(200)->assertJsonPath('data.name', 'Astrophysics for Young People');

        auth()->forgetGuards();

        // Customer writes book -> 401
        $this->withHeaders($custHeaders)->postJson('/api/book', [
            'name' => 'Customer Book',
            'author' => 'Customer',
            'ISBN' => '999888777',
            'category_id' => $catId,
        ])->assertStatus(401);

        auth()->forgetGuards();

        // Delete book (unborrowed) -> 204
        $this->withHeaders($staffHeaders)->deleteJson("/api/book/{$bookId}")->assertStatus(204);

        auth()->forgetGuards();

        // Task 8: Search and filter books
        $search = $this->withHeaders($custHeaders)->getJson('/api/book?search=Clean');
        $search->assertStatus(200)->assertJsonCount(1, 'data');

        // Task 9: is_available field in BookResource
        $this->withHeaders($custHeaders)->getJson('/api/book')
            ->assertStatus(200)
            ->assertJsonPath('data.0.is_available', false) // Book 1 is borrowed
            ->assertJsonPath('data.1.is_available', true);  // Book 2 is available
    }

    public function test_conflict_protection_on_deletion(): void
    {
        $staff = Staff::first();
        $headers = ['Authorization' => 'Bearer ' . $staff->createToken('test')->plainTextToken];

        // Deleting category that has books gives 409
        $this->withHeaders($headers)->deleteJson('/api/category/1')
            ->assertStatus(409)
            ->assertJson(['message' => 'Cannot delete category that has books']);

        // Deleting book that has borrowings gives 409
        $this->withHeaders($headers)->deleteJson('/api/book/1')
            ->assertStatus(409)
            ->assertJson(['message' => 'Cannot delete book that has borrowings']);
    }

    /*
    |--------------------------------------------------------------------------
    | Try this: Borrowings Table (Page 29) + Tasks 1, 2, 3, 4, 5, 6, 7, 10
    |--------------------------------------------------------------------------
    */

    public function test_customer_signup_and_borrowings_flow(): void
    {
        // Task 2 & Page 29 Sign up (public)
        $signup = $this->postJson('/api/customer', [
            'name' => 'Layla',
            'email' => 'layla@gmail.com',
            'phone_number' => '0955555555',
            'password' => '12345678',
        ]);
        $signup->assertStatus(201)->assertJsonStructure(['customer', 'token']);
        $token = $signup->json('token');
        $headers = ['Authorization' => 'Bearer ' . $token];

        // Task 3: Date validation (return date before borrowing date -> 422)
        $badDate = $this->withHeaders($headers)->postJson('/api/borrowings', [
            'book_id' => 2,
            'borrowing_date' => now()->addDays(5)->toDateString(),
            'return_date' => now()->addDays(2)->toDateString(),
        ]);
        $badDate->assertStatus(422);

        // Bad book (book_id = 999 -> 422)
        $badBook = $this->withHeaders($headers)->postJson('/api/borrowings', [
            'book_id' => 9999,
            'borrowing_date' => now()->addDays(1)->toDateString(),
            'return_date' => now()->addDays(5)->toDateString(),
        ]);
        $badBook->assertStatus(422);

        // Task 4: Overlapping borrow on already borrowed book (Book 1) -> 422
        $overlap = $this->withHeaders($headers)->postJson('/api/borrowings', [
            'book_id' => 1,
            'borrowing_date' => now()->addDays(1)->toDateString(),
            'return_date' => now()->addDays(3)->toDateString(),
        ]);
        $overlap->assertStatus(422)->assertJson(['message' => 'This book is already borrowed on these dates']);

        // Borrow available book (Book 2) -> 200
        $borrow = $this->withHeaders($headers)->postJson('/api/borrowings', [
            'book_id' => 2,
            'borrowing_date' => now()->addDays(1)->toDateString(),
            'return_date' => now()->addDays(5)->toDateString(),
        ]);
        $borrow->assertStatus(200)->assertJson(['message' => 'borrowings created sucessfully']);

        // My borrowings (GET /api/borrowings) -> 200
        $my = $this->withHeaders($headers)->getJson('/api/borrowings');
        $my->assertStatus(200)->assertJsonCount(1);

        // Remove borrowing (DELETE /api/borrowings/2) -> 200
        $del = $this->withHeaders($headers)->deleteJson('/api/borrowings/2');
        $del->assertStatus(200)->assertJson(['message' => 'removed']);
    }

    public function test_task_5_max_3_borrowings(): void
    {
        $customer = Customer::where('phone_number', '0922222222')->first();
        $token = $customer->createToken('test')->plainTextToken;
        $headers = ['Authorization' => 'Bearer ' . $token];

        // Create 2 extra books so we have books 1, 2, 3, 4
        $cat = Category::first();
        $b3 = Book::create(['name' => 'Book 3', 'author' => 'Author', 'ISBN' => 'ISBN333', 'category_id' => $cat->id]);
        $b4 = Book::create(['name' => 'Book 4', 'author' => 'Author', 'ISBN' => 'ISBN444', 'category_id' => $cat->id]);

        // Customer borrows 3 books (Book 2, Book 3, Book 4)
        $this->withHeaders($headers)->postJson('/api/borrowings', [
            'book_id' => 2,
            'borrowing_date' => now()->addDays(1)->toDateString(),
            'return_date' => now()->addDays(3)->toDateString(),
        ])->assertStatus(200);

        $this->withHeaders($headers)->postJson('/api/borrowings', [
            'book_id' => $b3->id,
            'borrowing_date' => now()->addDays(1)->toDateString(),
            'return_date' => now()->addDays(3)->toDateString(),
        ])->assertStatus(200);

        $this->withHeaders($headers)->postJson('/api/borrowings', [
            'book_id' => $b4->id,
            'borrowing_date' => now()->addDays(1)->toDateString(),
            'return_date' => now()->addDays(3)->toDateString(),
        ])->assertStatus(200);

        // 4th open borrowing -> 422
        $b5 = Book::create(['name' => 'Book 5', 'author' => 'Author', 'ISBN' => 'ISBN555', 'category_id' => $cat->id]);
        $response = $this->withHeaders($headers)->postJson('/api/borrowings', [
            'book_id' => $b5->id,
            'borrowing_date' => now()->addDays(1)->toDateString(),
            'return_date' => now()->addDays(3)->toDateString(),
        ]);
        $response->assertStatus(422)->assertJson(['message' => 'Maximum 3 open borrowings per customer reached']);
    }

    public function test_tasks_1_6_7_10_staff_features(): void
    {
        $staff = Staff::first();
        $headers = ['Authorization' => 'Bearer ' . $staff->createToken('staff')->plainTextToken];

        // Task 1: Mark as returned (PUT /api/borrowings/return)
        $return = $this->withHeaders($headers)->putJson('/api/borrowings/return', [
            'customer_id' => 1,
            'book_id' => 1,
        ]);
        $return->assertStatus(200)->assertJson(['message' => 'Book marked as returned']);

        // Marking again gives 404
        $this->withHeaders($headers)->putJson('/api/borrowings/return', [
            'customer_id' => 1,
            'book_id' => 1,
        ])->assertStatus(404);

        // Task 6: Staff see all borrowings with customer and book
        $all = $this->withHeaders($headers)->getJson('/api/borrowings/all');
        $all->assertStatus(200)
            ->assertJsonPath('0.customer.name', 'khalid')
            ->assertJsonPath('0.book.name', 'Clean Code');

        // Task 6 Extra: ?is_returned=0
        $this->withHeaders($headers)->getJson('/api/borrowings/all?is_returned=0')->assertStatus(200);

        // Task 7: Staff see overdue borrowings
        $overdue = $this->withHeaders($headers)->getJson('/api/borrowings/overdue');
        $overdue->assertStatus(200);

        // Task 10: Staff list customers (GET /api/customer)
        $custList = $this->withHeaders($headers)->getJson('/api/customer');
        $custList->assertStatus(200)->assertJsonCount(2);

        // Task 10: Staff show customer with borrowings (GET /api/customer/1)
        $custShow = $this->withHeaders($headers)->getJson('/api/customer/1');
        $custShow->assertStatus(200)->assertJsonStructure(['id', 'name', 'borrowings']);
    }
}
