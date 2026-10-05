<?php

namespace Database\Seeders;

use App\Enums\CategoryStatus;
use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $tree = [
            'dien-tu' => ['Điện tử', ['laptop' => 'Laptop', 'dien-thoai' => 'Điện thoại', 'phu-kien' => 'Phụ kiện']],
            'thoi-trang' => ['Thời trang', ['ao' => 'Áo', 'quan' => 'Quần', 'giay' => 'Giày']],
            'gia-dung' => ['Gia dụng', ['ban-ghe' => 'Bàn ghế', 'nha-bep' => 'Nhà bếp', 'trang-tri' => 'Trang trí']],
        ];
        foreach ($tree as $slug => [$name, $children]) {
            $parent = Category::firstOrCreate(['slug' => $slug], ['name' => $name, 'status' => CategoryStatus::ACTIVE]);
            foreach ($children as $childSlug => $childName) {
                Category::firstOrCreate(['slug' => $childSlug], ['name' => $childName, 'parent_id' => $parent->id, 'status' => CategoryStatus::ACTIVE]);
            }
        }
    }
}
