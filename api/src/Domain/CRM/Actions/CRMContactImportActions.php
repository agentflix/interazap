<?php

declare(strict_types=1);

namespace Domain\CRM\Actions;

use Domain\CRM\DTOs\CRMContactImportDTO;
use Domain\CRM\Models\CRMContact;
use Domain\CRM\Models\CRMContactPhone;
use Domain\Platform\Models\PlatformUazapiInstance;
use Domain\Platform\Services\UazapiGatewayService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Use cases for CRM contact import.
 */
final class CRMContactImportActions
{
    public function __construct(
        private readonly UazapiGatewayService $uazapi,
    ) {}

    /**
     * Process a CSV import with deduplication and uazapi sync.
     *
     * @return array<string, mixed>
     */
    public function process(CRMContactImportDTO $dto): array
    {
        if (! file_exists($dto->filePath)) {
            throw new RuntimeException('Arquivo não encontrado para importação.');
        }

        $instance = $this->resolveInstance($dto->tenantId, $dto->instanceId);
        $token = $instance?->token;

        if (! $token) {
            throw new RuntimeException('Nenhuma instância Uazapi ativa configurada.');
        }

        $summary = [
            'processed' => 0,
            'imported' => 0,
            'skipped' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        $header = [];
        $seen = [];
        $batch = [];
        $batchSize = 200;
        $remoteNumbers = $this->fetchRemoteNumbers($token);

        foreach ($this->readCsvRows($dto->filePath, $dto->delimiter) as $index => $row) {
            $row = array_values($row);
            if ($index === 0 && $dto->hasHeader) {
                $header = $this->normalizeHeader($row);

                continue;
            }

            if ($row === [] || $row === [null]) {
                continue;
            }

            $summary['processed']++;

            $name = $this->resolveColumnValue($row, $header, $dto->mapping['name'] ?? '');
            $number = $this->resolveColumnValue($row, $header, $dto->mapping['number'] ?? '');
            $email = $this->resolveColumnValue($row, $header, $dto->mapping['email'] ?? '');
            $company = $this->resolveColumnValue($row, $header, $dto->mapping['company'] ?? '');

            $normalizedNumber = $this->normalizePhone($number);
            if (! $normalizedNumber) {
                $summary['failed']++;
                $this->pushError($summary, $index + 1, 'Telefone inválido.');

                continue;
            }

            if (isset($seen[$normalizedNumber])) {
                $summary['skipped']++;

                continue;
            }

            if (isset($remoteNumbers[$normalizedNumber])) {
                $summary['skipped']++;
                $seen[$normalizedNumber] = true;

                continue;
            }

            if ($this->existsPhone($dto->tenantId, $normalizedNumber)) {
                $summary['skipped']++;
                $seen[$normalizedNumber] = true;

                continue;
            }

            $seen[$normalizedNumber] = true;
            $batch[] = [
                'name' => $name !== '' ? $name : 'Contato',
                'phone' => $normalizedNumber,
                'email' => $email !== '' ? $email : null,
                'company' => $company !== '' ? $company : null,
            ];

            if (count($batch) >= $batchSize) {
                $this->persistBatch($dto->tenantId, $token, $batch, $summary);
                $batch = [];
            }
        }

        if ($batch !== []) {
            $this->persistBatch($dto->tenantId, $token, $batch, $summary);
        }

        return $summary;
    }

    /**
     * @param  array<int, string|null>  $row
     * @param  list<string>  $header
     */
    private function resolveColumnValue(array $row, array $header, string $mapping): string
    {
        if ($mapping === '') {
            return '';
        }

        if (is_numeric($mapping)) {
            $index = (int) $mapping;

            return isset($row[$index]) ? trim((string) $row[$index]) : '';
        }

        $key = strtolower(trim($mapping));
        $headerIndex = array_search($key, $header, true);
        if ($headerIndex === false) {
            return '';
        }

        return isset($row[$headerIndex]) ? trim((string) $row[$headerIndex]) : '';
    }

    /**
     * @param  array<int, string|null>  $row
     * @return list<string>
     */
    private function normalizeHeader(array $row): array
    {
        return array_values(array_map(
            static fn ($value) => is_string($value) ? strtolower(trim($value)) : '',
            $row
        ));
    }

    /**
     * @return iterable<int, array<int, string|null>>
     */
    private function readCsvRows(string $filePath, string $delimiter): iterable
    {
        $file = new \SplFileObject($filePath);
        $file->setFlags(\SplFileObject::READ_CSV | \SplFileObject::SKIP_EMPTY);
        $file->setCsvControl($delimiter);

        foreach ($file as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            yield $index => array_values($row);
        }
    }

    private function normalizePhone(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        $digits = preg_replace('/[^0-9]/', '', $value);
        if (! is_string($digits)) {
            return null;
        }

        $length = strlen($digits);
        if ($length < 10 || $length > 15) {
            return null;
        }

        return $digits;
    }

    private function existsPhone(string $tenantId, string $phone): bool
    {
        return CRMContactPhone::query()
            ->where('tenant_id', $tenantId)
            ->where('phone_e164', $phone)
            ->whereNull('valid_to')
            ->exists();
    }

    /**
     * @param  array<int, array{name: string, phone: string, email: string|null, company: string|null}>  $batch
     * @param  array{processed:int,imported:int,skipped:int,failed:int,errors:list<array{line?:int,message:string}>}  $summary
     */
    private function persistBatch(string $tenantId, string $token, array $batch, array &$summary): void
    {
        try {
            $payload = array_map(static fn (array $item) => [
                'number' => $item['phone'],
                'name' => $item['name'],
            ], $batch);

            $this->uazapi->syncContactsList($token, $payload);

            DB::transaction(function () use ($tenantId, $batch, &$summary): void {
                $now = now();
                $contactsData = [];
                $phonesData = [];

                foreach ($batch as $item) {
                    $contactId = (string) Str::orderedUuid();
                    $contactsData[] = [
                        'id' => $contactId,
                        'tenant_id' => $tenantId,
                        'name' => $item['name'],
                        'email' => $item['email'],
                        'phone' => $item['phone'],
                        'whatsapp' => $item['phone'],
                        'is_active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    $phonesData[] = [
                        'id' => (string) Str::orderedUuid(),
                        'tenant_id' => $tenantId,
                        'crm_contact_id' => $contactId,
                        'phone_e164' => $item['phone'],
                        'label' => 'mobile',
                        'is_primary' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                CRMContact::insert($contactsData);
                CRMContactPhone::insert($phonesData);

                $summary['imported'] += count($batch);
            });
        } catch (\Throwable $e) {
            $summary['failed'] += count($batch);
            $summary['errors'][] = ['message' => $e->getMessage()];
        }
    }

    private function resolveInstance(string $tenantId, ?string $instanceId): ?PlatformUazapiInstance
    {
        if ($instanceId) {
            return PlatformUazapiInstance::query()
                ->where('tenant_id', $tenantId)
                ->where('id', $instanceId)
                ->first();
        }

        return PlatformUazapiInstance::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'connected')
            ->orderByDesc('last_status_at')
            ->first();
    }

    /**
     * @return array<string, true>
     */
    private function fetchRemoteNumbers(string $token): array
    {
        try {
            $response = $this->uazapi->listContacts($token);
        } catch (\Throwable $e) {
            // Log API failures for debugging (OBS-CRIT-002)
            \Illuminate\Support\Facades\Log::warning('CRMContactImport: Failed to fetch remote numbers', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        $items = [];
        if (isset($response['data']) && is_array($response['data'])) {
            $items = $response['data'];
        } elseif (isset($response['contacts']) && is_array($response['contacts'])) {
            $items = $response['contacts'];
        } elseif (array_is_list($response)) {
            $items = $response;
        }

        $numbers = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $candidate = Arr::get($item, 'number')
                ?? Arr::get($item, 'phone')
                ?? Arr::get($item, 'jid')
                ?? Arr::get($item, 'id');

            if (! is_string($candidate)) {
                continue;
            }

            $normalized = $this->normalizePhone($candidate);
            if ($normalized) {
                $numbers[$normalized] = true;
            }
        }

        return $numbers;
    }

    /**
     * @param  array{processed:int,imported:int,skipped:int,failed:int,errors:list<array{line?:int,message:string}>}  $summary
     */
    private function pushError(array &$summary, int $line, string $message): void
    {
        if (count($summary['errors']) >= 20) {
            return;
        }

        $summary['errors'][] = [
            'line' => $line,
            'message' => $message,
        ];
    }
}
