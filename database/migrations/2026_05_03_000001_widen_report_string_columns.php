<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->text('visit_reason')->change();
            $table->text('contact_met')->nullable()->change();
            $table->text('contact_position')->nullable()->change();
            $table->text('sharepoint_url')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->string('visit_reason')->change();
            $table->string('contact_met')->nullable()->change();
            $table->string('contact_position')->nullable()->change();
            $table->string('sharepoint_url')->nullable()->change();
        });
    }
};
