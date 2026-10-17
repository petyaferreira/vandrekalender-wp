# Two-way and one-way doors

A two-way door is cheap to reverse: ship it and change it if it is wrong. A one-way door is expensive to undo once it reaches production: a public URL, a stored data shape, deleted data. Spend the review there, and ask Petya before walking through one.

## Example

Events moved from `/event/{slug}/` to `/begivenhed/{slug}/`. Google had hundreds of the old URLs indexed and they returned 404, so the old URL base can never really be dropped. `class-legacy-event-urls.php` exists only to pay for that:

```php
 * Permanently redirects the old /event/{slug}/ URL base to the current
 * /begivenhed/{slug}/ one.
 * ...
 * Google still has hundreds of the old URLs indexed, and they 404 today.
```

## Which door is it

- **One-way:** URL slugs and permalink structure, the shape of stored meta (for example the `event_routes` JSON), deleting or drafting events on production (#37), user and attendee data, anything a search engine or another site already links to.
- **Two-way:** CSS, copy, templates, docs, `./validate.sh` checks, CI steps.

## Related

- [Clean up after every change](cleanup.md): a one-way door still needs the old way cleaned up, carefully
