<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
 public function up(): void
{
    Schema::table('daily_sales', function (Blueprint $table) {
        $table->unsignedInteger('total_items_sold')->default(0)->after('total_orders');
        $table->decimal('total_net_profit', 12, 2)->default(0)->after('total_revenue');
    });
}

public function down(): void
{
    Schema::table('daily_sales', function (Blueprint $table) {
        $table->dropColumn(['total_items_sold', 'total_net_profit']);
    });
}
};
