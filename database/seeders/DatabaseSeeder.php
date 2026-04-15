<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Lawyer;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Ricardo',
            'email' => 'ricardo96.mgz@gmail.com',
        ]);

        Lawyer::create([
            'name' => 'Ricardo (Prueba)',
            'phone' => '524271513500',
            'email' => 'ricardo96.mgz@gmail.com',
            'is_active' => true,
        ]);

        Company::create([
            'name' => 'Empresa Demo',
            'rfc' => 'DEMO000000XXX',
            'address' => 'Dirección de prueba',
            'contact_name' => 'Contacto Demo',
            'contact_phone' => '5200000000',
            'is_active' => true,
        ]);
    }
}
