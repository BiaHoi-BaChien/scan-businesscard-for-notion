<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('users', 'passkey_hash') || Schema::hasColumn('users', 'passkey_registered_at')) {
            Schema::table('users', function (Blueprint $table) {
                if (Schema::hasColumn('users', 'passkey_hash')) {
                    $table->dropColumn('passkey_hash');
                }

                if (Schema::hasColumn('users', 'passkey_registered_at')) {
                    $table->dropColumn('passkey_registered_at');
                }
            });
        }
    }

    public function down(): void
    {
        // パスキー関連機能を完全に削除するため、ロールバック時もカラムを復元しない。
    }
};
