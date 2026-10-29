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
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('company');
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('stage', 20)->default('new');
            $table->string('priority', 20)->default('medium');
            $table->string('source', 30)->default('other');
            $table->decimal('value', 12, 2)->default(0);
            $table->text('notes')->nullable();
            // "Assigned to". The foreign key also indexes the column.
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('stage');
            $table->index('source');
            $table->index('priority');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
