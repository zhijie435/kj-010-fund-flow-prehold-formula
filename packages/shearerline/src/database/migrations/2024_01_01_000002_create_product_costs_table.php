<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shearerline_product_costs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->string('cost_type', 50)->comment('采购/物流/包装/平台/营销/税费/其他');
            $table->string('cost_name', 255)->comment('成本项名称');
            $table->decimal('unit_cost', 12, 2)->default(0)->comment('单位成本');
            $table->integer('quantity')->default(1)->comment('数量');
            $table->decimal('total_cost', 12, 2)->default(0)->comment('总成本 = 单位成本 × 数量');
            $table->date('effective_date')->comment('生效日期');
            $table->date('expiry_date')->nullable()->comment('失效日期');
            $table->tinyInteger('is_active')->default(1)->comment('是否启用');
            $table->text('remark')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('product_id')
                ->references('id')
                ->on('shearerline_products')
                ->onDelete('cascade');

            $table->index('product_id');
            $table->index('cost_type');
            $table->index('effective_date');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shearerline_product_costs');
    }
};
