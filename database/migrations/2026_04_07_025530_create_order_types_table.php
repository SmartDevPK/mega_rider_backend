<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Check if an index exists on a table
     */
    private function hasIndex($table, $indexName): bool
    {
        // Get the database connection
        $connection = DB::connection();
        $databaseName = $connection->getDatabaseName();

        // Check in information_schema
        $result = $connection->select(
            "SELECT COUNT(*) as count 
             FROM information_schema.statistics 
             WHERE table_schema = ? 
             AND table_name = ? 
             AND index_name = ?",
            [$databaseName, $table, $indexName]
        );

        return $result[0]->count > 0;
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create order_types table
        if (!Schema::hasTable('order_types')) {
            Schema::create('order_types', function (Blueprint $table): void {
                $table->id();
                $table->string('title', 100);
                $table->string('slug', 100)->unique();
                $table->decimal('base_price', 12, 2)->default(0);
                $table->decimal('price_per_km', 12, 2)->default(0);
                $table->decimal('base_distance', 10, 2)->default(0);
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->timestamps();

                $table->index('slug', 'idx_order_types_slug');
                $table->index('is_active', 'idx_order_types_active');
            });
        }

        // Add columns to orders table
        Schema::table('orders', function (Blueprint $table): void {
            // Add order_type_id if not exists
            if (!Schema::hasColumn('orders', 'order_type_id')) {
                $table->unsignedBigInteger('order_type_id')
                    ->nullable()
                    ->after('zone_id');
                $table->foreign('order_type_id')
                    ->references('id')
                    ->on('order_types')
                    ->nullOnDelete();
            }

            // Add delivery_fee if not exists
            if (!Schema::hasColumn('orders', 'delivery_fee')) {
                $table->decimal('delivery_fee', 12, 2)->default(0)->after('order_type_id');
            }

            // Add surge_multiplier if not exists
            if (!Schema::hasColumn('orders', 'surge_multiplier')) {
                $table->decimal('surge_multiplier', 5, 2)->default(1.00)->after('delivery_fee');
            }

            // Add surge_fee if not exists
            if (!Schema::hasColumn('orders', 'surge_fee')) {
                $table->decimal('surge_fee', 12, 2)->default(0)->after('surge_multiplier');
            }

            // Add total_amount if not exists
            if (!Schema::hasColumn('orders', 'total_amount')) {
                $table->decimal('total_amount', 12, 2)->default(0)->after('surge_fee');
            }

            // Add index only if it doesn't exist and column exists
            if (Schema::hasColumn('orders', 'order_type_id') && !$this->hasIndex('orders', 'idx_orders_order_type')) {
                $table->index('order_type_id', 'idx_orders_order_type');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            // Drop foreign key if exists
            if (Schema::hasColumn('orders', 'order_type_id')) {
                $table->dropForeign(['order_type_id']);
            }

            // Drop columns if they exist
            $columns = ['order_type_id', 'delivery_fee', 'surge_multiplier', 'surge_fee', 'total_amount'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::dropIfExists('order_types');
    }
};
