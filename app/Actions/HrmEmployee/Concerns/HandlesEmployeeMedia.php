<?php

namespace App\Actions\HrmEmployee\Concerns;

use App\Models\GlobalLookup;
use App\Models\HrmEmployee;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

trait HandlesEmployeeMedia
{
    /** Image fields used for employee uploads. */
    protected const IMAGE_FIELDS = [
        'employee_photo',
        'profile_picture',
        'signature_image',
        'nid_image',
        'business_card_image',
    ];

    /** Fields that contain GlobalLookup UUIDs in the family array. */
    protected const FAMILY_LOOKUP_FIELDS = [
        'gender_id',
        'marital_status_id',
        'blood_group_id',
        'relationship_id',
    ];

    /** Fields that contain GlobalLookup UUIDs in the emergency_contacts array. */
    protected const EMERGENCY_LOOKUP_FIELDS = [
        'relationship_id',
    ];

    /**
     * Handle image uploads for all image fields.
     */
    protected function handleImageUploads(Request $request, array $validated, ?HrmEmployee $employee = null): array
    {
        foreach (self::IMAGE_FIELDS as $field) {
            $uploadedFile = $request->file($field) ?: ($validated[$field] ?? null);

            if ($uploadedFile instanceof UploadedFile && $uploadedFile->isValid()) {
                if ($employee && $employee->$field) {
                    Storage::disk('public')->delete($employee->$field);
                }
                $path = $uploadedFile->store('employees', 'public');
                $validated[$field] = $path;
            } elseif (array_key_exists($field, $validated) && is_string($validated[$field])) {
                $val = trim($validated[$field]);
                if ($val === '' || in_array(strtolower($val), ['0', 'null', 'undefined'], true)) {
                    unset($validated[$field]);
                } else {
                    if (str_contains($val, '/storage/')) {
                        $val = explode('/storage/', $val, 2)[1];
                    }
                    $validated[$field] = $val;
                }
            } else {
                unset($validated[$field]);
            }
        }

        return $validated;
    }

    /**
     * Resolve GlobalLookup UUID strings to integer IDs for the given fields.
     */
    protected function resolveLookupFields(array $data, array $fields): array
    {
        foreach ($fields as $field) {
            if (!empty($data[$field])) {
                $data[$field] = GlobalLookup::resolveId($data[$field]);
            }
        }

        return $data;
    }

    /**
     * Sync a hasMany relation using update-or-create logic.
     */
    protected function syncRelation(HrmEmployee $employee, string $relation, mixed $items, array $lookupFields = []): void
    {
        $data = is_string($items) ? json_decode($items, true) : $items;

        if (!is_array($data)) {
            return;
        }

        $keptUuids = [];

        foreach ($data as $item) {
            $uuid     = $item['uuid'] ?? null;
            $itemData = Arr::except($item, ['uuid', 'id']);
            $itemData = $this->resolveLookupFields($itemData, $lookupFields);

            if ($uuid) {
                $record = $employee->{$relation}()->where('uuid', $uuid)->first();
                if ($record) {
                    $record->update($itemData);
                    $keptUuids[] = $record->uuid;
                    continue;
                }
            }

            $newRecord  = $employee->{$relation}()->create($itemData);
            $keptUuids[] = $newRecord->uuid;
        }

        $employee->{$relation}()->whereNotIn('uuid', $keptUuids)->delete();
    }

    /**
     * Store document files during employee creation.
     */
    protected function storeDocuments(HrmEmployee $employee, array $documents): void
    {
        foreach ($documents as $doc) {
            $filePath = null;
            if (isset($doc['doc']) && $doc['doc'] instanceof \Illuminate\Http\UploadedFile && $doc['doc']->isValid()) {
                $filePath = $doc['doc']->store('employees/documents', 'public');
            } elseif (isset($doc['doc']) && is_string($doc['doc'])) {
                $val = $doc['doc'];
                if (str_contains($val, '/storage/')) {
                    $val = explode('/storage/', $val, 2)[1];
                }
                $filePath = $val;
            }

            $employee->documents()->create([
                'title' => $doc['title'] ?? 'Untitled',
                'doc'   => $filePath,
            ]);
        }
    }

    /**
     * Sync documents during employee update.
     */
    protected function syncDocuments(HrmEmployee $employee, array $documents): void
    {
        $keptUuids = [];

        foreach ($documents as $doc) {
            $uuid       = $doc['uuid'] ?? null;
            $title      = $doc['title'] ?? 'Untitled';
            $hasNewFile = isset($doc['doc']) && $doc['doc'] instanceof \Illuminate\Http\UploadedFile && $doc['doc']->isValid();

            if ($uuid) {
                $record = $employee->documents()->where('uuid', $uuid)->first();
                if ($record) {
                    $updateData = ['title' => $title];

                    if ($hasNewFile) {
                        if ($record->doc) {
                            Storage::disk('public')->delete($record->doc);
                        }
                        $updateData['doc'] = $doc['doc']->store('employees/documents', 'public');
                    }

                    $record->update($updateData);
                    $keptUuids[] = $record->uuid;
                    continue;
                }
            }

            $filePath = null;
            if ($hasNewFile) {
                $filePath = $doc['doc']->store('employees/documents', 'public');
            } elseif (isset($doc['doc']) && is_string($doc['doc'])) {
                $val = $doc['doc'];
                if (str_contains($val, '/storage/')) {
                    $val = explode('/storage/', $val, 2)[1];
                }
                $filePath = $val;
            }

            $newRecord = $employee->documents()->create([
                'title' => $title,
                'doc'   => $filePath,
            ]);
            $keptUuids[] = $newRecord->uuid;
        }

        $orphans = $employee->documents()->whereNotIn('uuid', $keptUuids)->get();
        foreach ($orphans as $orphan) {
            if ($orphan->doc) {
                Storage::disk('public')->delete($orphan->doc);
            }
            $orphan->delete();
        }
    }

    /**
     * Clean up all image files and document files for an employee.
     */
    protected function deleteAllMedia(HrmEmployee $employee): void
    {
        foreach (self::IMAGE_FIELDS as $field) {
            if ($employee->{$field}) {
                Storage::disk('public')->delete($employee->{$field});
            }
        }

        foreach ($employee->documents as $doc) {
            if ($doc->doc) {
                Storage::disk('public')->delete($doc->doc);
            }
        }
    }
}
