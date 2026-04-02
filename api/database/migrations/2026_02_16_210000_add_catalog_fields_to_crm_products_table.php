<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_products', function (Blueprint $table): void {
            $table->string('code', 100)->nullable();
            $table->decimal('cost', 12, 2)->nullable();
            $table->string('unit', 50)->nullable();
            $table->integer('stock_quantity')->default(0);
            $table->integer('min_stock')->default(0);
            $table->boolean('is_featured')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('crm_products', function (Blueprint $table): void {
            $table->dropColumn([
                'code',
                'cost',
                'unit',
                'stock_quantity',
                'min_stock',
                'is_featured',
            ]);
        });
    }
};
