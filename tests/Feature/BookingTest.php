<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Cat;
use App\Models\Sitter;
use App\Models\SitterPackage;
use App\Models\Setting;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_calculates_sitter_pricing_correctly()
    {
        // Arrange: Create required settings and packages
        Setting::create(['key' => 'admin_fee', 'value' => '5000']);
        $p1 = SitterPackage::create(['id' => 1, 'name' => '1x Visit/hari', 'price' => 75000]);
        $p2 = SitterPackage::create(['id' => 2, 'name' => '2x Visit/hari', 'price' => 130000]);

        $service = new BookingService();

        // Act & Assert
        // Tiered pricing:
        // 1-2 cats: 60k
        // 3-4 cats: 80k
        // 5+ cats: 120k
        
        // 1 cat, 1 day, 1x visit: (1 * 60,000) + 5,000 = 65,000
        $this->assertEquals(65000.0, $service->calculateSitterPrice(1, '2026-06-01', '2026-06-01', 1));

        // 3 cats, 1 day, 1x visit: (1 * 80,000) + 5,000 = 85,000
        $this->assertEquals(85000.0, $service->calculateSitterPrice(1, '2026-06-01', '2026-06-01', 3));

        // 5 cats, 1 day, 2x visit: (1 * 240,000) + 5,000 = 245,000
        $this->assertEquals(245000.0, $service->calculateSitterPrice(2, '2026-06-01', '2026-06-01', 5));
    }

    public function test_authenticated_user_can_create_multi_cat_booking()
    {
        // Arrange
        Setting::create(['key' => 'admin_fee', 'value' => '5000']);
        $p1 = SitterPackage::create(['id' => 1, 'name' => '1x Visit/hari', 'price' => 75000]);
        $sitter = Sitter::create(['id' => 1, 'name' => 'John Doe', 'phone' => '0812345678', 'area' => 'Jakarta', 'speciality' => 'Medical care', 'status' => 'active']);

        $user = User::create([
            'name' => 'Test User',
            'email' => 'test_' . uniqid() . '@meowstay.com',
            'password' => bcrypt('password'),
            'role' => 'user',
            'phone' => '08123456789'
        ]);
        
        $cat1 = Cat::create(['name' => 'Milo', 'user_id' => $user->id, 'breed' => 'Persian', 'age' => 2, 'gender' => 'male', 'weight' => 4.5]);
        $cat2 = Cat::create(['name' => 'Kiko', 'user_id' => $user->id, 'breed' => 'Angora', 'age' => 1, 'gender' => 'female', 'weight' => 3.5]);

        // Act
        $response = $this->actingAs($user)
            ->postJson('/api/bookings', [
                'booking_type' => 'sitter',
                'sitter_id' => $sitter->id,
                'sitter_package' => $p1->id,
                'check_in' => '2026-06-01',
                'check_out' => '2026-06-01',
                'cat_ids' => [$cat1->id, $cat2->id],
                'visit_time' => 'morning',
                'notes' => 'Tolong mandikan Milo dan Kiko'
            ]);

        // Assert
        $response->assertStatus(201);
        $response->assertJsonPath('total_cats', 2);
        $response->assertJsonPath('total_price', 65000); // 60k + 5k admin fee

        // Verify pivot sync
        $this->assertDatabaseHas('booking_cat', [
            'cat_id' => $cat1->id,
        ]);
        $this->assertDatabaseHas('booking_cat', [
            'cat_id' => $cat2->id,
        ]);
    }
}
