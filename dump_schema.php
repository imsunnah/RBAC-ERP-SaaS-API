<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$tableName = 'hrm_attendances';
$columns = Illuminate\Support\Facades\DB::select("
    SELECT column_name, data_type, is_nullable, column_default 
    FROM information_schema.columns 
    WHERE table_name = ?
", [$tableName]);

echo "Schema for {$tableName}:\n";
foreach ($columns as $column) {
    echo sprintf(
        "Column: %s, Type: %s, Nullable: %s, Default: %s\n",
        $column->column_name,
        $column->data_type,
        $column->is_nullable,
        $column->column_default
    );
}

$constraints = Illuminate\Support\Facades\DB::select("
    SELECT conname, pg_get_constraintdef(c.oid)
    FROM pg_constraint c
    JOIN pg_namespace n ON n.oid = c.connamespace
    WHERE conrelid = ?::regclass
", [$tableName]);

echo "\nConstraints for {$tableName}:\n";
foreach ($constraints as $con) {
    echo sprintf("Name: %s, Def: %s\n", $con->conname, $con->pg_get_constraintdef);
}
