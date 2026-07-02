<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Company\StoreTreatmentProductRequest;
use App\Http\Requests\Company\UpdateTreatmentProductRequest;
use App\Http\Resources\TreatmentProductResource;
use App\Models\Company;
use App\Models\TreatmentCatalog;
use Illuminate\Validation\ValidationException;

class CompanyTreatmentProductController extends Controller
{
    public function index(Company $company)
    {
        $products = $company->treatmentCatalog()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return $this->success(TreatmentProductResource::collection($products));
    }

    public function store(StoreTreatmentProductRequest $request, Company $company)
    {
        $data = $request->validated();

        $product = TreatmentCatalog::query()->create([
            'company_id' => $company->id,
            'code' => $data['code'],
            'name_ar' => $data['name_ar'],
            'name_en' => $data['name_en'],
            'name_tr' => $data['name_tr'] ?? null,
            'color' => $data['color'] ?? null,
            'default_price' => $data['price'],
            'is_active' => $data['status'] === 'active',
            'sort_order' => ((int) $company->treatmentCatalog()->max('sort_order')) + 1,
        ]);

        return $this->success(
            TreatmentProductResource::make($product),
            'Treatment product created successfully.',
            201
        );
    }

    public function update(UpdateTreatmentProductRequest $request, Company $company, TreatmentCatalog $product)
    {
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
            'color' => $data['color'] ?? null,
            'default_price' => $data['price'],
            'is_active' => ($data['status'] ?? 'active') === 'active',
        ]);

        return $this->success(TreatmentProductResource::make($product->fresh()), 'Treatment product updated successfully.');
    }
}
