<?php

use App\Enums\TransactionStatus;
use App\Jobs\ScrapeNfceInvoiceJob;
use App\Models\FinancialTransaction;
use App\Models\User;
use App\Notifications\GeneralNotification;
use App\Services\Nfce\Exceptions\NfceInvoiceParsingException;
use App\Services\Nfce\Exceptions\NfcePortalUnavailableException;
use App\Services\Nfce\NfceScraperResolver;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Http::preventStrayRequests();
    Notification::fake();
});

test('it creates a draft transaction and notifies the requester after importing an NFC-e', function () {
    $requester = User::factory()->create();
    Http::fake([
        'dfe-portal.svrs.rs.gov.br/*' => Http::response(jobNfceFixture()),
    ]);

    $job = nfceImportJob($requester);
    $job->handle(app(NfceScraperResolver::class));

    $transaction = FinancialTransaction::query()->sole();

    expect($transaction->type)->toBe('expense')
        ->and($transaction->status)->toBe(TransactionStatus::Draft)
        ->and($transaction->amount)->toBe('125.69')
        ->and($transaction->description)->toBe('EMPRESA FICTICIA DE TESTE LTDA')
        ->and($transaction->date->toDateString())->toBe('2026-07-14')
        ->and($transaction->financial_account_id)->toBeNull()
        ->and($transaction->financial_credit_card_invoice_id)->toBeNull()
        ->and($transaction->nfce_access_key)->toBe('43111111111111111111111111111111111111111111')
        ->and($transaction->nfce_issuer_document)->toBe('12345678000195')
        ->and($transaction->nfce_source_url)->toBe(jobNfceRequestUrl())
        ->and($transaction->nfceSourceUrl())->toBe(jobNfceRequestUrl())
        ->and($transaction->items)->toHaveCount(7)
        ->and($transaction->items[2]->total)->toBe('5.61')
        ->and($transaction->items[6]->description)->toBe('Desconto da NFC-e')
        ->and($transaction->items[6]->total)->toBe('-16.29');

    $expectedNotificationItems = [
        [
            'description' => 'ERVA MATE TRADICIONAL 1KG',
            'quantity' => '2',
            'unit_price' => formatCurrency(14.59),
            'total' => formatCurrency(29.18),
        ],
        [
            'description' => 'ARROZ PARBOILIZADO 1KG',
            'quantity' => '1',
            'unit_price' => formatCurrency(8.79),
            'total' => formatCurrency(8.79),
        ],
        [
            'description' => 'PAO FRANCES KG',
            'quantity' => '0,416',
            'unit_price' => formatCurrency(13.49),
            'total' => formatCurrency(5.61),
        ],
        [
            'description' => 'FILE MIGNON SUINO KG',
            'quantity' => '3',
            'unit_price' => formatCurrency(8.49),
            'total' => formatCurrency(25.47),
        ],
        [
            'description' => 'OVOS VERMELHOS DUZIA',
            'quantity' => '2',
            'unit_price' => formatCurrency(23.99),
            'total' => formatCurrency(47.98),
        ],
        [
            'description' => 'REFRIGERANTE 2L',
            'quantity' => '5',
            'unit_price' => formatCurrency(4.99),
            'total' => formatCurrency(24.95),
        ],
        [
            'description' => 'Desconto da NFC-e',
            'quantity' => '1',
            'unit_price' => formatCurrency(-16.29),
            'total' => formatCurrency(-16.29),
        ],
    ];

    Http::assertSent(fn (Request $request): bool => $request->url() === jobNfceRequestUrl());
    Notification::assertSentTo($requester, GeneralNotification::class, function (GeneralNotification $notification) use ($expectedNotificationItems, $transaction): bool {
        return $notification->title === 'NFC-e importada com sucesso'
            && $notification->actionUrl === route('financial.transactions.edit', $transaction)
            && $notification->items === $expectedNotificationItems
            && $notification->channels === ['database', 'telegram', 'mail'];
    });
});

test('it prevents concurrent jobs for the same NFC-e without serializing its complete URL', function () {
    $job = nfceImportJob(User::factory()->create());

    expect($job)->toBeInstanceOf(ShouldBeUnique::class)
        ->and($job->uniqueId())->toBe('43111111111111111111111111111111111111111111')
        ->and($job->timeout)->toBe(90)
        ->and($job->tries)->toBe(3)
        ->and($job->backoff)->toBe([5, 15, 30])
        ->and(config('services.nfce.http.timeout'))->toBe(15)
        ->and(config('services.nfce.http.connect_timeout'))->toBe(5)
        ->and(config('queue.connections.database.retry_after'))->toBe(120)
        ->and(serialize($job))->not->toContain(jobNfceRequestUrl());
});

test('it does not duplicate an NFC-e that was already imported, including a trashed transaction', function () {
    $requester = User::factory()->create();
    $existingTransaction = FinancialTransaction::factory()
        ->nfce('43111111111111111111111111111111111111111111')
        ->trashed()
        ->create();

    nfceImportJob($requester)->handle(app(NfceScraperResolver::class));

    expect(FinancialTransaction::withTrashed()->count())->toBe(1);
    $this->assertModelExists($existingTransaction);
    Notification::assertNothingSent();
});

test('it revalidates duplicate NFC-e keys after another import completes', function () {
    $requester = User::factory()->create();
    Http::fake([
        'dfe-portal.svrs.rs.gov.br/*' => Http::response(jobNfceFixture()),
    ]);

    nfceImportJob($requester)->handle(app(NfceScraperResolver::class));
    nfceImportJob($requester)->handle(app(NfceScraperResolver::class));

    expect(FinancialTransaction::query()->count())->toBe(1);
    Http::assertSentCount(1);
    Notification::assertSentToTimes($requester, GeneralNotification::class, 1);
});

test('it notifies the requester when the portal remains unavailable', function () {
    $requester = User::factory()->create();
    $job = nfceImportJob($requester);

    $job->failed(new NfcePortalUnavailableException('Portal unavailable.'));

    Notification::assertSentTo($requester, GeneralNotification::class, function (GeneralNotification $notification): bool {
        return $notification->title === 'Falha ao importar NFC-e'
            && $notification->message === 'Não foi possível consultar o portal da NFC-e. Tente novamente mais tarde.'
            && $notification->actionLabel === 'Tentar novamente'
            && $notification->actionUrl !== null
            && str_contains($notification->actionUrl, 'signature=')
            && ! str_contains($notification->actionUrl, '43111111111111111111111111111111111111111111')
            && $notification->details === [
                'Portal' => 'svrs',
                'UF' => 'RS',
                'Chave de acesso' => '43111111111111111111111111111111111111111111',
            ]
            && $notification->channels === ['database', 'telegram'];
    });
});

test('it leaves the database unchanged when invoice parsing fails', function () {
    $requester = User::factory()->create();
    Http::fake([
        'dfe-portal.svrs.rs.gov.br/*' => Http::response('<html><body>invalid</body></html>'),
    ]);

    $job = nfceImportJob($requester);

    try {
        $job->handle(app(NfceScraperResolver::class));
    } catch (NfceInvoiceParsingException $exception) {
        $job->failed($exception);
    }

    expect(FinancialTransaction::query()->exists())->toBeFalse();
    Notification::assertSentTo($requester, GeneralNotification::class, function (GeneralNotification $notification): bool {
        return $notification->message === 'Não foi possível interpretar os dados retornados pelo portal da NFC-e. O portal pode estar fora do padrão esperado ou ainda não ter suporte.';
    });
});

function nfceImportJob(User $requester): ScrapeNfceInvoiceJob
{
    return new ScrapeNfceInvoiceJob(
        requesterId: $requester->id,
        provider: 'svrs',
        accessKey: '43111111111111111111111111111111111111111111',
        uf: 'RS',
        sourceEndpoint: 'https://dfe-portal.svrs.rs.gov.br/Dfe/QrCodeNFce',
        requestParameterSuffix: '|3|1',
    );
}

function jobNfceRequestUrl(): string
{
    return 'https://dfe-portal.svrs.rs.gov.br/Dfe/QrCodeNFce?p=43111111111111111111111111111111111111111111%7C3%7C1';
}

function jobNfceFixture(): string
{
    return (string) file_get_contents(base_path('tests/Fixtures/Nfce/svrs-invoice.html'));
}
