<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shearerline_settlement_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('settlement_id');
            $table->unsignedBigInteger('product_id');
            $table->string('product_name', 255)->comment('商品名称快照');
            $table->string('product_sku', 100)->comment('商品SKU快照');
            $table->integer('quantity')->default(1)->comment('数量');
            $table->decimal('sale_price', 12, 2)->default(0)->comment('销售单价');
            $table->decimal('total_sales', 14, 2)->default(0)->comment('销售金额 = 单价 × 数量');
            $table->decimal('unit_cost', 12, 2)->default(0)->comment('单位成本(结算时快照)');
            $table->decimal('total_cost', 14, 2)->default(0)->comment('总成本 = 单位成本 × 数量');
            $table->decimal('profit', 14, 2)->default(0)->comment('利润 = 销售金额 - 总成本');

            $table->foreign('settlement_id')
                ->references('id')
                ->on('shearerline_settlements')
                ->onDelete('cascade');

            $table->foreign('product_id')
                ->references('id')
                ->on('shearerline_products')
                ->onDelete('restrict');

            $table->index('settlement_id');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shearerline_settlement_items');
    }
};
