# Social OAuth Setup (Google + Facebook)

This project supports only `google` and `facebook` for storefront social login.

## 1) Application Environment Variables

Set these in `.env`:

```env
FACEBOOK_CLIENT_ID=
FACEBOOK_CLIENT_SECRET=
FACEBOOK_CALLBACK_URL="${APP_URL}/customer/social-login/facebook/callback"

GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_CALLBACK_URL="${APP_URL}/customer/social-login/google/callback"
```

## 2) Bagisto Admin Configuration

Go to Admin panel:

- `Configure` -> `Customers` -> `Settings` -> `Social Login`

Enable and fill only:

- Facebook
- Google

Use callback URLs exactly as above (or your production domain variant).

## 3) Google Cloud Console (OAuth 2.0)

1. Open Google Cloud Console and select/create a project.
2. Configure OAuth consent screen.
3. Create OAuth client credentials for a `Web application`.
4. Add authorized redirect URI:
   - `https://your-domain.com/customer/social-login/google/callback`
5. Copy `Client ID` and `Client Secret` into `.env`.

Official references:

- https://developers.google.com/identity/protocols/oauth2/web-server
- https://developers.google.com/workspace/guides/configure-oauth-consent

## 4) Facebook Developers (Meta)

1. Open Meta for Developers and create/select an app.
2. Add the Facebook Login product.
3. In Facebook Login settings, set Valid OAuth Redirect URI:
   - `https://your-domain.com/customer/social-login/facebook/callback`
4. Copy `App ID` and `App Secret` into `.env` as `FACEBOOK_CLIENT_ID` and `FACEBOOK_CLIENT_SECRET`.

Official reference:

- https://developers.facebook.com/docs/facebook-login/web

## 5) Finalize

Run:

```bash
php artisan optimize:clear
```

Then test:

- Sign in with Google
- Sign in with Facebook
