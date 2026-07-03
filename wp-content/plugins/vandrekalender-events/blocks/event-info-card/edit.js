/**
 * WordPress dependencies.
 */
import { useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';

export default function Edit() {
  const blockProps = useBlockProps({
    className: 'vk-info-card vk-info-card--editor',
  });

  return (
    <div {...blockProps}>
      <p>
        🎟️{' '}
        {__(
          'Event Info Card — the price, date, place, and a per-route start/cutoff time switcher render on the published event page.',
          'vandrekalender-events'
        )}
      </p>
    </div>
  );
}
