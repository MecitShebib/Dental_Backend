<?php

namespace App\Support;

/**
 * Static reference data behind the public /api-docs page. Kept as plain PHP
 * (not pulled from routes/api.php at runtime) because documenting a field's
 * *meaning* -- valid enum values, which ID a path param actually expects --
 * needs a human reading each FormRequest/Resource, not route introspection.
 * When routes/api.php changes, update this file's matching group by hand.
 */
class ApiDocumentation
{
    public static function baseUrl(): string
    {
        return rtrim(config('app.url'), '/').'/api';
    }

    public static function enums(): array
    {
        return [
            'AppointmentStatus' => ['scheduled', 'completed', 'no_show', 'cancelled'],
            'AppointmentType' => ['booked', 'unavailable'],
            'AttendanceStatus' => ['attended', 'no_show', 'walk_in'],
            'CapitalTransactionType' => ['injection', 'withdrawal'],
            'ClientGender' => ['male', 'female'],
            'ClientStatus' => ['new', 'under_treatment', 'completed', 'inactive'],
            'ExpenseCategory' => ['dental_supplies', 'lab_fees', 'rent', 'utilities', 'equipment', 'marketing', 'insurance', 'maintenance', 'taxes', 'other'],
            'LabCaseStatus' => ['sent', 'in_progress', 'ready', 'delivered'],
            'LabCaseWorkType' => ['crown', 'bridge', 'denture_full', 'denture_partial', 'veneer', 'retainer', 'night_guard', 'implant_abutment', 'aligner', 'other'],
            'PaymentMethod' => ['cash', 'card', 'bank_transfer'],
            'SubscriptionStatus' => ['active', 'inactive'],
            'UserStatus' => ['active', 'inactive', 'suspended'],
            'Weekday' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
            'appointment duration (minutes)' => ['30', '60', '90'],
        ];
    }

    public static function groups(): array
    {
        return [
            [
                'id' => 'auth',
                'title' => 'Authentication',
                'intro' => 'Every endpoint below except this group requires a Bearer token. Get one either by completing the two-step OTP login flow, or — for outside systems/integrations — by generating a long-lived token from Settings > API Token after logging into the app once.',
                'endpoints' => [
                    [
                        'method' => 'POST', 'path' => '/auth/login', 'auth' => 'Public',
                        'summary' => 'Verifies mobile + password and issues an OTP login challenge (does not return a token yet).',
                        'request' => [
                            ['name' => 'mobile', 'type' => 'string', 'required' => true],
                            ['name' => 'password', 'type' => 'string', 'required' => true],
                            ['name' => 'branch_code', 'type' => 'string', 'required' => false],
                        ],
                        'response' => [
                            ['name' => 'message', 'type' => 'string'],
                            ['name' => 'otp_reference', 'type' => 'string'],
                            ['name' => 'masked_mobile', 'type' => 'string'],
                            ['name' => 'expires_at', 'type' => 'datetime'],
                        ],
                    ],
                    [
                        'method' => 'POST', 'path' => '/auth/login/verify-otp', 'auth' => 'Public',
                        'summary' => 'Confirms the OTP from login, creates a Sanctum token, and logs the user in.',
                        'request' => [
                            ['name' => 'mobile', 'type' => 'string', 'required' => true],
                            ['name' => 'password', 'type' => 'string', 'required' => true],
                            ['name' => 'branch_code', 'type' => 'string', 'required' => false],
                            ['name' => 'otp', 'type' => 'string', 'required' => true],
                            ['name' => 'otp_reference', 'type' => 'string', 'required' => false],
                        ],
                        'response' => [
                            ['name' => 'token', 'type' => 'string', 'notes' => 'Bearer token — send as Authorization: Bearer {token} on every subsequent request.'],
                            ['name' => 'user', 'type' => 'User object'],
                        ],
                    ],
                    [
                        'method' => 'POST', 'path' => '/auth/forgot-password', 'auth' => 'Public',
                        'summary' => 'Issues an OTP to begin a password-reset flow.',
                        'request' => [['name' => 'mobile', 'type' => 'string', 'required' => true]],
                        'response' => [
                            ['name' => 'message', 'type' => 'string'], ['name' => 'otp_reference', 'type' => 'string'],
                            ['name' => 'masked_mobile', 'type' => 'string'], ['name' => 'expires_at', 'type' => 'datetime'],
                        ],
                    ],
                    [
                        'method' => 'POST', 'path' => '/auth/forgot-password/verify-otp', 'auth' => 'Public',
                        'summary' => 'Marks the forgot-password OTP challenge as verified (must precede reset-password).',
                        'request' => [
                            ['name' => 'mobile', 'type' => 'string', 'required' => true],
                            ['name' => 'otp', 'type' => 'string', 'required' => true],
                            ['name' => 'otp_reference', 'type' => 'string', 'required' => false],
                        ],
                        'response' => [['name' => 'message', 'type' => 'string'], ['name' => 'verified', 'type' => 'boolean']],
                    ],
                    [
                        'method' => 'POST', 'path' => '/auth/reset-password', 'auth' => 'Public',
                        'summary' => 'Sets a new password once the forgot-password OTP has been verified.',
                        'request' => [
                            ['name' => 'mobile', 'type' => 'string', 'required' => true],
                            ['name' => 'otp_reference', 'type' => 'string', 'required' => true],
                            ['name' => 'new_password', 'type' => 'string', 'required' => true, 'notes' => 'min 6, must be confirmed by a matching new_password_confirmation field'],
                        ],
                        'response' => [['name' => 'message', 'type' => 'string']],
                    ],
                    [
                        'method' => 'POST', 'path' => '/auth/logout', 'auth' => 'Bearer token',
                        'summary' => 'Revokes the current access token.', 'request' => [], 'response' => [],
                    ],
                    [
                        'method' => 'GET', 'path' => '/auth/me', 'auth' => 'Bearer token',
                        'summary' => "Returns the authenticated user's profile, roles, and permissions.",
                        'request' => [], 'response' => [['name' => '(User object)', 'type' => '', 'notes' => 'see Users group below']],
                    ],
                ],
            ],
            [
                'id' => 'users',
                'title' => 'Users',
                'intro' => 'Staff accounts belonging to your company (doctors, receptionists, managers). Creating/deleting other users, or updating someone other than yourself, requires the acting user to be a system manager or project admin.',
                'endpoints' => [
                    ['method' => 'GET', 'path' => '/doctors', 'auth' => 'Bearer token', 'summary' => 'Lists active doctors.', 'request' => [], 'response' => [['name' => '[User object]', 'type' => 'array']]],
                    ['method' => 'GET', 'path' => '/users', 'auth' => 'Bearer token', 'summary' => "Lists the company's users.", 'request' => [], 'response' => [['name' => '[User object]', 'type' => 'array']]],
                    [
                        'method' => 'POST', 'path' => '/users', 'auth' => 'Bearer token · manager/admin only',
                        'summary' => 'Creates a new staff/doctor user (enforced against the subscription seat limit).',
                        'request' => [
                            ['name' => 'name', 'type' => 'string', 'required' => true],
                            ['name' => 'email', 'type' => 'string', 'required' => true, 'notes' => 'unique'],
                            ['name' => 'phone', 'type' => 'string', 'required' => false],
                            ['name' => 'password', 'type' => 'string', 'required' => true, 'notes' => 'min 6'],
                            ['name' => 'job_title', 'type' => 'string', 'required' => false],
                            ['name' => 'branch_name', 'type' => 'string', 'required' => false],
                            ['name' => 'status', 'type' => 'enum', 'required' => false, 'enum' => 'UserStatus', 'notes' => 'defaults active'],
                            ['name' => 'is_doctor', 'type' => 'boolean', 'required' => false],
                            ['name' => 'notes', 'type' => 'string', 'required' => false],
                            ['name' => 'role_ids', 'type' => 'integer[]', 'required' => false],
                            ['name' => 'permission_ids', 'type' => 'integer[]', 'required' => false],
                        ],
                        'response' => [['name' => '(User object)', 'type' => '', 'status' => 201]],
                    ],
                    ['method' => 'GET', 'path' => '/users/{id}', 'auth' => 'Bearer token', 'summary' => "Fetches a user's profile.", 'request' => [], 'response' => [['name' => '(User object)', 'type' => '']]],
                    [
                        'method' => 'PUT', 'path' => '/users/{id}', 'auth' => 'Bearer token · self, or manager/admin for others',
                        'summary' => "Updates a user's profile/roles/permissions.",
                        'request' => [['name' => '(all fields from POST /users)', 'type' => '', 'notes' => 'all optional/"sometimes"']],
                        'response' => [['name' => '(User object)', 'type' => '']],
                    ],
                    ['method' => 'DELETE', 'path' => '/users/{id}', 'auth' => 'Bearer token · manager/admin only', 'summary' => 'Deletes a user.', 'request' => [], 'response' => []],
                ],
                'object' => [
                    'name' => 'User object', 'fields' => [
                        'id, uuid', 'company_id, company_name', 'name, email, phone (mobile is an alias of phone)',
                        'job_title, branch_name', 'status (UserStatus)', 'is_project_admin, is_doctor', 'notes',
                        'last_login_at', 'roles: [{id, name, slug}]', 'permissions: [{id, name, slug}]',
                    ],
                ],
            ],
            [
                'id' => 'companies',
                'title' => 'Companies & Pricing',
                'intro' => "Your clinic's profile, subscription history, and the price list used for services and odontogram procedures.",
                'endpoints' => [
                    ['method' => 'GET', 'path' => '/companies/{id}', 'auth' => 'Bearer token', 'summary' => 'Fetches company profile with subscription/user-count summary.', 'request' => [], 'response' => [
                        ['name' => 'id, uuid, name, code, email, phone, address, status, notes', 'type' => ''],
                        ['name' => 'users_count, active_users_count', 'type' => 'integer'],
                        ['name' => 'latest_active_subscription', 'type' => 'Subscription object | null'],
                    ]],
                    ['method' => 'GET', 'path' => '/companies/{id}/subscriptions', 'auth' => 'Bearer token', 'summary' => "Lists the company's subscription history.", 'request' => [], 'response' => [
                        ['name' => 'id, company_id, plan_name', 'type' => ''],
                        ['name' => 'status', 'type' => 'enum', 'enum' => 'SubscriptionStatus'],
                        ['name' => 'starts_at, ends_at', 'type' => 'date'],
                        ['name' => 'max_users, active_users, max_ai_tokens, ai_tokens_used, price', 'type' => 'number'],
                        ['name' => 'is_currently_active', 'type' => 'boolean'],
                    ]],
                    ['method' => 'GET', 'path' => '/companies/{id}/treatment-products', 'auth' => 'Bearer token', 'summary' => 'Lists every priced item: company-managed services and odontogram-widget procedures.', 'request' => [], 'response' => [
                        ['name' => 'id, company_id, scope (company|odontogram), code', 'type' => ''],
                        ['name' => 'name, name_ar, name_en, name_tr', 'type' => 'string'],
                        ['name' => 'price, unit_price', 'type' => 'number'],
                        ['name' => 'status', 'type' => 'string', 'enum' => 'active|inactive'],
                    ]],
                    [
                        'method' => 'POST', 'path' => '/companies/{id}/treatment-products', 'auth' => 'Bearer token',
                        'summary' => 'Creates a new company-scoped treatment/service price entry.',
                        'request' => [
                            ['name' => 'code', 'type' => 'string', 'required' => true],
                            ['name' => 'name_ar', 'type' => 'string', 'required' => true],
                            ['name' => 'name_en', 'type' => 'string', 'required' => true],
                            ['name' => 'name_tr', 'type' => 'string', 'required' => false],
                            ['name' => 'price', 'type' => 'number', 'required' => true, 'notes' => 'min 0 (falls back to unit_price)'],
                            ['name' => 'status', 'type' => 'string', 'required' => true, 'enum' => 'active|inactive'],
                        ],
                        'response' => [['name' => '(product object, see index)', 'type' => '', 'status' => 201]],
                    ],
                    ['method' => 'PUT', 'path' => '/companies/{id}/treatment-products/{productId}', 'auth' => 'Bearer token', 'summary' => "Updates a product's price/name/status.", 'request' => [['name' => '(same fields as create)', 'type' => '']], 'response' => [['name' => '(product object)', 'type' => '']]],
                    ['method' => 'DELETE', 'path' => '/companies/{id}/treatment-products/{productId}', 'auth' => 'Bearer token', 'summary' => 'Deletes a treatment product (blocked if still referenced by a treatment record).', 'request' => [], 'response' => []],
                    ['method' => 'GET', 'path' => '/companies/{id}/odontogram-treatment-prices', 'auth' => 'Bearer token', 'summary' => 'A flat {code: price} map of every active odontogram-widget procedure price.', 'request' => [], 'response' => [['name' => '{ "<code>": <price>, ... }', 'type' => 'object']]],
                ],
            ],
            [
                'id' => 'clients',
                'title' => 'Clients (Patients)',
                'intro' => 'Patient records, their legacy per-tooth treatment record, visits, and payments.',
                'endpoints' => [
                    [
                        'method' => 'GET', 'path' => '/clients', 'auth' => 'Bearer token',
                        'summary' => 'Lists/searches patients.',
                        'request' => [
                            ['name' => 'name', 'type' => 'string (query)', 'required' => false, 'notes' => 'partial match'],
                            ['name' => 'phone', 'type' => 'string (query)', 'required' => false, 'notes' => 'partial match'],
                            ['name' => 'per_page', 'type' => 'integer (query)', 'required' => false, 'notes' => '1–100. Omit for a flat array; include for {data, links, meta}.'],
                        ],
                        'response' => [
                            ['name' => 'id, uuid, client_code, name, phone, city', 'type' => ''],
                            ['name' => 'status', 'type' => 'enum', 'enum' => 'ClientStatus'],
                            ['name' => 'last_visit_at', 'type' => 'datetime | null'],
                            ['name' => 'next_appointment', 'type' => 'Appointment object | null'],
                        ],
                    ],
                    [
                        'method' => 'POST', 'path' => '/clients', 'auth' => 'Bearer token', 'summary' => 'Registers a new patient.',
                        'request' => [
                            ['name' => 'client_code', 'type' => 'string', 'required' => false, 'notes' => 'auto-generated (CL-XXXXXXXX) if omitted, must be unique'],
                            ['name' => 'name', 'type' => 'string', 'required' => true],
                            ['name' => 'email', 'type' => 'string', 'required' => false],
                            ['name' => 'phone', 'type' => 'string', 'required' => true],
                            ['name' => 'gender', 'type' => 'enum', 'required' => true, 'enum' => 'ClientGender'],
                            ['name' => 'age', 'type' => 'integer', 'required' => false],
                            ['name' => 'date_of_birth', 'type' => 'date', 'required' => false],
                            ['name' => 'city, address, medical_notes', 'type' => 'string', 'required' => false],
                            ['name' => 'status', 'type' => 'enum', 'required' => false, 'enum' => 'ClientStatus', 'notes' => 'defaults new'],
                        ],
                        'response' => [['name' => '(Client object, see below)', 'type' => '', 'status' => 201]],
                    ],
                    ['method' => 'GET', 'path' => '/clients/{id}', 'auth' => 'Bearer token', 'summary' => "Fetches a patient's full profile.", 'request' => [], 'response' => [['name' => '(Client object)', 'type' => '']]],
                    ['method' => 'PUT', 'path' => '/clients/{id}', 'auth' => 'Bearer token', 'summary' => 'Updates patient details.', 'request' => [['name' => '(same fields as create, all optional)', 'type' => '']], 'response' => [['name' => '(Client object)', 'type' => '']]],
                    ['method' => 'DELETE', 'path' => '/clients/{id}', 'auth' => 'Bearer token', 'summary' => 'Deletes a patient record.', 'request' => [], 'response' => []],
                    ['method' => 'GET', 'path' => '/clients/{id}/treatment-record', 'auth' => 'Bearer token', 'summary' => "Fetches the client's legacy tooth-by-tooth treatment record (auto-created empty on first call).", 'request' => [], 'response' => [
                        ['name' => 'id, uuid, client_id, treatment_plan, currency_code, notes', 'type' => ''],
                        ['name' => 'teeth', 'type' => 'array', 'notes' => '[{id, tooth_number, treatment_catalog_id, treatment_code, treatment_name, unit_price, notes}]'],
                    ]],
                    [
                        'method' => 'PUT', 'path' => '/clients/{id}/treatment-record', 'auth' => 'Bearer token', 'summary' => "Replaces the client's treatment-record tooth line items.",
                        'request' => [
                            ['name' => 'treatment_plan, currency_code, notes', 'type' => 'string', 'required' => false],
                            ['name' => 'teeth', 'type' => 'array', 'required' => false, 'notes' => 'each: {tooth_number, treatment_catalog_id, unit_price, notes}'],
                        ],
                        'response' => [['name' => '(treatment record object)', 'type' => '']],
                    ],
                    ['method' => 'GET', 'path' => '/clients/{id}/visits', 'auth' => 'Bearer token', 'summary' => "Lists a client's visit history.", 'request' => [], 'response' => [['name' => '[Visit object]', 'type' => 'array']]],
                    [
                        'method' => 'POST', 'path' => '/clients/{id}/visits', 'auth' => 'Bearer token', 'summary' => 'Records an ad-hoc visit (walk-in, not tied to check-in).',
                        'request' => [
                            ['name' => 'doctor_id', 'type' => 'integer', 'required' => true],
                            ['name' => 'appointment_id', 'type' => 'integer', 'required' => false],
                            ['name' => 'visit_date', 'type' => 'date', 'required' => true],
                            ['name' => 'start_time', 'type' => 'string (H:i)', 'required' => false],
                            ['name' => 'duration_minutes', 'type' => 'integer', 'required' => false],
                            ['name' => 'summary, notes', 'type' => 'string', 'required' => false],
                            ['name' => 'charge_items', 'type' => 'array', 'required' => false, 'notes' => 'each: {description, amount}'],
                        ],
                        'response' => [['name' => '(Visit object)', 'type' => '', 'status' => 201]],
                    ],
                    ['method' => 'PUT', 'path' => '/visits/{id}', 'auth' => 'Bearer token', 'summary' => 'Edits a visit and its charge items.', 'request' => [
                        ['name' => '(same as create, all optional)', 'type' => ''],
                        ['name' => 'attendance_status', 'type' => 'enum', 'required' => false, 'enum' => 'AttendanceStatus'],
                    ], 'response' => [['name' => '(Visit object)', 'type' => '']]],
                    ['method' => 'DELETE', 'path' => '/visits/{id}', 'auth' => 'Bearer token', 'summary' => 'Deletes a visit and its charges.', 'request' => [], 'response' => []],
                    ['method' => 'GET', 'path' => '/clients/{id}/payments', 'auth' => 'Bearer token', 'summary' => "Lists a client's payments.", 'request' => [], 'response' => [['name' => '[Payment object]', 'type' => 'array']]],
                    [
                        'method' => 'POST', 'path' => '/clients/{id}/payments', 'auth' => 'Bearer token', 'summary' => 'Records a payment (also posts a fund transaction and auto-creates an invoice).',
                        'request' => [
                            ['name' => 'visit_id', 'type' => 'integer', 'required' => false],
                            ['name' => 'payment_date', 'type' => 'date', 'required' => true],
                            ['name' => 'amount', 'type' => 'number', 'required' => true, 'notes' => 'min 0.01'],
                            ['name' => 'payment_method', 'type' => 'enum', 'required' => true, 'enum' => 'PaymentMethod'],
                            ['name' => 'notes', 'type' => 'string', 'required' => false],
                        ],
                        'response' => [['name' => '(Payment object)', 'type' => '', 'status' => 201]],
                    ],
                    ['method' => 'PUT', 'path' => '/payments/{id}', 'auth' => 'Bearer token', 'summary' => 'Edits a payment (also updates the linked fund transaction/invoice).', 'request' => [['name' => '(same as create, all optional)', 'type' => '']], 'response' => [['name' => '(Payment object)', 'type' => '']]],
                    ['method' => 'DELETE', 'path' => '/payments/{id}', 'auth' => 'Bearer token', 'summary' => 'Deletes a payment, its fund transaction, and its invoice.', 'request' => [], 'response' => []],
                    ['method' => 'GET', 'path' => '/clients/{id}/appointments', 'auth' => 'Bearer token', 'summary' => "Lists a client's appointment history.", 'request' => [], 'response' => [['name' => '[Appointment object]', 'type' => 'array']]],
                ],
                'object' => [
                    'name' => 'Client object', 'fields' => [
                        'id, uuid, client_code, name, email, phone', 'gender (ClientGender), age, date_of_birth',
                        'city, address, medical_notes', 'status (ClientStatus)', 'last_visit_at',
                        'next_appointment: Appointment object | null',
                        'financial_summary: { total_services_amount, total_paid_amount, remaining_amount }',
                    ],
                ],
            ],
            [
                'id' => 'invoices',
                'title' => 'Invoices',
                'intro' => 'Invoices are generated automatically as a side effect of the Payments endpoints — there is no create/update/delete endpoint for them directly.',
                'endpoints' => [
                    ['method' => 'GET', 'path' => '/invoices/{id}', 'auth' => 'Bearer token', 'summary' => 'Fetches a single auto-generated invoice.', 'request' => [], 'response' => [
                        ['name' => 'id, uuid, invoice_number, formatted_number', 'type' => 'string', 'notes' => 'formatted_number e.g. INV-000123'],
                        ['name' => 'amount, issued_date', 'type' => ''],
                        ['name' => 'client: {id, name, phone}', 'type' => 'object'],
                        ['name' => 'payment: {id, payment_method, notes}', 'type' => 'object'],
                    ]],
                ],
            ],
            [
                'id' => 'ai-plan',
                'title' => 'AI Treatment Plan',
                'intro' => 'Restricted to doctors and system managers. Turns a case description (typed or transcribed from audio) into a structured, multi-session treatment plan via OpenAI, which is only persisted once the doctor confirms it.',
                'endpoints' => [
                    [
                        'method' => 'POST', 'path' => '/clients/{id}/ai-treatment-plan', 'auth' => 'Bearer token · doctor/manager', 'summary' => 'Generates a draft multi-session plan from a case description (not yet persisted).',
                        'request' => [
                            ['name' => 'description', 'type' => 'string', 'required' => true, 'notes' => 'max 2000 — required unless audio is sent'],
                            ['name' => 'audio', 'type' => 'file', 'required' => false, 'notes' => 'multipart, mp3/wav/m4a/webm/ogg, max 20MB'],
                            ['name' => 'doctor_id', 'type' => 'integer', 'required' => false, 'notes' => 'required if the acting user is not a doctor'],
                        ],
                        'response' => [
                            ['name' => 'diagnosis_summary', 'type' => 'string'],
                            ['name' => 'sessions', 'type' => 'array', 'notes' => 'up to 8, each: {date, start_time, duration_minutes, session_description, odontogram_v2_status}'],
                        ],
                    ],
                    ['method' => 'POST', 'path' => '/clients/{id}/ai-treatment-plan/transcribe', 'auth' => 'Bearer token · doctor/manager', 'summary' => 'Transcribes a voice recording to text (not counted against the AI token cap).', 'request' => [['name' => 'audio', 'type' => 'file', 'required' => true]], 'response' => [['name' => 'description', 'type' => 'string']]],
                    [
                        'method' => 'POST', 'path' => '/clients/{id}/ai-treatment-plan/confirm', 'auth' => 'Bearer token · doctor/manager', 'summary' => 'Persists the (optionally edited) sessions as real, conflict-checked appointments.',
                        'request' => [
                            ['name' => 'doctor_id', 'type' => 'integer', 'required' => false],
                            ['name' => 'sessions', 'type' => 'array', 'required' => true, 'notes' => 'min 1, max 8, each: {date, start_time (H:i), duration_minutes (30|60|90), session_description, odontogram_v2_status (JSON string), charge_items[]}'],
                        ],
                        'response' => [['name' => '[Appointment object]', 'type' => 'array', 'status' => 201]],
                    ],
                    [
                        'method' => 'POST', 'path' => '/clients/{id}/ai-treatment-plan/charge', 'auth' => 'Bearer token · doctor/manager', 'summary' => 'Appends one-off manual charges/discounts on top of an already-confirmed plan.',
                        'request' => [['name' => 'charge_items', 'type' => 'array', 'required' => true, 'notes' => 'min 1, each: {description, amount} — amount can be negative for a discount']],
                        'response' => [['name' => 'total_services_amount, total_paid_amount, remaining_amount', 'type' => 'number', 'status' => 201]],
                    ],
                ],
            ],
            [
                'id' => 'schedule',
                'title' => 'Doctor Schedule & Availability',
                'intro' => "Read a doctor's weekly working hours, or compute free/filled slots for a given day.",
                'endpoints' => [
                    ['method' => 'GET', 'path' => '/doctors/{id}/schedule', 'auth' => 'Bearer token', 'summary' => "Fetches a doctor's weekly schedule (auto-created with 09:00–17:00/30-min defaults if none exists).", 'request' => [], 'response' => [
                        ['name' => 'doctor_id, start_time (H:i), end_time (H:i), slot_minutes', 'type' => ''],
                        ['name' => 'working_days', 'type' => 'enum[]', 'enum' => 'Weekday'],
                    ]],
                    [
                        'method' => 'PUT', 'path' => '/doctors/{id}/schedule', 'auth' => 'Bearer token', 'summary' => "Replaces a doctor's schedule.",
                        'request' => [
                            ['name' => 'start_time, end_time', 'type' => 'string (H:i)', 'required' => true],
                            ['name' => 'slot_minutes', 'type' => 'integer', 'required' => true, 'enum' => '30|60|90'],
                            ['name' => 'working_days', 'type' => 'enum[]', 'required' => true, 'enum' => 'Weekday', 'notes' => 'min 1'],
                        ],
                        'response' => [['name' => '(schedule object)', 'type' => '']],
                    ],
                    ['method' => 'GET', 'path' => '/doctors/{id}/availability', 'auth' => 'Bearer token', 'summary' => 'Renders a full-day slot grid for a scheduling calendar.', 'request' => [['name' => 'date', 'type' => 'date (query)', 'required' => true]], 'response' => [['name' => 'doctor_id, date, slots', 'type' => '', 'notes' => 'slots: [{time, status: free|filled, appointment}]']]],
                    ['method' => 'GET', 'path' => '/doctors/{id}/available-start-times', 'auth' => 'Bearer token', 'summary' => 'Lists valid start times for a new appointment.', 'request' => [
                        ['name' => 'date', 'type' => 'date (query)', 'required' => true],
                        ['name' => 'duration_minutes', 'type' => 'integer (query)', 'required' => true, 'enum' => '30|60|90'],
                    ], 'response' => [['name' => 'start_times', 'type' => 'string[] (H:i)']]],
                    ['method' => 'GET', 'path' => '/doctors/{id}/available-durations', 'auth' => 'Bearer token', 'summary' => 'Given a start time, tells which durations would fit without conflict.', 'request' => [
                        ['name' => 'date', 'type' => 'date (query)', 'required' => true],
                        ['name' => 'start_time', 'type' => 'string H:i (query)', 'required' => true],
                    ], 'response' => [['name' => 'durations', 'type' => 'array', 'notes' => '[{value: 30|60|90, available: boolean}]']]],
                ],
            ],
            [
                'id' => 'appointments',
                'title' => 'Appointments',
                'intro' => 'Booking, rescheduling, and the check-in / no-show actions that turn a scheduled appointment into a Visit.',
                'endpoints' => [
                    ['method' => 'GET', 'path' => '/appointments', 'auth' => 'Bearer token', 'summary' => 'Lists/filters appointments across the clinic.', 'request' => [
                        ['name' => 'date_from, date_to', 'type' => 'date (query)', 'required' => false],
                        ['name' => 'date', 'type' => 'date (query)', 'required' => false, 'notes' => 'used only if date_from/date_to not both given'],
                        ['name' => 'doctor_id, client_id', 'type' => 'integer (query)', 'required' => false],
                        ['name' => 'status', 'type' => 'string (query)', 'required' => false],
                        ['name' => 'per_page', 'type' => 'integer (query)', 'required' => false, 'notes' => '1–100, default 20'],
                    ], 'response' => [['name' => '[Appointment object]', 'type' => 'array']]],
                    [
                        'method' => 'POST', 'path' => '/appointments', 'auth' => 'Bearer token', 'summary' => 'Books a new appointment, or blocks out doctor unavailability.',
                        'request' => [
                            ['name' => 'client_id', 'type' => 'integer', 'required' => false, 'notes' => 'required if type=booked'],
                            ['name' => 'doctor_id', 'type' => 'integer', 'required' => true],
                            ['name' => 'type', 'type' => 'enum', 'required' => true, 'enum' => 'AppointmentType'],
                            ['name' => 'status', 'type' => 'enum', 'required' => false, 'enum' => 'AppointmentStatus', 'notes' => 'defaults scheduled'],
                            ['name' => 'date', 'type' => 'date', 'required' => true],
                            ['name' => 'start_time', 'type' => 'string (H:i)', 'required' => true],
                            ['name' => 'duration_minutes', 'type' => 'integer', 'required' => true, 'enum' => '30|60|90'],
                            ['name' => 'notes, planned_summary', 'type' => 'string', 'required' => false],
                            ['name' => 'charge_items', 'type' => 'array', 'required' => false],
                        ],
                        'response' => [['name' => '(Appointment object)', 'type' => '', 'status' => 201]],
                    ],
                    ['method' => 'GET', 'path' => '/appointments/{id}', 'auth' => 'Bearer token', 'summary' => 'Fetches a single appointment.', 'request' => [], 'response' => [['name' => '(Appointment object)', 'type' => '']]],
                    ['method' => 'PUT', 'path' => '/appointments/{id}', 'auth' => 'Bearer token', 'summary' => 'Reschedules/edits an appointment.', 'request' => [['name' => '(same as create, all optional, plus planned_notes)', 'type' => '']], 'response' => [['name' => '(Appointment object)', 'type' => '']]],
                    ['method' => 'DELETE', 'path' => '/appointments/{id}', 'auth' => 'Bearer token', 'summary' => 'Deletes an appointment and any charges tied to it.', 'request' => [], 'response' => []],
                    [
                        'method' => 'POST', 'path' => '/appointments/{id}/check-in', 'auth' => 'Bearer token', 'summary' => 'Converts a scheduled appointment into a completed Visit.',
                        'request' => [
                            ['name' => 'summary, notes', 'type' => 'string', 'required' => false],
                            ['name' => 'create_payment_after_visit', 'type' => 'boolean', 'required' => false],
                            ['name' => 'charge_items', 'type' => 'array', 'required' => false],
                        ],
                        'response' => [['name' => '(Visit object)', 'type' => ''], ['name' => '', 'type' => '', 'notes' => 'Only scheduled appointments without an existing visit can be checked in.']],
                    ],
                    ['method' => 'POST', 'path' => '/appointments/{id}/no-show', 'auth' => 'Bearer token', 'summary' => 'Marks an appointment as a no-show and clears any charges tied to it.', 'request' => [['name' => 'notes', 'type' => 'string', 'required' => false]], 'response' => [['name' => '(Visit object)', 'type' => '']]],
                ],
                'object' => [
                    'name' => 'Appointment object', 'fields' => [
                        'id, uuid, client_id, client_name, doctor_id, doctor_name',
                        'type (AppointmentType), status (AppointmentStatus)',
                        'date, start_time (H:i), end_time (H:i), duration_minutes',
                        'notes, planned_summary, planned_notes',
                        'action_state: "locked" | "checkin" | "manage" (computed)',
                        'is_past, is_future, is_within_one_hour (booleans)',
                    ],
                ],
            ],
            [
                'id' => 'dashboard',
                'title' => 'Dashboard',
                'endpoints' => [
                    ['method' => 'GET', 'path' => '/dashboard/stats', 'auth' => 'Bearer token', 'summary' => 'Appointment and income stats over a date range, optionally filtered to one doctor.', 'request' => [
                        ['name' => 'date_from, date_to', 'type' => 'date (query)', 'required' => true],
                        ['name' => 'doctor_id', 'type' => 'integer (query)', 'required' => false],
                    ], 'response' => [
                        ['name' => 'appointments.total, appointments.by_status', 'type' => 'object'],
                        ['name' => 'income.total, income.by_method, income.by_day', 'type' => 'object'],
                    ]],
                ],
            ],
            [
                'id' => 'accounting',
                'title' => 'Accounting',
                'intro' => "Every endpoint in this group additionally requires the acting user to be a system manager, accountant, or project admin. Covers the company fund ledger, expenses, capital transactions, and payroll (including each doctor's revenue-share commission).",
                'endpoints' => [
                    ['method' => 'GET', 'path' => '/fund/summary', 'auth' => 'Bearer token · accounting access', 'summary' => 'Range summary of the fund ledger, broken down by source.', 'request' => [['name' => 'from, to', 'type' => 'date (query)', 'required' => false]], 'response' => [
                        ['name' => 'balance, period_total_in, period_total_out, period_net', 'type' => 'number'],
                        ['name' => 'by_source', 'type' => 'object', 'notes' => '{payment, expense, capital, salary_advance, salary_payment}'],
                    ]],
                    ['method' => 'GET', 'path' => '/fund/transactions', 'auth' => 'Bearer token · accounting access', 'summary' => 'Raw ledger of every fund-affecting event.', 'request' => [
                        ['name' => 'source_type', 'type' => 'string (query)', 'required' => false, 'enum' => 'payment|expense|capital|salary_advance|salary_payment'],
                        ['name' => 'from, to, per_page', 'type' => '(query)', 'required' => false],
                    ], 'response' => [['name' => 'id, uuid, source_type, source_id, amount (signed), description, occurred_on, created_by', 'type' => '']]],

                    ['method' => 'GET', 'path' => '/expenses', 'auth' => 'Bearer token · accounting access', 'summary' => 'Lists company expenses.', 'request' => [['name' => 'category, from, to, per_page', 'type' => '(query)', 'required' => false]], 'response' => [['name' => '[Expense object]', 'type' => 'array']]],
                    [
                        'method' => 'POST', 'path' => '/expenses', 'auth' => 'Bearer token · accounting access', 'summary' => 'Records a company expense (posts a negative fund transaction).',
                        'request' => [
                            ['name' => 'category', 'type' => 'enum', 'required' => true, 'enum' => 'ExpenseCategory'],
                            ['name' => 'vendor_name, invoice_number', 'type' => 'string', 'required' => false],
                            ['name' => 'amount', 'type' => 'number', 'required' => true, 'notes' => 'min 0.01'],
                            ['name' => 'expense_date', 'type' => 'date', 'required' => true],
                            ['name' => 'description', 'type' => 'string', 'required' => false],
                            ['name' => 'attachment', 'type' => 'file', 'required' => false, 'notes' => 'multipart, jpg/jpeg/png/pdf, max 10MB'],
                        ],
                        'response' => [['name' => '(Expense object)', 'type' => '', 'status' => 201]],
                    ],
                    ['method' => 'POST', 'path' => '/expenses/{id}', 'auth' => 'Bearer token · accounting access', 'summary' => 'Updates an expense (note: POST, not PUT — supports re-uploading the attachment).', 'request' => [['name' => '(same fields as create, all optional)', 'type' => '']], 'response' => [['name' => '(Expense object)', 'type' => '']]],
                    ['method' => 'DELETE', 'path' => '/expenses/{id}', 'auth' => 'Bearer token · accounting access', 'summary' => 'Deletes an expense and its fund transaction.', 'request' => [], 'response' => []],

                    ['method' => 'GET', 'path' => '/capital-transactions', 'auth' => 'Bearer token · accounting access', 'summary' => 'Lists owner capital injections/withdrawals.', 'request' => [['name' => 'type, per_page', 'type' => '(query)', 'required' => false]], 'response' => [['name' => '[CapitalTransaction object]', 'type' => 'array']]],
                    [
                        'method' => 'POST', 'path' => '/capital-transactions', 'auth' => 'Bearer token · accounting access', 'summary' => 'Records a capital injection or withdrawal (posts a signed fund transaction).',
                        'request' => [
                            ['name' => 'type', 'type' => 'enum', 'required' => true, 'enum' => 'CapitalTransactionType'],
                            ['name' => 'amount', 'type' => 'number', 'required' => true, 'notes' => 'min 0.01'],
                            ['name' => 'party_name', 'type' => 'string', 'required' => false],
                            ['name' => 'transaction_date', 'type' => 'date', 'required' => true],
                            ['name' => 'description', 'type' => 'string', 'required' => false],
                        ],
                        'response' => [['name' => '(CapitalTransaction object)', 'type' => '', 'status' => 201]],
                    ],
                    ['method' => 'PUT', 'path' => '/capital-transactions/{id}', 'auth' => 'Bearer token · accounting access', 'summary' => 'Edits a capital transaction.', 'request' => [['name' => '(same fields as create, all optional)', 'type' => '']], 'response' => [['name' => '(CapitalTransaction object)', 'type' => '']]],
                    ['method' => 'DELETE', 'path' => '/capital-transactions/{id}', 'auth' => 'Bearer token · accounting access', 'summary' => 'Deletes a capital transaction and its fund transaction. (No GET /capital-transactions/{id} — show is not exposed.)', 'request' => [], 'response' => []],

                    ['method' => 'GET', 'path' => '/payroll/employees', 'auth' => 'Bearer token · accounting access', 'summary' => 'Lists employees with their current salary/commission configuration.', 'request' => [], 'response' => [
                        ['name' => 'id, uuid, name, job_title', 'type' => ''],
                        ['name' => 'is_doctor', 'type' => 'boolean'],
                        ['name' => 'monthly_salary, commission_percentage', 'type' => 'number | null'],
                    ]],
                    ['method' => 'PUT', 'path' => '/payroll/employees/{userId}/salary', 'auth' => 'Bearer token · accounting access', 'summary' => "Sets an employee's base salary and (for doctors) commission rate.", 'request' => [
                        ['name' => 'monthly_salary', 'type' => 'number', 'required' => true, 'notes' => 'min 0'],
                        ['name' => 'commission_percentage', 'type' => 'number', 'required' => false, 'notes' => '0–100, doctors only'],
                    ], 'response' => [['name' => '(employee salary object)', 'type' => '']]],

                    ['method' => 'GET', 'path' => '/payroll/salary-advances', 'auth' => 'Bearer token · accounting access', 'summary' => 'Lists salary advances given to employees.', 'request' => [['name' => 'user_id, unsettled_only, per_page', 'type' => '(query)', 'required' => false]], 'response' => [['name' => '[SalaryAdvance object]', 'type' => 'array']]],
                    [
                        'method' => 'POST', 'path' => '/payroll/salary-advances', 'auth' => 'Bearer token · accounting access', 'summary' => 'Records an advance against future salary (posts a negative fund transaction).',
                        'request' => [
                            ['name' => 'user_id', 'type' => 'integer', 'required' => true],
                            ['name' => 'amount', 'type' => 'number', 'required' => true, 'notes' => 'min 0.01'],
                            ['name' => 'advance_date', 'type' => 'date', 'required' => true],
                            ['name' => 'note', 'type' => 'string', 'required' => false],
                        ],
                        'response' => [['name' => '(SalaryAdvance object)', 'type' => '', 'status' => 201]],
                    ],
                    ['method' => 'PUT', 'path' => '/payroll/salary-advances/{id}', 'auth' => 'Bearer token · accounting access', 'summary' => 'Edits an outstanding advance (blocked once settled by a salary payment).', 'request' => [['name' => 'amount, advance_date, note', 'type' => '', 'required' => false]], 'response' => [['name' => '(SalaryAdvance object)', 'type' => '']]],
                    ['method' => 'DELETE', 'path' => '/payroll/salary-advances/{id}', 'auth' => 'Bearer token · accounting access', 'summary' => 'Removes an outstanding advance (blocked once settled).', 'request' => [], 'response' => []],

                    ['method' => 'GET', 'path' => '/payroll/salary-payments', 'auth' => 'Bearer token · accounting access', 'summary' => 'Lists processed salary payments.', 'request' => [['name' => 'user_id, period_year, period_month, per_page', 'type' => '(query)', 'required' => false]], 'response' => [['name' => '[SalaryPayment object]', 'type' => 'array']]],
                    [
                        'method' => 'POST', 'path' => '/payroll/salary-payments', 'auth' => 'Bearer token · accounting access',
                        'summary' => "Processes a month's salary payment: nets base salary + doctor commission against unsettled advances, then posts the remainder to the fund.",
                        'request' => [
                            ['name' => 'user_id', 'type' => 'integer', 'required' => true],
                            ['name' => 'period_year', 'type' => 'integer', 'required' => true, 'notes' => '2000–2100'],
                            ['name' => 'period_month', 'type' => 'integer', 'required' => true, 'notes' => '1–12'],
                            ['name' => 'paid_at', 'type' => 'date', 'required' => true],
                        ],
                        'response' => [['name' => '(SalaryPayment object)', 'type' => '', 'status' => 201, 'notes' => 'employee must have monthly_salary set; the same employee/period cannot be paid twice']],
                    ],
                    ['method' => 'GET', 'path' => '/payroll/salary-payments/{id}', 'auth' => 'Bearer token · accounting access', 'summary' => "Fetches a single payment's breakdown.", 'request' => [], 'response' => [['name' => '(SalaryPayment object)', 'type' => '']]],
                    ['method' => 'PUT', 'path' => '/payroll/salary-payments/{id}', 'auth' => 'Bearer token · accounting access', 'summary' => 'Corrects the paid date — amounts are immutable (delete + recreate to correct those).', 'request' => [['name' => 'paid_at', 'type' => 'date', 'required' => true]], 'response' => [['name' => '(SalaryPayment object)', 'type' => '']]],
                    ['method' => 'DELETE', 'path' => '/payroll/salary-payments/{id}', 'auth' => 'Bearer token · accounting access', 'summary' => 'Reverses a payment: un-settles any advances it absorbed and removes its fund transaction.', 'request' => [], 'response' => []],
                ],
                'object' => [
                    'name' => 'SalaryPayment object', 'fields' => [
                        'id, uuid, user_id, employee_name', 'period_year, period_month',
                        'base_salary, treatment_revenue, commission_percentage, commission_amount, advances_total, net_amount',
                        'paid_at, created_at',
                    ],
                ],
            ],
            [
                'id' => 'lab',
                'title' => 'Dental Lab',
                'intro' => 'Lab partner directory and lab-case tracking (sent → in_progress → ready → delivered). Setting lab_cost on a case automatically syncs an expense to your books.',
                'endpoints' => [
                    ['method' => 'GET', 'path' => '/lab-partners', 'auth' => 'Bearer token', 'summary' => 'Lists external dental lab partners.', 'request' => [], 'response' => [['name' => 'id, uuid, name, phone, email, address, notes, is_active', 'type' => '']]],
                    ['method' => 'POST', 'path' => '/lab-partners', 'auth' => 'Bearer token', 'summary' => 'Adds a new lab partner.', 'request' => [
                        ['name' => 'name', 'type' => 'string', 'required' => true],
                        ['name' => 'phone, email, address, notes', 'type' => 'string', 'required' => false],
                        ['name' => 'is_active', 'type' => 'boolean', 'required' => false],
                    ], 'response' => [['name' => '(LabPartner object)', 'type' => '', 'status' => 201]]],
                    ['method' => 'PUT', 'path' => '/lab-partners/{id}', 'auth' => 'Bearer token', 'summary' => 'Edits a lab partner.', 'request' => [['name' => '(same fields as create)', 'type' => '']], 'response' => [['name' => '(LabPartner object)', 'type' => '']]],
                    ['method' => 'DELETE', 'path' => '/lab-partners/{id}', 'auth' => 'Bearer token', 'summary' => 'Removes a lab partner.', 'request' => [], 'response' => []],

                    ['method' => 'GET', 'path' => '/lab-cases', 'auth' => 'Bearer token', 'summary' => 'Company-wide view of all lab cases across every patient (a lab-workflow dashboard).', 'request' => [
                        ['name' => 'status', 'type' => 'enum (query)', 'required' => false, 'enum' => 'LabCaseStatus'],
                        ['name' => 'doctor_id, per_page', 'type' => '(query)', 'required' => false],
                    ], 'response' => [['name' => '[LabCase object]', 'type' => 'array']]],
                    ['method' => 'GET', 'path' => '/clients/{id}/lab-cases', 'auth' => 'Bearer token', 'summary' => "Lists one patient's lab-case history.", 'request' => [], 'response' => [['name' => '[LabCase object]', 'type' => 'array']]],
                    [
                        'method' => 'POST', 'path' => '/clients/{id}/lab-cases', 'auth' => 'Bearer token', 'summary' => 'Sends a lab case out for a patient.',
                        'request' => [
                            ['name' => 'doctor_id', 'type' => 'integer', 'required' => true],
                            ['name' => 'lab_partner_id, appointment_id', 'type' => 'integer', 'required' => false],
                            ['name' => 'work_type', 'type' => 'enum', 'required' => true, 'enum' => 'LabCaseWorkType'],
                            ['name' => 'teeth', 'type' => 'string[]', 'required' => false],
                            ['name' => 'material', 'type' => 'string', 'required' => false],
                            ['name' => 'shade', 'type' => 'string', 'required' => false],
                            ['name' => 'sent_date', 'type' => 'date', 'required' => true],
                            ['name' => 'expected_return_date', 'type' => 'date', 'required' => false],
                            ['name' => 'lab_cost', 'type' => 'number', 'required' => false, 'notes' => 'min 0 — auto-syncs an expense when set'],
                            ['name' => 'notes', 'type' => 'string', 'required' => false],
                        ],
                        'response' => [['name' => '(LabCase object)', 'type' => '', 'status' => 201]],
                    ],
                    ['method' => 'PUT', 'path' => '/lab-cases/{id}', 'auth' => 'Bearer token', 'summary' => "Updates a lab case's status/details.", 'request' => [
                        ['name' => '(same fields as create, all optional)', 'type' => ''],
                        ['name' => 'status', 'type' => 'enum', 'required' => false, 'enum' => 'LabCaseStatus'],
                        ['name' => 'received_date', 'type' => 'date', 'required' => false],
                    ], 'response' => [['name' => '(LabCase object)', 'type' => '']]],
                    ['method' => 'DELETE', 'path' => '/lab-cases/{id}', 'auth' => 'Bearer token', 'summary' => 'Deletes a lab case (also removes any synced expense).', 'request' => [], 'response' => []],
                ],
                'object' => [
                    'name' => 'LabCase object', 'fields' => [
                        'id, uuid, client_id, client_name, doctor_id, doctor_name',
                        'lab_partner_id, lab_partner_name, appointment_id, appointment_date',
                        'work_type (LabCaseWorkType), teeth, material, shade',
                        'status (LabCaseStatus), sent_date, expected_return_date, received_date',
                        'lab_cost, expense_id, notes, created_at',
                    ],
                ],
            ],
            [
                'id' => 'xray-images',
                'title' => 'X-Ray Images',
                'intro' => 'The shared company gallery an outside X-ray machine (or any integration) posts images into. Images land unlinked by default and get attached to a patient afterward — either by the machine itself (if it knows the client_id) or by staff picking them from the gallery in the app.',
                'endpoints' => [
                    [
                        'method' => 'GET', 'path' => '/xray-images', 'auth' => 'Bearer token', 'summary' => 'Lists the company-wide gallery, optionally scoped to one client or to unlinked-only.',
                        'request' => [
                            ['name' => 'client_id', 'type' => 'integer (query)', 'required' => false, 'notes' => 'only images linked to this client'],
                            ['name' => 'unlinked', 'type' => 'boolean (query)', 'required' => false, 'notes' => 'only images not yet linked to any client'],
                            ['name' => 'per_page', 'type' => 'integer (query)', 'required' => false],
                        ],
                        'response' => [['name' => '[XrayImage object]', 'type' => 'array']],
                    ],
                    [
                        'method' => 'POST', 'path' => '/xray-images', 'auth' => 'Bearer token', 'summary' => "Uploads one or more images. This is the endpoint an X-ray machine's integration token calls.",
                        'request' => [
                            ['name' => 'images', 'type' => 'file[]', 'required' => true, 'notes' => 'multipart, min 1, each: jpg/jpeg/png/webp, max 20MB'],
                            ['name' => 'client_id', 'type' => 'integer', 'required' => false, 'notes' => 'tag a client directly, if already known — usually omitted'],
                            ['name' => 'notes', 'type' => 'string', 'required' => false, 'notes' => 'max 255, applied to every image in this batch'],
                        ],
                        'response' => [['name' => '[XrayImage object]', 'type' => 'array', 'status' => 201]],
                    ],
                    [
                        'method' => 'PUT', 'path' => '/xray-images/{id}', 'auth' => 'Bearer token', 'summary' => 'Links (or unlinks) an image to a client, or edits its note — the "Save" action in the picker.',
                        'request' => [
                            ['name' => 'client_id', 'type' => 'integer | null', 'required' => false, 'notes' => 'send null to unlink'],
                            ['name' => 'notes', 'type' => 'string', 'required' => false],
                        ],
                        'response' => [['name' => '(XrayImage object)', 'type' => '']],
                    ],
                    ['method' => 'DELETE', 'path' => '/xray-images/{id}', 'auth' => 'Bearer token', 'summary' => 'Deletes an image and its stored file.', 'request' => [], 'response' => []],
                ],
                'object' => [
                    'name' => 'XrayImage object', 'fields' => [
                        'id, uuid, client_id, client_name (if linked)',
                        'image_url', 'original_filename, notes', 'uploaded_by, created_at',
                    ],
                ],
            ],
            [
                'id' => 'integrations',
                'title' => 'API Tokens (for integrations)',
                'intro' => 'Manage the long-lived tokens created from Settings > API Token, used to authenticate outside systems (e.g. an X-ray imaging machine) instead of a login session.',
                'endpoints' => [
                    ['method' => 'GET', 'path' => '/settings/api-tokens', 'auth' => 'Bearer token', 'summary' => "Lists the current user's own API tokens (never another user's).", 'request' => [], 'response' => [['name' => 'id, name, created_at, last_used_at', 'type' => ''], ['name' => '', 'type' => '', 'notes' => 'The plain-text token value is never returned again after creation.']]],
                    ['method' => 'POST', 'path' => '/settings/api-tokens', 'auth' => 'Bearer token', 'summary' => 'Creates a new token. Copy the plain-text value immediately — it is shown only this once.', 'request' => [['name' => 'name', 'type' => 'string', 'required' => true, 'notes' => 'max 100 — a label to remember what it\'s for']], 'response' => [
                        ['name' => 'token: {id, name, created_at, last_used_at}', 'type' => 'object'],
                        ['name' => 'plain_text_token', 'type' => 'string', 'notes' => 'use this as the Bearer token — it has the same access as the account that created it'],
                    ]],
                    ['method' => 'DELETE', 'path' => '/settings/api-tokens/{id}', 'auth' => 'Bearer token', 'summary' => 'Revokes a token immediately. Any system still using it loses access right away.', 'request' => [], 'response' => []],
                ],
            ],
        ];
    }
}
