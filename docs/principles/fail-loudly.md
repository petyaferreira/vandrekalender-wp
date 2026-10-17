# Fail loudly at the right layer

Fail at the layer that can name the missing fact and still say how to fix it. A quiet fallback (an empty region, a skipped check) hides which fact was missing, and the problem shows up later somewhere else.

## Example

The geocoder cannot find a municipality code in `data/municipalities.json`. The event is still saved, but the problem is recorded in the Scraper Log with the fix, instead of the event silently getting no region (`class-geocoder.php`):

```php
__( 'Municipality code "%s" is not in data/municipalities.json, so the event gets no region. Add it to the file.', 'vandrekalender-events' ),
```

One layer down, `class-municipalities.php` writes to the server log when the file itself is missing or invalid.

`./validate.sh` follows the same rule: a missing `vendor/` or `node_modules/` is a failed check with the install command, never a skipped one.

## Related

- [Normalized source of truth](normalized-source-of-truth.md)
