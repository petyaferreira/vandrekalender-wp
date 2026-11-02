/**
 * WordPress dependencies.
 */
import { useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';

export default function Edit() {
  return (
    <p {...useBlockProps()}>
      {__(
        'Past tours: the organiser’s last three past walks, newest first, and a link to all of them.',
        'vandrekalender-events'
      )}
    </p>
  );
}
