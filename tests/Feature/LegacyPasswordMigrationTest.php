<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LegacyPasswordMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_encrypted_password_column_migration_drops_legacy_column(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('encrypted_password')->nullable();
        });

        $migration = require database_path(
            'migrations/2026_06_09_000000_drop_encrypted_password_from_users_table.php'
        );
        $migration->up();

        $this->assertFalse(Schema::hasColumn('users', 'encrypted_password'));
    }
}
