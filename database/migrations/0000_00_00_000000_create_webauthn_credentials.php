<?php

use Illuminate\Database\Schema\Blueprint;
use Laragear\WebAuthn\Models\WebAuthnCredential;

return WebAuthnCredential::migration()->with(function (Blueprint $table) {
    // The base WebAuthn credentials table stores each credential on its own row, keyed by
    // its credential ID rather than the user ID. That means multiple passkeys can already
    // be associated with a single user without changing the schema. Add custom columns
    // below if you need to persist extra metadata per credential.
    //
    // $table->string('alias')->nullable();
});
