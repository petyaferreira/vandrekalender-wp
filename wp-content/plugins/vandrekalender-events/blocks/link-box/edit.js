/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import {
  useBlockProps,
  useInnerBlocksProps,
  BlockControls,
  InspectorControls,
  __experimentalColorGradientSettingsDropdown as ColorGradientSettingsDropdown,
  __experimentalUseMultipleOriginColorsAndGradients as useMultipleOriginColorsAndGradients,
  __experimentalLinkControl as LinkControl,
} from '@wordpress/block-editor';
import { ToolbarButton, Popover } from '@wordpress/components';
import { link as linkIcon, linkOff } from '@wordpress/icons';

export default function Edit({
  attributes,
  setAttributes,
  isSelected,
  clientId,
}) {
  const { url, opensInNewTab, hoverTextColor, hoverBackgroundColor } =
    attributes;
  const [isLinkPickerOpen, setIsLinkPickerOpen] = useState(false);
  const colorGradientSettings = useMultipleOriginColorsAndGradients();

  const classes = [];
  const style = {};
  if (hoverTextColor) {
    classes.push('has-hover-text');
    style['--link-box-hover-text'] = hoverTextColor;
  }
  if (hoverBackgroundColor) {
    classes.push('has-hover-background');
    style['--link-box-hover-bg'] = hoverBackgroundColor;
  }

  const blockProps = useBlockProps({ className: classes.join(' '), style });
  const innerBlocksProps = useInnerBlocksProps(blockProps);

  return (
    <>
      <BlockControls group="block">
        <ToolbarButton
          icon={linkIcon}
          title={__('Link', 'vandrekalender-events')}
          onClick={() => setIsLinkPickerOpen(true)}
          isActive={!!url}
        />
        {!!url && (
          <ToolbarButton
            icon={linkOff}
            title={__('Unlink', 'vandrekalender-events')}
            onClick={() => {
              setAttributes({ url: '', opensInNewTab: false });
              setIsLinkPickerOpen(false);
            }}
          />
        )}
      </BlockControls>
      {isSelected && isLinkPickerOpen && (
        <Popover
          placement="bottom"
          onClose={() => setIsLinkPickerOpen(false)}
          focusOnMount="firstElement"
        >
          <LinkControl
            value={{ url, opensInNewTab }}
            onChange={({ url: newUrl = '', opensInNewTab: newTab = false }) =>
              setAttributes({ url: newUrl, opensInNewTab: newTab })
            }
            onRemove={() => {
              setAttributes({ url: '', opensInNewTab: false });
              setIsLinkPickerOpen(false);
            }}
          />
        </Popover>
      )}
      <InspectorControls group="color">
        <ColorGradientSettingsDropdown
          __experimentalIsRenderedInSidebar
          panelId={clientId}
          settings={[
            {
              label: __('Hover text', 'vandrekalender-events'),
              colorValue: hoverTextColor,
              onColorChange: value =>
                setAttributes({ hoverTextColor: value || '' }),
              isShownByDefault: true,
              clearable: true,
              enableAlpha: true,
              resetAllFilter: () => ({ hoverTextColor: '' }),
            },
            {
              label: __('Hover background', 'vandrekalender-events'),
              colorValue: hoverBackgroundColor,
              onColorChange: value =>
                setAttributes({ hoverBackgroundColor: value || '' }),
              isShownByDefault: true,
              clearable: true,
              enableAlpha: true,
              resetAllFilter: () => ({ hoverBackgroundColor: '' }),
            },
          ]}
          {...colorGradientSettings}
        />
      </InspectorControls>
      <div {...innerBlocksProps} />
    </>
  );
}
