# Signed URLs WordPress Plugin

A signed URL is a URL that provies limited permission and time to make a request. Signed URLs contain authentication information in their query string, allowing users without credentials to perform specific actions. After you generate a signed URL, anyone who possesses it can use it within a specified period of time.

## Installing

1. Download the plugin to your plugins directory
```bash
cd wp-content/plugins/
wget https://github.com/mkornatz/wp-signed-urls/archive/master.zip
```
2. Activate the plugin on the WP Admin Plugins page
3. Configure the plugin settings

## Signed URL Example

Say your site hosts private resources only available to certain people, https://private-resources.example.com/dashboard.

Without Signed URLs enabled, any user with the link to your dashboard can see the dashboard. With Signed URLs enabled, only users with the private link can access the dashboard:

 > https://private-resources.example.com/dashboard?expiresAt=2020-01-01T09:30+00:00&signature=726b6174686a6e613834686d6e

You can also see a `expiresAt` parameter in this URL which indicates when the URL is no longer considered valid.

## Parameters

> https://example.com?expiresAt=2020-01-01T09:30+00:00&returnTo=https%3A%2F%2Fexample.com&signature=2fdf08aa44660dd8332131f0cc46ab9df025304ac750724d63964fa32d1d2db4

- `expiresAt` - A ISO 8601 formatted timestamp which indicates the point in time when the URL is no longer valid
- `signature` - A hexidecimal hash generated using the URL along with the secret signing key
- `returnTo` - A URL -- which must be URL encodeded to work properly -- to use in place of the HTTP Referrer header when adding a menu item to return back to where you came from

## Generating a URL Signature

To generate a signature, you must concatenate the URL string (without the signature parameter) with the pre-shared key and use the resulting string to generate a SHA256 hash.

```php
$secret_signing_key = '788e2b5c5ac5a2fc2880ec87b5326f5a82f6d6c7865f13f12c5a7ffa0';
$url = 'https://example.com/?page=example&size=123';

$signature = hash('sha256', $url . $secret_signing_key);

$final_signed_url = $url . '&signature=' . $signature;

// $final_signed_url is "https://example.com/?page=example&size=123&signature=2e9208a59dd2ff9ac181d21c27c855166f88237552ff5ae681a8fba028d82007"
```

Note:

- Signatures are different depending on the case of the URL. For example, https://example.com will generate a different signature than HTTPS://EXAMPLE.COM.
- The signing key must be appended to the end of the URL when hashing (not prepended)
