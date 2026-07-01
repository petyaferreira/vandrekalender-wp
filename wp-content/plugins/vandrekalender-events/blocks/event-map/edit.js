/**
 * WordPress dependencies.
 */
import { useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';

export default function Edit() {
  const blockProps = useBlockProps({ className: 'vk-map vk-map--editor' });

  return (
    <div {...blockProps}>
      <p>
        🗺️{' '}
        {__(
          'Event Map — an interactive Denmark map with event pins renders on the published page.',
          'vandrekalender-events'
        )}
      </p>
    </div>
  );
}
