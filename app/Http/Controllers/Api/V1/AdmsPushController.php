<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AdmsDevice;
use App\Models\HrmAttendance;
use App\Models\HrmEmployee;
use App\Services\ShiftService;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * ZKTeco ADMS Push Receiver
 * ─────────────────────────────────────────────────────────────────────
 * ZKTeco device configuration:
 *   Server:  http://YOUR_SERVER/attn/push
 *   Port:    80
 *
 * Device calls:
 *   GET  /attn/push?SN=<sn>&options=all    → Handshake / register
 *   POST /attn/push?SN=<sn>&table=ATTLOG   → Push attendance records
 *   POST /attn/push?SN=<sn>&table=OPERLOG  → Push operation logs
 */
class AdmsPushController extends Controller
{
    protected ShiftService $shiftService;

    public function __construct(ShiftService $shiftService)
    {
        $this->shiftService = $shiftService;
    }

    /**
     * Single entry point for both GET (handshake) and POST (data push).
     */
    public function handle(Request $request)
    {
        $sn     = $request->input('SN') ?? $request->input('sn')
            ?? $request->input('serialnumber') ?? $request->input('DeviceSN')
            ?? 'UNKNOWN';
        $table  = strtoupper($request->input('table', ''));
        $method = $request->method();

        // ── Register / update device ───────────────────────────────
        $info = [
            'firmware'   => $request->input('PushVer') ?? $request->input('pushver', ''),
            'platform'   => $request->input('Language', ''),
            'ip_address' => $request->ip(),
        ];
        $device = AdmsDevice::heartbeat($sn, $info);

        // ══════════════════════════════════════════════════════════
        // GET — Device handshake
        // ══════════════════════════════════════════════════════════
        if ($method === 'GET') {
            return response("GET STAMP\n" .
                "GET OPTION FROM: {$sn}\n" .
                "ATTLOGStamp=0\n" .
                "OPERLOGStamp=9999\n" .
                "ErrorDelay=60\n" .
                "Delay=30\n" .
                "TransTimes=00:00;14:05\n" .
                "TransInterval=1\n" .
                "TransFlag=TransData AttLog OpLog UserInfo\n" .
                "TimeZone=6\n" .
                "Realtime=1\n" .
                "Encrypt=None\n", 200, ['Content-Type' => 'text/plain']);
        }

        // ══════════════════════════════════════════════════════════
        // POST — Receive pushed data
        // ══════════════════════════════════════════════════════════
        if ($table === 'ATTLOG') {
            $body = $request->getContent();
            [$saved, $skipped, $lastPunchAt] = $this->processAttlog($body, $sn, $device);
            $device->recordPush($saved, $lastPunchAt);
            return response("OK: {$saved} saved, {$skipped} skipped\n", 200, ['Content-Type' => 'text/plain']);
        }

        // OPERLOG, USERPIC, or unknown — just count the push, acknowledge
        $device->recordPush(0);
        return response("OK\n", 200, ['Content-Type' => 'text/plain']);
    }

    /**
     * GET /api/adms/devices — List all registered ADMS devices (authenticated).
     */
    public function devices(Request $request)
    {
        $devices = AdmsDevice::orderBy('last_seen', 'desc')->get();

        $deviceUrl = rtrim(config('app.url'), '/') . '/public/i/cdata.php';

        return response()->json([
            'device_url' => $deviceUrl,
            'data' => $devices->map(fn($d) => [
                'id'                  => $d->id,
                'serial_number'       => $d->serial_number,
                'name'                => $d->name,
                'alias'               => $d->alias,
                'ip_address'          => $d->ip_address,
                'firmware'            => $d->firmware,
                'platform'            => $d->platform,
                'last_seen'           => $d->last_seen?->toDateTimeString(),
                'last_seen_human'     => $d->last_seen_human,
                'last_record_at'      => $d->last_record_at?->toDateTimeString(),
                'last_record_human'   => $d->last_record_human,
                'push_count'          => $d->push_count,
                'total_records_sent'  => $d->total_records_sent,
                'status'              => $this->deviceStatus($d),
            ]),
            'total' => $devices->count(),
        ]);
    }

    /**
     * PUT /api/adms/devices/{id} — Update device name (authenticated).
     */
    public function updateDevice(Request $request, int $id)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        $device = AdmsDevice::findOrFail($id);
        $device->name = trim($request->input('name'));
        $device->save();

        return response()->json([
            'id'   => $device->id,
            'name' => $device->name,
        ]);
    }

    /**
     * GET /iclock/getrequest — ZKTeco command poll endpoint.
     */
    public function getrequest(Request $request): \Illuminate\Http\Response
    {
        $sn   = $request->query('SN', 'UNKNOWN');
        $info = $request->query('INFO', '');

        if ($sn !== 'UNKNOWN') {
            try {
                $device = AdmsDevice::firstOrCreate(
                    ['serial_number' => $sn],
                    ['alias' => $sn, 'org_no' => 1]
                );
                $updates = ['last_seen' => now()];
                if ($info && preg_match('/^Ver\s+[\w.]+,(\d+),(\d+),(\d+)/', $info, $m)) {
                    $updates['user_count'] = (int)$m[1];
                    $updates['fp_count']   = (int)$m[2];
                    $updates['tx_count']   = (int)$m[3];
                }
                if ($info && preg_match('/^(Ver\s+[\w.-]+)/', $info, $m)) {
                    $updates['firmware'] = $m[1];
                }
                $device->update($updates);
            } catch (\Throwable) {
                // Never crash on heartbeat failure
            }
        }

        return response("OK\n", 200, ['Content-Type' => 'text/plain']);
    }

    // ──────────────────────────────────────────────────────────────
    // Private helpers
    // ──────────────────────────────────────────────────────────────

    /**
     * Parse ATTLOG body and save to hrm_attendances table.
     * Each line:  PIN  DATE  TIME  STATUS  VERIFY  WORKCODE  RESERVED
     *
     * @return array [saved, skipped, latestPunchAt|null]
     */
    private function processAttlog(string $body, string $sn, AdmsDevice $device): array
    {
        $lines         = explode("\n", trim($body));
        $saved         = 0;
        $skipped       = 0;
        $latestPunchAt = null;

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;

            $parts = preg_split('/\s+/', $line);
            if (count($parts) < 3) {
                $skipped++;
                continue;
            }

            $pin      = trim($parts[0]);
            $datePart = trim($parts[1]);
            $timePart = isset($parts[2]) ? trim($parts[2]) : '00:00:00';
            $datetime = "{$datePart} {$timePart}";

            $ts = strtotime($datetime);
            if (!$ts) {
                $skipped++;
                continue;
            }
            $datetime = date('Y-m-d H:i:s', $ts);
            $date     = substr($datetime, 0, 10);
            $time     = substr($datetime, 11, 8);

            // Resolve employee by biometric_id (PIN) — bypass org scope as we hit this from a public hardware push
            $employee = HrmEmployee::withoutGlobalScopes()->where('biometric_id', (int) $pin)->first();

            $result = $this->upsertAttendance($employee, (int) $pin, $date, $time, $sn);
            if ($result === true) {
                $saved++;
                if ($latestPunchAt === null || $datetime > $latestPunchAt) {
                    $latestPunchAt = $datetime;
                }
            } else {
                $skipped++;
            }
        }

        return [$saved, $skipped, $latestPunchAt];
    }

    /**
     * Upsert attendance for one punch event.
     * First punch of the day  → check_in (with late detection + shift link)
     * Subsequent punch        → check_out + recompute working_hours + overtime
     * Exact duplicate         → skip (idempotent)
     *
     * @return true|string  true on success, error string on failure
     */
    private function upsertAttendance(?HrmEmployee $employee, int $pin, string $date, string $time, string $deviceSn): bool|string
    {
        if (!$employee) {
            \Illuminate\Support\Facades\Log::warning("ADMS: No employee found for PIN {$pin} (SN: {$deviceSn})");
            return "no employee with biometric_id={$pin}";
        }

        try {
            $shift = $this->shiftService->getEmployeeShift($employee->id, $date, $employee->org_no ?? null);

            $existing = HrmAttendance::where('employee_id', $employee->id)
                ->where('date', $date)
                ->first();

            if (!$existing) {
                // ── First punch → check_in ──────────────────────────────
                $status = $this->detectLateStatus($time, $shift);

                HrmAttendance::create([
                    'company_id'      => $employee->company_id,
                    'organization_id' => $employee->organization_id,
                    'employee_id'     => $employee->id,
                    'biometric_id'    => $pin,
                    'date'            => $date,
                    'status'          => $status,
                    'check_in'        => $time,
                    'check_out'       => null,
                    'working_hours'   => null,
                    'overtime_hours'  => null,
                    'shift_id'        => $shift['shift_id'],
                    'source'          => HrmAttendance::SOURCE_BIOMETRIC,
                    'approval_status' => HrmAttendance::APPROVAL_APPROVED,
                    'remarks'         => "Device: {$deviceSn}",
                    'org_no'          => $employee->org_no ?? null,
                    'created_by'      => null,
                ]);
                return true;
            }

            // ── Subsequent punch → update check_out ────────────────────
            if ($existing->check_in && $time > $existing->check_in) {
                if ($existing->check_out === $time) {
                    return 'exact duplicate check_out';
                }

                $checkIn  = Carbon::createFromTimeString($existing->check_in);
                $checkOut = Carbon::createFromTimeString($time);
                if ($checkOut->lt($checkIn)) {
                    $checkOut->addDay();
                }

                $totalMinutes   = $checkIn->diffInMinutes($checkOut);
                $breakMinutes   = $shift['break_duration_minutes'] ?? 0;
                $effectiveBreak = $totalMinutes > $breakMinutes ? $breakMinutes : 0;
                $workingMinutes = max(0, $totalMinutes - $effectiveBreak);
                $workingHours   = round($workingMinutes / 60, 2);

                $expectedHours = (float) ($shift['expected_hours'] ?? 8);
                $overtimeHours = $workingHours > $expectedHours
                    ? round($workingHours - $expectedHours, 2)
                    : 0;

                $existing->update([
                    'biometric_id'   => $pin,
                    'check_out'      => $time,
                    'working_hours'  => $workingHours,
                    'overtime_hours' => $overtimeHours,
                    'shift_id'       => $existing->shift_id ?? $shift['shift_id'],
                    'remarks'        => "Device: {$deviceSn}",
                ]);
                return true;
            }

            return 'time not later than existing check_in';
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('ADMS upsertAttendance failed', [
                'pin'   => $pin,
                'date'  => $date,
                'time'  => $time,
                'error' => $e->getMessage(),
            ]);
            return $e->getMessage();
        }
    }

    /**
     * Detect if check-in time makes the employee LATE.
     */
    private function detectLateStatus(string $checkIn, array $shift): string
    {
        $checkInTime = Carbon::createFromTimeString($checkIn);
        $shiftStart  = Carbon::createFromTimeString($shift['start_time']);
        $grace       = $shift['grace_minutes'] ?? 15;

        $lateThreshold = $shiftStart->copy()->addMinutes($grace);

        return $checkInTime->gt($lateThreshold)
            ? HrmAttendance::STATUS_LATE
            : HrmAttendance::STATUS_PRESENT;
    }

    /**
     * Determine device online status based on last_seen.
     */
    private function deviceStatus(AdmsDevice $device): string
    {
        if (!$device->last_seen) return 'UNKNOWN';
        $diffSeconds = $device->last_seen->diffInSeconds(now());
        if ($diffSeconds <= 60)   return 'ONLINE';
        if ($diffSeconds <= 300)  return 'IDLE';
        return 'OFFLINE';
    }
}
