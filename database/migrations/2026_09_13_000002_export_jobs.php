<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_exports', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users');
            $t->string('report');
            $t->string('format');
            $t->json('filters');
            $t->string('state')->default('queued');
            $t->string('path')->nullable();
            $t->text('error')->nullable();
            $t->unsignedInteger('row_count')->nullable();
            $t->timestampsTz();
        });
        Schema::table('payments', function (Blueprint $t) {
            $t->json('contact')->nullable();
            $t->json('method_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('payments', fn (Blueprint $t) => $t->dropColumn(['contact', 'method_snapshot']));
        Schema::dropIfExists('report_exports');
    }
};
