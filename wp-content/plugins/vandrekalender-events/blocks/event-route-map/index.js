/**
 * WordPress dependencies.
 */
import { registerBlockType } from '@wordpress/blocks';

/**
 * Internal dependencies.
 */
import Edit from './edit';
import metadata from './block.json';
import './style.scss';
import './leaflet/style.css';

registerBlockType(metadata.name, {
  edit: Edit,
});
