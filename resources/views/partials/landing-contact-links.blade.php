{{--
    Doctovaria's own contact details (SUPPORT_EMAIL / SUPPORT_PHONE /
    SUPPORT_WHATSAPP, the same ones the in-app Help & Support page shows) as
    footer links. Renders one <li> per configured value; an empty value is
    skipped. Inline sizes on purpose -- the compiled CSS only carries the
    Tailwind classes other pages already use.

    @param string $locale     en | ar | tr
    @param string $linkClass  classes for each link
--}}
@php
    $support = config('services.support');
    $displayPhone = function (?string $phone): string {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        // +90 551 088 22 39
        return str_starts_with($digits, '90') && strlen($digits) === 12
            ? '+90 '.substr($digits, 2, 3).' '.substr($digits, 5, 3).' '.substr($digits, 8, 2).' '.substr($digits, 10, 2)
            : (string) $phone;
    };
    $contactLabels = [
        'en' => ['email' => 'Email', 'phone' => 'Phone', 'whatsapp' => 'WhatsApp'],
        'ar' => ['email' => 'البريد الإلكتروني', 'phone' => 'الهاتف', 'whatsapp' => 'واتساب'],
        'tr' => ['email' => 'E-posta', 'phone' => 'Telefon', 'whatsapp' => 'WhatsApp'],
    ][$locale] ?? ['email' => 'Email', 'phone' => 'Phone', 'whatsapp' => 'WhatsApp'];
    $contactLinks = array_filter([
        'email' => filled($support['email'] ?? null) ? ['href' => 'mailto:'.$support['email'], 'text' => $support['email']] : null,
        'phone' => filled($support['phone'] ?? null) ? ['href' => 'tel:'.preg_replace('/[^\d+]/', '', $support['phone']), 'text' => $displayPhone($support['phone'])] : null,
        'whatsapp' => filled($support['whatsapp'] ?? null) ? ['href' => 'https://wa.me/'.preg_replace('/\D+/', '', $support['whatsapp']), 'text' => $displayPhone($support['whatsapp'])] : null,
    ]);
@endphp
@foreach ($contactLinks as $kind => $link)
    <li>
        <span class="text-slate-600">{{ $contactLabels[$kind] }}:</span>
        <a href="{{ $link['href'] }}" @if ($kind === 'whatsapp') target="_blank" rel="noopener" @endif class="{{ $linkClass }}" dir="ltr">{{ $link['text'] }}</a>
    </li>
@endforeach
