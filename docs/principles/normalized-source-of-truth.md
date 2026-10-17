# Normalized source of truth

Keep each fact in one place. Anything that can be computed from it is computed when it is read, not stored as a second copy that has to be kept in sync.

## Example

Route prices live in the `event_routes` meta. "Is this event free?" is computed from them every time (`Vandrekalender_Event_Rest_Api::is_event_free()`), and there is no `event_is_free` meta:

```php
return empty( $prices ) || 0.0 === min( $prices );
```

Docs once described `is_free` as a stored field "derived on save". If you see `event_is_free` used as meta anywhere, it is stale. Details: [data-model.md → is_free](../data-model.md).

The same goes for code: "which language is this post" is answered in one place, `trait-polylang-language.php`, which every class that matches languages uses, instead of three copies that could drift.

## Related

- [Fail loudly at the right layer](fail-loudly.md)
