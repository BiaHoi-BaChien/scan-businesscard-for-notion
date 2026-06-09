<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'encrypted_password')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('encrypted_password');
        });
    }

    public function down(): void
    {
        // Passwords must not be stored in a recoverable form.
    }
};
