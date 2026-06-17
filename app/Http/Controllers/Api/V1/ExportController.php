<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\HrmAttendanceExportJob;
use App\Jobs\HrmEmployeeExportJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportController extends Controller
{
    /**
     * Trigger queued export for HRM Employees.
     */
    public function exportEmployees(Request $request): JsonResponse
    {
        $org = app('current_organization');
        $filename = 'employees_' . $org->slug . '_' . now()->format('Y-m-d_His') . '.csv';

        HrmEmployeeExportJob::dispatch($org->id, $filename, $request->all());

        return response()->json([
            'success' => true,
            'message' => 'Employee export has been queued. You will get a download link soon.',
            'data' => [
                'filename' => $filename,
                'download_url' => url('/api/v1/exports/' . $filename)
            ]
        ]);
    }

    /**
     * Trigger queued export for HRM Attendance.
     */
    public function exportAttendance(Request $request): JsonResponse
    {
        $org = app('current_organization');
        $filename = 'attendance_' . $org->slug . '_' . now()->format('Y-m-d_His') . '.csv';

        HrmAttendanceExportJob::dispatch($org->id, $filename, $request->all());

        return response()->json([
            'success' => true,
            'message' => 'Attendance export has been queued.',
            'data' => [
                'filename' => $filename,
                'download_url' => url('/api/v1/exports/' . $filename)
            ]
        ]);
    }

    /**
     * Download the exported file.
     */
    public function download(string $filename): BinaryFileResponse|JsonResponse
    {
        $path = 'exports/' . $filename;

        if (!Storage::disk('public')->exists($path)) {
            return response()->json([
                'success' => false,
                'message' => 'File not found or export still in progress.'
            ], 404);
        }

        return response()->download(Storage::disk('public')->path($path));
    }
}
