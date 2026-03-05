<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CashflowCategoriesSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('cashflow_categories')->insert([
            [
                'code' => 'sale',
                'name' => 'Sale',
                'default_direction' => 'in',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'expense',
                'name' => 'Expense',
                'default_direction' => 'out',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'supplier_payment',
                'name' => 'Supplier Payment',
                'default_direction' => 'out',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'supplier_refund',
                'name' => 'Supplier Refund',
                'default_direction' => 'in',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'owner_withdraw',
                'name' => 'Owner Withdraw',
                'default_direction' => 'out',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'transfer',
                'name' => 'Transfer',
                'default_direction' => null,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
