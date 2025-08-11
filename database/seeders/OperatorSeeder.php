<?php


namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OperatorSeeder extends Seeder
{
    public function run(): void
    {
        $genders = ['male', 'female'];
        $statuses = ['active', 'inactive'];

        for ($i = 1; $i <= 50; $i++) {
            DB::table('sacco_operators')->insert([
                'full_name' => 'Operator ' . $i,
                'phone' => '07' . rand(10000000, 99999999),
                'national_id' => rand(10000000, 99999999),
                'gender' => $genders[array_rand($genders)],
                'operator_type' => rand(0, 1) ? 'driver' : 'conductor',
                'status' => $statuses[array_rand($statuses)],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}