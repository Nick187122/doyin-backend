<?php

namespace Tests\Unit\Models;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_has_fillable_attributes(): void
    {
        $setting = Setting::create([
            'key' => 'site_name',
            'value' => 'Doyin Pumps',
        ]);

        $this->assertSame('site_name', $setting->key);
        $this->assertSame('Doyin Pumps', $setting->value);
    }

    public function test_it_can_store_long_values(): void
    {
        $longValue = str_repeat('A', 5000);
        $setting = Setting::create([
            'key' => 'long_text',
            'value' => $longValue,
        ]);

        $this->assertSame($longValue, $setting->value);
    }

    public function test_it_can_update_value(): void
    {
        $setting = Setting::create([
            'key' => 'contact_phone',
            'value' => '+254700000000',
        ]);

        $setting->update(['value' => '+254700000001']);

        $this->assertSame('+254700000001', $setting->fresh()->value);
    }
}
