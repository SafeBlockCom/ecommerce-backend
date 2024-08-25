<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Miner;
use Illuminate\Support\Str;

class MinersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        for ($i = 1; $i <= 15; $i++) {
            Miner::create([
                'identifier' => Str::uuid()->toString(),
                'name' => 'Miner ' . $i,
                'mining_rewards' => 0,
                'nonce' => 0,
            ]);
        }
    }
}
