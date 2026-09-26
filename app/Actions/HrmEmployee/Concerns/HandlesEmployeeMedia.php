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

    /** Fields that contain lookup UUIDs in the emergency_contacts array. */
    protected const EMERGENCY_LOOKUP_FIELDS = [
        'relationship_id',
    ];

    /**
     * Handle image uploads for all image fields.
     */
    protected function handleImageUploads(Request $request, array $validated, ?HrmEmployee $employee = null): array
    {
        // 1. Employee Photo / Profile Picture resolution
        $photoInput = null;
        $photoProvided = false;

        // First pass: search for an UploadedFile instance (multipart/form-data)
        foreach (['profile_picture', 'employee_photo', 'photo', 'avatar'] as $alias) {
            $file = $request->file($alias);
            if ($file instanceof UploadedFile && $file->isValid()) {
                $photoInput = $file;
                $photoProvided = true;
                break;
            }
            if (isset($validated[$alias]) && $validated[$alias] instanceof UploadedFile && $validated[$alias]->isValid()) {
                $photoInput = $validated[$alias];
                $photoProvided = true;
                break;
            }
        }

        // Check allFiles() if nested or flat file array
        if (!$photoInput) {
            foreach ($request->allFiles() as $key => $file) {
                if (in_array($key, ['profile_picture', 'employee_photo', 'photo', 'avatar'], true)) {
                    if ($file instanceof UploadedFile && $file->isValid()) {
                        $photoInput = $file;
                        $photoProvided = true;
                        break;
                    }
                }
            }
        }

        // Second pass: if no UploadedFile found, search for non-empty string input
        if (!$photoInput) {
            foreach (['profile_picture', 'employee_photo', 'photo', 'avatar'] as $alias) {
                $val = $request->input($alias) ?? ($validated[$alias] ?? null);
                if (is_string($val)) {
                    $cleanVal = trim($val);
                    if ($cleanVal !== '' && !in_array(strtolower($cleanVal), ['null', 'undefined', '0', 'false'], true)) {
                        $photoInput = $cleanVal;
                        $photoProvided = true;
                        break;
                    }
                }
            }
        }

        $oldPhotoPath = $employee ? ($employee->employee_photo ?? $employee->profile_picture) : null;
        $savedPhotoPath = $this->saveImageValue($photoInput, 'photo_', $oldPhotoPath);

        if ($savedPhotoPath) {
            $validated['employee_photo']  = $savedPhotoPath;
            $validated['profile_picture'] = $savedPhotoPath;
        } elseif ($photoProvided && !$employee) {
            unset($validated['employee_photo'], $validated['profile_picture']);
        } elseif (!$savedPhotoPath && $employee) {
            unset($validated['employee_photo'], $validated['profile_picture']);
        }

        unset($validated['photo'], $validated['avatar']);

        // 2. Handle remaining image fields: signature_image, nid_image, business_card_image
        foreach (['signature_image', 'nid_image', 'business_card_image'] as $field) {
            $fieldInput = $request->file($field);
            if (!($fieldInput instanceof UploadedFile)) {
                $fieldInput = $validated[$field] ?? $request->input($field);
            }

            $oldFieldPath = $employee ? $employee->$field : null;
            $savedFieldPath = $this->saveImageValue($fieldInput, $field . '_', $oldFieldPath);

            if ($savedFieldPath) {
                $validated[$field] = $savedFieldPath;
            } else {
                unset($validated[$field]);
            }
        }

        return $validated;
    }

    /**
     * Save an uploaded file, base64 string, local file path, or URL to public storage disk.
     */
    protected function saveImageValue(mixed $value, string $prefix = 'photo_', ?string $oldPath = null): ?string
    {
        // 1. UploadedFile instance (multipart/form-data)
        if ($value instanceof UploadedFile) {
            if ($value->isValid()) {
                if ($oldPath && Storage::disk('public')->exists($oldPath)) {
                    Storage::disk('public')->delete($oldPath);
                }
                return $value->store('employees', 'public');
            }
            return null;
        }

        if (empty($value) || !is_string($value) || in_array(strtolower(trim($value)), ['null', 'undefined', '0', 'false', ''], true)) {
            return null;
        }

        $val = trim($value);
        if (str_contains($val, '/storage/')) {
            $val = explode('/storage/', $val, 2)[1];
        }
        $val = ltrim($val, '/');

        // 2. Base64 Data URI string
        if (preg_match('/^data:image\/(\w+);base64,/i', $val, $matches)) {
            $decoded = base64_decode(substr($val, strpos($val, ',') + 1));
            if ($decoded !== false) {
                $ext = strtolower($matches[1]) === 'jpeg' ? 'jpg' : strtolower($matches[1]);
                $path = 'employees/' . uniqid($prefix, true) . '.' . $ext;
                if ($oldPath && Storage::disk('public')->exists($oldPath)) {
                    Storage::disk('public')->delete($oldPath);
                }
                Storage::disk('public')->put($path, $decoded);
                return $path;
            }
        }

        // 3. Raw Base64 string without data URI header
        if (strlen($val) > 100 && !str_contains($val, ' ') && preg_match('/^[a-zA-Z0-9\/\r\n+=\-_]+$/', $val)) {
            $decoded = base64_decode($val, true);
            if ($decoded !== false) {
                $finfo = new \finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->buffer($decoded);
                if ($mime && str_starts_with($mime, 'image/')) {
                    $ext = explode('/', $mime)[1] ?? 'jpg';
                    $ext = $ext === 'jpeg' ? 'jpg' : $ext;
                    $path = 'employees/' . uniqid($prefix, true) . '.' . $ext;
                    if ($oldPath && Storage::disk('public')->exists($oldPath)) {
                        Storage::disk('public')->delete($oldPath);
                    }
                    Storage::disk('public')->put($path, $decoded);
                    return $path;
                }
            }
        }

        // 4. Pre-existing file on public storage disk
        if (Storage::disk('public')->exists($val)) {
            return $val;
        }

        // 5. Local file path check (temp directory, public path, storage path)
        $possiblePaths = [
            $val,
            public_path($val),
            storage_path($val),
            storage_path('app/' . $val),
            storage_path('app/public/' . $val),
            sys_get_temp_dir() . '/' . basename($val),
        ];

        foreach ($possiblePaths as $possiblePath) {
            if (!empty($possiblePath) && file_exists($possiblePath) && is_file($possiblePath)) {
                $ext = pathinfo($possiblePath, PATHINFO_EXTENSION) ?: 'jpg';
                $path = 'employees/' . uniqid($prefix, true) . '.' . $ext;
                if ($oldPath && Storage::disk('public')->exists($oldPath)) {
                    Storage::disk('public')->delete($oldPath);
                }
                Storage::disk('public')->put($path, file_get_contents($possiblePath));
                return $path;
            }
        }

        // 6. External URL check
        if (filter_var($val, FILTER_VALIDATE_URL)) {
            try {
                $contents = @file_get_contents($val);
                if ($contents !== false) {
                    $finfo = new \finfo(FILEINFO_MIME_TYPE);
                    $mime = $finfo->buffer($contents);
                    $ext = ($mime && str_starts_with($mime, 'image/')) ? explode('/', $mime)[1] : 'jpg';
                    $ext = $ext === 'jpeg' ? 'jpg' : $ext;
                    $path = 'employees/' . uniqid($prefix, true) . '.' . $ext;
                    if ($oldPath && Storage::disk('public')->exists($oldPath)) {
                        Storage::disk('public')->delete($oldPath);
                    }
                    Storage::disk('public')->put($path, $contents);
                    return $path;
                }
            } catch (\Throwable $e) {
                // Ignore download error
            }
        }

        return $val;
    }

    /** Resolve lookup UUID strings to integer IDs for the given fields. */
    protected function resolveLookupFields(array $data, array $fields, string $lookupModel = GlobalLookup::class): array
    {
        foreach ($fields as $field) {
            if (!empty($data[$field])) {
                $data[$field] = $lookupModel::resolveId($data[$field]);
            }
        }

        return $data;
    }

    /**
     * Sync a hasMany relation using update-or-create logic.
     */
    protected function syncRelation(
        HrmEmployee $employee,
        string $relation,
        mixed $items,
        array $lookupFields = [],
        string $lookupModel = GlobalLookup::class
    ): void
    {
        $data = is_string($items) ? json_decode($items, true) : $items;

        if (!is_array($data)) {
            return;
        }

        $keptUuids = [];

        foreach ($data as $item) {
            $uuid     = $item['uuid'] ?? null;
            $itemData = Arr::except($item, ['uuid', 'id']);
            $itemData = $this->resolveLookupFields($itemData, $lookupFields, $lookupModel);

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
