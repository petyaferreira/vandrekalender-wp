/**
 * WordPress dependencies.
 */
import { useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';

export default function Edit() {
  const blockProps = useBlockProps({
    className: 'vk-calendar vk-calendar--editor',
  });

  return (
    <div {...blockProps}>
      <p>
        📅{' '}
        {__(
          'Event Calendar — a month grid with event dots renders on the published page.',
          'vandrekalender-events'
        )}
      </p>
    </div>
  );
}
