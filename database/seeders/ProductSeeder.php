<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            ['code' => 'BRG001', 'barcode' => '899123456001', 'name' => 'Indomie Goreng',        'price' => 3500],
            ['code' => 'BRG002', 'barcode' => '899123456002', 'name' => 'Aqua 600ml',            'price' => 4000],
            ['code' => 'BRG003', 'barcode' => '899123456003', 'name' => 'Teh Botol Sosro',       'price' => 5000],
            ['code' => 'BRG004', 'barcode' => '899123456004', 'name' => 'Beras 5 Kg',            'price' => 75000],
            ['code' => 'BRG005', 'barcode' => '899123456005', 'name' => 'Minyak Goreng 1 Liter', 'price' => 18000],
            ['code' => 'BRG006', 'barcode' => '899123456006', 'name' => 'Gula Pasir 1 Kg',       'price' => 17000],
        ];

        foreach ($products as $product) {
            Product::updateOrCreate(['code' => $product['code']], $product);
        }
    }
}
