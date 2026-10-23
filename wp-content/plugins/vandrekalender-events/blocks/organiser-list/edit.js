/**
 * WordPress dependencies.
 */
import { useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';

export default function Edit({ attributes: { show } }) {
  return (
    <p {...useBlockProps()}>
      {'all' === show
        ? __(
            'Organiser list: every organiser with a page, by name, with its upcoming walks and regions.',
            'vandrekalender-events'
          )
        : __(
            'Other organisers: up to six other organisers with a page, those with walks in the same regions first.',
            'vandrekalender-events'
          )}
    </p>
  );
}
