<?php

namespace Shearerline\Database\Seeders;

use Shearerline\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            [
                'name' => 'Python 编程入门到精通',
                'sku' => 'COURSE-PY-001',
                'sale_price' => 299.00,
                'supplier_price' => 150.00,
                'status' => 1,
                'weight' => 0.0,
                'length' => 0.0,
                'width' => 0.0,
                'height' => 0.0,
            ],
            [
                'name' => 'Java 企业级开发实战',
                'sku' => 'COURSE-JV-002',
                'sale_price' => 399.00,
                'supplier_price' => 200.00,
                'status' => 1,
                'weight' => 0.0,
                'length' => 0.0,
                'width' => 0.0,
                'height' => 0.0,
            ],
            [
                'name' => '前端全栈工程师培养计划',
                'sku' => 'COURSE-FE-003',
                'sale_price' => 499.00,
                'supplier_price' => 250.00,
                'status' => 1,
                'weight' => 0.0,
                'length' => 0.0,
                'width' => 0.0,
                'height' => 0.0,
            ],
            [
                'name' => '数据分析与可视化',
                'sku' => 'COURSE-DA-004',
                'sale_price' => 349.00,
                'supplier_price' => 175.00,
                'status' => 1,
                'weight' => 0.0,
                'length' => 0.0,
                'width' => 0.0,
                'height' => 0.0,
            ],
            [
                'name' => '人工智能基础与应用',
                'sku' => 'COURSE-AI-005',
                'sale_price' => 599.00,
                'supplier_price' => 300.00,
                'status' => 1,
                'weight' => 0.0,
                'length' => 0.0,
                'width' => 0.0,
                'height' => 0.0,
            ],
            [
                'name' => 'MySQL 数据库优化实战',
                'sku' => 'COURSE-DB-006',
                'sale_price' => 249.00,
                'supplier_price' => 120.00,
                'status' => 1,
                'weight' => 0.0,
                'length' => 0.0,
                'width' => 0.0,
                'height' => 0.0,
            ],
            [
                'name' => 'Linux 运维工程师进阶',
                'sku' => 'COURSE-OP-007',
                'sale_price' => 379.00,
                'supplier_price' => 190.00,
                'status' => 1,
                'weight' => 0.0,
                'length' => 0.0,
                'width' => 0.0,
                'height' => 0.0,
            ],
            [
                'name' => 'Go 语言高性能编程',
                'sku' => 'COURSE-GO-008',
                'sale_price' => 429.00,
                'supplier_price' => 215.00,
                'status' => 1,
                'weight' => 0.0,
                'length' => 0.0,
                'width' => 0.0,
                'height' => 0.0,
            ],
            [
                'name' => 'React 全家桶开发指南',
                'sku' => 'COURSE-RC-009',
                'sale_price' => 369.00,
                'supplier_price' => 185.00,
                'status' => 1,
                'weight' => 0.0,
                'length' => 0.0,
                'width' => 0.0,
                'height' => 0.0,
            ],
            [
                'name' => '软件测试从入门到精通',
                'sku' => 'COURSE-QA-010',
                'sale_price' => 279.00,
                'supplier_price' => 140.00,
                'status' => 1,
                'weight' => 0.0,
                'length' => 0.0,
                'width' => 0.0,
                'height' => 0.0,
            ],
        ];

        foreach ($products as $product) {
            Product::create($product);
        }

        $this->command->info('ProductSeeder: 已创建 ' . count($products) . ' 个商品');
    }
}
