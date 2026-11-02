/**
 * WordPress dependencies.
 */
import { useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';

export default function Edit() {
  return (
    <p {...useBlockProps()}>
      {__(
        'Past walks: the walks of the last months that match the Event Filters, newest first, grouped by month, with "Show more walks".',
        'vandrekalender-events'
      )}
    </p>
  );
}
