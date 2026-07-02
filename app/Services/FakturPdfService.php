<?php

namespace App\Services;

use Barryvdh\DomPDF\PDF;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FakturPdfService
{
    public const DISK = 'b2';

    public const DIR_PDF = 'faktur/documents';

    public const DIR_LOGO = 'faktur/logos';

    public function paperConfig(string $size): array
    {
        return match ($size) {
            'half_a4' => [[0, 0, 419.53, 595.28], 'portrait'],
            'third_a4' => [[0, 0, 595.28, 280.63], 'portrait'],
            default => ['a4', 'portrait'],
        };
    }

    /**
     * Upload logo (opsional) ke B2. Return path atau null.
     * B2 menolak canned ACL 'private' → wajib visibilitas 'public'.
     */
    public function storeLogo(?UploadedFile $logo): ?string
    {
        if (! $logo) {
            return null;
        }

        $filename = time().'_logo_'.Str::slug(pathinfo($logo->getClientOriginalName(), PATHINFO_FILENAME)).'.'.$logo->getClientOriginalExtension();

        return $logo->storePubliclyAs(self::DIR_LOGO, $filename, self::DISK);
    }

    public function formatRupiah(float $nominal): string
    {
        return 'Rp '.number_format($nominal, 0, ',', '.');
    }

    /**
     * @param  array{name: string, nominal: float, items: array, terbilang: string, memo: ?string, paper_size: string, logo_path: ?string, nomor_faktur: string}  $data
     * @return array{pdf_path: string, preview: string}
     */
    public function generate(array $data): array
    {
        [$paper, $orientation] = $this->paperConfig($data['paper_size']);

        $logoDataUri = null;
        if ($data['logo_path']) {
            $storage = Storage::disk(self::DISK);
            $logoDataUri = 'data:'.$storage->mimeType($data['logo_path']).';base64,'.base64_encode($storage->get($data['logo_path']));
        }

        /** @var PDF $pdf */
        $pdf = app('dompdf.wrapper')->loadView('pdf.faktur', [
            'nama' => $data['name'],
            'nominal' => $this->formatRupiah($data['nominal']),
            'items' => $data['items'],
            'terbilang' => $data['terbilang'],
            'memo' => $data['memo'] ?? null,
            'logoDataUri' => $logoDataUri,
            'tanggal' => now()->translatedFormat('d F Y'),
            'nomorFaktur' => $data['nomor_faktur'],
            'paperSize' => $data['paper_size'],
        ]);

        $pdf->setPaper($paper, $orientation);

        $filename = $data['nomor_faktur'].'_'.Str::slug($data['name']).'.pdf';
        $path = self::DIR_PDF.'/'.$filename;

        /** @var FilesystemAdapter $storage */
        $storage = Storage::disk(self::DISK);
        // B2 menolak canned ACL 'private' → tulis dengan visibilitas 'public'.
        if (! $storage->put($path, $pdf->output(), 'public')) {
            throw new \RuntimeException('Gagal menyimpan PDF ke B2 (disk write failed).');
        }

        return [
            'pdf_path' => $path,
            'preview' => 'data:application/pdf;base64,'.base64_encode($pdf->output()),
        ];
    }
}
