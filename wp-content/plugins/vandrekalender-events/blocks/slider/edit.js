import { useBlockProps, InnerBlocks } from '@wordpress/block-editor';
import {
  TextControl,
  SelectControl,
  RangeControl,
  PanelBody,
  ColorPalette,
  BaseControl,
} from '@wordpress/components';
import { InspectorControls } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';

function Edit({ attributes, setAttributes }) {
  const {
    slidesPerViewDesktop,
    slidesPerViewMobile,
    behavior,
    progressBarColor,
    marqueeGap,
    marqueeSpeed,
  } = attributes;
  const blockProps = useBlockProps({
    className: `swiper is-${behavior || 'normal'}`,
    style:
      behavior === 'marquee'
        ? { '--marquee-gap': `${marqueeGap}rem` }
        : undefined,
  });
  const ALLOWED_BLOCKS = ['vandrekalender/slider-slide'];

  const themeColors = useSelect(select => {
    const settings = select('core/block-editor').getSettings();
    return settings.colors || [];
  }, []);

  return (
    <div {...blockProps}>
      <InnerBlocks allowedBlocks={ALLOWED_BLOCKS} />
      <InspectorControls>
        <PanelBody
          title={__('Slider settings', 'vandrekalender-events')}
          initialOpen={true}
        >
          <SelectControl
            label={__('Behavior', 'vandrekalender-events')}
            value={behavior}
            options={[
              {
                label: __('Normal slider', 'vandrekalender-events'),
                value: 'normal',
              },
              {
                label: __('Continuous marquee', 'vandrekalender-events'),
                value: 'marquee',
              },
              {
                label: __('Vertical', 'vandrekalender-events'),
                value: 'vertical',
              },
              {
                label: __('Hero with Progress Bar', 'vandrekalender-events'),
                value: 'hero-progress',
              },
            ]}
            onChange={value => setAttributes({ behavior: value })}
            __next40pxDefaultSize
            __nextHasNoMarginBottom
          />

          {behavior === 'hero-progress' && (
            <BaseControl
              label={__('Progress bar color', 'vandrekalender-events')}
              __nextHasNoMarginBottom
            >
              <ColorPalette
                colors={themeColors}
                value={progressBarColor}
                onChange={value =>
                  setAttributes({ progressBarColor: value || '#C2D9B0' })
                }
              />
            </BaseControl>
          )}

          {behavior === 'marquee' && (
            <>
              <RangeControl
                label={__(
                  'Spacing between slides (rem)',
                  'vandrekalender-events'
                )}
                value={marqueeGap}
                onChange={value => setAttributes({ marqueeGap: value ?? 3 })}
                min={0}
                max={8}
                step={0.25}
                __next40pxDefaultSize
                __nextHasNoMarginBottom
              />
              <RangeControl
                label={__('Marquee speed', 'vandrekalender-events')}
                help={__('Higher = slower scroll.', 'vandrekalender-events')}
                value={marqueeSpeed}
                onChange={value =>
                  setAttributes({ marqueeSpeed: value || 6000 })
                }
                min={2000}
                max={20000}
                step={500}
                __next40pxDefaultSize
                __nextHasNoMarginBottom
              />
            </>
          )}

          {behavior === 'normal' && (
            <>
              <TextControl
                label={__('Slides per view (Desktop)', 'vandrekalender-events')}
                type="number"
                value={slidesPerViewDesktop}
                onChange={value =>
                  setAttributes({ slidesPerViewDesktop: Number(value) || 1 })
                }
                __next40pxDefaultSize
                __nextHasNoMarginBottom
              />
              <TextControl
                label={__('Slides per view (Mobile)', 'vandrekalender-events')}
                type="number"
                value={slidesPerViewMobile}
                onChange={value =>
                  setAttributes({ slidesPerViewMobile: Number(value) || 1 })
                }
                __next40pxDefaultSize
                __nextHasNoMarginBottom
              />
            </>
          )}
        </PanelBody>
      </InspectorControls>
    </div>
  );
}

export default Edit;
