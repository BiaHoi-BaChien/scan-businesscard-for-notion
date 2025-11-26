<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::dropIfExists('business_cards');
    }

    public function down(): void
    {
        // Table intentionally left dropped; restoring would require original migration.
    }
};
