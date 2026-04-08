<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@crem-poitiers.fr'],
            [
                'name'     => 'Admin CREM',
                'email'    => 'admin@crem-poitiers.fr',
                'password' => Hash::make('changeme'),
            ]
        );
    }
}
