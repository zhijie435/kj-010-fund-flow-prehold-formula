<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shearerline_products', function (Blueprint $table) {
            $table->decimal('supplier_price', 12, 2)->default(0)->after('sale_price')->comment('供货价');
        });
    }

    public function down(): void
    {
        Schema::table('shearerline_products', function (Blueprint $table) {
            $table->dropColumn('supplier_price');
        });
    }
};
