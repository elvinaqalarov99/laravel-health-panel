<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('health_check_result_history_items', function (Blueprint $table) {
            $table->index(['check_name', 'created_at'], 'hcr_check_name_created_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('health_check_result_history_items', function (Blueprint $table) {
            $table->dropIndex('hcr_check_name_created_at_idx');
        });
    }
};
