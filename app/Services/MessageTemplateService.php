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
    public function render(Company $company, string $key, string $channel, ClientLanguage $language, array $variables): array
    {
        $default = MessageTemplateDefaults::all()[$key][$channel][$language->value] ?? ['body' => ''];

        $custom = MessageTemplate::query()
            ->where('company_id', $company->id)
            ->where('key', $key)
            ->where('channel', $channel)
            ->where('language', $language->value)
            ->first();

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
