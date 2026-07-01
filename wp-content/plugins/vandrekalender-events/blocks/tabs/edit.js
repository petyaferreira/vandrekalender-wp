/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';
import { useBlockProps, InnerBlocks, RichText } from '@wordpress/block-editor';
import { withSelect, withDispatch } from '@wordpress/data';
import { createBlock } from '@wordpress/blocks';
import { useEffect } from '@wordpress/element';
import { compose } from '@wordpress/compose';

const TEMPLATE = [
  ['vandrekalender/tab', { label: __('Tab 1', 'vandrekalender-events') }],
  ['vandrekalender/tab', { label: __('Tab 2', 'vandrekalender-events') }],
];

/**
 * Build a unique, slug-like id from a label.
 *
 * @param {string}   label       The tab label.
 * @param {string[]} existingIds Ids already in use.
 * @return {string} A unique id.
 */
const generateUniqueId = (label, existingIds) => {
  const baseId = (label || 'tab').toLowerCase().replace(/[^a-z0-9]/g, '-');
  let uniqueId = baseId;
  let counter = 1;

  while (existingIds.includes(uniqueId)) {
    uniqueId = `${baseId}-${counter}`;
    counter++;
  }

  return uniqueId;
};

function Edit({
  clientId,
  attributes,
  setAttributes,
  innerBlocks,
  setInnerBlockAttributes,
  selectBlock,
  insertBlock,
}) {
  const blockProps = useBlockProps();

  // Ensure every tab has a unique id, and open the first tab by default.
  useEffect(() => {
    const taken = innerBlocks.map(block => block.attributes.id).filter(Boolean);

    innerBlocks.forEach(block => {
      if (block.attributes.id === '') {
        const newId = generateUniqueId(block.attributes.label, taken);
        taken.push(newId);
        setInnerBlockAttributes(block.clientId, { attributes: { id: newId } });
      }
    });

    const ids = innerBlocks.map(block => block.attributes.id).filter(Boolean);
    if (ids.length && !ids.includes(attributes.activeTabId)) {
      setAttributes({ activeTabId: ids[0] });
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [innerBlocks.length]);

  const openTab = block => {
    setAttributes({ activeTabId: block.attributes.id });
    if (block.innerBlocks.length === 0) {
      insertBlock(createBlock('core/paragraph'), null, block.clientId);
    }
  };

  const navItemClassName = 'wp-block-vandrekalender-tabs__navigation-item';

  return (
    <div {...blockProps}>
      <div className="wp-block-vandrekalender-tabs__navigation">
        <div className="wp-block-vandrekalender-tabs__navigation-inner">
          {innerBlocks.map((block, index) => (
            <RichText
              key={index}
              tagName="div"
              value={block.attributes.label}
              allowedFormats={[]}
              className={
                block.attributes.id === attributes.activeTabId
                  ? `${navItemClassName} ${navItemClassName}--active`
                  : navItemClassName
              }
              onChange={label =>
                setInnerBlockAttributes(block.clientId, {
                  attributes: {
                    label,
                    id: generateUniqueId(
                      label,
                      innerBlocks
                        .filter(b => b.clientId !== block.clientId)
                        .map(b => b.attributes.id)
                    ),
                  },
                })
              }
              onClick={() => openTab(block)}
            />
          ))}
          <Button
            variant="secondary"
            size="small"
            className="wp-block-vandrekalender-tabs__navigation-add"
            label={__('Add tab', 'vandrekalender-events')}
            showTooltip
            onClick={() => {
              insertBlock(
                createBlock('vandrekalender/tab', {
                  label: __('Tab', 'vandrekalender-events'),
                }),
                innerBlocks.length,
                clientId
              );
              selectBlock(clientId);
            }}
          >
            +
          </Button>
        </div>
      </div>
      <InnerBlocks allowedBlocks={['vandrekalender/tab']} template={TEMPLATE} />
    </div>
  );
}

export default compose([
  withSelect((select, props) => ({
    innerBlocks: select('core/block-editor').getBlocks(props.clientId),
  })),
  withDispatch(dispatch => ({
    setInnerBlockAttributes: (clientId, attributes) =>
      dispatch('core/block-editor').updateBlock(clientId, attributes),
    insertBlock: (block, index, clientId) =>
      dispatch('core/block-editor').insertBlock(block, index, clientId),
    selectBlock: clientId =>
      dispatch('core/block-editor').selectBlock(clientId),
  })),
])(Edit);
