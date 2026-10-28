/**
 * WordPress dependencies.
 */
import { useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';

export default function Edit() {
  return (
    <p {...useBlockProps()}>
      {__(
        'Past walks: the walks of the last months, newest first, grouped by month, with filters and pages.',
        'vandrekalender-events'
      )}
    </p>
  );
}
