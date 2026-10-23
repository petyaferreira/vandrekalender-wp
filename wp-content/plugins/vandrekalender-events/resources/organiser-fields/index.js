import { registerPlugin } from '@wordpress/plugins';
import { PluginDocumentSettingPanel } from '@wordpress/editor';
import { ComboboxControl, Flex, ToggleControl } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

// Enqueued only on organiser posts (Organiser::enqueue_editor_assets()).

const EMPTY_META = {};

const OrganiserPageFields = () => {
  const { meta, terms } = useSelect(
    select => ({
      meta: select('core/editor').getEditedPostAttribute('meta') ?? EMPTY_META,
      terms: select('core').getEntityRecords('taxonomy', 'event_organiser', {
        per_page: -1,
        orderby: 'name',
        _fields: 'id,name',
      }),
    }),
    []
  );
  const { editPost } = useDispatch('core/editor');
  const setMeta = patch => editPost({ meta: { ...meta, ...patch } });

  return (
    <PluginDocumentSettingPanel
      name="vandrekalender-organiser-page"
      title={__('Organiser', 'vandrekalender-events')}
      className="vandrekalender-organiser-page"
    >
      <Flex direction="column" gap={2}>
        <ComboboxControl
          label={__('Organisation', 'vandrekalender-events')}
          value={meta.organiser_term || null}
          options={(terms ?? []).map(term => ({
            value: term.id,
            label: term.name,
          }))}
          onChange={id => setMeta({ organiser_term: id || 0 })}
          help={__(
            'The page shows this organisation’s walks. One page per organisation.',
            'vandrekalender-events'
          )}
          __next40pxDefaultSize
          __nextHasNoMarginBottom
        />

        <ToggleControl
          label={__('Official organiser', 'vandrekalender-events')}
          checked={!!meta.organiser_official}
          onChange={value => setMeta({ organiser_official: value })}
          help={__(
            'Shows the “Officiel arrangør” badge on the page.',
            'vandrekalender-events'
          )}
          __nextHasNoMarginBottom
        />
      </Flex>
    </PluginDocumentSettingPanel>
  );
};

registerPlugin('vandrekalender-organiser-page-fields', {
  render: OrganiserPageFields,
});
