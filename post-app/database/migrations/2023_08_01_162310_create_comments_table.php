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
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->integer('post_id');
            $table->integer('comment_count');
            $table->integer('like_count');
            $table->unsignedBigInteger('comment_created_by')->default(0);
            $table->unsignedBigInteger('comment_up_by')->default(0);
            $table->unsignedBigInteger('comment_delete_by')->default(0);
            $table->timestamps();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
