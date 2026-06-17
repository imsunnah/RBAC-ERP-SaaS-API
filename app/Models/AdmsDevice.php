<?php

namespace App\Models;

use App\Models\Traits\HasUuidColumn;
use App\Models\Traits\BelongsToOrganization;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;

class AdmsDevice extends Model
{
    use HasUuidColumn, BelongsToOrganization;
    protected $fillable = [
        'serial_number',
        'alias',
        'name',
        'ip_address',      // captured from request on every handshake
        'firmware',
        'platform',
        'last_seen',
        'last_record_at',
        'push_count',
        'total_records_sent',
        'organization_id',
        'company_id',
        'org_no',
    ];

    protected $casts = [
        'last_seen'          => 'datetime',
        'last_record_at'     => 'datetime',
        'push_count'         => 'integer',
        'total_records_sent' => 'integer',
    ];

    protected $appends = ['last_seen_human', 'last_record_human'];

    /**
     * Human-readable "last seen" — e.g. "3 min ago", "2 hours ago", or full date if > 1 day.
     */
    public function getLastSeenHumanAttribute(): ?string
    {
        return $this->humanTime($this->last_seen);
    }

    /**
     * Human-readable "last record at".
     */
    public function getLastRecordHumanAttribute(): ?string
    {
        return $this->humanTime($this->last_record_at);
    }

    /**
     * Convert a datetime to relative string.
     * < 1 min: "Just now", < 60 min: "X min ago", < 24h: "X hours ago", else: full datetime.
     */
    private function humanTime($dt): ?string
    {
        if (!$dt) return null;

        $now = now();
        $diffSeconds = (int) abs($now->diffInSeconds($dt));
        $diffMinutes = (int) abs($now->diffInMinutes($dt));
        $diffHours   = (int) abs($now->diffInHours($dt));

        if ($diffSeconds < 60) return 'Just now';
        if ($diffMinutes < 60) return $diffMinutes . ' min ago';
        if ($diffHours < 24)   return $diffHours . ($diffHours === 1 ? ' hour ago' : ' hours ago');

        // More than 1 day — show full date/time
        return $dt->format('d M Y, h:i A');
    }

    /**
     * Upsert the device record on every handshake or push.
     * Returns the device model.
     */
    public static function heartbeat(string $serialNumber, array $info = []): self
    {
        $device = self::firstOrNew(['serial_number' => $serialNumber]);
        $device->last_seen = now();

        foreach (['firmware', 'platform', 'alias'] as $field) {
            if (!empty($info[$field])) {
                $device->$field = $info[$field];
            }
        }

        // Always update IP — it may change if device moves
        if (!empty($info['ip_address'])) {
            $device->ip_address = $info['ip_address'];
        }

        if (!empty($info['org_no'])) {
            $device->org_no = $info['org_no'];

            // Resolve organization_id and company_id from legacy org_no
            $org = Organization::find($info['org_no']);
            if ($org) {
                $device->organization_id = $org->id;
                $device->company_id = $org->company_id;
            }
        }

        $device->save();
        return $device;
    }

    /**
     * Increment push_count and optionally update last_record_at + total_records_sent.
     *
     * @param int         $recordsSaved  Number of new records saved.
     * @param string|null $lastPunchAt   Actual punch datetime from ATTLOG (not server time).
     *                                   If null, last_record_at is not updated.
     */
    public function recordPush(int $recordsSaved = 0, ?string $lastPunchAt = null): void
    {
        try {
            $updates  = 'push_count = push_count + 1';
            $bindings = [];

            if ($recordsSaved > 0 && $lastPunchAt !== null) {
                // Store the actual punch time — not server-processing time (NOW())
                $updates   .= ', total_records_sent = total_records_sent + ?, last_record_at = ?';
                $bindings[] = $recordsSaved;
                $bindings[] = $lastPunchAt;
            } elseif ($recordsSaved > 0) {
                // Fallback: punch time unavailable, at least increment the counter
                $updates   .= ', total_records_sent = total_records_sent + ?';
                $bindings[] = $recordsSaved;
            }

            DB::update(
                "UPDATE adms_devices SET {$updates} WHERE id = ?",
                array_merge($bindings, [$this->id])
            );
        } catch (\Throwable) {
            // Never crash on stats update
        }
    }
}
