@php
    $locale = in_array($locale ?? 'en', ['en', 'ar', 'tr'], true) ? $locale : 'en';
    $isRtl = $locale === 'ar';
    $isSubmitted = $survey->isSubmitted();

    $t = [
        'en' => [
            'title' => 'Rate your visit', 'question' => 'How was your experience?',
            'waitTime' => 'Wait time', 'staff' => 'Staff friendliness', 'cleanliness' => 'Cleanliness',
            'optional' => 'optional',
            'commentLabel' => 'Any additional comments? (optional)', 'submit' => 'Submit',
            'thanksTitle' => 'Thank you!', 'thanksBody' => 'Your feedback has been recorded. We appreciate you taking the time.',
        ],
        'ar' => [
            'title' => 'قيّم زيارتك', 'question' => 'كيف كانت تجربتك؟',
            'waitTime' => 'وقت الانتظار', 'staff' => 'لطف الطاقم', 'cleanliness' => 'النظافة',
            'optional' => 'اختياري',
            'commentLabel' => 'أي ملاحظات إضافية؟ (اختياري)', 'submit' => 'إرسال',
            'thanksTitle' => 'شكرًا لك!', 'thanksBody' => 'تم تسجيل تقييمك. نقدّر وقتك.',
        ],
        'tr' => [
            'title' => 'Ziyaretinizi değerlendirin', 'question' => 'Deneyiminiz nasıldı?',
            'waitTime' => 'Bekleme süresi', 'staff' => 'Personel ilgisi', 'cleanliness' => 'Temizlik',
            'optional' => 'isteğe bağlı',
            'commentLabel' => 'Ek yorumunuz var mı? (isteğe bağlı)', 'submit' => 'Gönder',
            'thanksTitle' => 'Teşekkürler!', 'thanksBody' => 'Geri bildiriminiz kaydedildi. Zaman ayırdığınız için teşekkür ederiz.',
        ],
    ][$locale];
@endphp
<!doctype html>
<html lang="{{ $locale }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $t['title'] }} — {{ $companyName }}</title>
    <link rel="icon" type="image/png" href="/favicon.png">
    <style>
        body { margin: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif; background: #f4f7f6; color: #1f2a24; }
        .wrap { max-width: 480px; margin: 0 auto; padding: 24px 16px 64px; }
        .brand { text-align: center; padding: 16px 0 24px; font-size: 20px; font-weight: 700; color: #0f9d6c; }
        .card { background: #fff; border: 1px solid #e2e8e5; border-radius: 14px; padding: 24px; text-align: center; }
        .card h2 { margin-top: 0; }
        .stars { display: flex; flex-direction: row-reverse; justify-content: center; gap: 4px; margin: 20px 0; }
        .stars input { display: none; }
        .stars label { font-size: 40px; color: #d7e0dc; cursor: pointer; transition: color .1s; }
        .stars input:checked ~ label,
        .stars label:hover,
        .stars label:hover ~ label { color: #f5b301; }
        .sub-rating { margin: 14px 0; }
        .sub-rating .sub-rating-label { font-size: 13px; color: #5b6b63; margin-bottom: 4px; }
        .sub-rating .sub-rating-label em { font-style: normal; color: #9aa8a1; }
        .sub-rating .stars label { font-size: 22px; }
        hr.divider { border: none; border-top: 1px solid #eef2f0; margin: 20px 0; }
        textarea { width: 100%; padding: 12px 14px; border: 1px solid #d7e0dc; border-radius: 10px; font-size: 15px; margin: 12px 0; box-sizing: border-box; font-family: inherit; }
        .btn { width: 100%; padding: 13px; border-radius: 10px; border: none; background: #0f9d6c; color: #fff; font-size: 15px; font-weight: 600; cursor: pointer; }
        .btn:disabled { opacity: .5; }
        label.field-label { display: block; text-align: {{ $isRtl ? 'right' : 'left' }}; font-size: 13px; color: #5b6b63; margin-bottom: 6px; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="brand">{{ $companyName }}</div>

        @if ($isSubmitted)
            <div class="card">
                <h2>{{ $t['thanksTitle'] }}</h2>
                <p>{{ $t['thanksBody'] }}</p>
            </div>
        @else
            <div class="card">
                <h2>{{ $t['question'] }}</h2>
                <form method="POST" action="{{ route('survey.submit', $survey->token) }}">
                    @csrf
                    <div class="stars">
                        @for ($value = 5; $value >= 1; $value--)
                            <input type="radio" name="rating" id="star{{ $value }}" value="{{ $value }}" required>
                            <label for="star{{ $value }}">★</label>
                        @endfor
                    </div>

                    <hr class="divider">

                    @foreach (['wait_time_rating' => 'waitTime', 'staff_rating' => 'staff', 'cleanliness_rating' => 'cleanliness'] as $field => $labelKey)
                        <div class="sub-rating">
                            <div class="sub-rating-label">{{ $t[$labelKey] }} <em>({{ $t['optional'] }})</em></div>
                            <div class="stars">
                                @for ($value = 5; $value >= 1; $value--)
                                    <input type="radio" name="{{ $field }}" id="{{ $field }}{{ $value }}" value="{{ $value }}">
                                    <label for="{{ $field }}{{ $value }}">★</label>
                                @endfor
                            </div>
                        </div>
                    @endforeach

                    <hr class="divider">

                    <label class="field-label" for="comment">{{ $t['commentLabel'] }}</label>
                    <textarea id="comment" name="comment" rows="3"></textarea>
                    <button type="submit" class="btn">{{ $t['submit'] }}</button>
                </form>
            </div>
        @endif
    </div>
</body>
</html>
