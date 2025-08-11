<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SaccoMatatusRoutesSeeder extends Seeder
{
    public function run()
    {
        $routes = [
            ['route_name' => 'CBD - Westlands', 'route_start' => 'CBD', 'route_end' => 'Westlands', 'route_distance_km' => 6.5],
            ['route_name' => 'CBD - Rongai', 'route_start' => 'CBD', 'route_end' => 'Rongai', 'route_distance_km' => 17.2],
            ['route_name' => 'CBD - Thika', 'route_start' => 'CBD', 'route_end' => 'Thika', 'route_distance_km' => 42.1],
            ['route_name' => 'CBD - Umoja', 'route_start' => 'CBD', 'route_end' => 'Umoja', 'route_distance_km' => 9.7],
            ['route_name' => 'CBD - Githurai', 'route_start' => 'CBD', 'route_end' => 'Githurai', 'route_distance_km' => 15.0],
        ];

        DB::table('sacco_matatus_routes')->insert($routes);
    }
}