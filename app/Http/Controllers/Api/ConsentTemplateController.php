<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Consent\StoreConsentTemplateRequest;
use App\Http\Requests\Consent\UpdateConsentTemplateRequest;
use App\Http\Resources\ConsentTemplateResource;
use App\Models\ConsentTemplate;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ConsentTemplateController extends Controller
{
    public function index()
    {
        return $this->success(ConsentTemplateResource::collection(
            ConsentTemplate::query()->orderBy('title')->get()
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

        return $this->success(ConsentTemplateResource::make($template), 'Consent template created successfully.', 201);
    }

    public function update(UpdateConsentTemplateRequest $request, ConsentTemplate $template)
    {
        $this->assertCanManageTemplates($request);

        $template->update($request->validated());

        return $this->success(ConsentTemplateResource::make($template), 'Consent template updated successfully.');
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
