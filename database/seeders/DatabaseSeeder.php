<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Staff;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 3 Staff members
        Staff::create([
            'name' => 'ali',
            'email' => 'ali2@gmail.com',
            'password' => '12345678',
        ]);

        Staff::create([
            'name' => 'sara',
            'email' => 'sara@gmail.com',
            'password' => '12345678',
        ]);

        Staff::create([
            'name' => 'omar',
            'email' => 'omar@gmail.com',
            'password' => '12345678',
        ]);

        // 2 Customers
        $customer1 = Customer::create([
            'name' => 'khalid',
            'email' => 'khalid@gmail.com',
            'phone_number' => '0911111111',
            'password' => '12345678',
        ]);

        Customer::create([
            'name' => 'mona',
            'email' => 'mona@gmail.com',
            'phone_number' => '0922222222',
            'password' => '12345678',
        ]);

        // 2 Categories
        $category1 = Category::create([
            'name' => 'Programming',
            'description' => 'Software engineering and coding',
        ]);

        Category::create([
            'name' => 'History',
            'description' => 'World history and documentaries',
        ]);

        // 2 Books
        $book1 = Book::create([
            'name' => 'Clean Code',
            'author' => 'Robert Martin',
            'ISBN' => '9780132350884',
            'category_id' => $category1->id,
        ]);

        Book::create([
            'name' => 'Sapiens',
            'author' => 'Yuval Noah Harari',
            'ISBN' => '9780062316097',
            'category_id' => 2,
        ]);

        // 1 Open Borrowing
        $customer1->borrowings()->attach([
            $book1->id => [
                'borrowing_date' => now()->toDateString(),
                'return_date' => now()->addDays(5)->toDateString(),
                'is_returned' => false,
            ]
        ]);
    }
}
