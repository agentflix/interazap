<?php

declare(strict_types=1);

namespace Domain\CRM\Services;

use Domain\CRM\Models\CRMProposal;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Generates a simple PDF document for CRM proposals and stores it on public disk.
 */
final class ProposalPdfGeneratorService
{
    /**
     * @return array{path: string, url: string, file_name: string, mime_type: string, file_size: int}
     */
    public function generate(CRMProposal $proposal): array
    {
        $proposal->loadMissing(['items', 'negotiation.contact', 'negotiation.company']);

        $relativePath = sprintf('crm/proposals/%s/%s.pdf', (string) $proposal->tenant_id, (string) $proposal->id);
        $fileName = $this->buildFileName($proposal);
        $binary = $this->buildPdfBinary($proposal);

        Storage::disk('public')->put($relativePath, $binary);

        $diskUrl = (string) Storage::disk('public')->url($relativePath);
        $url = $this->toAbsoluteUrl($diskUrl);

        return [
            'path' => $relativePath,
            'url' => $url,
            'file_name' => $fileName,
            'mime_type' => 'application/pdf',
            'file_size' => strlen($binary),
        ];
    }

    private function buildFileName(CRMProposal $proposal): string
    {
        $slug = Str::slug((string) $proposal->title);
        $number = $proposal->number !== null ? (string) $proposal->number : 'sem-numero';

        return sprintf('proposta-%s-%s.pdf', $number, $slug !== '' ? $slug : 'comercial');
    }

    private function toAbsoluteUrl(string $diskUrl): string
    {
        if (str_starts_with($diskUrl, 'http://') || str_starts_with($diskUrl, 'https://')) {
            return $diskUrl;
        }

        $base = rtrim((string) config('app.url', 'http://localhost:8000'), '/');

        return $base.'/'.ltrim($diskUrl, '/');
    }

    private function buildPdfBinary(CRMProposal $proposal): string
    {
        $lines = $this->buildDocumentLines($proposal);
        $operations = "BT\n/F1 12 Tf\n";

        $initialY = 800;
        $lineHeight = 16;
        foreach ($lines as $index => $line) {
            $y = $initialY - ($index * $lineHeight);
            $operations .= sprintf("1 0 0 1 40 %d Tm\n(%s) Tj\n", $y, $this->escapePdfString($line));
        }

        $operations .= "ET\n";

        return $this->composePdf($operations);
    }

    /**
     * @return list<string>
     */
    private function buildDocumentLines(CRMProposal $proposal): array
    {
        $contactName = (string) ($proposal->negotiation?->contact->name ?? 'Nao informado');
        $companyName = (string) ($proposal->negotiation?->company->name ?? 'Nao informado');
        $validUntil = $proposal->valid_until?->format('d/m/Y') ?? 'Nao informado';
        $proposalNumber = $proposal->number !== null ? (string) $proposal->number : 'N/A';

        $lines = [
            'InteraZap - Proposta Comercial',
            str_repeat('-', 60),
            sprintf('Proposta: #%s', $proposalNumber),
            sprintf('Titulo: %s', (string) $proposal->title),
            sprintf('Cliente: %s', $contactName),
            sprintf('Empresa: %s', $companyName),
            sprintf('Validade: %s', $validUntil),
            '',
            'Itens:',
        ];

        foreach ($proposal->items as $item) {
            $lines[] = sprintf(
                '- %s | Qtd: %d | Unit: %s | Desc: %s | Total: %s',
                (string) $item->name,
                (int) $item->quantity,
                $this->formatBrl((float) $item->unit_price),
                $this->formatBrl((float) ($item->discount ?? 0)),
                $this->formatBrl((float) $item->total),
            );
        }

        $lines[] = str_repeat('-', 60);
        $lines[] = sprintf('Total da proposta: %s', $this->formatBrl((float) $proposal->total));
        $lines[] = sprintf('Gerado em: %s', now()->format('d/m/Y H:i:s'));

        return array_map(fn (string $line): string => $this->toAscii($line), $lines);
    }

    private function composePdf(string $stream): string
    {
        $objects = [
            "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n",
            "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n",
            "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n",
            sprintf("4 0 obj\n<< /Length %d >>\nstream\n%sendstream\nendobj\n", strlen($stream), $stream),
            "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object;
        }

        $xrefPosition = strlen($pdf);
        $count = count($objects) + 1;

        $pdf .= "xref\n0 {$count}\n";
        $pdf .= "0000000000 65535 f \n";

        for ($i = 1; $i < $count; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }

        $pdf .= "trailer\n";
        $pdf .= "<< /Size {$count} /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xrefPosition}\n%%EOF";

        return $pdf;
    }

    private function escapePdfString(string $text): string
    {
        $text = str_replace('\\', '\\\\', $text);
        $text = str_replace('(', '\\(', $text);
        $text = str_replace(')', '\\)', $text);

        return preg_replace('/[\x00-\x1F\x7F]/', ' ', $text) ?? $text;
    }

    private function formatBrl(float $amount): string
    {
        return 'R$'.number_format($amount, 2, ',', '.');
    }

    private function toAscii(string $text): string
    {
        $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);

        return is_string($converted) && $converted !== '' ? $converted : $text;
    }
}
