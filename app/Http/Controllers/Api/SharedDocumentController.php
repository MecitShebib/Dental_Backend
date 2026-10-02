<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesOwnDoctorRecords;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\SharedDocument;
use App\Services\WhatsAppService;
use App\Support\WhatsAppPhone;
use Illuminate\Http\Request;

/**
 * "Send via WhatsApp" on every generated patient document. The app opens the
 * patient's WhatsApp chat instantly with a link to /d/{uuid} (the UUID is
 * generated in the browser), then renders the PDF and posts it here; it's
 * kept for SharedDocument::LIFETIME_DAYS.
 *
 * With `send_via_api` (clinics whose subscription includes WhatsApp and that
 * have their own Business number connected), the PDF also goes straight to
 * the patient as a WhatsApp document message instead of a wa.me tab.
 */
class SharedDocumentController extends Controller
{
    use AuthorizesOwnDoctorRecords;

    public function store(Request $request, WhatsAppService $whatsApp)
    {
        $data = $request->validate([
            'uuid' => ['required', 'uuid', 'unique:shared_documents,uuid'],
            'client_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:255'],
            'file' => ['required', 'file', 'mimes:pdf', 'max:15360'],
            'send_via_api' => ['nullable', 'boolean'],
        ]);

        $actingUser = $request->user();
        $client = Client::query()->where('company_id', $actingUser->company_id)->findOrFail($data['client_id']);
        $this->assertActingDoctorOwnsClient($request, $client);

        // Opportunistic cleanup -- a real cron may not be running
        // documents:purge-expired on every host.
        SharedDocument::purgeExpired();

        $uuid = strtolower($data['uuid']);
        $filename = static::filename($data['title'], $request->file('file')->getClientOriginalName());
        $path = $request->file('file')->storeAs('shared-documents', "{$uuid}.pdf", SharedDocument::DISK);

        $document = SharedDocument::create([
            'uuid' => $uuid,
            'company_id' => $client->company_id,
            'client_id' => $client->id,
            'created_by' => $actingUser->id,
            'title' => $data['title'],
            'filename' => $filename,
            'path' => $path,
            'expires_at' => now()->addDays(SharedDocument::LIFETIME_DAYS),
        ]);

        $phone = WhatsAppPhone::normalize($client->phone);
        $sent = false;
        if (($data['send_via_api'] ?? false) && $phone && $client->company && $whatsApp->enabledFor($client->company)) {
            $sent = $whatsApp->sendDocument(
                $client->company,
                $phone,
                (string) file_get_contents($request->file('file')->getRealPath()),
                $filename,
                $this->caption($client, $data['title']),
            );
        }

        return $this->success([
            'url' => route('shared-documents.file', $document->uuid),
            'expires_at' => $document->expires_at->toDateString(),
            'sent' => $sent,
            'phone' => $phone,
        ], 'Document stored.', 201);
    }

    /**
     * The app names the file after the document's own title, the same name
     * "Issue PDF" gives it (e.g. "Ayşe Yılmaz — Tedavi Planı.pdf") -- keep
     * that, only re-sanitized; fall back to the title.
     */
    public static function filename(string $title, ?string $uploadedName = null): string
    {
        $name = trim((string) preg_replace('/[\\\\\/:*?"<>|\x00-\x1f]+/u', '_', (string) $uploadedName));

        if ($name !== '' && str_ends_with(mb_strtolower($name), '.pdf')) {
            return $name;
        }

        $slug = trim(preg_replace('/[^\pL\pN]+/u', '-', $title) ?? '', '-');

        return ($slug !== '' ? $slug : 'document').'.pdf';
    }

    protected function caption(Client $client, string $title): string
    {
        $company = $client->company?->name ?? '';

        return match ($client->preferred_language?->value ?? 'tr') {
            'ar' => "مرحبًا {$client->name}، مرفق مستند \"{$title}\" من {$company}.",
            'en' => "Hello {$client->name}, please find your \"{$title}\" document from {$company} attached.",
            default => "Merhaba {$client->name}, {$company} tarafından hazırlanan \"{$title}\" belgeniz ektedir.",
        };
    }
}
