<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shearerline_products', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('sku', 100)->unique();
            $table->string('barcode', 100)->nullable();
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->string('category', 100)->nullable();
            $table->string('unit', 50)->nullable();
            $table->decimal('sale_price', 12, 2)->default(0);
            $table->decimal('weight', 10, 2)->nullable();
            $table->text('description')->nullable();
            $table->string('image_url', 500)->nullable();
            $table->integer('stock')->default(0);
            $table->integer('warning_stock')->default(0);
            $table->tinyInteger('status')->default(1);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('sku');
            $table->index('supplier_id');
            $table->index('category');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shearerline_products');
    }
};
