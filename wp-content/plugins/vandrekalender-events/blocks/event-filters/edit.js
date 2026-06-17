/**
 * WordPress dependencies.
 */
import { useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';

export default function Edit() {
	const blockProps = useBlockProps( { className: 'vk-filters vk-filters--editor' } );

	return (
		<div { ...blockProps }>
			<p>
				🔍{ ' ' }
				{ __(
					'Event Filters — region, length, date, free. Active on the published page.',
					'vandrekalender-events'
				) }
			</p>
		</div>
	);
}
