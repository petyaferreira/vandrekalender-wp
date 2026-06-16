import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';
import './style.scss';

registerBlockType( metadata.name, {
	edit: () => (
		<div className="vk-calendar vk-calendar--editor">
			<p>📅 Event Calendar — events load on the published page.</p>
		</div>
	),
	save: () => null, // dynamic block rendered server-side
} );
