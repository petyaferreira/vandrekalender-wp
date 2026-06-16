import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';
import './style.scss';

registerBlockType( metadata.name, {
	edit: () => (
		<div className="vk-filters vk-filters--editor">
			<p>🔍 Event Filters — region, length, date, free. Active on the published page.</p>
		</div>
	),
	save: () => null,
} );
