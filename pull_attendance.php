<?php

/**
 * Pull attendance from ZKTeco device using TCP protocol with commkey auth.
 */

$deviceIp   = '192.168.100.20';
$devicePort = 4370;
$commKey    = 0; // Default commkey, try 0, 123456, etc.
$timeout    = 5;

echo "=== ZKTeco TCP Pull (with CommKey) ===\n\n";

echo "1. Connecting via TCP to {$deviceIp}:{$devicePort}...\n";
$fp = @stream_socket_client("tcp://{$deviceIp}:{$devicePort}", $errno, $errstr, $timeout);
if (!$fp) {
    echo "   TCP FAIL: {$errstr}\n";
    exit(1);
}
stream_set_timeout($fp, $timeout);
echo "   TCP Connected!\n";

function createHeader($command, $chksum, $sessionId, $replyId, $data = '')
{
    return pack('v', $command) . pack('v', $chksum) . pack('v', $sessionId) . pack('v', $replyId) . $data;
}

function calcChecksum($p)
{
    $l = count($p);
    $chksum = 0;
    $i = $l;
    $j = 1;
    while ($i > 1) {
        $u = unpack('v', pack('C2', $p[$j - 1], $p[$j]));
        $chksum += $u[1];
        if ($chksum > 0xFFFF) $chksum -= 0x10000;
        $i -= 2;
        $j += 2;
    }
    if ($i) $chksum = $chksum + $p[$l - 1];

    while ($chksum > 0xFFFF) $chksum -= 0x10000;
    if ($chksum > 0x7FFF) $chksum -= 0x10000;
    $chksum = ~$chksum;
    while ($chksum < 0) $chksum += 0x10000;

    return pack('v', $chksum);
}

function zkSend($fp, $command, $sessionId, $replyId, $data = '')
{
    $buf = createHeader($command, 0, $sessionId, $replyId, $data);

    // Calculate and set checksum
    $bytes = unpack('C*', $buf);
    $bytes = array_values($bytes);
    $chksum = calcChecksum($bytes);
    $buf = substr_replace($buf, $chksum, 2, 2);

    // TCP wrapper: 0x5050827D + length(4 bytes LE)
    $tcpHeader = pack('C4', 0x50, 0x50, 0x82, 0x7D) . pack('V', strlen($buf));
    fwrite($fp, $tcpHeader . $buf);

    // Read response
    $resp = '';
    $hdr = fread($fp, 8);
    if (!$hdr || strlen($hdr) < 8) return false;

    $tcpHdr = unpack('C4tag/Vlen', $hdr);
    $bodyLen = $tcpHdr['len'];

    $body = '';
    while (strlen($body) < $bodyLen) {
        $chunk = fread($fp, $bodyLen - strlen($body));
        if (!$chunk) break;
        $body .= $chunk;
    }

    if (strlen($body) < 8) return false;

    $parsed = unpack('vcommand/vchecksum/vsession/vreply', $body);
    return [
        'command' => $parsed['command'],
        'session' => $parsed['session'],
        'reply'   => $parsed['reply'],
        'data'    => substr($body, 8),
    ];
}

// CMD_CONNECT = 1000
echo "2. Sending connect (commKey={$commKey})...\n";
$commKeyData = ($commKey > 0) ? pack('V', $commKey) : '';
$result = zkSend($fp, 1000, 0, 0, $commKeyData);

if (!$result) {
    echo "   No response.\n";
    fclose($fp);
    exit(1);
}

echo "   Response: cmd={$result['command']} (2000=OK, 2001=ERR, 65535=UNAUTH)\n";

if ($result['command'] == 65535 || $result['command'] == 2001) {
    echo "   Device requires a CommKey! Trying common keys...\n";
    fclose($fp);

    foreach ([123456, 1, 12345, 888888, 999999, 888] as $key) {
        echo "   Trying commKey={$key}...\n";
        $fp = @stream_socket_client("tcp://{$deviceIp}:{$devicePort}", $errno, $errstr, $timeout);
        if (!$fp) continue;
        stream_set_timeout($fp, $timeout);

        $result = zkSend($fp, 1000, 0, 0, pack('V', $key));
        if ($result && $result['command'] == 2000) {
            echo "   SUCCESS with commKey={$key}!\n";
            $commKey = $key;
            break;
        }
        fclose($fp);
        $result = null;
    }

    if (!$result || $result['command'] != 2000) {
        echo "   Could not authenticate. Please check the CommKey on the device.\n";
        exit(1);
    }
}

if ($result['command'] != 2000) {
    echo "   Connection failed: cmd={$result['command']}\n";
    fclose($fp);
    exit(1);
}

$sessionId = $result['session'];
$replyId = 1;
echo "   Connected! Session: {$sessionId}\n";

// Get Serial Number
echo "\n3. Getting device info...\n";
$r = zkSend($fp, 11, $sessionId, $replyId++, "~SerialNumber\0");
if ($r && $r['command'] == 2000) {
    echo "   SN: " . rtrim(str_replace("~SerialNumber=", "", $r['data']), "\0") . "\n";
}

$r = zkSend($fp, 11, $sessionId, $replyId++, "~DeviceName\0");
if ($r && $r['command'] == 2000) {
    echo "   Name: " . rtrim(str_replace("~DeviceName=", "", $r['data']), "\0") . "\n";
}

$r = zkSend($fp, 11, $sessionId, $replyId++, "~Platform\0");
if ($r && $r['command'] == 2000) {
    echo "   Platform: " . rtrim(str_replace("~Platform=", "", $r['data']), "\0") . "\n";
}

// Get Cloud Server settings from device
echo "\n4. Getting ADMS/Cloud settings from device...\n";
foreach (['CloudServerURL', 'ServerAddress', 'ServerPort', 'PushProtocol', 'TransInterval', 'TransFlag'] as $key) {
    $r = zkSend($fp, 11, $sessionId, $replyId++, "{$key}\0");
    if ($r && $r['command'] == 2000) {
        echo "   {$key}: " . rtrim($r['data'], "\0") . "\n";
    } else {
        echo "   {$key}: (not readable)\n";
    }
}

// Get record counts
echo "\n5. Getting attendance count...\n";
$r = zkSend($fp, 75, $sessionId, $replyId++);
if ($r && $r['command'] == 2000 && strlen($r['data']) >= 80) {
    $sizes = unpack('V*', $r['data']);
    echo "   Raw sizes: " . json_encode(array_slice($sizes, 0, 20, true)) . "\n";
}

// Request attendance log
echo "\n6. Requesting attendance records...\n";
// Disable device temporarily
zkSend($fp, 1003, $sessionId, $replyId++, "\x00\x00");

// CMD_DATA_WRRQ (13) — request attendance log
$r = zkSend($fp, 13, $sessionId, $replyId++, "\x01\x0d\x00\x00\x00\x00\x00\x00\x00\x00\x00");
if ($r) {
    echo "   Response: cmd={$r['command']}\n";

    if ($r['command'] == 2002) {
        $dataSize = unpack('V', substr($r['data'], 1, 4))[1] ?? 0;
        echo "   Expected: {$dataSize} bytes\n";

        $allData = '';
        while (strlen($allData) < $dataSize) {
            $hdr = fread($fp, 8);
            if (!$hdr || strlen($hdr) < 8) break;
            $tcpH = unpack('C4tag/Vlen', $hdr);
            $body = '';
            while (strlen($body) < $tcpH['len']) {
                $c = fread($fp, $tcpH['len'] - strlen($body));
                if (!$c) break;
                $body .= $c;
            }

            $cmd = unpack('v', substr($body, 0, 2))[1];
            if ($cmd == 2005) {
                echo "   End-of-data received.\n";
                break;
            }
            $allData .= substr($body, 8);
        }

        echo "   Received: " . strlen($allData) . " bytes\n";

        // Parse records
        if (strlen($allData) > 0) {
            // Try to detect record size
            foreach ([40, 56, 32, 44] as $recSize) {
                $n = (int)(strlen($allData) / $recSize);
                if ($n > 0 && $n < 100000) {
                    echo "\n   Trying record size {$recSize}: {$n} records\n";
                    $epoch2000 = mktime(0, 0, 0, 1, 1, 2000);

                    // Show first record hex for debugging
                    echo "   First record hex: " . bin2hex(substr($allData, 0, $recSize)) . "\n";

                    // Try to parse last 10
                    $lastN = min($n, 10);
                    $start = max(0, $n - $lastN);
                    for ($i = $start; $i < $n; $i++) {
                        $rec = substr($allData, $i * $recSize, $recSize);
                        $uid = unpack('v', substr($rec, 0, 2))[1];

                        // Try multiple timestamp offsets
                        foreach ([24, 28, 20, 4] as $tsOff) {
                            if ($tsOff + 4 <= $recSize) {
                                $ts = unpack('V', substr($rec, $tsOff, 4))[1];
                                $dt = date('Y-m-d H:i:s', $epoch2000 + $ts);
                                $yr = (int)date('Y', $epoch2000 + $ts);
                                if ($yr >= 2020 && $yr <= 2030) {
                                    echo "   [{$i}] UID={$uid} Time={$dt} (offset={$tsOff})\n";
                                    break;
                                }
                            }
                        }
                    }
                    break; // Use first working record size
                }
            }
        }
    } elseif ($r['command'] == 2000) {
        echo "   No records or data inline: " . strlen($r['data']) . " bytes\n";
        if (strlen($r['data']) > 0) {
            echo "   Hex: " . bin2hex(substr($r['data'], 0, 100)) . "\n";
        }
    }
}

// Re-enable & disconnect
echo "\n7. Cleaning up...\n";
zkSend($fp, 1003, $sessionId, $replyId++, "\x01\x00");
zkSend($fp, 1001, $sessionId, $replyId++);
fclose($fp);

echo "\n=== Done ===\n";
