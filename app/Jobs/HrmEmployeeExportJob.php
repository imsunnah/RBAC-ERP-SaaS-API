<?php

namespace App\Jobs;

use App\Models\HrmEmployee;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class HrmEmployeeExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $organizationId,
        public string $filename,
        public array $filters = []
    ) {}

    public function handle(): void
    {
        $query = HrmEmployee::where('organization_id', $this->organizationId);

        if (!empty($this->filters['search'])) {
            $search = $this->filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('user_employee_id', 'like', "%{$search}%");
            });
        }

        $filePath = 'exports/' . $this->filename;
        Storage::disk('public')->makeDirectory('exports');
        $fullPath = Storage::disk('public')->path($filePath);

        $handle = fopen($fullPath, 'w');

        // Header
        fputcsv($handle, [
            'Employee ID',
            'Full Name',
            'Email',
            'Mobile',
            'Designation',
            'Department',
            'Status',
            'Joined At'
        ]);

        $query->chunk(1000, function ($employees) use ($handle) {
            foreach ($employees as $employee) {
                fputcsv($handle, [
                    $employee->user_employee_id,
                    $employee->full_name,
                    $employee->office_email,
                    $employee->office_mobile_no,
                    $employee->designation,
                    $employee->department?->name ?? 'N/A',
                    $employee->is_active ? 'Active' : 'Inactive',
                    $employee->joining_date?->format('Y-m-d') ?? 'N/A',
                ]);
            }
        });

        fclose($handle);
    }
}
