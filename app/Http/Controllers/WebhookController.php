<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class WebhookController extends Controller
{
    public function evenHandle(Request $request)
    {
        $headers = json_encode($request->headers->all(), JSON_PRETTY_PRINT);

        $payload = json_encode($request->all(), JSON_PRETTY_PRINT);

        // Monta o conteúdo do log
        $logData = "===== NOVA REQUISIÇÃO RECEBIDA =====\n";
        $logData .= "Horário: " . now() . "\n";
        $logData .= "Headers:\n" . $headers . "\n\n";
        $logData .= "Payload:\n" . $payload . "\n";
        $logData .= "=====================================\n\n";

        Storage::append('logs/webhook.log', $logData);

        return response()->json(['message' => 'Webhook recebido com sucesso']);
    }
}
