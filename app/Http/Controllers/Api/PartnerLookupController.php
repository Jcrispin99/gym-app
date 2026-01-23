<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ApiPeruService;
use Illuminate\Http\Request;

class PartnerLookupController extends Controller
{
    public function lookup(Request $request, ApiPeruService $api)
    {
        $validated = $request->validate([
            'document_number' => ['required', 'string', 'max:20'],
        ]);

        $doc = trim($validated['document_number']);
        $docType = strlen($doc) === 8 ? 'dni' : (strlen($doc) === 11 ? 'ruc' : null);
        if (!$docType) {
            return response()->json(['message' => 'Documento inválido. Use DNI (8) o RUC (11).'], 422);
        }

        try {
            $raw = $api->lookup($docType, $doc);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }

        // Soportar ambas formas de respuesta (ApiPeru: data[], APIs Perú: top-level)
        $payload = $raw['data'] ?? $raw;

        $name = '';
        $address = null;
        $documentType = '';

        if ($docType === 'dni') {
            $name = trim(($payload['nombres'] ?? '') . ' ' . ($payload['apellido_paterno'] ?? $payload['apellidoPaterno'] ?? '') . ' ' . ($payload['apellido_materno'] ?? $payload['apellidoMaterno'] ?? ''));
            $address = $payload['direccion'] ?? null;
            $documentType = 'DNI';
        } else { // ruc
            $name = $payload['nombre_o_razon_social'] ?? ($payload['razonSocial'] ?? ($payload['nombre'] ?? ''));
            $address = $payload['direccion'] ?? ($payload['direccion_completa'] ?? null);
            $documentType = 'RUC';
        }

        return response()->json([
            'document_type' => $documentType,
            'document_number' => $doc,
            'name' => $name,
            'address' => $address,
            // Additional helpful fields if needed
            'first_name' => $payload['nombres'] ?? null,
            'last_name' => trim(($payload['apellido_paterno'] ?? $payload['apellidoPaterno'] ?? '') . ' ' . ($payload['apellido_materno'] ?? $payload['apellidoMaterno'] ?? '')),
            'business_name' => $docType === 'ruc' ? $name : null,
        ]);
    }
}
