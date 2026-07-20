import { useBlockProps, InnerBlocks } from '@wordpress/block-editor';

function Edit() {
  const blockProps = useBlockProps({
    className: 'wp-block-vandrekalender-slider-slide swiper-slide',
  });

  return (
    <div {...blockProps}>
      <InnerBlocks />
    </div>
  );
}

export default Edit;
