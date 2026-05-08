import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';

registerBlockType( metadata.name, {
	edit: () => (
		<div>
			<p>Event Filters — region, distance, difficulty, date.</p>
		</div>
	),
	save: () => null,
} );
