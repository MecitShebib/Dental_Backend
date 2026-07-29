<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Company\StoreTreatmentProductRequest;
use App\Http\Requests\Company\UpdateTreatmentProductRequest;
use App\Http\Resources\TreatmentProductResource;
use App\Models\Company;
use App\Models\TreatmentCatalog;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
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
