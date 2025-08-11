<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;
use Illuminate\Support\Str;

class SaccoMatatusVehiclesSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create();

        $routeNames = DB::table('sacco_matatus_routes')->pluck('route_name')->toArray();
        $memberIds = DB::table('sacco_members')->pluck('member_id')->toArray();

        $statuses = [
            'pending_approval', 'active', 'inactive',
            'suspended', 'under_maintenance', 'decommissioned', 'blacklisted'
        ];

        $vehicles = [];

        for ($i = 0; $i < 50; $i++) {
            $reg = strtoupper(Str::random(3) . ' ' . rand(100, 999) . chr(rand(65, 90)));

            $vehicles[] = [
                'vehicles_registration_number' => str_replace(' ', '', $reg),
                'vehicles_make' => $faker->randomElement(['Toyota', 'Nissan', 'Isuzu', 'Mitsubishi']),
                'vehicles_model' => $faker->randomElement(['Hiace', 'Caravan', 'Rosa', 'Fuso']),
                'vehicles_year' => $faker->year,
                'vehicles_chassis_number' => strtoupper(Str::random(10)),
                'vehicles_insurance_provider' => $faker->company,
                'vehicles_insurance_expiry' => $faker->dateTimeBetween('+1 month', '+1 year')->format('Y-m-d'),
                'vehicles_last_inspection_date' => $faker->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
                'vehicles_psv_license_number' => strtoupper('PSV-' . rand(1000, 9999)),
                'vehicles_psv_expiry' => $faker->dateTimeBetween('+1 month', '+1 year')->format('Y-m-d'),
                'vehicles_route_name' => $faker->randomElement($routeNames),
                'vehicles_member_id' => $faker->optional()->randomElement($memberIds),
                'vehicles_status' => $faker->randomElement($statuses),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('sacco_matatus_vehicles')->insert($vehicles);
    }
}