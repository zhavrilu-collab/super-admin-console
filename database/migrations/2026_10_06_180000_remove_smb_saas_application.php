<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $applicationId = DB::table('applications')->where('slug', 'smb-saas')->value('id');

        if ($applicationId === null) {
            return;
        }

        DB::table('tenants')->where('application_id', $applicationId)->delete();
        DB::table('applications')->where('id', $applicationId)->delete();
    }

    public function down(): void
    {
    }
};
