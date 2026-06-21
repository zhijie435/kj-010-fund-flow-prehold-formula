<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shearerline_products', function (Blueprint $table) {
            $table->decimal('length', 10, 2)->nullable()->after('weight')->comment('长(cm)');
            $table->decimal('width', 10, 2)->nullable()->after('length')->comment('宽(cm)');
            $table->decimal('height', 10, 2)->nullable()->after('width')->comment('高(cm)');
        });
    }

    public function down(): void
    {
        Schema::table('shearerline_products', function (Blueprint $table) {
            $table->dropColumn(['length', 'width', 'height']);
        });
    }
};
