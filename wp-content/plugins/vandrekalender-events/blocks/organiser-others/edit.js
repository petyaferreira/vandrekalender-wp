/**
 * WordPress dependencies.
 */
import { useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';

export default function Edit() {
  return (
    <p {...useBlockProps()}>
      {__(
        'Other organisers: up to six other organisers with a page, those with walks in the same regions first.',
        'vandrekalender-events'
      )}
    </p>
  );
}
