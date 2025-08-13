<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pet_shop_pets', function (Blueprint $table) {
            $table->uuid('uuid')->primary()->nullable();
            $table->string('name', 100);
            $table->string('species', 50);
            $table->string('breed', 50)->nullable();
            $table->string('birth_date')->nullable();
            $table->string('weight')->nullable();
            $table->uuid('owner_uuid');
            $table->boolean('vaccinated')->default(false)->nullable();
            $table->string('created_at')->useCurrent();
            $table->string('updated_at')->nullable();
            $table->softDeletes();
            $table->foreign('owner_uuid')->references('uuid')->on('pet_shop_owners');
            $table->index(['name','species'], 'idx_pets_name_species');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pet_shop_pets');
    }
};