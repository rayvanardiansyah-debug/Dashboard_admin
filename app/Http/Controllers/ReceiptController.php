<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Services\ReceiptPrinter;
use Illuminate\Http\Request;
use Mike42\Escpos\Printer;

class ReceiptController extends Controller
{

    public function print(Request $request, ReceiptPrinter $receipt)
    {
        try {
            $connector = $receipt->connector('demo');

            $printer = new Printer($connector);

            // Custom formatting: center + bold (Acara 35, bagian 5)
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->setEmphasis(true);
            $printer->text("TOKO CONTOH\n");
            $printer->setEmphasis(false);
            $printer->text("Jl. Mawar No. 123\n");

            $printer->setJustification(Printer::JUSTIFY_LEFT);
            $printer->text("========================\n");
            $printer->text("Barang A       2 x 5.000\n");
            $printer->text("Barang B      1 x 10.000\n");
            $printer->text("------------------------\n");

            $printer->setEmphasis(true);
            $printer->text("Total:          Rp20.000\n");
            $printer->setEmphasis(false);
            $printer->text("========================\n");

            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->setUnderline(1);
            $printer->text("Terima kasih!\n");
            $printer->setUnderline(0);

            if ($request->boolean('qr')) {
                $printer->qrCode('TOKO-CONTOH', Printer::QR_ECLEVEL_M, 6);
            }

            $printer->feed(3);
            $printer->cut();
            $printer->close();

            return response()->json(['message' => 'Struk berhasil dicetak']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Acara 36: cetak (ulang) struk transaksi tertentu ke printer thermal dari backend.
     */
    public function printTransaction(Transaction $transaction, ReceiptPrinter $receipt)
    {
        try {
            $receipt->printTransaction($transaction);

            return response()->json(['message' => 'Struk '.$transaction->transaction_number.' berhasil dicetak']);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
