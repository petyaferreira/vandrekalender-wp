import { registerPlugin } from '@wordpress/plugins';
import { PluginDocumentSettingPanel } from '@wordpress/editor';
import { MediaUpload, MediaUploadCheck } from '@wordpress/block-editor';
import {
  BaseControl,
  Button,
  Flex,
  Icon,
  SelectControl,
  TextControl,
  DatePicker,
  Modal,
  Spinner,
  __experimentalText as Text,
} from '@wordpress/components';
import { useSelect, useDispatch, dispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { useState, useEffect, useRef } from '@wordpress/element';
import {
  format as wpFormat,
  getSettings as getDateSettings,
} from '@wordpress/date';
import { check } from '@wordpress/icons';
import municipalities from '../../data/municipalities.json';
import { utmToLatLng } from './utm-to-latlng';

// event_region and event_length are auto-assigned on save — hide their panels.
dispatch('core/editor').removeEditorPanel('taxonomy-panel-event_region');
dispatch('core/editor').removeEditorPanel('taxonomy-panel-event_length');

const POST_TYPE = 'event';
const EMPTY_META = {};
const ADRESSEVAELGER = 'https://adressevaelger.dk';
// Passed in from PHP (VANDREKALENDER_ADRESSEVAELGER_TOKEN). Empty when the
// constant is not defined — the panel then says search is not configured.
const ADDRESS_TOKEN = window.vandrekalenderAddressSearch?.token || '';

// ── Helpers ───────────────────────────────────────────────────────────────────

const toISODate = dateLike => {
  const d = dateLike instanceof Date ? dateLike : new Date(dateLike);
  if (Number.isNaN(d.getTime())) return '';
  const yyyy = d.getFullYear();
  const mm = String(d.getMonth() + 1).padStart(2, '0');
  const dd = String(d.getDate()).padStart(2, '0');
  return `${yyyy}-${mm}-${dd}`;
};

const generateRouteId = () => `route_${window.crypto.randomUUID()}`;

// Keeps the Replace/Remove buttons from being squeezed off the row by a
// long GPX filename — short names show in full, longer ones are cut to
// 10 characters plus an ellipsis.
const truncateFilename = (name, max = 10) =>
  name.length > max ? `${name.slice(0, max)}…` : name;

const emptyRoute = () => ({
  id: generateRouteId(),
  distance_km: '',
  start_time: '',
  cutoff_time: '',
  price: '',
  gpx_id: '',
  gpx_source_url: '',
  gpx_name: '',
});

const normalizeRoutes = raw => {
  if (!Array.isArray(raw)) return [];
  return raw.filter(Boolean).map(r => ({
    id: typeof r?.id === 'string' ? r.id : generateRouteId(),
    distance_km: typeof r?.distance_km === 'string' ? r.distance_km : '',
    start_time: typeof r?.start_time === 'string' ? r.start_time : '',
    cutoff_time: typeof r?.cutoff_time === 'string' ? r.cutoff_time : '',
    price: typeof r?.price === 'string' ? r.price : '',
    gpx_id: typeof r?.gpx_id === 'string' ? r.gpx_id : '',
    // Set by scrapers only; the editor never shows or edits it, but must
    // still round-trip it unchanged — routes save as one meta array, so
    // dropping it here would erase the scraper's re-download guard on the
    // next editor save (see docs/route-gpx-plan.md).
    gpx_source_url:
      typeof r?.gpx_source_url === 'string' ? r.gpx_source_url : '',
    gpx_name: typeof r?.gpx_name === 'string' ? r.gpx_name : '',
  }));
};

// Parse a coordinate pair in the formats events are shared with:
// "56.052777, 9.749856" (Facebook) or "56.8036° N, 9.0192° E" (degree +
// hemisphere, Danish Ø/V accepted). Dot decimals; comma, semicolon, or
// space between. S and W/V flip the sign.
const parseCoords = text => {
  const m = String(text)
    .trim()
    .match(
      /^(-?\d{1,3}(?:\.\d+)?)\s*°?\s*([NSns])?(?:\s*[,;]\s*|\s+)(-?\d{1,3}(?:\.\d+)?)\s*°?\s*([EWØVewøv])?$/
    );
  if (!m) return null;
  const lat = parseFloat(m[1]) * (/s/i.test(m[2] || '') ? -1 : 1);
  const lng = parseFloat(m[3]) * (/[wv]/i.test(m[4] || '') ? -1 : 1);
  if (Math.abs(lat) > 90 || Math.abs(lng) > 180) return null;
  return { lat, lng };
};

const adressevaelgerUrl = (path, params = {}) =>
  `${ADRESSEVAELGER}${path}?${new URLSearchParams({
    ...params,
    token: ADDRESS_TOKEN,
  })}`;

// Fetch JSON and throw on anything but a 200 with a JSON body, so a dead
// endpoint shows up in the panel instead of silently returning nothing.
const fetchJson = async url => {
  const res = await fetch(url);
  if (!res.ok) throw new Error(`HTTP ${res.status}`);
  return res.json();
};

const formatDate = iso => {
  if (!iso) return '';
  const dateFormat = getDateSettings().formats.date;
  // Noon, not midnight — see the DatePicker comment below.
  return wpFormat(dateFormat, new Date(`${iso}T12:00:00`));
};

const timeOptions = (() => {
  const opts = [{ label: __('—', 'vandrekalender-events'), value: '' }];
  for (let h = 4; h < 24; h++) {
    for (let m = 0; m < 60; m += 30) {
      const t = `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`;
      opts.push({ label: t, value: t });
    }
  }
  return opts;
})();

// ── Location panel ────────────────────────────────────────────────────────────

const LocationPanel = ({ meta, setMeta }) => {
  const [suggestions, setSuggestions] = useState([]);
  const [loading, setLoading] = useState(false);
  const [open, setOpen] = useState(false);
  const [searchError, setSearchError] = useState('');
  // While the user is typing coordinates the field shows their raw text;
  // otherwise it mirrors the stored meta values.
  const [coordsDraft, setCoordsDraft] = useState(null);
  const debounceRef = useRef(null);
  const coordsDebounceRef = useRef(null);
  const wrapperRef = useRef(null);
  // Bumped on every keystroke and request, so a slow response for older
  // input never overwrites newer results.
  const requestRef = useRef(0);
  const setMetaRef = useRef(setMeta);
  setMetaRef.current = setMeta;

  const unavailable = __(
    'Address search is unavailable. Try again later, or paste coordinates below.',
    'vandrekalender-events'
  );

  // Close dropdown when clicking outside.
  useEffect(() => {
    const onClickOutside = e => {
      if (wrapperRef.current && !wrapperRef.current.contains(e.target)) {
        setOpen(false);
      }
    };
    document.addEventListener('mousedown', onClickOutside);
    return () => document.removeEventListener('mousedown', onClickOutside);
  }, []);

  const search = async text => {
    const request = ++requestRef.current;
    setLoading(true);
    try {
      const data = await fetchJson(
        adressevaelgerUrl('/husnumre/soeg', { tekst: text, maksimum: 8 })
      );
      if (!Array.isArray(data?.fund)) throw new Error('No fund array');
      if (request !== requestRef.current) return;
      setSuggestions(data.fund.filter(f => typeof f?.titel === 'string'));
      setSearchError('');
      setOpen(true);
    } catch {
      if (request !== requestRef.current) return;
      setSuggestions([]);
      setOpen(false);
      setSearchError(unavailable);
    } finally {
      if (request === requestRef.current) setLoading(false);
    }
  };

  const onQueryChange = value => {
    // Clear derived fields when the user edits the address manually.
    setMeta({
      event_address: value,
      event_lat: 0,
      event_lng: 0,
      event_municipality: '',
    });

    clearTimeout(debounceRef.current);
    requestRef.current++;
    setLoading(false);

    if (value.length < 3 || !ADDRESS_TOKEN) {
      setSuggestions([]);
      setOpen(false);
      return;
    }

    debounceRef.current = setTimeout(() => search(value), 300);
  };

  const onSelect = async suggestion => {
    const { type, id, titel } = suggestion;
    // Suggestions stay clickable while a search for newer input is still
    // pending. Cancel it, or it would fire after the pick and its request
    // would make this pick's lookup look stale and get dropped.
    clearTimeout(debounceRef.current);
    setCoordsDraft(null);
    setMeta({
      event_address: titel,
      event_lat: 0,
      event_lng: 0,
      event_municipality: '',
    });

    // A street (with or without postcode) is not a finished address:
    // search again with its text so the list shows its house numbers, and
    // the user can keep typing.
    if (type !== 'husnummer' || !id) {
      search(titel);
      return;
    }

    setOpen(false);
    setSuggestions([]);
    const request = ++requestRef.current;
    setLoading(true);
    try {
      const data = await fetchJson(
        adressevaelgerUrl(`/husnumre/${encodeURIComponent(id)}`)
      );
      const point = data?.husnummer?.adgangspunkt?.koordinater;
      if (!Number.isFinite(point?.x) || !Number.isFinite(point?.y)) {
        throw new Error('No coordinates');
      }
      if (request !== requestRef.current) return;
      const { lat, lng } = utmToLatLng(point.x, point.y);
      const kode = data.husnummer.navngivenvejkommunedel?.kommune;
      setMetaRef.current({
        event_lat: lat,
        event_lng: lng,
        event_municipality: municipalities[kode]?.name || '',
      });
      setSearchError('');
    } catch {
      if (request === requestRef.current) setSearchError(unavailable);
    } finally {
      if (request === requestRef.current) setLoading(false);
    }
  };

  const onCoordsChange = value => {
    setCoordsDraft(value);
    clearTimeout(coordsDebounceRef.current);

    // An emptied field removes the pin. It is not a coordinate pair, so
    // without this the meta would never change and the post would not
    // become dirty (Save stays disabled).
    if (!value.trim()) {
      setMeta({ event_lat: 0, event_lng: 0 });
      setCoordsDraft(null);
      return;
    }

    const parsed = parseCoords(value);
    if (!parsed) return;

    const moved =
      parsed.lat !== Number(meta.event_lat) ||
      parsed.lng !== Number(meta.event_lng);

    coordsDebounceRef.current = setTimeout(() => {
      // The pasted coordinates are the source of truth for the map pin.
      // An address and municipality picked earlier no longer describe the
      // new point, so clear them (as typing in the address field clears the
      // coordinates), or the card and the region would disagree with the
      // pin. Filling them from the coordinates needs the nearest-address
      // lookup, which needs Datafordeler (docs/dawa-migration-plan.md, PR 3).
      setMetaRef.current({
        event_lat: parsed.lat,
        event_lng: parsed.lng,
        ...(moved && { event_address: '', event_municipality: '' }),
      });
      setCoordsDraft(null);
    }, 600);
  };

  const hasCoords = Boolean(meta.event_lat && meta.event_lng);
  const coordsValue =
    coordsDraft !== null
      ? coordsDraft
      : hasCoords
        ? `${meta.event_lat}, ${meta.event_lng}`
        : '';

  return (
    <PluginDocumentSettingPanel
      name="vandrekalender-location"
      title={__('Location', 'vandrekalender-events')}
      className="vandrekalender-location"
      initialOpen={true}
    >
      <TextControl
        label={__('Place name (optional)', 'vandrekalender-events')}
        value={meta.event_place_name || ''}
        onChange={value => setMeta({ event_place_name: value })}
        placeholder={__(
          'e.g. Dyrehaven or Silkeborg Sti',
          'vandrekalender-events'
        )}
        help={__(
          'Human-readable name shown on event cards. Falls back to municipality if left empty.',
          'vandrekalender-events'
        )}
        __next40pxDefaultSize
        __nextHasNoMarginBottom
      />

      <div ref={wrapperRef} style={{ position: 'relative', marginTop: '16px' }}>
        <TextControl
          label={__('Address', 'vandrekalender-events')}
          value={meta.event_address || ''}
          onChange={onQueryChange}
          placeholder={__('Start typing an address…', 'vandrekalender-events')}
          __next40pxDefaultSize
          __nextHasNoMarginBottom
        />

        {loading && (
          <div style={{ position: 'absolute', right: '8px', top: '28px' }}>
            <Spinner />
          </div>
        )}

        {!ADDRESS_TOKEN && (
          <Text
            isBlock
            style={{ marginTop: '8px', fontSize: '12px', color: '#cc1818' }}
          >
            {__(
              'Address search is not configured. Paste coordinates below instead.',
              'vandrekalender-events'
            )}
          </Text>
        )}

        {Boolean(searchError) && (
          <Text
            isBlock
            style={{ marginTop: '8px', fontSize: '12px', color: '#cc1818' }}
          >
            {searchError}
          </Text>
        )}

        {open && suggestions.length > 0 && (
          <ul
            style={{
              position: 'absolute',
              zIndex: 9999,
              background: '#fff',
              border: '1px solid #ddd',
              borderRadius: '2px',
              margin: 0,
              padding: 0,
              listStyle: 'none',
              width: '100%',
              boxShadow: '0 2px 8px rgba(0,0,0,0.12)',
            }}
          >
            {suggestions.map((s, i) => (
              <li
                key={s.id || s.titel}
                onMouseDown={e => {
                  // Keep focus in the input so the user can keep typing
                  // after picking a street.
                  e.preventDefault();
                  onSelect(s);
                }}
                style={{
                  padding: '8px 12px',
                  cursor: 'pointer',
                  fontSize: '13px',
                  borderBottom:
                    i < suggestions.length - 1 ? '1px solid #f0f0f0' : 'none',
                }}
                onMouseEnter={e =>
                  (e.currentTarget.style.background = '#f0f6fc')
                }
                onMouseLeave={e => (e.currentTarget.style.background = '#fff')}
              >
                {s.titel}
              </li>
            ))}
          </ul>
        )}
      </div>

      <div style={{ marginTop: '16px' }}>
        <TextControl
          label={__('Coordinates', 'vandrekalender-events')}
          value={coordsValue}
          onChange={onCoordsChange}
          placeholder="56.052777, 9.749856"
          help={__(
            'Filled automatically when an address is chosen. Or paste coordinates as "56.052777, 9.749856" or "56.8036° N, 9.0192° E" to place the pin directly.',
            'vandrekalender-events'
          )}
          __next40pxDefaultSize
          __nextHasNoMarginBottom
        />
      </div>

      {Boolean(meta.event_municipality) && (
        <Text
          variant="muted"
          isBlock
          style={{ marginTop: '8px', fontSize: '12px' }}
        >
          {meta.event_municipality}
        </Text>
      )}
    </PluginDocumentSettingPanel>
  );
};

// ── Route GPX field ───────────────────────────────────────────────────────────

const RouteGpxField = ({ route, onSelect, onRemove }) => (
  <div style={{ marginTop: '4px' }}>
    <BaseControl.VisualLabel>
      {__('GPX route', 'vandrekalender-events')}
    </BaseControl.VisualLabel>

    {route.gpx_id ? (
      <Flex align="center" gap={2}>
        <Text
          title={route.gpx_name || route.gpx_id}
          style={{ flex: '1 1 auto', minWidth: 0, whiteSpace: 'nowrap' }}
        >
          {truncateFilename(route.gpx_name || route.gpx_id)}
        </Text>
        <MediaUploadCheck>
          <MediaUpload
            allowedTypes={['application/gpx+xml']}
            value={Number(route.gpx_id)}
            onSelect={onSelect}
            render={({ open }) => (
              <Button variant="secondary" onClick={open} __next40pxDefaultSize>
                {__('Replace', 'vandrekalender-events')}
              </Button>
            )}
          />
        </MediaUploadCheck>
        <Button variant="tertiary" onClick={onRemove} __next40pxDefaultSize>
          {__('Remove', 'vandrekalender-events')}
        </Button>
      </Flex>
    ) : (
      <MediaUploadCheck>
        <MediaUpload
          allowedTypes={['application/gpx+xml']}
          onSelect={onSelect}
          render={({ open }) => (
            <Button variant="secondary" onClick={open} __next40pxDefaultSize>
              {__('Upload GPX', 'vandrekalender-events')}
            </Button>
          )}
        />
      </MediaUploadCheck>
    )}
  </div>
);

// ── Event details panel ───────────────────────────────────────────────────────

const EventDetailsPanel = ({ meta, setMeta }) => {
  const [expandedIndex, setExpandedIndex] = useState(-1);

  const eventDate = meta.event_date || '';
  const routes = normalizeRoutes(meta.event_routes);
  const setRoutes = next => setMeta({ event_routes: next });

  const addRoute = () => {
    const next = [...routes, emptyRoute()];
    setRoutes(next);
    setExpandedIndex(next.length - 1);
  };

  const removeRoute = index => {
    const next = routes.filter((_, i) => i !== index);
    setRoutes(next);
    if (expandedIndex === index) setExpandedIndex(-1);
    else if (expandedIndex > index) setExpandedIndex(expandedIndex - 1);
  };

  const updateRoute = (index, patch) => {
    const next = routes.map((r, i) => (i !== index ? r : { ...r, ...patch }));
    setRoutes(next);
  };

  return (
    <PluginDocumentSettingPanel
      name="vandrekalender-event-details"
      title={__('Event Details', 'vandrekalender-events')}
      className="vandrekalender-event-details"
      initialOpen={true}
    >
      {/* ── Event date ── */}
      <Text
        variant="muted"
        isBlock
        style={{ marginBottom: '4px', fontWeight: 600 }}
      >
        {__('Event Date', 'vandrekalender-events')}
      </Text>

      {/* Anchor the date-only value at noon: DatePicker normalizes the
          instant through UTC, so local midnight (22:00 UTC the previous
          day in Danish summer time) highlights the wrong day. Noon stays
          on the same calendar day in every timezone. */}
      <DatePicker
        currentDate={eventDate ? new Date(`${eventDate}T12:00:00`) : new Date()}
        onChange={newDate => setMeta({ event_date: toISODate(newDate) })}
      />

      {eventDate && (
        <Text isBlock style={{ marginBottom: '16px' }}>
          {formatDate(eventDate)}
        </Text>
      )}

      {/* ── Routes ── */}
      <Text
        variant="muted"
        isBlock
        style={{ marginTop: '8px', marginBottom: '8px', fontWeight: 600 }}
      >
        {__('Routes', 'vandrekalender-events')}
      </Text>

      {routes.length === 0 && (
        <Text variant="muted" isBlock>
          {__('No routes added yet.', 'vandrekalender-events')}
        </Text>
      )}

      <Flex direction="column" gap={3}>
        {routes.map((route, index) => {
          const isExpanded = expandedIndex === index;

          if (!isExpanded) {
            return (
              <Flex
                key={route.id}
                justify="space-between"
                align="flex-start"
                style={{ borderTop: '1px solid #e0e0e0', paddingTop: '8px' }}
              >
                <Flex
                  direction="column"
                  gap={1}
                  style={{ flex: '1 1 auto', minWidth: 0 }}
                >
                  {route.distance_km && <Text>{route.distance_km} km</Text>}
                  {route.start_time && (
                    <Text>
                      {__('Start:', 'vandrekalender-events')} {route.start_time}
                    </Text>
                  )}
                  {route.price && <Text>{route.price} kr</Text>}
                  {route.gpx_id && (
                    <Flex gap={1} align="center" justify="flex-start">
                      <Text>{__('GPX', 'vandrekalender-events')}</Text>
                      <Icon
                        icon={check}
                        size={16}
                        style={{ color: '#2D5F3F' /* theme palette: forest */ }}
                      />
                    </Flex>
                  )}
                  {(!route.distance_km || !route.start_time) && (
                    <Text variant="muted">
                      {__('Incomplete route', 'vandrekalender-events')}
                    </Text>
                  )}
                </Flex>
                {/* flexShrink:0 — the sidebar is ~280px and translated labels
                    ("Rediger", "Fjern") are longer than the English ones, so
                    without this the buttons compress until the text collides
                    with the border. */}
                <Flex gap={2} style={{ flexShrink: 0, width: 'auto' }}>
                  <Button
                    variant="secondary"
                    onClick={() => setExpandedIndex(index)}
                    __next40pxDefaultSize
                  >
                    {__('Edit', 'vandrekalender-events')}
                  </Button>
                  <Button
                    variant="tertiary"
                    onClick={() => removeRoute(index)}
                    __next40pxDefaultSize
                  >
                    {__('Remove', 'vandrekalender-events')}
                  </Button>
                </Flex>
              </Flex>
            );
          }

          return (
            <Flex
              key={route.id}
              direction="column"
              gap={2}
              style={{ borderTop: '1px solid #e0e0e0', paddingTop: '8px' }}
            >
              <Flex justify="space-between" align="center">
                <Text style={{ fontWeight: 600 }}>
                  {__('Route', 'vandrekalender-events')} {index + 1}
                </Text>
                <Button
                  variant="tertiary"
                  onClick={() => removeRoute(index)}
                  __next40pxDefaultSize
                >
                  {__('Remove', 'vandrekalender-events')}
                </Button>
              </Flex>

              <TextControl
                label={__('Distance (km)', 'vandrekalender-events')}
                type="number"
                min="0"
                value={route.distance_km}
                onChange={value => updateRoute(index, { distance_km: value })}
                placeholder="25"
                __next40pxDefaultSize
                __nextHasNoMarginBottom
              />

              <SelectControl
                label={__('Start Time', 'vandrekalender-events')}
                value={route.start_time}
                options={timeOptions}
                onChange={value => updateRoute(index, { start_time: value })}
                __next40pxDefaultSize
                __nextHasNoMarginBottom
              />

              <TextControl
                label={__('Cutoff Time (hours)', 'vandrekalender-events')}
                type="number"
                min="0"
                value={route.cutoff_time}
                onChange={value => updateRoute(index, { cutoff_time: value })}
                placeholder="8"
                help={__(
                  'Maximum hours allowed to finish the route.',
                  'vandrekalender-events'
                )}
                __next40pxDefaultSize
                __nextHasNoMarginBottom
              />

              <TextControl
                label={__('Price (DKK)', 'vandrekalender-events')}
                type="number"
                min="0"
                value={route.price}
                onChange={value => updateRoute(index, { price: value })}
                placeholder="0"
                __next40pxDefaultSize
                __nextHasNoMarginBottom
              />

              <RouteGpxField
                route={route}
                onSelect={media =>
                  updateRoute(index, {
                    gpx_id: String(media.id),
                    // Prefer the human-entered title; fall back to the raw
                    // filename (e.g. with a -1 de-dupe suffix) only when the
                    // attachment has no title.
                    gpx_name: media.title || media.filename || '',
                  })
                }
                onRemove={() =>
                  // gpx_source_url is cleared too, not just gpx_id/gpx_name:
                  // PR 4's scraper re-attaches whenever gpx_source_url is set
                  // but gpx_id no longer points to a real attachment, so
                  // leaving it would silently undo this Remove on the next
                  // scrape. Replace (onSelect above) leaves gpx_source_url
                  // alone on purpose — the new gpx_id is a real attachment,
                  // so the scraper's "keep it" check already respects the
                  // organiser's replacement without needing this.
                  updateRoute(index, {
                    gpx_id: '',
                    gpx_name: '',
                    gpx_source_url: '',
                  })
                }
              />

              <Flex justify="flex-end" style={{ marginTop: '8px' }}>
                <Button
                  variant="primary"
                  onClick={() => setExpandedIndex(-1)}
                  __next40pxDefaultSize
                >
                  {__('Done', 'vandrekalender-events')}
                </Button>
              </Flex>
            </Flex>
          );
        })}
      </Flex>

      <Button
        variant="primary"
        onClick={addRoute}
        style={{ marginTop: '12px', width: '100%' }}
        __next40pxDefaultSize
      >
        {__('Add route', 'vandrekalender-events')}
      </Button>
    </PluginDocumentSettingPanel>
  );
};

// ── Organiser panel ───────────────────────────────────────────────────────────

const OrganiserPanel = ({ meta, setMeta }) => {
  // PHP strips event_organiser_email from the REST response for non-admins.
  // If the key is present (even as empty string), the current user is an admin.
  const isAdmin = 'event_organiser_email' in meta;

  return (
    <PluginDocumentSettingPanel
      name="vandrekalender-organiser"
      title={__('Organiser', 'vandrekalender-events')}
      className="vandrekalender-organiser"
      initialOpen={false}
    >
      <Flex direction="column" gap={2}>
        <TextControl
          label={__('Name', 'vandrekalender-events')}
          value={meta.event_organiser_name || ''}
          onChange={value => setMeta({ event_organiser_name: value })}
          placeholder={__('Organiser or club name', 'vandrekalender-events')}
          __next40pxDefaultSize
          __nextHasNoMarginBottom
        />

        <TextControl
          label={__('Website', 'vandrekalender-events')}
          value={meta.event_organiser_url || ''}
          onChange={value => setMeta({ event_organiser_url: value })}
          placeholder="https://"
          type="url"
          __next40pxDefaultSize
          __nextHasNoMarginBottom
        />

        {isAdmin && (
          <TextControl
            label={__('Contact email (admin only)', 'vandrekalender-events')}
            value={meta.event_organiser_email || ''}
            onChange={value => setMeta({ event_organiser_email: value })}
            placeholder="contact@example.com"
            type="email"
            help={__(
              'Stored securely. Never shown publicly. Used for the event claim flow.',
              'vandrekalender-events'
            )}
            __next40pxDefaultSize
            __nextHasNoMarginBottom
          />
        )}
      </Flex>
    </PluginDocumentSettingPanel>
  );
};

// ── Root component ────────────────────────────────────────────────────────────

const EventDocumentFields = () => {
  const postType = useSelect(
    select => select('core/editor').getCurrentPostType(),
    []
  );
  const meta = useSelect(
    select =>
      select('core/editor').getEditedPostAttribute('meta') ?? EMPTY_META,
    []
  );
  const { editPost } = useDispatch('core/editor');

  if (postType !== POST_TYPE) return null;

  const setMeta = patch => editPost({ meta: { ...meta, ...patch } });

  console.log('Current meta:', meta); // Debug log to inspect meta structure

  return (
    <>
      <EventDetailsPanel meta={meta} setMeta={setMeta} />
      <LocationPanel meta={meta} setMeta={setMeta} />
      <OrganiserPanel meta={meta} setMeta={setMeta} />
    </>
  );
};

registerPlugin('vandrekalender-event-document-fields', {
  render: EventDocumentFields,
});

// ── Onboarding video ──────────────────────────────────────────────────────────

/**
 * "Watch the how-to video" help link on the event creation screen.
 *
 * Rendered as an editor notice rather than a PHP admin notice: core hides
 * classic notices on block editor screens. window.vkOnboardingVideo is set
 * from PHP (Vandrekalender_Organizer_Dashboard) and is only present when an
 * onboarding video is configured and a new event is being created.
 */
const OnboardingVideo = () => {
  const video = window.vkOnboardingVideo;
  const [isOpen, setIsOpen] = useState(false);
  const { createInfoNotice } = useDispatch('core/notices');
  const noticeShown = useRef(false);

  useEffect(() => {
    if (!video || noticeShown.current) return;
    noticeShown.current = true;

    createInfoNotice(__('New here?', 'vandrekalender-events'), {
      id: 'vandrekalender-onboarding-video',
      isDismissible: true,
      actions: [
        {
          label: __('Watch the how-to video', 'vandrekalender-events'),
          onClick: () => setIsOpen(true),
        },
      ],
    });
  }, [video, createInfoNotice]);

  if (!video || !isOpen) return null;

  return (
    <Modal
      title={__('Watch the how-to video', 'vandrekalender-events')}
      onRequestClose={() => setIsOpen(false)}
      size="large"
    >
      <video
        controls
        playsInline
        preload="metadata"
        poster={video.poster || undefined}
        style={{ width: '100%', height: 'auto', display: 'block' }}
      >
        <source src={video.url} type={video.mime} />
      </video>
    </Modal>
  );
};

registerPlugin('vandrekalender-onboarding-video', { render: OnboardingVideo });
