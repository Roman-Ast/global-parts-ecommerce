<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ExpenseCategoriesSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('expense_categories')->insert([
            ['code' => 'fuel', 'name' => 'Fuel', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'rent', 'name' => 'Rent', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'tax', 'name' => 'Tax', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'salary', 'name' => 'Salary', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'credit_2gis', 'name' => 'Credit 2GIS', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'credit_apartment', 'name' => 'Credit Apartment', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'credit_grandma', 'name' => 'Credit Grandma', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'google_ads', 'name' => 'Google Ads', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'olx', 'name' => 'OLX', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'food', 'name' => 'Food', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
