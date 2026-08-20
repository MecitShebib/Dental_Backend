@php
    $locale = in_array($locale ?? 'en', ['en', 'ar', 'tr'], true) ? $locale : 'en';
    $isRtl = $locale === 'ar';

    $t = [
        'en' => [
            'title' => 'Book an appointment', 'step1' => 'Choose a doctor', 'step2' => 'Choose a date and time',
            'step3' => 'Your details', 'name' => 'Full name', 'phone' => 'Phone number', 'email' => 'Email (optional)',
            'date' => 'Date', 'no_slots' => 'No available times on this date. Try another day.',
            'confirm' => 'Confirm booking', 'back' => 'Back', 'success_title' => 'Appointment confirmed',
            'success_body' => 'We look forward to seeing you. A confirmation has been sent to your phone.',
            'book_another' => 'Book another appointment', 'loading' => 'Loading...', 'error_generic' => 'Something went wrong. Please try again.',
            'no_doctors' => 'Online booking is not available for this clinic right now.',
            'required' => 'This field is required.',
        ],
        'ar' => [
            'title' => 'احجز موعدًا', 'step1' => 'اختر الطبيب', 'step2' => 'اختر التاريخ والوقت',
            'step3' => 'بياناتك', 'name' => 'الاسم الكامل', 'phone' => 'رقم الهاتف', 'email' => 'البريد الإلكتروني (اختياري)',
            'date' => 'التاريخ', 'no_slots' => 'لا توجد أوقات متاحة في هذا التاريخ. جرب يومًا آخر.',
            'confirm' => 'تأكيد الحجز', 'back' => 'رجوع', 'success_title' => 'تم تأكيد الموعد',
            'success_body' => 'بانتظار زيارتكم. تم إرسال تأكيد إلى هاتفك.',
            'book_another' => 'حجز موعد آخر', 'loading' => 'جارٍ التحميل...', 'error_generic' => 'حدث خطأ ما. يرجى المحاولة مرة أخرى.',
            'no_doctors' => 'الحجز الإلكتروني غير متاح لهذه العيادة حاليًا.',
            'required' => 'هذا الحقل مطلوب.',
        ],
        'tr' => [
            'title' => 'Randevu alın', 'step1' => 'Doktor seçin', 'step2' => 'Tarih ve saat seçin',
            'step3' => 'Bilgileriniz', 'name' => 'Ad soyad', 'phone' => 'Telefon numarası', 'email' => 'E-posta (isteğe bağlı)',
            'date' => 'Tarih', 'no_slots' => 'Bu tarihte uygun saat yok. Başka bir gün deneyin.',
            'confirm' => 'Randevuyu onayla', 'back' => 'Geri', 'success_title' => 'Randevu onaylandı',
            'success_body' => 'Sizi görmekten mutluluk duyarız. Telefonunuza bir onay gönderildi.',
            'book_another' => 'Başka bir randevu al', 'loading' => 'Yükleniyor...', 'error_generic' => 'Bir şeyler ters gitti. Lütfen tekrar deneyin.',
            'no_doctors' => 'Bu klinik için online randevu şu anda kullanılamıyor.',
            'required' => 'Bu alan zorunludur.',
        ],
    ][$locale];
@endphp
<!doctype html>
<html lang="{{ $locale }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $t['title'] }} — {{ $company->name }}</title>
    <link rel="icon" type="image/png" href="/favicon.png">
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif; background: #f4f7f6; color: #1f2a24; }
        .wrap { max-width: 520px; margin: 0 auto; padding: 24px 16px 64px; }
        .brand { text-align: center; padding: 16px 0 24px; font-size: 20px; font-weight: 700; color: #0f9d6c; }
        .card { background: #fff; border: 1px solid #e2e8e5; border-radius: 14px; padding: 20px; margin-bottom: 16px; }
        .step-title { font-size: 15px; font-weight: 600; margin: 0 0 12px; color: #1f2a24; }
        .doctor-list, .time-grid { display: flex; flex-wrap: wrap; gap: 8px; }
        .pill { border: 1px solid #d7e0dc; background: #fff; border-radius: 999px; padding: 10px 16px; font-size: 14px; cursor: pointer; color: #1f2a24; }
        .pill.selected { background: #0f9d6c; border-color: #0f9d6c; color: #fff; }
        .pill:disabled { opacity: .4; cursor: not-allowed; }
        input[type="date"], input[type="text"], input[type="tel"], input[type="email"] {
            width: 100%; padding: 12px 14px; border: 1px solid #d7e0dc; border-radius: 10px; font-size: 15px; margin-bottom: 12px;
        }
        label { display: block; font-size: 13px; color: #5b6b63; margin-bottom: 6px; }
        .btn { width: 100%; padding: 13px; border-radius: 10px; border: none; background: #0f9d6c; color: #fff; font-size: 15px; font-weight: 600; cursor: pointer; }
        .btn:disabled { opacity: .5; cursor: not-allowed; }
        .btn-link { background: none; color: #5b6b63; text-decoration: underline; padding: 8px 0; font-size: 13px; }
        .muted { color: #5b6b63; font-size: 13px; }
        .error { color: #c0392b; font-size: 13px; margin-top: 4px; }
        .hidden { display: none !important; }
        .hp-field { position: absolute; left: -9999px; top: -9999px; }
        .success { text-align: center; padding: 24px 0; }
        .success h2 { color: #0f9d6c; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="brand">{{ $company->name }}</div>

        <div id="app"></div>
    </div>

    <script>
        (function () {
            var t = @json($t);
            var isRtl = @json($isRtl);
            var slug = @json($company->booking_slug);
            var apiBase = '/api/public/companies/' + encodeURIComponent(slug);
            var app = document.getElementById('app');

            var state = { doctors: [], doctorId: null, date: null, time: null };

            function el(tag, attrs, children) {
                var node = document.createElement(tag);
                attrs = attrs || {};
                Object.keys(attrs).forEach(function (key) {
                    if (key === 'text') node.textContent = attrs[key];
                    else if (key === 'html') node.innerHTML = attrs[key];
                    else node.setAttribute(key, attrs[key]);
                });
                (children || []).forEach(function (child) { node.appendChild(child); });
                return node;
            }

            function render() {
                app.innerHTML = '';
                app.appendChild(renderDoctorStep());
                if (state.doctorId) app.appendChild(renderDateTimeStep());
                if (state.doctorId && state.time) app.appendChild(renderDetailsStep());
            }

            function renderDoctorStep() {
                var card = el('div', { class: 'card' });
                card.appendChild(el('p', { class: 'step-title', text: t.step1 }));

                if (!state.doctors.length) {
                    card.appendChild(el('p', { class: 'muted', text: t.no_doctors }));
                    return card;
                }

                var list = el('div', { class: 'doctor-list' });
                state.doctors.forEach(function (doctor) {
                    var pill = el('button', {
                        type: 'button',
                        class: 'pill' + (state.doctorId === doctor.id ? ' selected' : ''),
                        text: doctor.name,
                    });
                    pill.addEventListener('click', function () {
                        state.doctorId = doctor.id;
                        state.date = null;
                        state.time = null;
                        render();
                    });
                    list.appendChild(pill);
                });
                card.appendChild(list);
                return card;
            }

            function renderDateTimeStep() {
                var card = el('div', { class: 'card' });
                card.appendChild(el('p', { class: 'step-title', text: t.step2 }));

                var label = el('label', { text: t.date });
                var dateInput = el('input', { type: 'date', min: todayIso() });
                if (state.date) dateInput.value = state.date;
                dateInput.addEventListener('change', function () {
                    state.date = dateInput.value;
                    state.time = null;
                    loadAvailability();
                });
                card.appendChild(label);
                card.appendChild(dateInput);

                var slotsWrap = el('div', { class: 'time-grid', id: 'slots-wrap' });
                card.appendChild(slotsWrap);

                if (state.date) {
                    renderSlots(slotsWrap);
                }

                return card;
            }

            function renderSlots(container) {
                container.innerHTML = '';
                if (state.loadingSlots) {
                    container.appendChild(el('p', { class: 'muted', text: t.loading }));
                    return;
                }
                if (state.slots && !state.slots.length) {
                    container.appendChild(el('p', { class: 'muted', text: t.no_slots }));
                    return;
                }
                (state.slots || []).forEach(function (time) {
                    var pill = el('button', {
                        type: 'button',
                        class: 'pill' + (state.time === time ? ' selected' : ''),
                        text: time,
                    });
                    pill.addEventListener('click', function () {
                        state.time = time;
                        render();
                    });
                    container.appendChild(pill);
                });
            }

            function renderDetailsStep() {
                var card = el('div', { class: 'card' });
                card.appendChild(el('p', { class: 'step-title', text: t.step3 }));

                var form = el('form');
                var nameLabel = el('label', { text: t.name });
                var nameInput = el('input', { type: 'text', id: 'client_name', required: 'required' });
                var phoneLabel = el('label', { text: t.phone });
                var phoneInput = el('input', { type: 'tel', id: 'client_phone', required: 'required' });
                var emailLabel = el('label', { text: t.email });
                var emailInput = el('input', { type: 'email', id: 'client_email' });

                var honeypot = el('div', { class: 'hp-field' });
                var honeypotLabel = el('label', { text: 'Website', for: 'website' });
                var honeypotInput = el('input', { type: 'text', id: 'website', name: 'website', tabindex: '-1', autocomplete: 'off' });
                honeypot.appendChild(honeypotLabel);
                honeypot.appendChild(honeypotInput);

                var errorBox = el('p', { class: 'error hidden', id: 'form-error' });
                var submit = el('button', { type: 'submit', class: 'btn', text: t.confirm });

                form.appendChild(nameLabel);
                form.appendChild(nameInput);
                form.appendChild(phoneLabel);
                form.appendChild(phoneInput);
                form.appendChild(emailLabel);
                form.appendChild(emailInput);
                form.appendChild(honeypot);
                form.appendChild(errorBox);
                form.appendChild(submit);

                form.addEventListener('submit', function (event) {
                    event.preventDefault();
                    submit.disabled = true;
                    errorBox.classList.add('hidden');

                    fetch(apiBase + '/book', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                        body: JSON.stringify({
                            doctor_id: state.doctorId,
                            date: state.date,
                            start_time: state.time,
                            client_name: nameInput.value,
                            client_phone: phoneInput.value,
                            client_email: emailInput.value || null,
                            website: honeypotInput.value,
                        }),
                    })
                        .then(function (response) {
                            if (!response.ok) {
                                return response.json().then(function (body) { throw body; });
                            }
                            return response.json();
                        })
                        .then(function () {
                            renderSuccess();
                        })
                        .catch(function (body) {
                            submit.disabled = false;
                            var message = (body && body.errors && Object.values(body.errors)[0] && Object.values(body.errors)[0][0])
                                || (body && body.message)
                                || t.error_generic;
                            errorBox.textContent = message;
                            errorBox.classList.remove('hidden');
                        });
                });

                card.appendChild(form);
                return card;
            }

            function renderSuccess() {
                app.innerHTML = '';
                var wrap = el('div', { class: 'card success' });
                wrap.appendChild(el('h2', { text: t.success_title }));
                wrap.appendChild(el('p', { class: 'muted', text: t.success_body }));
                var again = el('button', { type: 'button', class: 'btn', text: t.book_another });
                again.addEventListener('click', function () {
                    state = { doctors: state.doctors, doctorId: null, date: null, time: null };
                    render();
                });
                wrap.appendChild(again);
                app.appendChild(wrap);
            }

            function todayIso() {
                var d = new Date();
                return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
            }

            function loadAvailability() {
                state.loadingSlots = true;
                state.slots = null;
                render();

                fetch(apiBase + '/availability?doctor_id=' + state.doctorId + '&date=' + state.date, { headers: { Accept: 'application/json' } })
                    .then(function (response) { return response.json(); })
                    .then(function (body) {
                        state.loadingSlots = false;
                        state.slots = (body.data && body.data.free_times) || [];
                        render();
                    })
                    .catch(function () {
                        state.loadingSlots = false;
                        state.slots = [];
                        render();
                    });
            }

            fetch(apiBase + '/doctors', { headers: { Accept: 'application/json' } })
                .then(function (response) { return response.json(); })
                .then(function (body) {
                    state.doctors = (body.data) || [];
                    render();
                })
                .catch(function () {
                    render();
                });

            render();
        })();
    </script>
</body>
</html>
