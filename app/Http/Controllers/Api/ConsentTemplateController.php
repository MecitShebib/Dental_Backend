<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Consent\StoreConsentTemplateRequest;
use App\Http\Requests\Consent\UpdateConsentTemplateRequest;
use App\Http\Resources\ConsentTemplateResource;
use App\Models\ConsentTemplate;
use App\Models\Specialty;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ConsentTemplateController extends Controller
{
    public function index(Request $request)
    {
        $specialtyId = $request->filled('specialty')
            ? Specialty::query()->where('key', $request->string('specialty')->value())->value('id')
            : null;

        return $this->success(ConsentTemplateResource::collection(
            ConsentTemplate::query()
                ->with('specialty')
                // A template with no specialty (e.g. the KVKK disclosure/consent
                // templates, which apply company-wide) stays visible from every
                // specialty rather than only showing in "all specialties" mode.
                ->when($specialtyId, fn ($query) => $query->where(fn ($q) => $q->where('specialty_id', $specialtyId)->orWhereNull('specialty_id')))
                ->orderBy('title')
                ->get()
        ));
    }

    public function store(StoreConsentTemplateRequest $request)
    {
        $this->assertCanManageTemplates($request);

        $template = ConsentTemplate::create([
            ...$request->validated(),
            'company_id' => $request->user()->company_id,
            'is_active' => $request->validated('is_active') ?? true,
        ]);

        return $this->success(ConsentTemplateResource::make($template->load('specialty')), 'Consent template created successfully.', 201);
    }

    public function update(UpdateConsentTemplateRequest $request, ConsentTemplate $template)
    {
        $this->assertCanManageTemplates($request);

        $template->update($request->validated());

        return $this->success(ConsentTemplateResource::make($template->load('specialty')), 'Consent template updated successfully.');
    }

    public function destroy(Request $request, ConsentTemplate $template)
    {
        $this->assertCanManageTemplates($request);

        $template->delete();

        return $this->success(null, 'Consent template deleted successfully.');
    }

    protected function assertCanManageTemplates(Request $request): void
    {
        if ($request->user()->isSystemManager() || $request->user()->isProjectAdmin()) {
            return;
        }

        throw ValidationException::withMessages([
            'user' => ['You are not authorized to manage consent templates.'],
        ]);
    }
}
