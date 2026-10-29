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
        Schema::table('leads', function (Blueprint $table) {
            // An id in ANOTHER system (SAIMS), so deliberately not a foreign key.
            $table->unsignedBigInteger('saims_customer_id')->nullable()->after('owner_id');
            // A snapshot of the name, so the link can still be shown when SAIMS is down.
            $table->string('saims_customer_name')->nullable()->after('saims_customer_id');

            $table->index('saims_customer_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['saims_customer_id']);
            $table->dropColumn(['saims_customer_id', 'saims_customer_name']);
        });
    }
};
