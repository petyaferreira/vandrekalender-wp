/**
 * WordPress dependencies.
 */
import { useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';

export default function Edit() {
  const blockProps = useBlockProps({
    className: 'vk-route-map vk-route-map--editor',
  });

  return (
    <div {...blockProps}>
      <p>
        🥾{' '}
        {__(
          'Event Route Map — renders the GPX track(s) attached to this event’s routes on the published page. Shows nothing here or on the front end if no route has a GPX file.',
          'vandrekalender-events'
        )}
      </p>
    </div>
  );
}
