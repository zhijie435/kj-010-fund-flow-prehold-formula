<?php

namespace Shearerline\Database\Seeders;

use Illuminate\Database\Seeder;

class ShearerlineSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ProductSeeder::class,
            ProductCostSeeder::class,
            SettlementSeeder::class,
        ]);

        $this->command->info('ShearerlineSeeder: 所有种子数据已填充完成');
    }
}
