<?php

/**
 * Test direct TCP connection to ZKTeco device
 * and attempt to pull attendance data via ZK protocol.
 */

$deviceIp   = '192.168.100.20';
$devicePort = 4370;

echo "=== ZKTeco Device Connection Test ===\n\n";

// 1. Test basic TCP connectivity
echo "1. Testing TCP connection to {$deviceIp}:{$devicePort}...\n";
$sock = @fsockopen($deviceIp, $devicePort, $errno, $errstr, 5);
if ($sock) {
    echo "   ✓ Port {$devicePort} is OPEN and reachable.\n";
    fclose($sock);
} else {
    echo "   ✗ Port {$devicePort} is CLOSED or unreachable: {$errstr}\n";
    exit(1);
}

// 2. Test HTTP connectivity (some devices also listen on port 80)
echo "\n2. Testing HTTP connection to {$deviceIp}:80...\n";
$sock80 = @fsockopen($deviceIp, 80, $errno, $errstr, 3);
if ($sock80) {
    echo "   ✓ Port 80 is OPEN (device has web interface).\n";
    fclose($sock80);

    // Try to fetch device info page
    $ctx = stream_context_create(['http' => ['timeout' => 5, 'method' => 'GET']]);
    $html = @file_get_contents("http://{$deviceIp}/", false, $ctx);
    if ($html) {
        echo "   Device web page title: ";
        if (preg_match('/<title>(.*?)<\/title>/i', $html, $m)) {
            echo $m[1] . "\n";
        } else {
            echo "(no title)\n";
        }
    }
} else {
    echo "   ✗ Port 80 is CLOSED.\n";
}

// 3. Try ZK UDP protocol (the native protocol uses UDP on port 4370)
echo "\n3. Testing ZK UDP protocol on {$deviceIp}:{$devicePort}...\n";
$udp = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
if ($udp) {
    socket_set_option($udp, SOL_SOCKET, SO_RCVTIMEO, ['sec' => 3, 'usec' => 0]);
    socket_set_option($udp, SOL_SOCKET, SO_SNDTIMEO, ['sec' => 3, 'usec' => 0]);

    // ZK Connect command: command_id=1000 (0x03E8)
    // Protocol header: 0x5050827D + command(2) + checksum(2) + session(2) + reply(2) + data
    $connectCmd = pack('CCCC', 0x50, 0x50, 0x82, 0x7D)  // header
        . pack('v', 1000)                         // command: CMD_CONNECT
        . pack('v', 0)                            // checksum (initial 0)
        . pack('v', 0)                            // session_id
        . pack('v', 0);                           // reply_id

    $sent = @socket_sendto($udp, $connectCmd, strlen($connectCmd), 0, $deviceIp, $devicePort);
    if ($sent) {
        echo "   Sent ZK connect command ({$sent} bytes).\n";
        $buf = '';
        $from = '';
        $port = 0;
        $received = @socket_recvfrom($udp, $buf, 1024, 0, $from, $port);
        if ($received && $received > 0) {
            echo "   ✓ Got response! ({$received} bytes from {$from}:{$port})\n";
            echo "   Raw hex: " . bin2hex(substr($buf, 0, min(32, $received))) . "\n";

            // Parse response - check if command was successful (CMD_ACK_OK = 2000)
            if ($received >= 8) {
                $resp = unpack('C4header/vcommand/vchecksum', $buf);
                echo "   Response command: " . $resp['command'] . " (2000=OK, 2001=ERROR, 2002=DATA)\n";

                if ($resp['command'] == 2000) {
                    echo "   ✓ Device accepted connection! ZK protocol is working.\n";

                    // Try to get device info
                    $sessionId = 0;
                    if ($received >= 10) {
                        $sess = unpack('vsession', substr($buf, 6, 2));
                        $sessionId = $sess['session'];
                        echo "   Session ID: {$sessionId}\n";
                    }
                }
            }
        } else {
            echo "   ✗ No response from device (timeout). Device may not support UDP on this port.\n";
        }
    } else {
        echo "   ✗ Failed to send UDP packet.\n";
    }
    socket_close($udp);
} else {
    echo "   ✗ Cannot create UDP socket (socket extension not loaded?).\n";
}

echo "\n=== Test Complete ===\n";
echo "\nNOTE: The device's ADMS cloud server URL should point to:\n";
echo "  http://192.168.100.55/i/cdata.php\n";
echo "  (Port 80, no 'rbac-erp-api/public' prefix when using 'php artisan serve')\n";
