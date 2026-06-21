<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shearerline_settlements', function (Blueprint $table) {
            $table->id();
            $table->string('settlement_no', 50)->unique()->comment('结算单号');
            $table->string('type', 30)->default('manual')->comment('结算类型: order/monthly/manual');
            $table->date('settlement_date')->comment('结算日期');
            $table->string('order_no', 100)->nullable()->comment('关联订单号(按订单结算时)');
            $table->integer('order_count')->default(0)->comment('订单数量');
            $table->decimal('total_amount', 14, 2)->default(0)->comment('销售总额');
            $table->decimal('product_cost', 14, 2)->default(0)->comment('商品成本合计');
            $table->decimal('platform_fee', 14, 2)->default(0)->comment('平台费用');
            $table->decimal('other_cost', 14, 2)->default(0)->comment('其他成本');
            $table->decimal('total_cost', 14, 2)->default(0)->comment('总成本合计');
            $table->decimal('total_profit', 14, 2)->default(0)->comment('利润总额');
            $table->decimal('profit_rate', 8, 4)->default(0)->comment('利润率');
            $table->decimal('supplier_ratio', 8, 4)->default(0.50)->comment('供应商分成比例');
            $table->decimal('distributor_ratio', 8, 4)->default(0.20)->comment('分销商分成比例');
            $table->decimal('platform_ratio', 8, 4)->default(0.30)->comment('平台分成比例');
            $table->decimal('supplier_share', 14, 2)->default(0)->comment('供应商分成金额');
            $table->decimal('distributor_share', 14, 2)->default(0)->comment('分销商分成金额');
            $table->decimal('platform_share', 14, 2)->default(0)->comment('平台分成金额');
            $table->string('status', 30)->default('pending')->comment('状态: pending/confirmed/settled/cancelled');
            $table->text('remark')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('settled_by')->nullable()->comment('结算操作人');
            $table->timestamp('settled_at')->nullable()->comment('结算时间');
            $table->timestamps();
            $table->softDeletes();

            $table->index('settlement_no');
            $table->index('type');
            $table->index('settlement_date');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shearerline_settlements');
    }
};
