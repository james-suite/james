<?php

namespace App\Jobs;

use App\Enums\NotificationLevel;
use App\Enums\TransactionStatus;
use App\Models\FinancialTransaction;
use App\Models\FinancialTransactionItem;
use App\Models\User;
use App\Notifications\GeneralNotification;
use App\Services\Nfce\Data\NfceInvoiceData;
use App\Services\Nfce\Data\NfceInvoiceItemData;
use App\Services\Nfce\Data\NfceSource;
use App\Services\Nfce\Exceptions\NfceInvoiceParsingException;
use App\Services\Nfce\Exceptions\NfcePortalUnavailableException;
use App\Services\Nfce\NfceScraperResolver;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Uri;
use Throwable;

class ScrapeNfceInvoiceJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 90;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [5, 15, 30];

    /**
     * @param  int  $requesterId  The requester receives import notifications.
     * @param  string  $requestParameterSuffix  The non-sensitive part of the NFC-e request parameter.
     */
    public function __construct(
        public int $requesterId,
        public string $provider,
        public string $accessKey,
        public ?string $uf,
        public string $sourceEndpoint,
        public string $requestParameterSuffix,
    ) {}

    public function uniqueId(): string
    {
        return $this->accessKey;
    }

    public function handle(NfceScraperResolver $scraperResolver): void
    {
        if ($this->transactionAlreadyExists()) {
            return;
        }

        $source = $this->source();
        $invoice = $scraperResolver->resolve($source)->scrape($source);

        try {
            $transaction = DB::transaction(function () use ($invoice, $source): ?FinancialTransaction {
                if ($this->transactionAlreadyExists()) {
                    return null;
                }

                $transaction = FinancialTransaction::query()->create([
                    'type' => 'expense',
                    'amount' => $invoice->totalAmount,
                    'description' => $invoice->issuer,
                    'date' => $invoice->issuedAt->toDateString(),
                    'status' => TransactionStatus::Draft,
                    'nfce_access_key' => $this->accessKey,
                    'nfce_issuer_document' => $invoice->issuerDocument,
                    'nfce_source_url' => $source->requestUrl,
                ]);

                $transaction->setRelation('items', $transaction->items()->createMany($this->itemsFor($invoice)));

                return $transaction;
            });
        } catch (QueryException $exception) {
            if ($this->isNfceAccessKeyCollision($exception)) {
                return;
            }

            throw $exception;
        }

        if ($transaction === null) {
            return;
        }

        $this->notifySuccess($transaction, $invoice);
    }

    public function failed(?Throwable $exception): void
    {
        $requester = User::query()->find($this->requesterId);

        if ($requester === null) {
            return;
        }

        $requester->notify(new GeneralNotification(
            title: 'Falha ao importar NFC-e',
            message: $this->failureMessage($exception),
            actionUrl: $this->retryUrl(),
            level: NotificationLevel::Danger,
            details: [
                'Portal' => $this->provider,
                'UF' => $this->uf ?? 'Não identificada',
                'Chave de acesso' => $this->accessKey,
            ],
            channels: ['database', 'telegram'],
            actionLabel: 'Tentar novamente',
        ));
    }

    private function transactionAlreadyExists(): bool
    {
        return FinancialTransaction::withTrashed()
            ->where('nfce_access_key', $this->accessKey)
            ->exists();
    }

    private function source(): NfceSource
    {
        $requestUrl = (string) Uri::of($this->sourceEndpoint)
            ->withQuery(['p' => $this->accessKey.$this->requestParameterSuffix], merge: false);

        return new NfceSource(
            requestUrl: $requestUrl,
            provider: $this->provider,
            accessKey: $this->accessKey,
            uf: $this->uf,
            sourceEndpoint: $this->sourceEndpoint,
            requestParameterSuffix: $this->requestParameterSuffix,
        );
    }

    /**
     * @return list<array{description: string, quantity: string, unit_price: string, total: float}>
     */
    private function itemsFor(NfceInvoiceData $invoice): array
    {
        return array_map(
            fn (NfceInvoiceItemData $item): array => [
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_price' => $item->unitPrice,
                'total' => round((float) $item->quantity * (float) $item->unitPrice, 2),
            ],
            $invoice->items,
        );
    }

    private function notifySuccess(FinancialTransaction $transaction, NfceInvoiceData $invoice): void
    {
        $requester = User::query()->find($this->requesterId);

        if ($requester === null) {
            return;
        }

        $requester->notify(new GeneralNotification(
            title: 'NFC-e importada com sucesso',
            message: 'A transação foi criada como rascunho e está pronta para revisão.',
            actionUrl: route('financial.transactions.edit', $transaction),
            level: NotificationLevel::Success,
            details: [
                'Emitente' => $invoice->issuer,
                'Valor' => formatCurrency((float) $transaction->amount),
                'Emissão' => $invoice->issuedAt->format('d/m/Y H:i'),
            ],
            channels: ['database', 'telegram'],
            items: $this->notificationItems($transaction),
        ));
    }

    /**
     * @return list<array{description: string, quantity: string, unit_price: string, total: string}>
     */
    private function notificationItems(FinancialTransaction $transaction): array
    {
        return $transaction->items
            ->map(fn (FinancialTransactionItem $item): array => [
                'description' => $item->description,
                'quantity' => $this->formatQuantity($item->quantity),
                'unit_price' => formatCurrency((float) $item->unit_price),
                'total' => formatCurrency((float) $item->total),
            ])
            ->values()
            ->all();
    }

    private function formatQuantity(string $quantity): string
    {
        return rtrim(rtrim(number_format((float) $quantity, 3, ',', '.'), '0'), ',');
    }

    private function isNfceAccessKeyCollision(QueryException $exception): bool
    {
        return in_array((string) $exception->getCode(), ['23000', '23505'], true)
            && str_contains($exception->getMessage(), 'nfce_access_key');
    }

    private function failureMessage(?Throwable $exception): string
    {
        return match (true) {
            $exception instanceof NfcePortalUnavailableException => 'Não foi possível consultar o portal da NFC-e. Tente novamente mais tarde.',
            $exception instanceof NfceInvoiceParsingException => 'Não foi possível interpretar os dados retornados pelo portal da NFC-e. O portal pode estar fora do padrão esperado ou ainda não ter suporte.',
            default => 'A importação da NFC-e não pôde ser concluída.',
        };
    }

    private function retryUrl(): string
    {
        return URL::signedRoute('financial.transactions.nfce.retry', [
            'payload' => Crypt::encrypt([
                'requester_id' => $this->requesterId,
                'provider' => $this->provider,
                'access_key' => $this->accessKey,
                'uf' => $this->uf,
                'source_endpoint' => $this->sourceEndpoint,
                'request_parameter_suffix' => $this->requestParameterSuffix,
            ]),
        ]);
    }
}
