<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\FuelProduct;
use App\Models\FuelTank;
use App\Models\User;
use App\Notifications\LowStockThresholdReached;
use App\Services\InventoryLedger;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class LowStockAlertTest extends TestCase
{
    use DatabaseMigrations;

    public function test_threshold_crossing_notifies_global_owners_and_users_at_the_affected_branch(): void
    {
        Mail::fake();
        $manaoag = Branch::create(['code' => 'manaoag', 'name' => 'Manaoag']);
        $sanFabian = Branch::create(['code' => 'san-fabian', 'name' => 'San Fabian']);
        $owner = User::factory()->create(['role' => UserRole::Owner, 'branch_id' => null]);
        $manaoagOwner = User::factory()->create(['role' => UserRole::Owner, 'branch_id' => $manaoag->id]);
        $sanFabianOwner = User::factory()->create(['role' => UserRole::Owner, 'branch_id' => $sanFabian->id]);
        $manaoagStaff = User::factory()->create(['role' => UserRole::Staff, 'branch_id' => $manaoag->id]);
        $sanFabianStaff = User::factory()->create(['role' => UserRole::Staff, 'branch_id' => $sanFabian->id]);
        $product = FuelProduct::create(['name' => 'Unleaded 91']);
        $tank = FuelTank::create([
            'branch_id' => $manaoag->id,
            'fuel_product_id' => $product->id,
            'code' => 'MNG-T1',
            'capacity_liters' => 100,
            'low_stock_threshold_liters' => 20,
        ]);
        $tank->inventoryMovements()->create([
            'branch_id' => $manaoag->id,
            'fuel_tank_id' => $tank->id,
            'movement_type' => 'opening_balance',
            'signed_quantity_liters' => 25,
            'recorded_by' => $owner->id,
            'occurred_at' => now()->subHour(),
        ]);

        app(InventoryLedger::class)->record(
            $tank,
            'sale',
            -6,
            $manaoagStaff,
            $tank,
            now(),
        );

        $this->assertDatabaseCount('notifications', 3);
        $this->assertSame(1, $owner->notifications()->where('data->branch_id', $manaoag->id)->count());
        $this->assertSame(1, $manaoagOwner->notifications()->where('data->branch_id', $manaoag->id)->count());
        $this->assertSame(1, $manaoagStaff->notifications()->where('data->branch_id', $manaoag->id)->count());
        $this->assertSame(0, $sanFabianOwner->notifications()->count());
        $this->assertSame(0, $sanFabianStaff->notifications()->count());
        $this->assertSame(
            ['database', 'mail'],
            (new LowStockThresholdReached(['fuel' => 'Unleaded 91']))->via($owner),
        );

        app(InventoryLedger::class)->record(
            $tank,
            'adjustment',
            -1,
            $manaoagStaff,
            $tank,
            now()->addMinute(),
        );
        $this->assertDatabaseCount('notifications', 3);

        $notification = $owner->notifications()->firstOrFail();
        $this->actingAs($sanFabianOwner)->post(route('notifications.inventory', $notification->id))
            ->assertNotFound();
        $this->actingAs($owner)
            ->withSession(['active_branch_id' => $sanFabian->id])
            ->post(route('notifications.inventory', $notification->id))
            ->assertRedirect(route('inventory.index', ['highlight_tank' => $tank->id]))
            ->assertSessionHas('active_branch_id', $manaoag->id);
        $this->getJson(route('api.workspace'))
            ->assertOk()
            ->assertJsonPath('active_branch_id', $manaoag->id);

        $this->actingAs($owner)->getJson(route('api.notifications.index'))
            ->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonPath('data.data.0.data.branch_id', $manaoag->id);
        $this->actingAs($owner)->patchJson(route('api.notifications.read', $notification->id))
            ->assertOk()
            ->assertJsonPath('data.id', $notification->id);
        $this->assertNotNull($notification->fresh()->read_at);

        $this->actingAs($sanFabianStaff)->getJson(route('api.notifications.index'))
            ->assertOk()
            ->assertJsonPath('unread_count', 0);
        $this->actingAs($manaoagStaff)->getJson(route('api.notifications.index'))
            ->assertOk()
            ->assertJsonPath('unread_count', 1);
        $this->patchJson(route('api.notifications.read-all'))
            ->assertOk()
            ->assertJsonPath('marked_read', 1)
            ->assertJsonPath('unread_count', 0);
        $this->getJson(route('api.notifications.index'))
            ->assertOk()
            ->assertJsonPath('unread_count', 0);
    }
}
