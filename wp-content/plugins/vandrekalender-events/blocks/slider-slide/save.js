import { InnerBlocks, useBlockProps } from '@wordpress/block-editor';

function Save() {
  const blockProps = useBlockProps.save({
    className: 'wp-block-vandrekalender-slider-slide swiper-slide',
  });

  return (
    <div {...blockProps}>
      <InnerBlocks.Content />
    </div>
  );
}

export default Save;
