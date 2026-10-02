<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesOwnDoctorRecords;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Services\WhatsAppService;
use App\Support\WhatsAppPhone;
use Illuminate\Http\Request;

/**
 * "Send WhatsApp message" on the patient pages, for clinics whose
 * subscription includes WhatsApp AND that have their own WhatsApp Business
 * number connected (WhatsAppService::enabledFor): the text goes out through
 * the Cloud API. Every other clinic opens a wa.me tab in the browser
 * instead and never calls this. `sent: false` (Meta refused, e.g. outside
 * the 24h customer-service window) lets the app fall back to wa.me.
 */
class ClientWhatsAppMessageController extends Controller
{
    use AuthorizesOwnDoctorRecords;

    public function send(Request $request, Client $client, WhatsAppService $whatsApp)
    {
        $this->assertActingDoctorOwnsClient($request, $client);
        $data = $request->validate(['text' => ['required', 'string', 'max:4096']]);

        $phone = WhatsAppPhone::normalize($client->phone);
        $company = $client->company;
        $sent = $phone && $company && $whatsApp->enabledFor($company)
            && $whatsApp->send($company, $phone, $data['text']);

        return $this->success(['sent' => $sent, 'phone' => $phone], $sent ? 'Message sent.' : 'Message not sent automatically.');
    }
}
