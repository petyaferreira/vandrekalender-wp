/**
 * WordPress dependencies.
 */
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

export default function Edit({ attributes, setAttributes }) {
  const { walks } = attributes;
  const blockProps = useBlockProps({
    className: 'vk-filters vk-filters--editor',
  });

  return (
    <>
      <InspectorControls>
        <PanelBody title={__('Settings', 'vandrekalender-events')}>
          <SelectControl
            __nextHasNoMarginBottom
            __next40pxDefaultSize
            label={__('Walks', 'vandrekalender-events')}
            help={__(
              'Upcoming: for the calendar, cards and map. Past: for the Past Walks block; the region and organiser lists and the dates then cover the walks of the last months.',
              'vandrekalender-events'
            )}
            value={walks}
            options={[
              {
                label: __('Upcoming', 'vandrekalender-events'),
                value: 'upcoming',
              },
              { label: __('Past', 'vandrekalender-events'), value: 'past' },
            ]}
            onChange={value => setAttributes({ walks: value })}
          />
        </PanelBody>
      </InspectorControls>
      <div {...blockProps}>
        <p>
          🔍{' '}
          {'past' === walks
            ? __(
                'Event Filters for past walks — region, organiser, length, date, free. Active on the published page.',
                'vandrekalender-events'
              )
            : __(
                'Event Filters — region, organiser, length, date, free. Active on the published page.',
                'vandrekalender-events'
              )}
        </p>
      </div>
    </>
  );
}
