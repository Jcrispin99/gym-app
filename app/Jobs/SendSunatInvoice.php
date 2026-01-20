<?php

namespace App\Jobs;

use App\Models\Sale;
use App\Services\GreenterInvoiceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendSunatInvoice implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $saleId;

    public function __construct(int $saleId)
    {
        $this->saleId = $saleId;
    }

    public function handle(GreenterInvoiceService $service): void
    {
        $sale = Sale::query()->find($this->saleId);

        if (! $sale) {
            return;
        }

        $service->sendInvoiceFromSale($sale);
    }
}
