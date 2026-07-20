import { registerPlugin } from '@wordpress/plugins';
import { PluginDocumentSettingPanel } from '@wordpress/editor';
import {
  Button,
  Flex,
  SelectControl,
  TextControl,
  DatePicker,
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

// event_region and event_length are auto-assigned on save — hide their panels.
dispatch('core/editor').removeEditorPanel('taxonomy-panel-event_region');
dispatch('core/editor').removeEditorPanel('taxonomy-panel-event_length');

const POST_TYPE = 'event';
const EMPTY_META = {};
const DAWA_AUTOCOMPLETE =
  'https://api.dataforsyningen.dk/autocomplete?type=adresse&q=';
const DAWA_KOMMUNE = 'https://api.dataforsyningen.dk/kommuner/';
const DAWA_REVERSE = 'https://api.dataforsyningen.dk/adgangsadresser/reverse';

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

const emptyRoute = () => ({
  id: generateRouteId(),
  distance_km: '',
  start_time: '',
  cutoff_time: '',
  price: '',
});

const normalizeRoutes = raw => {
  if (!Array.isArray(raw)) return [];
  return raw.filter(Boolean).map(r => ({
    id: typeof r?.id === 'string' ? r.id : generateRouteId(),
    distance_km: typeof r?.distance_km === 'string' ? r.distance_km : '',
    start_time: typeof r?.start_time === 'string' ? r.start_time : '',
    cutoff_time: typeof r?.cutoff_time === 'string' ? r.cutoff_time : '',
    price: typeof r?.price === 'string' ? r.price : '',
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
  // While the user is typing coordinates the field shows their raw text;
  // otherwise it mirrors the stored meta values.
  const [coordsDraft, setCoordsDraft] = useState(null);
  const debounceRef = useRef(null);
  const reverseRef = useRef(null);
  const wrapperRef = useRef(null);
  const setMetaRef = useRef(setMeta);
  setMetaRef.current = setMeta;

  const applyMunicipality = kommunekode => {
    if (!kommunekode) return;
    fetch(DAWA_KOMMUNE + kommunekode)
      .then(res => res.json())
      .then(kommune => {
        if (kommune.navn) {
          setMetaRef.current({ event_municipality: kommune.navn });
        }
      })
      .catch(() => {});
  };

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

  const onQueryChange = value => {
    // Clear derived fields when the user edits the address manually.
    setMeta({
      event_address: value,
      event_lat: 0,
      event_lng: 0,
      event_municipality: '',
    });

    clearTimeout(debounceRef.current);

    if (value.length < 3) {
      setSuggestions([]);
      setOpen(false);
      return;
    }

    debounceRef.current = setTimeout(async () => {
      setLoading(true);
      try {
        const res = await fetch(DAWA_AUTOCOMPLETE + encodeURIComponent(value));
        const data = await res.json();
        setSuggestions(data.slice(0, 8));
        setOpen(true);
      } catch {
        setSuggestions([]);
      } finally {
        setLoading(false);
      }
    }, 300);
  };

  const onSelect = suggestion => {
    const { tekst, data } = suggestion;
    setOpen(false);
    setSuggestions([]);
    setCoordsDraft(null);

    setMeta({
      event_address: tekst,
      event_lat: data.y,
      event_lng: data.x,
      event_municipality: '',
    });

    applyMunicipality(data.kommunekode);
  };

  const onCoordsChange = value => {
    setCoordsDraft(value);
    clearTimeout(reverseRef.current);

    const parsed = parseCoords(value);
    if (!parsed) return;

    reverseRef.current = setTimeout(async () => {
      // The pasted coordinates are the source of truth for the map pin;
      // the reverse-geocoded nearest address is for display and municipality.
      setMetaRef.current({ event_lat: parsed.lat, event_lng: parsed.lng });

      try {
        const res = await fetch(
          `${DAWA_REVERSE}?x=${parsed.lng}&y=${parsed.lat}&struktur=mini`
        );
        const data = await res.json();
        if (data && data.betegnelse) {
          setMetaRef.current({ event_address: data.betegnelse });
        }
        if (data && data.kommunekode) {
          applyMunicipality(data.kommunekode);
        }
      } catch {
        // Coordinates are stored even when the address lookup fails.
      }

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
                key={i}
                onMouseDown={() => onSelect(s)}
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
                {s.tekst}
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
            'Filled automatically when an address is chosen. Or paste coordinates as "56.052777, 9.749856" or "56.8036° N, 9.0192° E" and the nearest address is looked up for you.',
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
                <Flex direction="column" gap={1} style={{ width: '100%' }}>
                  {route.distance_km && <Text>{route.distance_km} km</Text>}
                  {route.start_time && (
                    <Text>
                      {__('Start:', 'vandrekalender-events')} {route.start_time}
                    </Text>
                  )}
                  {route.price && <Text>{route.price} kr</Text>}
                  {(!route.distance_km || !route.start_time) && (
                    <Text variant="muted">
                      {__('Incomplete route', 'vandrekalender-events')}
                    </Text>
                  )}
                </Flex>
                <Flex gap={2}>
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
