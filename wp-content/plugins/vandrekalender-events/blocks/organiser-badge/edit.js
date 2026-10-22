/**
 * WordPress dependencies.
 */
import { useBlockProps } from '@wordpress/block-editor';
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import { __ } from '@wordpress/i18n';

export default function Edit({ context: { postId, postType } }) {
  const official = useSelect(
    select =>
      !!select(coreStore).getEditedEntityRecord('postType', postType, postId)
        ?.meta?.organiser_official,
    [postId, postType]
  );
  const blockProps = useBlockProps({
    className: official
      ? 'vk-organiser-badge'
      : 'vk-organiser-badge vk-organiser-badge--off',
    title: official
      ? undefined
      : __(
          'Hidden on the page: the organiser is not marked as official (Organiser panel).',
          'vandrekalender-events'
        ),
  });

  return (
    <p {...blockProps}>{__('Official organiser', 'vandrekalender-events')}</p>
  );
}
