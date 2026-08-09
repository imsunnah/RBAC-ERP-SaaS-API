<?php

namespace App\Actions\HrmLeaveApplication\Concerns;

use App\Models\HrmLeaveApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

trait HandlesLeaveDocuments
{
    /**
     * Upload and save attached leave documents.
     */
    protected function saveUploadedDocuments(Request $request, HrmLeaveApplication $leave): void
    {
        $fileGroups = array_filter([
            $request->file('documents'),
            $request->file('essential_documents'),
        ]);

        foreach ($fileGroups as $files) {
            $items = is_array($files) ? $files : [$files];

            foreach ($items as $item) {
                if ($item instanceof \Illuminate\Http\UploadedFile && $item->isValid()) {
                    $path = $item->store('hrm/leave-documents', 'public');
                    $leave->documents()->create([
                        'title'     => $item->getClientOriginalName(),
                        'file_name' => $item->getClientOriginalName(),
                        'file_path' => $path,
                    ]);
                } elseif (is_array($item)) {
                    $fileObj = $item['file'] ?? $item['doc'] ?? null;
                    $title   = $item['title'] ?? null;

                    if ($fileObj instanceof \Illuminate\Http\UploadedFile && $fileObj->isValid()) {
                        $path = $fileObj->store('hrm/leave-documents', 'public');
                        $leave->documents()->create([
                            'title'     => $title ?? $fileObj->getClientOriginalName(),
                            'file_name' => $fileObj->getClientOriginalName(),
                            'file_path' => $path,
                        ]);
                    }
                }
            }
        }
    }

    /**
     * Remove specified documents from storage disk and database.
     */
    protected function removeDocuments(HrmLeaveApplication $leave, array $documentUuids): void
    {
        if (empty($documentUuids)) {
            return;
        }

        $docsToRemove = $leave->documents()
            ->whereIn('uuid', $documentUuids)
            ->get();

        foreach ($docsToRemove as $doc) {
            if ($doc->file_path) {
                Storage::disk('public')->delete($doc->file_path);
            }
            $doc->delete();
        }
    }

    /**
     * Delete all documents belonging to a leave application from disk.
     */
    protected function deleteAllDocuments(HrmLeaveApplication $leave): void
    {
        foreach ($leave->documents as $doc) {
            if ($doc->file_path) {
                Storage::disk('public')->delete($doc->file_path);
            }
        }
    }
}
