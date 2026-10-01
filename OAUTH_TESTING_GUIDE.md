# OAuth 2.0 / OIDC Testing Guide

This guide explains how to test the OAuth 2.0 Authorization Code flow for the Auth Management System using Postman or any HTTP client.

**Note**: This implementation uses HS256 (HMAC-SHA256) for JWT signing, which is simpler and doesn't require RSA key management. The shared secret is stored on the server and used for both signing and validation.

## Test Client Credentials

- **Client ID**: `ds1_test_client`
- **Client Secret**: `ds1_test_secret_12345`
- **Redirect URI**: `http://localhost:3000/auth/callback` (or `http://localhost:8000/auth/callback`)

## OAuth Flow Overview

```
1. User visits DS1 → DS1 redirects to Auth Management /oauth/authorize
2. Auth Management checks session → If not logged in, show login page
3. User logs in (password, Google, or Facebook)
4. Auth Management generates authorization code → Redirects to DS1 callback
5. DS1 backend exchanges code for tokens (POST to /oauth/token)
6. DS1 validates JWT using JWKS → Creates local session
7. DS1 uses access token to call protected APIs
```

## Step-by-Step Testing

### Step 1: Authorization Request

**Browser URL** (open in your browser):
```
http://localhost:8000/oauth/authorize?client_id=ds1_test_client&redirect_uri=http://localhost:3000/auth/callback&response_type=code&scope=openid+profile+email&state=random_state_123
```

**Parameters:**
- `client_id`: `ds1_test_client`
- `redirect_uri`: `http://localhost:3000/auth/callback`
- `response_type`: `code`
- `scope`: `openid profile email`
- `state`: `random_state_123` (to prevent CSRF)

**Expected Result:**
- If not logged in: Redirects to login page at `/oauth/login`
- If already logged in: Redirects to `http://localhost:3000/auth/callback?code=...&state=random_state_123`

### Step 2: Login (if not already logged in)

If redirected to login page, you can:
1. Login with username/password
2. Login with Google
3. Login with Facebook

After successful login, you'll be redirected back to the redirect URI with an authorization code.

### Step 3: Exchange Authorization Code for Tokens

**POST Request** to:
```
http://localhost:8000/api/oauth/token
```

**Headers:**
```
Content-Type: application/x-www-form-urlencoded
```

**Body (form-urlencoded):**
```
grant_type=authorization_code
&code=YOUR_AUTHORIZATION_CODE_FROM_STEP_2
&redirect_uri=http://localhost:3000/auth/callback
&client_id=ds1_test_client
&client_secret=ds1_test_secret_12345
```

**Expected Response:**
```json
{
  "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...",
  "token_type": "Bearer",
  "expires_in": 3600,
  "refresh_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...",
  "id_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9..."
}
```

### Step 4: Get User Info

**GET Request** to:
```
http://localhost:8000/api/oauth/userinfo
```

**Headers:**
```
Authorization: Bearer YOUR_ACCESS_TOKEN_FROM_STEP_3
```

**Expected Response:**
```json
{
  "sub": "1",
  "email": "user@example.com",
  "email_verified": true,
  "name": "John Doe",
  "given_name": "John",
  "family_name": "Doe"
}
```

### Step 5: Access Protected API

**GET Request** to:
```
http://localhost:8000/api/oauth/test
```

**Headers:**
```
Authorization: Bearer YOUR_ACCESS_TOKEN_FROM_STEP_3
```

**Expected Response:**
```json
{
  "message": "OAuth token is valid!",
  "user_id": "1",
  "email": "user@example.com",
  "name": "John Doe",
  "client_id": "ds1_test_client"
}
```

### Step 6: Refresh Token (Optional)

When the access token expires (after 1 hour), use the refresh token to get a new one.

**POST Request** to:
```
http://localhost:8000/api/oauth/token
```

**Headers:**
```
Content-Type: application/x-www-form-urlencoded
```

**Body (form-urlencoded):**
```
grant_type=refresh_token
&refresh_token=YOUR_REFRESH_TOKEN_FROM_STEP_3
&client_id=ds1_test_client
&client_secret=ds1_test_secret_12345
```

**Expected Response:**
```json
{
  "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...",
  "token_type": "Bearer",
  "expires_in": 3600,
  "refresh_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...",
  "id_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9..."
}
```

### Step 7: Get JWKS (for JWT validation)

**GET Request** to:
```
http://localhost:8000/api/oauth/.well-known/jwks.json
```

**Expected Response:**
```json
{
  "keys": [
    {
      "kty": "RSA",
      "use": "sig",
      "alg": "RS256",
      "n": "...",
      "e": "AQAB",
      "kid": "..."
    }
  ]
}
```

## Postman Collection Setup

1. Create a new collection named "Auth Management OAuth"
2. Add the following requests:

### Request 1: Get Authorization Code
- **Method**: GET
- **URL**: `{{base_url}}/oauth/authorize`
- **Params**:
  - `client_id`: `ds1_test_client`
  - `redirect_uri`: `http://localhost:3000/auth/callback`
  - `response_type`: `code`
  - `scope`: `openid profile email`
  - `state`: `{{$randomUUID}}`
- **Note**: This request should be opened in a browser, not Postman

### Request 2: Exchange Code for Tokens
- **Method**: POST
- **URL**: `{{base_url}}/api/oauth/token`
- **Headers**:
  - `Content-Type`: `application/x-www-form-urlencoded`
- **Body** (x-www-form-urlencoded):
  - `grant_type`: `authorization_code`
  - `code`: `{{auth_code}}`
  - `redirect_uri`: `http://localhost:3000/auth/callback`
  - `client_id`: `ds1_test_client`
  - `client_secret`: `ds1_test_secret_12345`
- **Tests** (to save tokens):
  ```javascript
  var jsonData = pm.response.json();
  pm.environment.set("access_token", jsonData.access_token);
  pm.environment.set("refresh_token", jsonData.refresh_token);
  ```

### Request 3: Get User Info
- **Method**: GET
- **URL**: `{{base_url}}/api/oauth/userinfo`
- **Headers**:
  - `Authorization`: `Bearer {{access_token}}`

### Request 4: Test Protected API
- **Method**: GET
- **URL**: `{{base_url}}/api/oauth/test`
- **Headers**:
  - `Authorization`: `Bearer {{access_token}}`

### Request 5: Refresh Token
- **Method**: POST
- **URL**: `{{base_url}}/api/oauth/token`
- **Headers**:
  - `Content-Type`: `application/x-www-form-urlencoded`
- **Body** (x-www-form-urlencoded):
  - `grant_type`: `refresh_token`
  - `refresh_token`: `{{refresh_token}}`
  - `client_id`: `ds1_test_client`
  - `client_secret`: `ds1_test_secret_12345`
- **Tests** (to update tokens):
  ```javascript
  var jsonData = pm.response.json();
  pm.environment.set("access_token", jsonData.access_token);
  pm.environment.set("refresh_token", jsonData.refresh_token);
  ```

## Environment Variables

Create an environment in Postman with:
- `base_url`: `http://localhost:8000`
- `auth_code`: (manually set after authorization step)
- `access_token`: (auto-set by token exchange)
- `refresh_token`: (auto-set by token exchange)

## Testing with Actual DS1 Frontend

When integrating with a real DS1 frontend:

1. DS1 frontend redirects user to:
   ```
   https://authmanagement.com/oauth/authorize?client_id=ds1_test_client&redirect_uri=https://ds1.authmanagement.com/auth/callback&response_type=code&scope=openid+profile+email&state={{random_state}}
   ```

2. After login, DS1 receives callback with code at:
   ```
   https://ds1.authmanagement.com/auth/callback?code=...&state=...
   ```

3. DS1 backend exchanges code for tokens (server-to-server)

4. DS1 validates JWT using JWKS from:
   ```
   https://authmanagement.com/api/oauth/.well-known/jwks.json
   ```

5. DS1 creates local session and serves dashboard

## Security Notes

- **Always use HTTPS in production**
- **Never expose client_secret in frontend code** (only use on backend)
- **Validate state parameter** to prevent CSRF
- **Use short-lived access tokens** (1 hour is default)
- **Store refresh tokens securely** (httpOnly cookies for web, keychain for mobile)
- **Implement token revocation** for logout

## Troubleshooting

### Error: "Invalid client_id"
- Check that the client exists in the `oauth_clients` table
- Verify the client is active

### Error: "Invalid redirect_uri"
- Ensure the redirect URI matches one of the allowed URIs in the client configuration
- Check for trailing slashes or protocol mismatches (http vs https)

### Error: "Invalid authorization code"
- Authorization codes expire after 10 minutes
- Authorization codes can only be used once
- Ensure the redirect_uri matches the one used in the authorization request

### Error: "Invalid or expired access token"
- Access tokens expire after 1 hour
- Use the refresh token to get a new access token
- Check that the token hasn't been revoked

### Error: "User not found"
- Ensure the user exists in the `users` table
- The user must have authenticated via Auth Management at least once
