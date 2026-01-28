<?php

use App\Models\PosSession;
use App\Models\Sale;
use App\Services\SaleRefundService;
use Spatie\Activitylog\Models\Activity;

function refundInspect(): void
{
    $origin = Sale::query()
        ->with(['journal', 'products'])
        ->where('status', 'posted')
        ->whereHas('journal', function ($q) {
            $q->whereIn('document_type_code', ['01', '03']);
        })
        ->latest('id')
        ->first();

    if (! $origin) {
        echo "No hay venta origen 01/03 posted.\n";
        return;
    }

    $svc = new SaleRefundService();

    echo "Origen:\n";
    echo "- id={$origin->id}\n";
    echo "- doc={$origin->document_number}\n";
    echo "- doc_type=".(string) ($origin->journal?->document_type_code ?? '')."\n";
    echo "- journal_code=".(string) ($origin->journal?->code ?? '')."\n";
    echo "- total=".(string) $origin->total."\n";

    $available = $svc->availableQtyByProduct($origin);
    echo "Disponible por producto: ".json_encode($available)."\n";

    $journal = $svc->resolveCreditNoteJournalForCompany(
        (int) $origin->company_id,
        (string) ($origin->journal?->document_type_code ?? ''),
        (string) ($origin->journal?->code ?? '')
    );
    echo "Journal NC sugerido (company): ".(string) ($journal?->code ?? 'NULL')."\n";

    echo "Ultimas activities del origen:\n";
    $acts = Activity::forSubject($origin)->with('causer')->latest()->take(10)->get();
    foreach ($acts as $a) {
        $who = $a->causer?->name ?? 'null';
        echo "- {$a->created_at} {$a->description} (causer={$who})\n";
    }

    $lastRefund = Activity::query()
        ->where('description', 'Devolución POS creada')
        ->with('causer')
        ->latest()
        ->first();

    if (! $lastRefund) {
        echo "No existe Activity: Devolución POS creada (aún).\n";
        return;
    }

    $who = $lastRefund->causer?->name ?? 'null';
    echo "Ultima Devolución POS creada:\n";
    echo "- activity_id={$lastRefund->id}\n";
    echo "- causer={$who}\n";
    echo "- subject={$lastRefund->subject_type}#{$lastRefund->subject_id}\n";
    echo "- props=".json_encode($lastRefund->properties)."\n";

    $posSessionId = (int) (data_get($lastRefund->properties, 'pos_session_id') ?? 0);
    if ($posSessionId > 0) {
        $session = PosSession::query()->with('posConfig')->find($posSessionId);
        if ($session) {
            echo "POS Session:\n";
            echo "- id={$session->id}\n";
            echo "- status={$session->status}\n";
            echo "- pos_config_id={$session->pos_config_id}\n";
        }
    }
}

