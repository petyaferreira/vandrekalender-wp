import { registerPlugin } from '@wordpress/plugins';
import { PluginDocumentSettingPanel } from '@wordpress/editor';
import {
	Button,
	Flex,
	SelectControl,
	TextControl,
	DatePicker,
	__experimentalText as Text,
} from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import {
	format as wpFormat,
	getSettings as getDateSettings,
} from '@wordpress/date';

const POST_TYPE = 'event';

const toISODate = ( dateLike ) => {
	const d = dateLike instanceof Date ? dateLike : new Date( dateLike );
	if ( Number.isNaN( d.getTime() ) ) return '';
	const yyyy = d.getFullYear();
	const mm = String( d.getMonth() + 1 ).padStart( 2, '0' );
	const dd = String( d.getDate() ).padStart( 2, '0' );
	return `${ yyyy }-${ mm }-${ dd }`;
};

const generateRouteId = () => {
	if ( typeof window !== 'undefined' && window.crypto?.randomUUID ) {
		return `route_${ window.crypto.randomUUID() }`;
	}
	return `route_${ Date.now().toString( 36 ) }_${ Math.random()
		.toString( 36 )
		.slice( 2, 10 ) }`;
};

const emptyRoute = () => ( {
	id: generateRouteId(),
	tourId: '',
	startTime: '',
	cutoffTime: '',
	length: '',
	price: '',
} );

const normalizeRoutes = ( raw ) => {
	if ( ! Array.isArray( raw ) ) return [];
	return raw.filter( Boolean ).map( ( r ) => ( {
		id: typeof r?.id === 'string' ? r.id : generateRouteId(),
		tourId: typeof r?.tourId === 'string' ? r.tourId : '',
		startTime: typeof r?.startTime === 'string' ? r.startTime : '',
		cutoffTime: typeof r?.cutoffTime === 'string' ? r.cutoffTime : '',
		length: typeof r?.length === 'string' ? r.length : '',
		price: typeof r?.price === 'string' ? r.price : '',
	} ) );
};

const formatDate = ( iso ) => {
	if ( ! iso ) return '';
	const dateFormat = getDateSettings().formats.date;
	return wpFormat( dateFormat, new Date( `${ iso }T00:00:00` ) );
};

const timeOptions = ( () => {
	const opts = [ { label: __( '—', 'vandrekalender-events' ), value: '' } ];
	for ( let h = 4; h < 24; h++ ) {
		for ( let m = 0; m < 60; m += 30 ) {
			const t = `${ String( h ).padStart( 2, '0' ) }:${ String( m ).padStart( 2, '0' ) }`;
			opts.push( { label: t, value: t } );
		}
	}
	return opts;
} )();

const EventDocumentFields = () => {
	const postType = useSelect(
		( select ) => select( 'core/editor' ).getCurrentPostType(),
		[]
	);

	if ( postType !== POST_TYPE ) return null;

	const meta = useSelect(
		( select ) =>
			select( 'core/editor' ).getEditedPostAttribute( 'meta' ) || {},
		[]
	);

	const { editPost } = useDispatch( 'core/editor' );
	const setMeta = ( patch ) => editPost( { meta: { ...meta, ...patch } } );

	const [ expandedIndex, setExpandedIndex ] = useState( -1 );

	const eventDate = meta.event_date || '';

	const routes = normalizeRoutes( meta.event_routes );
	const setRoutes = ( next ) =>
		setMeta( { event_routes: normalizeRoutes( next ) } );

	const addRoute = () => {
		const next = [ ...routes, emptyRoute() ];
		setRoutes( next );
		setExpandedIndex( next.length - 1 );
	};

	const removeRoute = ( index ) => {
		const next = routes.filter( ( _, i ) => i !== index );
		setRoutes( next );
		if ( expandedIndex === index ) setExpandedIndex( -1 );
		else if ( expandedIndex > index ) setExpandedIndex( expandedIndex - 1 );
	};

	const updateRoute = ( index, patch ) => {
		const next = routes.map( ( r, i ) =>
			i !== index ? r : { ...r, ...patch }
		);
		setRoutes( next );
	};

	return (
		<PluginDocumentSettingPanel
			name="vandrekalender-event-details"
			title={ __( 'Event Details', 'vandrekalender-events' ) }
			className="vandrekalender-event-details"
			initialOpen={ true }
		>
			{ /* ── Event date ── */ }
			<Text
				variant="muted"
				isBlock
				style={ { marginBottom: '4px', fontWeight: 600 } }
			>
				{ __( 'Event Date', 'vandrekalender-events' ) }
			</Text>

			<DatePicker
				currentDate={
					eventDate ? new Date( `${ eventDate }T00:00:00` ) : new Date()
				}
				onChange={ ( newDate ) =>
					setMeta( { event_date: toISODate( newDate ) } )
				}
			/>

			{ eventDate && (
				<Text isBlock style={ { marginBottom: '16px' } }>
					{ formatDate( eventDate ) }
				</Text>
			) }

			{ /* ── Routes ── */ }
			<Text
				variant="muted"
				isBlock
				style={ { marginTop: '8px', marginBottom: '8px', fontWeight: 600 } }
			>
				{ __( 'Routes', 'vandrekalender-events' ) }
			</Text>

			{ routes.length === 0 && (
				<Text variant="muted" isBlock>
					{ __( 'No routes added yet.', 'vandrekalender-events' ) }
				</Text>
			) }

			<Flex direction="column" gap={ 3 }>
				{ routes.map( ( route, index ) => {
					const isExpanded = expandedIndex === index;

					if ( ! isExpanded ) {
						return (
							<Flex
								key={ route.id }
								justify="space-between"
								align="flex-start"
								style={ {
									borderTop: '1px solid #e0e0e0',
									paddingTop: '8px',
								} }
							>
								<Flex direction="column" gap={ 1 }>
									{ route.length && (
										<Text>{ route.length } km</Text>
									) }
									{ route.startTime && (
										<Text>
											{ __(
												'Start:',
												'vandrekalender-events'
											) }{ ' ' }
											{ route.startTime }
										</Text>
									) }
									{ route.price && (
										<Text>{ route.price } kr</Text>
									) }
									{ ( ! route.length || ! route.startTime ) && (
										<Text variant="muted">
											{ __(
												'Incomplete route',
												'vandrekalender-events'
											) }
										</Text>
									) }
								</Flex>

								<Flex gap={ 2 }>
									<Button
										variant="secondary"
										onClick={ () =>
											setExpandedIndex( index )
										}
										__next40pxDefaultSize
									>
										{ __( 'Edit', 'vandrekalender-events' ) }
									</Button>
									<Button
										variant="tertiary"
										onClick={ () => removeRoute( index ) }
										__next40pxDefaultSize
									>
										{ __(
											'Remove',
											'vandrekalender-events'
										) }
									</Button>
								</Flex>
							</Flex>
						);
					}

					return (
						<Flex
							key={ route.id }
							direction="column"
							gap={ 2 }
							style={ {
								borderTop: '1px solid #e0e0e0',
								paddingTop: '8px',
							} }
						>
							<Flex justify="space-between" align="center">
								<Text style={ { fontWeight: 600 } }>
									{ __( 'Route', 'vandrekalender-events' ) }{ ' ' }
									{ index + 1 }
								</Text>
								<Button
									variant="tertiary"
									onClick={ () => removeRoute( index ) }
									__next40pxDefaultSize
								>
									{ __( 'Remove', 'vandrekalender-events' ) }
								</Button>
							</Flex>

							<TextControl
								label={ __(
									'Distance (km)',
									'vandrekalender-events'
								) }
								type="number"
								min="0"
								value={ route.length }
								onChange={ ( value ) =>
									updateRoute( index, { length: value } )
								}
								placeholder="25"
								__next40pxDefaultSize
								__nextHasNoMarginBottom
							/>

							<SelectControl
								label={ __(
									'Start Time',
									'vandrekalender-events'
								) }
								value={ route.startTime }
								options={ timeOptions }
								onChange={ ( value ) =>
									updateRoute( index, { startTime: value } )
								}
								__next40pxDefaultSize
								__nextHasNoMarginBottom
							/>

							<TextControl
								label={ __(
									'Cutoff Time',
									'vandrekalender-events'
								) }
								value={ route.cutoffTime }
								onChange={ ( value ) =>
									updateRoute( index, { cutoffTime: value } )
								}
								placeholder={ __(
									'e.g. 8:00 or 30:00',
									'vandrekalender-events'
								) }
								help={ __(
									'Maximum time allowed to finish the route.',
									'vandrekalender-events'
								) }
								__next40pxDefaultSize
								__nextHasNoMarginBottom
							/>

							<TextControl
								label={ __(
									'Price (DKK)',
									'vandrekalender-events'
								) }
								type="number"
								min="0"
								value={ route.price }
								onChange={ ( value ) =>
									updateRoute( index, { price: value } )
								}
								placeholder="0"
								__next40pxDefaultSize
								__nextHasNoMarginBottom
							/>

							<TextControl
								label={ __( 'Tour ID', 'vandrekalender-events' ) }
								value={ route.tourId }
								onChange={ ( value ) =>
									updateRoute( index, { tourId: value } )
								}
								placeholder="e.g. TOUR-123"
								help={ __(
									'External ID from the organiser or registration system.',
									'vandrekalender-events'
								) }
								__next40pxDefaultSize
								__nextHasNoMarginBottom
							/>

							<Flex
								justify="flex-end"
								style={ { marginTop: '8px' } }
							>
								<Button
									variant="primary"
									onClick={ () => setExpandedIndex( -1 ) }
									__next40pxDefaultSize
								>
									{ __( 'Done', 'vandrekalender-events' ) }
								</Button>
							</Flex>
						</Flex>
					);
				} ) }
			</Flex>

			<Button
				variant="primary"
				onClick={ addRoute }
				style={ { marginTop: '12px', width: '100%' } }
				__next40pxDefaultSize
			>
				{ __( 'Add route', 'vandrekalender-events' ) }
			</Button>
		</PluginDocumentSettingPanel>
	);
};

registerPlugin( 'vandrekalender-event-document-fields', {
	render: EventDocumentFields,
} );
