{{--
    Optional features this subscription includes (Subscription::FEATURES).
    $useOld: true only for the form that just failed validation (the page
    holds several subscription modals). $selected: the subscription's saved `features` (null = legacy row, all on)
    or null for a new subscription (all ticked by default).
    `features_submitted` lets the request tell "nothing ticked" apart from
    "form didn't send the field".
--}}
@php
    $featureLabels = [
        'consent_templates' => 'Consent templates',
        'api_tokens' => 'API tokens',
        'whatsapp' => 'WhatsApp',
        'crm' => 'Zoho CRM',
        'call_webhook' => 'Call webhook link',
    ];
    $checked = ($useOld ?? false) ? (array) old('features', []) : ($selected ?? array_keys($featureLabels));
@endphp
<fieldset class="subscription-features" style="border: 1px solid var(--border); border-radius: 10px; padding: .75rem 1rem;">
    <legend class="muted" style="padding: 0 .35rem;">Included features</legend>
    <input type="hidden" name="features_submitted" value="1">
    @foreach ($featureLabels as $feature => $label)
        <label class="checkbox-option">
            <input type="checkbox" name="features[]" value="{{ $feature }}" @checked(in_array($feature, $checked, true))>
            {{ $label }}
        </label>
    @endforeach
</fieldset>
