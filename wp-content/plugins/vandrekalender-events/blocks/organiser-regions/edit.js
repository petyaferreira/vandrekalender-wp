/**
 * WordPress dependencies.
 */
import { useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';

export default function Edit() {
  return (
    <p {...useBlockProps()}>
      {__(
        'Organiser regions: the regions with the organiser’s upcoming walks and their counts, each linking to the walks there.',
        'vandrekalender-events'
      )}
    </p>
  );
}
