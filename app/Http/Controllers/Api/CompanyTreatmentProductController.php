<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Company\BulkAdjustTreatmentProductPricesRequest;
use App\Http\Requests\Company\StoreTreatmentProductRequest;
use App\Http\Requests\Company\UpdateTreatmentProductRequest;
use App\Http\Resources\TreatmentProductResource;
use App\Models\Company;
use App\Models\TreatmentCatalog;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompanyTreatmentProductController extends Controller
{
    public function index(Request $request, Company $company)
    {
        $this->assertBelongsToRequester($request, $company);

        // Every priced item is shown here, company-managed services and
        // odontogram-widget procedures alike, so a clinic can actually see and
        // adjust the real prices driving Services Total -- not just the 6
        // manually-curated services from before that feature existed.
        $products = $company->treatmentCatalog()
            ->orderBy('scope')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return $this->success(TreatmentProductResource::collection($products));
    }

    /**
     * A flat {code: price} map covering every procedure/condition the V2
     * odontogram widget can select -- fetched once by the frontend and used
     * to price a client's selections as they build/edit an odontogram,
     * instead of the widget's previous hardcoded mock price table.
     */
    public function odontogramPrices(Request $request, Company $company)
    {
        $this->assertBelongsToRequester($request, $company);

        $prices = $company->treatmentCatalog()
            ->where('scope', TreatmentCatalog::SCOPE_ODONTOGRAM)
            ->where('is_active', true)
            ->pluck('default_price', 'code');

        return $this->success($prices);
    }

    public function store(StoreTreatmentProductRequest $request, Company $company)
    {
        $this->assertBelongsToRequester($request, $company);

        $data = $request->validated();

        $product = TreatmentCatalog::query()->create([
            'company_id' => $company->id,
            'scope' => TreatmentCatalog::SCOPE_COMPANY,
            'code' => $data['code'],
            'name_ar' => $data['name_ar'],
            'name_en' => $data['name_en'],
            'name_tr' => $data['name_tr'] ?? null,
            'default_price' => $data['price'],
            'is_active' => $data['status'] === 'active',
            'sort_order' => ((int) $company->treatmentCatalog()->where('scope', TreatmentCatalog::SCOPE_COMPANY)->max('sort_order')) + 1,
        ]);

        return $this->success(
            TreatmentProductResource::make($product),
            'Treatment product created successfully.',
            201
        );
    }

    public function update(UpdateTreatmentProductRequest $request, Company $company, TreatmentCatalog $product)
    {
        $this->assertBelongsToRequester($request, $company);

        if ($product->company_id !== $company->id) {
            throw ValidationException::withMessages([
                'product' => ['The selected treatment product does not belong to the specified company.'],
            ]);
        }

        $data = $request->validated();

        $product->update([
            'code' => $data['code'],
            'name_en' => $data['name_en'],
            'name_ar' => $data['name_ar'],
            'name_tr' => $data['name_tr'] ?? null,
            'default_price' => $data['price'],
            'is_active' => ($data['status'] ?? 'active') === 'active',
        ]);

        return $this->success(TreatmentProductResource::make($product->fresh()), 'Treatment product updated successfully.');
    }

    public function destroy(Request $request, Company $company, TreatmentCatalog $product)
    {
        $this->assertBelongsToRequester($request, $company);

        if ($product->company_id !== $company->id) {
            throw ValidationException::withMessages([
                'product' => ['The selected treatment product does not belong to the specified company.'],
            ]);
        }

        // Odontogram-scope rows are the fixed, always-seeded price list behind
        // every procedure/condition the odontogram-v2 widget can select (see
        // TreatmentCatalogSeeder::odontogramItems(), kept 1:1 with
        // OdontogramV2Vocabulary) -- a clinic may only ever change their price,
        // never delete them, since that would silently zero-price and
        // untranslate that condition everywhere it's charged.
        if ($product->scope === TreatmentCatalog::SCOPE_ODONTOGRAM) {
            throw ValidationException::withMessages([
                'product' => ['Odontogram procedure prices are fixed and cannot be deleted -- only their price can be changed.'],
            ]);
        }

        try {
            $product->delete();
        } catch (QueryException $e) {
            // treatment_record_teeth.treatment_catalog_id is restrictOnDelete --
            // a friendly error beats a raw 500 if this product is still
            // referenced by an (old-style) treatment record.
            throw ValidationException::withMessages([
                'product' => ['This product is still referenced by an existing treatment record and cannot be deleted.'],
            ]);
        }

        return $this->success(null, 'Treatment product deleted successfully.');
    }

    /**
     * Adjust every priced item in the company's catalog (both scopes) at
     * once by a percentage or fixed amount, e.g. "+10% across the board" --
     * mirrors a feature competing dental-clinic software offers that we
     * previously only supported one item at a time.
     */
    public function bulkPriceAdjustment(BulkAdjustTreatmentProductPricesRequest $request, Company $company)
    {
        $this->assertBelongsToRequester($request, $company);

        $data = $request->validated();

        DB::transaction(function () use ($company, $data) {
            $company->treatmentCatalog()->get()->each(function (TreatmentCatalog $product) use ($data) {
                $current = (float) $product->default_price;

                $new = $data['type'] === 'percentage'
                    ? $current * (1 + $data['value'] / 100)
                    : $current + $data['value'];

                $product->update(['default_price' => max(0, round($new, 2))]);
            });
        });

        $products = $company->treatmentCatalog()
            ->orderBy('scope')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return $this->success(TreatmentProductResource::collection($products), 'Prices adjusted successfully.');
    }

    protected function assertBelongsToRequester(Request $request, Company $company): void
    {
        if ($request->user()->isProjectAdmin()) {
            return;
        }

        if ((int) $company->id !== (int) $request->user()->company_id) {
            throw ValidationException::withMessages([
                'company' => ['The selected company does not belong to your account.'],
            ]);
        }
    }
}
