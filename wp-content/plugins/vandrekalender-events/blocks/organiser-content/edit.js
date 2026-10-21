/**
 * WordPress dependencies.
 */
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, SelectControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

export default function Edit({ attributes, setAttributes }) {
  const { part } = attributes;
  const blockProps = useBlockProps();

  return (
    <>
      <InspectorControls>
        <PanelBody title={__('Settings', 'vandrekalender-events')}>
          <SelectControl
            __nextHasNoMarginBottom
            __next40pxDefaultSize
            label={__('Part', 'vandrekalender-events')}
            value={part}
            options={[
              { label: __('Hero', 'vandrekalender-events'), value: 'hero' },
              { label: __('About', 'vandrekalender-events'), value: 'about' },
              { label: __('FAQ', 'vandrekalender-events'), value: 'faq' },
            ]}
            onChange={value => setAttributes({ part: value })}
          />
        </PanelBody>
      </InspectorControls>
      <div {...blockProps}>
        <p>
          {sprintf(
            /* translators: %1$s: page part: hero, about or faq. */
            __(
              'Organiser Content — on an organiser page, its theme file patterns/organiser-<slug>-%1$s.php renders here.',
              'vandrekalender-events'
            ),
            part
          )}
        </p>
      </div>
    </>
  );
}
