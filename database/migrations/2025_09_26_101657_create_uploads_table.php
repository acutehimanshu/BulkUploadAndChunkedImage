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
        Schema::create('uploads', function (Blueprint $table) {
            $table->id();
            $table->string('file_name');
            $table->string('mime_type');
            $table->string('disk')->default('local');
            $table->string('path'); 
            $table->string('checksum')->nullable(); // Validation purpuse k liye
            $table->unsignedBigInteger('total_size');
            $table->unsignedBigInteger('uploaded_size')->default(0);
            $table->json('received_chunks')->nullable(); // array of received chunk indices
            $table->boolean('is_completed')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('uploads');
    }
};
