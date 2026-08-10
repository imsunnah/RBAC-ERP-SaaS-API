<?php

namespace App\Actions\HrmLeaveApplication\Concerns;

use App\Models\HrmLeaveApplication;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

trait HandlesLeaveDocuments
{
    /**
     * Upload and save attached leave documents.
     */
    protected function saveUploadedDocuments(?Request $request, HrmLeaveApplication $leave, array $data = []): void
    {
        $inputDocs = array_merge(
            is_array($data['documents'] ?? null) ? $data['documents'] : (is_array($request?->input('documents')) ? $request->input('documents') : []),
            is_array($data['essential_documents'] ?? null) ? $data['essential_documents'] : (is_array($request?->input('essential_documents')) ? $request->input('essential_documents') : [])
        );

        $fileGroups = [
            'documents'           => $request?->file('documents'),
            'essential_documents' => $request?->file('essential_documents'),
        ];

        $processedFiles = [];

        foreach (['documents', 'essential_documents'] as $groupKey) {
            $files = $fileGroups[$groupKey] ?? null;
            $items = is_array($data[$groupKey] ?? null) ? $data[$groupKey] : ($request ? $request->input($groupKey) : null);

            if ($items && !is_array($items)) {
                $items = [$items];
            }

            if ($files && !is_array($files)) {
                $files = [$files];
            }

            // 1. Process UploadedFile objects in request
            if (!empty($files)) {
                foreach ($files as $index => $fileItem) {
                    $fileObj = null;
                    $title   = null;

                    if ($fileItem instanceof UploadedFile && $fileItem->isValid()) {
                        $fileObj = $fileItem;
                        if (isset($items[$index])) {
                            if (is_array($items[$index])) {
                                $title = $items[$index]['title'] ?? $items[$index]['name'] ?? null;
                            } elseif (is_string($items[$index])) {
                                $title = $items[$index];
                            }
                        }
                    } elseif (is_array($fileItem)) {
                        $fileObj = $fileItem['file'] ?? $fileItem['doc'] ?? $fileItem['document'] ?? $fileItem['file_path'] ?? null;
                        $title   = $fileItem['title'] ?? $fileItem['name'] ?? null;

                        if (!$fileObj instanceof UploadedFile) {
                            foreach ($fileItem as $val) {
                                if ($val instanceof UploadedFile) {
                                    $fileObj = $val;
                                    break;
                                }
                            }
                        }

                        if (!$title && isset($items[$index])) {
                            if (is_array($items[$index])) {
                                $title = $items[$index]['title'] ?? $items[$index]['name'] ?? null;
                            } elseif (is_string($items[$index])) {
                                $title = $items[$index];
                            }
                        }
                    }

                    if ($fileObj instanceof UploadedFile && $fileObj->isValid()) {
                        $path = $fileObj->store('hrm/leave-documents', 'public');
                        $leave->documents()->create([
                            'title'     => $title ?: $fileObj->getClientOriginalName(),
                            'file_name' => $fileObj->getClientOriginalName(),
                            'file_path' => $path,
                        ]);
                        $processedFiles[] = $fileObj->getClientOriginalName();
                    }
                }
            }

            // 2. Process non-file items (e.g. pre-uploaded file path strings or document arrays)
            if (!empty($items)) {
                foreach ($items as $item) {
                    if (is_array($item)) {
                        $fileObj = $item['file'] ?? $item['doc'] ?? $item['document'] ?? null;
                        if ($fileObj instanceof UploadedFile && $fileObj->isValid()) {
                            // Handled in file upload loop above if in $files, but process if passed only in $data
                            $path  = $fileObj->store('hrm/leave-documents', 'public');
                            $title = $item['title'] ?? $item['name'] ?? $fileObj->getClientOriginalName();
                            $leave->documents()->create([
                                'title'     => $title,
                                'file_name' => $fileObj->getClientOriginalName(),
                                'file_path' => $path,
                            ]);
                            $processedFiles[] = $fileObj->getClientOriginalName();
                            continue;
                        }

                        $path  = $item['file_path'] ?? $item['file_url'] ?? $item['path'] ?? (is_string($fileObj) ? $fileObj : null);
                        $title = $item['title'] ?? $item['name'] ?? $item['file_name'] ?? null;

                        if (is_string($path) && !empty($path)) {
                            if (str_starts_with($path, '/storage/')) {
                                $path = substr($path, strlen('/storage/'));
                            }
                            $fileName = basename($path);
                            if (!in_array($fileName, $processedFiles, true)) {
                                $leave->documents()->create([
                                    'title'     => $title ?: $fileName,
                                    'file_name' => $fileName,
                                    'file_path' => $path,
                                ]);
                                $processedFiles[] = $fileName;
                            }
                        }
                    } elseif (is_string($item) && !empty($item)) {
                        $path = $item;
                        if (str_starts_with($path, '/storage/')) {
                            $path = substr($path, strlen('/storage/'));
                        }
                        $fileName = basename($path);
                        if (!in_array($fileName, $processedFiles, true)) {
                            $leave->documents()->create([
                                'title'     => $fileName,
                                'file_name' => $fileName,
                                'file_path' => $path,
                            ]);
                            $processedFiles[] = $fileName;
                        }
                    }
                }
            }
        }

        // 3. Fallback: process any loose uploaded files in $request->allFiles() if no documents were created
        if ($leave->documents()->count() === 0 && $request && !empty($request->allFiles())) {
            foreach ($request->allFiles() as $key => $fileOrArray) {
                $files = is_array($fileOrArray) ? $fileOrArray : [$fileOrArray];
                $this->processUploadedFileList($files, $leave);
            }
        }
    }

    /**
     * Helper to recursively process a list of uploaded files.
     */
    private function processUploadedFileList(array $files, HrmLeaveApplication $leave): void
    {
        foreach ($files as $fileObj) {
            if ($fileObj instanceof UploadedFile && $fileObj->isValid()) {
                $path = $fileObj->store('hrm/leave-documents', 'public');
                $leave->documents()->create([
                    'title'     => $fileObj->getClientOriginalName(),
                    'file_name' => $fileObj->getClientOriginalName(),
                    'file_path' => $path,
                ]);
            } elseif (is_array($fileObj)) {
                $this->processUploadedFileList($fileObj, $leave);
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
