<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        /** @var class-string<\Illuminate\Database\Seeder>[] $seeders */
        $seeders = [
            ProductSeeder::class,
            InventorySeeder::class,
            SaleSeeder::class,
            //BigSalesSeeder::class,
        ];

        $this->call($seeders);
    }
}
