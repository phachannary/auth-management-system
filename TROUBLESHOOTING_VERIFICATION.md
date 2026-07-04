# AWS Cognito Email Verification Troubleshooting Guide

## Issue: User status not updating to "Confirmed" after entering 6-digit code

### Root Cause Identified
The `SecretHash` was commented out in the `confirmSignUp` method in `CognitoService.php`. This is required when your Cognito App Client has a client secret configured.

### ✅ Fix Applied
1. **Uncommented SecretHash calculation** in `app/Services/CognitoService.php` line 117-121
2. **Added better error handling** in `app/Http/Controllers/Auth/AuthController.php`
3. **Improved user experience** by pre-filling username after successful verification

### Common Issues and Solutions

#### 1. **Invalid Verification Code Error**
- **Cause**: Code expired (typically 24 hours) or incorrect code entered
- **Solution**: 
  - Request a new verification code
  - Check email spam folder
  - Ensure code is entered correctly (6 digits)

#### 2. **NotAuthorizedException**
- **Cause**: User already confirmed or code expired
- **Solution**: Try logging in directly, the user might already be confirmed

#### 3. **User does not exist**
- **Cause**: Username incorrect or user not registered
- **Solution**: 
  - Verify the exact username used during registration
  - Check if registration was successful

#### 4. **SecretHash Issues**
- **Cause**: App Client has a secret but it's not being included in the request
- **Solution**: Ensure `SecretHash` is calculated and included in all Cognito API calls

### Testing Commands

#### Test Cognito Connection
```bash
php test_cognito.php
```

#### Check User Status
```bash
# Edit check_user_status.php and replace "testuser" with actual username
php check_user_status.php
```

#### Test Verification Process
```bash
# Edit test_verification.php with actual username and code
php test_verification.php
```

### AWS Cognito Configuration Checklist

#### User Pool Settings
- [ ] Email verification is enabled
- [ ] Message customization is set up correctly
- [ ] User pool is in the correct region (ap-southeast-2)

#### App Client Settings
- [ ] Client ID is correct: `441b9ad64ouufh99tcd6f91hum`
- [ ] Client secret is configured in `.env`
- [ ] ALLOW_USER_PASSWORD_AUTH is enabled
- [ ] ALLOW_USER_SRP_AUTH is enabled (if using SRP)
- [ ] ALLOW_REFRESH_TOKEN_AUTH is enabled

#### Email Configuration
- [ ] SES is configured in the same region
- [ ] Email sending is verified
- [ ] From address is verified in SES
- [ ] Email template is properly configured

### Debug Steps

1. **Check Laravel Logs**
   ```bash
   tail -f storage/logs/laravel.log
   ```

2. **Verify Environment Variables**
   ```bash
   php artisan tinker
   >>> env('COGNITO_CLIENT_ID')
   >>> env('COGNITO_CLIENT_SECRET')
   >>> env('COGNITO_USER_POOL_ID')
   ```

3. **Test with AWS CLI**
   ```bash
   aws cognito-idp confirm-sign-up \
     --client-id 441b9ad64ouufh99tcd6f91hum \
     --username testuser \
     --confirmation-code 123456 \
     --region ap-southeast-2
   ```

### Expected Flow After Fix

1. User registers → Receives verification email
2. User enters 6-digit code → Status changes to "CONFIRMED"
3. User can login with username/password
4. User appears in AWS Cognito console with:
   - User Status: CONFIRMED
   - Email Verified: true
   - Enabled: Yes

### If Issues Persist

1. **Check AWS CloudWatch Logs** for Cognito
2. **Verify IAM Permissions** for the AWS credentials
3. **Test with a new user account**
4. **Check email delivery** in AWS SES dashboard

### Contact Support
If the issue persists after applying these fixes, provide:
- Laravel logs from verification attempt
- AWS CloudWatch logs (if available)
- Exact error message received
- Username and timestamp of failed verification
