<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\UpdateTreatmentCatalogInventoryLinksRequest;
use App\Http\Resources\TreatmentCatalogInventoryLinkResource;
use App\Models\TreatmentCatalog;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Lets an admin declare which inventory items a procedure consumes (see
 * InventoryService::syncConsumptionForSource() for how a billed charge then
 * auto-decrements that stock). One catalog entry -> many linked items.
 */
class TreatmentCatalogInventoryLinkController extends Controller
{
    public function index(Request $request, TreatmentCatalog $catalogEntry)
    {
        $this->assertOwnedByRequester($request, $catalogEntry);

        return $this->success(TreatmentCatalogInventoryLinkResource::collection(
            $catalogEntry->inventoryLinks()->with('inventoryItem')->get()
        ));
    }

    /**
     * Full replace, same delete-then-recreate convention as
     * TreatmentChargeService::syncItems() -- the caller always sends the
     * catalog entry's complete current set of linked items.
     */
    public function update(UpdateTreatmentCatalogInventoryLinksRequest $request, TreatmentCatalog $catalogEntry)
    {
        $this->assertOwnedByRequester($request, $catalogEntry);

        $catalogEntry->inventoryLinks()->delete();

        $links = collect($request->validated('links'))
            ->map(fn (array $link) => [
                'inventory_item_id' => $link['inventory_item_id'],
                'quantity_per_use' => $link['quantity_per_use'],
            ])
            ->all();

        if (! empty($links)) {
            $catalogEntry->inventoryLinks()->createMany($links);
        }

        return $this->success(TreatmentCatalogInventoryLinkResource::collection(
            $catalogEntry->inventoryLinks()->with('inventoryItem')->get()
        ), 'Inventory links updated successfully.');
    }

    protected function assertOwnedByRequester(Request $request, TreatmentCatalog $catalogEntry): void
    {
        if ($catalogEntry->company_id !== $request->user()?->company_id) {
            throw ValidationException::withMessages([
                'catalogEntry' => ['The selected treatment product does not belong to your company.'],
            ]);
        }
    }
}
