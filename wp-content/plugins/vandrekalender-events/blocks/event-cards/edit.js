/**
 * WordPress dependencies.
 */
import { useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';

export default function Edit() {
	const blockProps = useBlockProps( { className: 'vk-cards vk-cards--editor' } );

	return (
		<div { ...blockProps }>
			<p>
				🗂️{ ' ' }
				{ __(
					'Event Cards — events load as a card grid on the published page.',
					'vandrekalender-events'
				) }
			</p>
		</div>
	);
}
