<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PrinterController extends Controller
{
    /**
     * POST /api/printer
     *
     * Body: { "ip": "192.168.1.100", "port": 9100, "bytes": [27, 64, ...] }
     *
     * Which printer (receipt / kitchen) is determined on the Vue side
     * by which localStorage key was read — no need to send it here.
     */
    public function print(Request $request)
    {
        $request->validate([
            'ip'    => ['required', 'string'],
            'port'  => ['required', 'integer', 'min:1', 'max:65535'],
            'bytes' => ['required', 'array'],
        ]);

        $ip   = $request->input('ip');
        $port = (int) $request->input('port');

        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return response()->json(['message' => 'Invalid IP address.'], 422);
        }

        $data = '';
        foreach ($request->input('bytes') as $byte) {
            $data .= chr((int) $byte & 0xFF);
        }

        $socket = @fsockopen($ip, $port, $errno, $errstr, 5);

        if (!$socket) {
            return response()->json([
                'message' => "Cannot connect to {$ip}:{$port} — {$errstr} (#{$errno})"
            ], 502);
        }

        fwrite($socket, $data);
        fclose($socket);

        return response()->json(['message' => "Sent to {$ip}:{$port}"]);
    }
}
