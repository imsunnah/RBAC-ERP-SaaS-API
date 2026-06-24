<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Checking columns for hrm_attendances...\n";

Schema::table('hrm_attendances', function (Blueprint $table) {
    if (!Schema::hasColumn('hrm_attendances', 'status')) {
        echo "Adding status...\n";
        $table->string('status')->nullable();
    }
    if (!Schema::hasColumn('hrm_attendances', 'working_hours')) {
        echo "Adding working_hours...\n";
        $table->decimal('working_hours', 5, 2)->nullable();
    }
    if (!Schema::hasColumn('hrm_attendances', 'overtime_hours')) {
        echo "Adding overtime_hours...\n";
        $table->decimal('overtime_hours', 5, 2)->nullable();
    }
    if (!Schema::hasColumn('hrm_attendances', 'shift_id')) {
        echo "Adding shift_id...\n";
        $table->bigInteger('shift_id')->unsigned()->nullable();
    }
    if (!Schema::hasColumn('hrm_attendances', 'source')) {
        echo "Adding source...\n";
        $table->string('source')->default('BIOMETRIC');
    }
    if (!Schema::hasColumn('hrm_attendances', 'approval_status')) {
        echo "Adding approval_status...\n";
        $table->string('approval_status')->default('APPROVED');
    }
});

echo "Done!\n";
