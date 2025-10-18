<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->decimal('total_amount', 12, 2)->unsigned()->default(0);
            $table->decimal('total_cost', 12, 2)->unsigned()->default(0);
            $table->decimal('total_profit', 12, 2)->default(0);

            $table->enum('status', ['queued', 'processing', 'completed', 'cancelled'])
                ->default('queued');

            $table->timestamps();
            $table->date('sale_date')->storedAs('DATE(created_at)');

            $table->index(['sale_date', 'id']);
            $table->index(['status', 'sale_date']);
        });

        // Índice de cobertura para otimiza SUMs por sale_date
        DB::statement('CREATE INDEX idx_sales_sale_date_cover ON sales (sale_date, total_amount, total_cost, total_profit)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_sales_sale_date_cover ON sales');

        Schema::dropIfExists('sales');
    }
};
