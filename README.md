# Signed URLs WordPress Plugin

A signed URL is a URL that provies limited permission and time to make a request. Signed URLs contain authentication information in their query string, allowing users without credentials to perform specific actions. After you generate a signed URL, anyone who possesses it can use it within a specified period of time.

## Signed URL Example

Say your site hosts private resources only available to certain people, https://private-resources.example.com/dashboard.

Without Signed URLs enabled, any user with the link to your dashboard can see the dashboard. With Signed URLs enabled, only users with the private link can access the dashboard, https://private-resources.example.com/dashboard?expiresAt=2020-01-01T09:30+00:00&signature=726b6174686a6e613834686d6e. You can also see a `expiresAt` parameter in this URL which indicates when the URL is no longer considered valid.

## Parameters

`https://example.com?expiresAt=2020-01-01T09:30+00:00&signature=2fdf08aa44660dd8332131f0cc46ab9df025304ac750724d63964fa32d1d2db4`

- `expiresAt` - A ISO 8601 formatted timestamp which indicates the point in time when the URL is no longer valid
- `signature` - A hexidecimal hash generated using the URL along with the secret signing key
- `returnTo` - A URL to use in place of the HTTP Referrer header when adding a menu item to return back to where you came from
