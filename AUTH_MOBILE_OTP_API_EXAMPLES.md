# Mobile OTP Authentication API

## 1. Request login OTP

`POST /api/auth/login`

```json
{
  "mobile": "963955123456",
  "password": "secret",
  "branch_code": "DAM-01"
}
```

```json
{
  "message": "OTP sent successfully",
  "otp_reference": "login_otp_ref_123",
  "masked_mobile": "********3456",
  "expires_at": "2026-05-14T15:30:00Z"
}
```

## 2. Verify login OTP

`POST /api/auth/login/verify-otp`

```json
{
  "mobile": "963955123456",
  "password": "secret",
  "branch_code": "DAM-01",
  "otp": "123456",
  "otp_reference": "login_otp_ref_123"
}
```

```json
{
  "token": "sanctum_token_here",
  "user": {
    "id": 15,
    "uuid": "....",
    "company_id": 5,
    "company_name": "Dental HQ",
    "version": 2,
    "name": "Clinic Admin",
    "email": "admin@clinic.com",
    "mobile": "+963955123456",
    "phone": "+963955123456",
    "job_title": "System Manager",
    "branch_name": "Damascus",
    "status": "active",
    "is_project_admin": false,
    "is_doctor": false,
    "notes": null,
    "last_login_at": "2026-05-14 18:00:00",
    "roles": [],
    "permissions": []
  }
}
```

## 3. Request forgot password OTP

`POST /api/auth/forgot-password`

```json
{
  "mobile": "963955123456"
}
```

```json
{
  "message": "OTP sent successfully",
  "otp_reference": "forgot_otp_ref_123",
  "masked_mobile": "********3456",
  "expires_at": "2026-05-14T15:30:00Z"
}
```

## 4. Verify forgot password OTP

`POST /api/auth/forgot-password/verify-otp`

```json
{
  "mobile": "963955123456",
  "otp": "123456",
  "otp_reference": "forgot_otp_ref_123"
}
```

```json
{
  "message": "OTP verified successfully",
  "verified": true
}
```

## 5. Reset password after OTP verification

`POST /api/auth/reset-password`

```json
{
  "mobile": "963955123456",
  "otp_reference": "forgot_otp_ref_123",
  "new_password": "new-secret",
  "new_password_confirmation": "new-secret"
}
```

```json
{
  "message": "Password reset successfully"
}
```

## Validation Errors

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "mobile": [
      "The provided credentials are incorrect."
    ]
  }
}
```
