<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $firstDay = Carbon::today(config('app.timezone'));
        $timestamp = now();
        $slots = [];

        for ($day = 0; $day < 7; $day++) {
            for ($hour = 9; $hour < 18; $hour++) {
                $start = $firstDay->copy()->addDays($day)->setTime($hour, 0);

                $slots[] = [
                    'start_at' => $start->format('Y-m-d H:i:s'),
                    'end_at' => $start->copy()->addHour()->format('Y-m-d H:i:s'),
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];
            }
        }

        DB::table('slots')->insertOrIgnore($slots);
    }
}
