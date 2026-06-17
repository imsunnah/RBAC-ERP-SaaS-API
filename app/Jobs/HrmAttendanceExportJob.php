<?php

namespace App\Jobs;

use App\Models\HrmAttendance;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class HrmAttendanceExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $organizationId,
        public string $filename,
        public array $filters = []
    ) {}

    public function handle(): void
    {
        $query = HrmAttendance::where('organization_id', $this->organizationId)
            ->with('employee');

        if (!empty($this->filters['start_date'])) {
            $query->where('date', '>=', $this->filters['start_date']);
        }
        if (!empty($this->filters['end_date'])) {
            $query->where('date', '<=', $this->filters['end_date']);
        }

        $filePath = 'exports/' . $this->filename;
        Storage::disk('public')->makeDirectory('exports');
        $fullPath = Storage::disk('public')->path($filePath);

        $handle = fopen($fullPath, 'w');

        // Header
        fputcsv($handle, [
            'Employee ID',
            'Full Name',
            'Date',
            'Punch In',
            'Punch Out',
            'State',
            'Type'
        ]);

        $query->chunk(1000, function ($records) use ($handle) {
            foreach ($records as $record) {
                fputcsv($handle, [
                    $record->device_user_id,
                    $record->employee?->full_name ?? 'N/A',
                    $record->date?->format('Y-m-d') ?? 'N/A',
                    $record->punch_in?->format('H:i:s') ?? 'N/A',
                    $record->punch_out?->format('H:i:s') ?? 'N/A',
                    $record->punch_state,
                    $record->punch_type,
                ]);
            }
        });

        fclose($handle);
    }
}
