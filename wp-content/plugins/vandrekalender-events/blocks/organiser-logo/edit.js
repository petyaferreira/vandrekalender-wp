/**
 * WordPress dependencies.
 */
import { useBlockProps } from '@wordpress/block-editor';
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';

// Same rule as Event::organiser_initials(): up to three initials, short
// joining words skipped.
const SKIP = ['og', 'en', 'et', 'i', 'på', 'af', 'the', 'and', 'of'];
const initials = name =>
  name
    .trim()
    .split(/\s+/)
    .filter(word => word && !SKIP.includes(word.toLowerCase()))
    .map(word => word.charAt(0).toUpperCase())
    .join('')
    .slice(0, 3);

export default function Edit({ context: { postId, postType } }) {
  const { title, imageUrl } = useSelect(
    select => {
      const post = select(coreStore).getEditedEntityRecord(
        'postType',
        postType,
        postId
      );
      const media = post?.featured_media
        ? select(coreStore).getMedia(post.featured_media)
        : null;
      return {
        title: post?.title ?? '',
        imageUrl: media?.media_details?.sizes?.medium?.source_url,
      };
    },
    [postId, postType]
  );
  const blockProps = useBlockProps({ className: 'vk-organiser-logo' });

  return (
    <div {...blockProps}>
      {imageUrl ? (
        <img className="vk-organiser-logo__image" src={imageUrl} alt="" />
      ) : (
        <span className="vk-organiser-logo__initials">{initials(title)}</span>
      )}
    </div>
  );
}
