<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesAccounting;
use App\Http\Controllers\Controller;
use App\Http\Requests\MessageTemplate\UpdateMessageTemplateRequest;
use App\Models\MessageTemplate;
use App\Support\MessageTemplateDefaults;
use Illuminate\Http\Request;

/**
 * Lets a company admin see and override the wording of every automated
 * SMS/email the app sends (appointment reminders, patient recalls, online
 * booking confirmations) -- see MessageTemplateDefaults for the built-in
 * text and MessageTemplateService for how a company's overrides here get
 * applied at send time.
 */
class MessageTemplateController extends Controller
{
    use AuthorizesAccounting;

    /**
     * Every key/channel/language slot the app supports, each with its
     * default text plus this company's override (if any) -- the settings
     * page renders one row per slot regardless of whether it's customized.
     */
    public function index(Request $request)
    {
        $this->assertHasAccountingAccess($request);

        $company = $request->user()->company;
        $custom = MessageTemplate::query()
            ->where('company_id', $company->id)
            ->get()
            ->keyBy(fn (MessageTemplate $template) => "{$template->key}|{$template->channel}|{$template->language}");

        $rows = collect();

        foreach (MessageTemplateDefaults::all() as $key => $channels) {
            foreach ($channels as $channel => $languages) {
                foreach ($languages as $language => $default) {
                    $override = $custom->get("{$key}|{$channel}|{$language}");

                    $rows->push([
                        'key' => $key,
                        'channel' => $channel,
                        'language' => $language,
                        'placeholders' => array_keys(MessageTemplateDefaults::placeholdersFor($key)),
                        'default_subject' => $default['subject'] ?? null,
                        'default_body' => $default['body'],
                        'subject' => $override?->subject,
                        'body' => $override?->body,
                        'is_custom' => (bool) $override,
                    ]);
                }
            }
        }

        return $this->success($rows->values());
    }

    public function update(UpdateMessageTemplateRequest $request)
    {
        $this->assertHasAccountingAccess($request);

        $data = $request->validated();
        $company = $request->user()->company;

        $subject = $data['subject'] ?? null;
        $body = $data['body'] ?? null;

        $existing = MessageTemplate::query()
            ->where('company_id', $company->id)
            ->where('key', $data['key'])
            ->where('channel', $data['channel'])
            ->where('language', $data['language']);

        // Both blank means "revert to default" -- delete the override rather
        // than storing an empty row MessageTemplateService would just skip.
        if ($subject === null && $body === null) {
            $existing->delete();

            return $this->success(null, 'Reverted to default.');
        }

        $template = $existing->first();

        if ($template) {
            $template->update(['subject' => $subject, 'body' => $body]);
        } else {
            $template = MessageTemplate::create([
                'company_id' => $company->id,
                'key' => $data['key'],
                'channel' => $data['channel'],
                'language' => $data['language'],
                'subject' => $subject,
                'body' => $body,
            ]);
        }

        return $this->success([
            'key' => $template->key,
            'channel' => $template->channel,
            'language' => $template->language,
            'subject' => $template->subject,
            'body' => $template->body,
            'is_custom' => true,
        ], 'Template saved.');
    }
}
