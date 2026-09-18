<?php

namespace App\Services;

use App\Enums\ClientLanguage;
use App\Models\Company;
use App\Models\MessageTemplate;
use App\Support\MessageTemplateDefaults;

/**
 * The one place every automated message's wording is resolved: a company's
 * own MessageTemplate row (if they've customized that key/channel/language
 * in Settings > Message Templates) wins, otherwise MessageTemplateDefaults'
 * built-in text is used. Either way, {placeholder} tokens get substituted
 * with real values here -- callers never touch raw template text directly.
 */
class MessageTemplateService
{
    /**
     * @param  array<string, string>  $variables
     * @return array{subject: ?string, body: string}
     */
    public function render(Company $company, string $key, string $channel, ClientLanguage $language, array $variables, ?int $specialtyId = null): array
    {
        $default = MessageTemplateDefaults::all()[$key][$channel][$language->value] ?? ['body' => ''];

        $query = MessageTemplate::query()
            ->where('company_id', $company->id)
            ->where('key', $key)
            ->where('channel', $channel)
            ->where('language', $language->value);

        // A specialty-specific override wins over a company-wide one, which wins over the built-in default.
        $custom = $specialtyId ? (clone $query)->where('specialty_id', $specialtyId)->first() : null;
        $custom ??= (clone $query)->whereNull('specialty_id')->first();

        $subject = ($custom?->subject !== null && $custom?->subject !== '') ? $custom->subject : ($default['subject'] ?? null);
        $body = ($custom?->body !== null && $custom?->body !== '') ? $custom->body : $default['body'];

        return [
            'subject' => $subject ? $this->substitute($subject, $variables) : null,
            'body' => $this->substitute($body, $variables),
        ];
    }

    /**
     * @param  array<string, string>  $variables
     */
    protected function substitute(string $text, array $variables): string
    {
        $replacements = [];

        foreach ($variables as $name => $value) {
            $replacements['{'.$name.'}'] = $value;
        }

        return strtr($text, $replacements);
    }
}
