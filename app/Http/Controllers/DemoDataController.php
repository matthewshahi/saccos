<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Faker\Factory as Faker;

class DemoDataController extends Controller
{
    public function anonymizeMembers()
    {
        $faker = Faker::create();

        $members = DB::table('sacco_members')->get();

        foreach ($members as $member) {
            DB::table('sacco_members')
                ->where('member_id', $member->member_id)
                ->update([
                    'member_name' => $faker->name,
                    'member_national_id' => rand(10000000, 99999999),
                    'member_postal_address' => $faker->address,
                    'member_phone_no' => $faker->phoneNumber,
                    'member_email' => $faker->unique()->safeEmail,
                    'member_image' => null,
                    'member_signature' => null,
                    'member_id_copy_front' => null,
                    'member_id_copy_back' => null,
                    'member_payslips_bank_statements' => null,
                    'member_kra_pin' => 'A' . strtoupper(Str::random(9)),
                    'employee_number' => strtoupper(Str::random(5)),
                    'member_town' => $faker->city,
                    'member_city' => $faker->city,
                    'member_country' => $faker->country,
                    'bank_name' => $faker->company,
                    'bank_branch' => $faker->citySuffix,
                    'bank_account_number' => $faker->bankAccountNumber,
                ]);
        }

        return response()->json(['message' => 'Sacco members anonymized successfully.']);
    }
}