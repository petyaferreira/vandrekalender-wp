/**
 * WordPress dependencies.
 */
import { useBlockProps, InnerBlocks } from '@wordpress/block-editor';

export default function Edit( { attributes, context } ) {
	const blockProps = useBlockProps();

	return (
		<div { ...blockProps }>
			<div
				className="wp-block-vandrekalender-tab__content"
				hidden={ context[ 'vandrekalender/activeTabId' ] !== attributes.id }
			>
				<InnerBlocks />
			</div>
		</div>
	);
}
