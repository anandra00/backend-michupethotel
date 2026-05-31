<?php

namespace App\Exports;

use App\Models\Booking;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class BookingsExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        return Booking::with(['user', 'room', 'sitter'])->orderBy('created_at', 'desc')->get();
    }

    public function headings(): array
    {
        return [
            'ID Booking',
            'Tipe Layanan',
            'Nama Pelanggan',
            'Ruang/Paket',
            'Check In',
            'Check Out',
            'Total Kucing',
            'Total Harga',
            'Status',
            'Pembayaran',
            'Tanggal Dibuat',
        ];
    }

    public function map($booking): array
    {
        return [
            $booking->id,
            $booking->booking_type === 'board' ? 'Cat Boarding' : 'Cat Sitter',
            $booking->user->name ?? 'N/A',
            $booking->booking_type === 'board' ? ($booking->room->name ?? 'N/A') : ($booking->sitter_package ?? 'N/A'),
            $booking->check_in,
            $booking->check_out,
            $booking->total_cats,
            'Rp ' . number_format($booking->total_price, 0, ',', '.'),
            $booking->status,
            $booking->payment_status,
            $booking->created_at->format('Y-m-d H:i:s'),
        ];
    }
}

