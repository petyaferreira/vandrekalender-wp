import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';

registerBlockType( metadata.name, {
	edit: () => (
		<div>
			<p>Event Calendar — configure in frontend.</p>
		</div>
	),
	save: () => null, // dynamic block rendered server-side
} );
