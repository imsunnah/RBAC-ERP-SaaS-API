<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class HrmLeaveApplicationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'             => $this->uuid,

            // ── Employee ─────────────────────────────────────────────────
            'employee'         => $this->whenLoaded('employee', function () {
                return [
                    'uuid'             => $this->employee->uuid,
                    'full_name'        => $this->employee->full_name,
                    'user_employee_id' => $this->employee->user_employee_id,
                    'employee_photo'   => $this->employee->employee_photo
                        ? Storage::url($this->employee->employee_photo)
                        : null,
                ];
            }),

            // ── Leave Details ────────────────────────────────────────────
            'leave_type'       => new MetadataResource($this->whenLoaded('leaveType')),
            'from_date'        => $this->from_date?->format('Y-m-d'),
            'to_date'          => $this->to_date?->format('Y-m-d'),
            'total_days'       => $this->total_days,
            'reason'           => $this->reason,

            // ── Status ───────────────────────────────────────────────────
            'status'           => $this->status,
            'rejection_reason' => $this->rejection_reason,
            'approved_by'      => $this->whenLoaded('approvedByUser', function () {
                return [
                    'id'   => $this->approvedByUser->id,
                    'name' => $this->approvedByUser->name,
                ];
            }),
            'approved_at'      => $this->approved_at?->format('Y-m-d H:i:s'),

            // ── Documents ────────────────────────────────────────────────
            'documents'           => $this->whenLoaded('documents', function () {
                return $this->documents->map(fn ($doc) => [
                    'uuid'      => $doc->uuid,
                    'title'     => $doc->title ?? $doc->file_name,
                    'file_name' => $doc->file_name,
                    'file_url'  => $doc->file_path ? Storage::url($doc->file_path) : null,
                    'file_path' => $doc->file_path,
                ]);
            }),
            'essential_documents' => $this->whenLoaded('documents', function () {
                return $this->documents->map(fn ($doc) => [
                    'uuid'      => $doc->uuid,
                    'title'     => $doc->title ?? $doc->file_name,
                    'file_name' => $doc->file_name,
                    'file_url'  => $doc->file_path ? Storage::url($doc->file_path) : null,
                    'file_path' => $doc->file_path,
                ]);
            }),

            // ── Audit ────────────────────────────────────────────────────
            'created_at'       => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at'       => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
