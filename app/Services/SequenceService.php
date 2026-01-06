<?php

namespace App\Services;

use App\Models\Journal;
use App\Models\Sequence;
use Illuminate\Support\Facades\DB;

class SequenceService
{
    /**
     * Obtiene la siguiente serie y correlativo para un journal específico
     * 
     * Este método es thread-safe usando lockForUpdate() para evitar
     * condiciones de carrera en ambientes concurrentes.
     * 
     * @param int $journalId ID del journal
     * @return array ['serie' => 'COMP', 'correlative' => '00000001']
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public static function getNextParts(int $journalId): array
    {
        return DB::transaction(function () use ($journalId) {
            // Buscar journal con su sequence
            $journal = Journal::with('sequence')->findOrFail($journalId);
            $sequence = $journal->sequence;

            // 🔒 Bloquear la fila de la secuencia para evitar condiciones de carrera
            // Esto es CRÍTICO en producción con múltiples usuarios
            $sequence = Sequence::where('id', $sequence->id)
                ->lockForUpdate()
                ->first();

            $nextNumber = $sequence->next_number;
            
            // Formatear el número correlativo con ceros a la izquierda
            // Ejemplo: si sequence_size=8 y nextNumber=125 → "00000125"
            $correlative = str_pad(
                $nextNumber, 
                $sequence->sequence_size, 
                '0', 
                STR_PAD_LEFT
            );

            // Incrementar el próximo número para la siguiente vez
            $sequence->next_number = $nextNumber + $sequence->step;
            $sequence->save();

            return [
                'serie' => $journal->code,        // Ejemplo: "COMP"
                'correlative' => $correlative,     // Ejemplo: "00000125"
            ];
        });
    }

    /**
     * Obtiene el número completo formateado (serie-correlativo)
     * 
     * @param int $journalId ID del journal
     * @return string Ejemplo: "COMP-00000001"
     */
    public static function getNextNumber(int $journalId): string
    {
        $parts = self::getNextParts($journalId);
        return "{$parts['serie']}-{$parts['correlative']}";
    }

    /**
     * Previsualiza el próximo número SIN incrementar el contador
     * Útil para mostrar al usuario el número que se generará
     * 
     * @param int $journalId ID del journal
     * @return array ['serie' => 'COMP', 'correlative' => '00000125']
     */
    public static function previewNextParts(int $journalId): array
    {
        $journal = Journal::with('sequence')->findOrFail($journalId);
        $sequence = $journal->sequence;

        $correlative = str_pad(
            $sequence->next_number,
            $sequence->sequence_size,
            '0',
            STR_PAD_LEFT
        );

        return [
            'serie' => $journal->code,
            'correlative' => $correlative,
        ];
    }
}
