/**
 * WordPress dependencies.
 */
import { useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';

export default function Edit() {
  return (
    <p {...useBlockProps()}>
      {__(
        'Organiser stats: upcoming walks, regions and distances, computed from the organiser’s walks.',
        'vandrekalender-events'
      )}
    </p>
  );
}
