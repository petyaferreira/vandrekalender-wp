/**
 * WordPress dependencies.
 */
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, SelectControl } from '@wordpress/components';
import { dateI18n } from '@wordpress/date';
import { __ } from '@wordpress/i18n';

/**
 * The 6 months a direction shows, from today: the same list render.php builds.
 *
 * @param {string} direction 'past' or 'future'.
 * @return {string[]} Chip labels, e.g. "Okt 2026".
 */
function monthLabels(direction) {
  const today = new Date();
  const step = 'past' === direction ? -1 : 1;

  return Array.from({ length: 6 }, (_, i) => {
    const month = new Date(today.getFullYear(), today.getMonth() + step * i, 1);
    const text = dateI18n('M Y', month).replace('.', '');
    return text.charAt(0).toUpperCase() + text.slice(1);
  });
}

export default function Edit({ attributes, setAttributes }) {
  const { direction } = attributes;
  const blockProps = useBlockProps({ className: 'vk-months' });

  return (
    <>
      <InspectorControls>
        <PanelBody title={__('Settings', 'vandrekalender-events')}>
          <SelectControl
            __nextHasNoMarginBottom
            __next40pxDefaultSize
            label={__('Months', 'vandrekalender-events')}
            value={direction}
            options={[
              { label: __('Past', 'vandrekalender-events'), value: 'past' },
              { label: __('Future', 'vandrekalender-events'), value: 'future' },
            ]}
            onChange={value => setAttributes({ direction: value })}
          />
        </PanelBody>
      </InspectorControls>
      <div {...blockProps}>
        <span className="vk-months__heading">
          {__('Jump to month', 'vandrekalender-events')}
        </span>
        <ul className="vk-months__list">
          {monthLabels(direction).map(label => (
            <li key={label}>
              <span className="vk-months__chip">{label}</span>
            </li>
          ))}
        </ul>
      </div>
    </>
  );
}
