<?php

namespace App\Services;

use App\Models\Transaction;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Mike42\Escpos\PrintConnectors\CupsPrintConnector;
use Mike42\Escpos\PrintConnectors\FilePrintConnector;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\PrintConnectors\PrintConnector;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Mike42\Escpos\Printer;

/**
 * Mencetak struk transaksi ke printer thermal ESC/POS dari backend PHP
 * (tanpa dialog cetak browser).
 */
class ReceiptPrinter
{
    /**
     * Cetak otomatis setelah transaksi lunas. 
     * @return array{printed: bool, message: string}|null  null bila auto print dimatikan
     */
    public function autoPrint(Transaction $transaction): ?array
    {
        if (! config('printer.auto_print')) {
            return null;
        }

        try {
            $this->printTransaction($transaction);

            return ['printed' => true, 'message' => 'Struk dicetak otomatis ke printer thermal.'];
        } catch (\Throwable $e) {
            report($e);

            return ['printed' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Cetak satu transaksi. Melempar exception bila printer tidak bisa diakses.
     */
    public function printTransaction(Transaction $transaction): void
    {
        $transaction->loadMissing(['details', 'user']);

        $lines = $this->buildLines($transaction);
        $connector = $this->connector($transaction->transaction_number);

        $printer = new Printer($connector);

        try {
            foreach ($lines as $line) {
                $printer->setJustification($line['align'] === 'center' ? Printer::JUSTIFY_CENTER : Printer::JUSTIFY_LEFT);
                $printer->setEmphasis($line['bold']);
                $printer->setTextSize($line['size'], $line['size']);
                $printer->text($line['text']."\n");
            }

            $printer->setTextSize(1, 1);
            $printer->setEmphasis(false);
            $printer->setJustification(Printer::JUSTIFY_CENTER);

            if (config('printer.qr')) {
                $printer->feed();
                $printer->qrCode($transaction->transaction_number, Printer::QR_ECLEVEL_M, 6);
            }

            $printer->feed(3);
            $printer->cut();
        } finally {
            $printer->close();
        }

        $this->writePreview($transaction->transaction_number, $lines);
    }

    /**
     * Buat connector sesuai PRINTER_DRIVER di .env.
     */
    public function connector(string $name = 'receipt'): PrintConnector
    {
        $driver = config('printer.driver');

        return match ($driver) {
            'windows' => new WindowsPrintConnector(config('printer.name')),
            'cups' => new CupsPrintConnector(config('printer.name')),
            'network' => new NetworkPrintConnector(config('printer.host'), config('printer.port')),
            'file' => new FilePrintConnector($this->filePath($name)),
            default => throw new InvalidArgumentException("PRINTER_DRIVER '{$driver}' tidak dikenal (windows, cups, network, file)."),
        };
    }

    /**
     * Susun isi struk menjadi baris-baris teks (dipakai printer dan pratinjau .txt).
     *
     * @return array<int, array{text: string, align: string, bold: bool, size: int}>
     */
    public function buildLines(Transaction $transaction): array
    {
        $w = $this->width();
        $lines = [];

        $add = function (string $text, string $align = 'left', bool $bold = false, int $size = 1) use (&$lines) {
            $lines[] = ['text' => $text, 'align' => $align, 'bold' => $bold, 'size' => $size];
        };
        $rule = fn (string $c = '-') => str_repeat($c, $w);
        $rp = fn ($n) => ($n < 0 ? '-' : '').'Rp'.number_format(abs((int) $n), 0, ',', '.');
        $num = fn ($n) => number_format((int) $n, 0, ',', '.');

        // Header toko
        $store = $this->ascii(config('kasir.store_name'));
        if (mb_strlen($store) * 2 <= $w) {
            $add($store, 'center', true, 2);
        } else {
            $add($store, 'center', true);
        }
        $add($this->ascii(config('kasir.store_address')), 'center');
        $add($this->ascii(config('kasir.store_phone')), 'center');
        $add($rule());

        // Info transaksi
        $add($this->pair('No', $transaction->transaction_number, $w));
        $add($this->pair('Tanggal', $transaction->created_at->format('d/m/Y H:i'), $w));
        $add($this->pair('Kasir', $this->ascii($transaction->user->name ?? '-'), $w));
        $add($this->pair('Pelanggan', $this->ascii($transaction->customer_type ?? 'Umum'), $w));
        $add($rule());

        // Daftar item: nama, lalu "qty x harga" dan subtotal
        foreach ($transaction->details as $item) {
            foreach (explode("\n", wordwrap($this->ascii($item->product_name), $w, "\n", true)) as $part) {
                $add($part);
            }
            $add($this->pair('  '.$item->qty.' x '.$num($item->price), $num($item->subtotal), $w));
        }
        $add($rule());

        // Ringkasan
        $add($this->pair('Subtotal', $num($transaction->subtotal), $w));

        if ($transaction->discount > 0) {
            $label = $transaction->discount_percent > 0
                ? 'Diskon ('.rtrim(rtrim(number_format($transaction->discount_percent, 2, ',', ''), '0'), ',').'%)'
                : 'Diskon';
            $add($this->pair($label, '-'.$num($transaction->discount), $w));
        }
        if ($transaction->tax > 0) {
            $add($this->pair('Pajak', $num($transaction->tax), $w));
        }
        if ($transaction->other_fee > 0) {
            $add($this->pair('Biaya lain', $num($transaction->other_fee), $w));
        }

        $add($this->pair('TOTAL', $rp($transaction->grand_total), $w), 'left', true);
        $add($rule());
        $add($this->pair('Metode', $this->ascii($transaction->payment_method), $w));
        $add($this->pair('Bayar', $rp($transaction->paid_amount), $w));
        $add($this->pair('Kembali', $rp($transaction->change_amount), $w));
        $add($rule());
        $add('Terima kasih!', 'center', true);
        $add('Barang yang sudah dibeli', 'center');
        $add('tidak dapat ditukar/dikembalikan', 'center');

        return $lines;
    }

    private function width(): int
    {
        if (config('printer.chars')) {
            return (int) config('printer.chars');
        }

        return (int) config('kasir.paper_width') === 58 ? 32 : 48;
    }

    /**
     * "Kiri ........ Kanan" selebar kolom.
     */
    private function pair(string $left, string $right, int $width): string
    {
        $space = $width - mb_strlen($left) - mb_strlen($right);

        if ($space < 1) {
            $left = mb_substr($left, 0, max(1, $width - mb_strlen($right) - 1));
            $space = max(1, $width - mb_strlen($left) - mb_strlen($right));
        }

        return $left.str_repeat(' ', $space).$right;
    }

    /**
     * Printer thermal umumnya hanya mendukung ASCII (tanpa karakter seperti "•").
     */
    private function ascii(?string $text): string
    {
        return Str::ascii((string) $text);
    }

    private function filePath(string $name): string
    {
        if ($custom = config('printer.file_path')) {
            return $custom;
        }

        $dir = storage_path('app/receipts');
        File::ensureDirectoryExists($dir);

        return $dir.DIRECTORY_SEPARATOR.Str::slug($name).'.escpos';
    }

    /**
     * Mode simulasi: simpan juga pratinjau teks biasa agar mudah dibaca/di-screenshot untuk laporan.
     */
    private function writePreview(string $name, array $lines): void
    {
        if (config('printer.driver') !== 'file' || config('printer.file_path')) {
            return;
        }

        $w = $this->width();
        $text = collect($lines)->map(function ($l) use ($w) {
            $t = $l['text'];
            if ($l['align'] === 'center') {
                $pad = max(0, intdiv($w - mb_strlen($t), 2));
                $t = str_repeat(' ', $pad).$t;
            }

            return $t;
        })->implode("\n");

        File::put(storage_path('app/receipts/'.Str::slug($name).'.txt'), $text."\n");
    }
}
