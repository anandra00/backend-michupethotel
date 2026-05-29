<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Cat;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // === USERS ===
        $admin = User::create([
            'name' => 'Admin Michu',
            'email' => 'admin@michumeowstay.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'phone' => '081234567890',
        ]);

        $user1 = User::create([
            'name' => 'Sarah Jenkins',
            'email' => 'sarah@example.com',
            'password' => Hash::make('password'),
            'role' => 'user',
            'phone' => '081298765432',
        ]);

        $user2 = User::create([
            'name' => 'Mark Davis',
            'email' => 'mark@example.com',
            'password' => Hash::make('password'),
            'role' => 'user',
            'phone' => '081387654321',
        ]);

        $user3 = User::create([
            'name' => 'Emily Chen',
            'email' => 'emily@example.com',
            'password' => Hash::make('password'),
            'role' => 'user',
            'phone' => '081456789012',
        ]);

        // === ROOMS ===
        $room1 = Room::create([
            'name' => 'Standard Cat Room',
            'type' => 'Standard',
            'price_per_night' => 50000,
            'description' => 'Kamar standar nyaman untuk 1 ekor kucing. Dilengkapi AC, litter box, dan makan 2x sehari.',
            'facilities' => json_encode(['AC', 'Litter Box', 'Makan 2x Sehari', 'Tempat Tidur']),
            'capacity' => 1,
            'status' => 'available',
        ]);

        $room2 = Room::create([
            'name' => 'Deluxe Cat Room',
            'type' => 'Deluxe',
            'price_per_night' => 85000,
            'description' => 'Kamar deluxe dengan ruang lebih luas. Cocok untuk kucing yang aktif dan suka bermain.',
            'facilities' => json_encode(['AC', 'Litter Box', 'Makan 3x Sehari', 'CCTV', 'Mainan']),
            'capacity' => 1,
            'status' => 'occupied',
        ]);

        $room3 = Room::create([
            'name' => 'Sultan Cat Suite',
            'type' => 'Suite',
            'price_per_night' => 150000,
            'description' => 'Kamar mewah premium untuk kucing sultan. Fasilitas lengkap dengan grooming gratis.',
            'facilities' => json_encode(['AC', 'Litter Box Premium', 'Makan 3x Sehari', 'CCTV 24/7', 'Mainan Premium', 'Grooming', 'Play Area']),
            'capacity' => 2,
            'status' => 'available',
        ]);

        $room4 = Room::create([
            'name' => 'Family Cat Room',
            'type' => 'Family',
            'price_per_night' => 120000,
            'description' => 'Kamar keluarga untuk 2-3 kucing. Ideal untuk kucing bersaudara yang tidak ingin dipisah.',
            'facilities' => json_encode(['AC', 'Litter Box x2', 'Makan 3x Sehari', 'CCTV', 'Mainan', 'Scratching Post']),
            'capacity' => 3,
            'status' => 'available',
        ]);

        $room5 = Room::create([
            'name' => 'Economy Cat Room',
            'type' => 'Economy',
            'price_per_night' => 35000,
            'description' => 'Kamar ekonomis dengan fasilitas dasar. Cocok untuk penitipan singkat.',
            'facilities' => json_encode(['Kipas Angin', 'Litter Box', 'Makan 2x Sehari']),
            'capacity' => 1,
            'status' => 'cleaning',
        ]);

        $room6 = Room::create([
            'name' => 'VIP Cat Penthouse',
            'type' => 'VIP',
            'price_per_night' => 250000,
            'description' => 'Penthouse eksklusif di lantai atas. View terbaik, fasilitas spa, dan personal sitter.',
            'facilities' => json_encode(['AC Dual Zone', 'Litter Box Auto-Clean', 'Makan 4x Sehari', 'CCTV 24/7', 'Spa', 'Personal Sitter', 'Play Area Private']),
            'capacity' => 2,
            'status' => 'available',
        ]);

        // === CATS ===
        Cat::create([
            'user_id' => $user1->id,
            'name' => 'Luna',
            'breed' => 'Persian',
            'age' => 24,
            'weight' => 4.2,
            'gender' => 'female',
            'notes' => 'Alergi makanan ikan laut.',
        ]);

        Cat::create([
            'user_id' => $user1->id,
            'name' => 'Milo',
            'breed' => 'British Shorthair',
            'age' => 18,
            'weight' => 5.1,
            'gender' => 'male',
            'notes' => 'Sangat aktif, suka berlari.',
        ]);

        Cat::create([
            'user_id' => $user2->id,
            'name' => 'Oliver',
            'breed' => 'Domestic Shorthair',
            'age' => 36,
            'weight' => 4.8,
            'gender' => 'male',
            'notes' => 'Pendiam dan suka tidur.',
        ]);

        Cat::create([
            'user_id' => $user3->id,
            'name' => 'Bella',
            'breed' => 'Anggora',
            'age' => 12,
            'weight' => 3.5,
            'gender' => 'female',
            'notes' => 'Makan hanya wet food.',
        ]);

        Cat::create([
            'user_id' => $user3->id,
            'name' => 'Simba',
            'breed' => 'Domestic',
            'age' => 48,
            'weight' => 6.0,
            'gender' => 'male',
            'notes' => 'Kucing senior, perlu perhatian ekstra.',
        ]);

        // === BOOKINGS ===
        Booking::create([
            'user_id' => $user1->id,
            'room_id' => $room3->id,
            'check_in' => '2026-05-12',
            'check_out' => '2026-05-18',
            'total_cats' => 2,
            'total_price' => 900000,
            'status' => 'approved',
            'payment_status' => 'paid',
            'notes' => 'Luna & Milo, tolong jaga bersama ya.',
        ]);

        Booking::create([
            'user_id' => $user2->id,
            'room_id' => $room1->id,
            'check_in' => '2026-05-15',
            'check_out' => '2026-05-17',
            'total_cats' => 1,
            'total_price' => 100000,
            'status' => 'pending',
            'notes' => 'Oliver agak pemalu dengan kucing lain.',
        ]);

        Booking::create([
            'user_id' => $user3->id,
            'room_id' => $room1->id,
            'check_in' => '2026-05-01',
            'check_out' => '2026-05-05',
            'total_cats' => 1,
            'total_price' => 200000,
            'status' => 'checked_out',
            'payment_status' => 'paid',
            'notes' => 'Bella sudah checkout.',
        ]);

        Booking::create([
            'user_id' => $user3->id,
            'room_id' => $room2->id,
            'check_in' => '2026-05-20',
            'check_out' => '2026-05-25',
            'total_cats' => 1,
            'total_price' => 425000,
            'status' => 'cancelled',
            'notes' => 'Simba batal dititipkan.',
        ]);

        Booking::create([
            'user_id' => $user1->id,
            'room_id' => $room4->id,
            'check_in' => '2026-05-25',
            'check_out' => '2026-05-30',
            'total_cats' => 2,
            'total_price' => 600000,
            'status' => 'pending',
            'notes' => 'Perlu kamar yang bisa untuk 2 kucing.',
        ]);
    }
}
