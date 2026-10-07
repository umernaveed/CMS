<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Models\PackageStatus;

class PackageStatusesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $statuses = [
            ['name' => 'Pending', 'color' => '#ffc107', 'is_default' => true],
            ['name' => 'Picked Up', 'color' => '#17a2b8', 'is_default' => false],
            ['name' => 'In Transit', 'color' => '#007bff', 'is_default' => false],
            ['name' => 'Out for Delivery', 'color' => '#fd7e14', 'is_default' => false],
            ['name' => 'Delivered', 'color' => '#28a745', 'is_default' => false],
            ['name' => 'Failed Delivery', 'color' => '#dc3545', 'is_default' => false],
            ['name' => 'Returned', 'color' => '#6c757d', 'is_default' => false],
            ['name' => 'Cancelled', 'color' => '#343a40', 'is_default' => false],
        ];

        foreach ($statuses as $status) {
            PackageStatus::create([
                'tenant_id' => 1,
                'name' => $status['name'],
                'slug' => Str::slug($status['name']),
                'color' => $status['color'],
                'is_default' => $status['is_default'],
                'sort_order' => 0,
                'is_active' => true,
            ]);
        }
    }
}