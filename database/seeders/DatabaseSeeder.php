<?php

namespace Database\Seeders;
use Database\Seeders\SaccoMatatusRoutesSeeder;
use Database\Seeders\SaccoMatatusVehiclesSeeder;
use Database\Seeders\OperatorSeeder;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
    //     $this->call([
    //     SaccoMatatusRoutesSeeder::class,
    // ]);

    
    $this->call(OperatorSeeder::class);
    }
}
