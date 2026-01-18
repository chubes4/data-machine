<?php
/**
 * RSS/Atom feed handler with timeframe and keyword filtering.
 *
 * @package DataMachine\Core\Steps\Fetch\Handlers\Rss
 */

namespace DataMachine\Core\Steps\Fetch\Handlers\Rss;

use DataMachine\Core\ExecutionContext;
use DataMachine\Core\Steps\Fetch\Handlers\FetchHandler;
use DataMachine\Core\Steps\HandlerRegistrationTrait;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rss extends FetchHandler {

	use HandlerRegistrationTrait;

	public function __construct() {
		parent::__construct( 'rss' );

		// Self-register with filters
		self::registerHandler(
			'rss',
			'fetch',
			self::class,
			'RSS Feed',
			'Fetch content from RSS and Atom feeds',
			false,
			null,
			RssSettings::class,
			null
		);
	}

	/**
	 * Fetch RSS/Atom content with timeframe and keyword filtering.
	 */
	protected function executeFetch( array $config, ExecutionContext $context ): array {
		$feed_url        = trim( $config['feed_url'] ?? '' );
		$timeframe_limit = $config['timeframe_limit'] ?? 'all_time';
		$search          = trim( $config['search'] ?? '' );

		$result = $this->httpGet( $feed_url, array( 'context' => 'RSS Feed' ) );

		if ( ! $result['success'] ) {
			$context->log(
				'error',
				'Rss: Failed to fetch RSS feed.',
				array(
					'error'    => $result['error'],
					'feed_url' => $feed_url,
				)
			);
			return array();
		}

		$feed_content = $result['data'];
		if ( empty( $feed_content ) ) {
			$context->log( 'error', 'Rss: RSS feed content is empty.', array( 'feed_url' => $feed_url ) );
			return array();
		}

		libxml_use_internal_errors( true );
		$xml = simplexml_load_string( $feed_content );
		if ( $xml === false ) {
			$errors         = libxml_get_errors();
			$error_messages = array_map(
				function ( $error ) {
					return trim( $error->message );
				},
				$errors
			);

			$context->log(
				'error',
				'Rss: Failed to parse RSS feed XML.',
				array(
					'feed_url'   => $feed_url,
					'xml_errors' => implode( ', ', $error_messages ),
				)
			);
			return array();
		}

		$items = array();

		if ( isset( $xml->channel->item ) ) {
			$items = $xml->channel->item;
		} elseif ( isset( $xml->item ) ) {
			$items = $xml->item;
		} elseif ( isset( $xml->entry ) ) {
			$items = $xml->entry;
		} else {
			$context->log( 'error', 'Rss: Unsupported feed format or no items found in feed.', array( 'feed_url' => $feed_url ) );
			return array();
		}

		if ( empty( $items ) ) {
			return array();
		}

		$total_checked = 0;

		foreach ( $items as $item ) {
			++$total_checked;

			$title       = $this->extract_item_title( $item );
			$description = $this->extract_item_description( $item );
			$link        = $this->extract_item_link( $item );
			$pub_date    = $this->extract_item_date( $item );
			$guid        = $this->extract_item_guid( $item, $link );

			if ( empty( $guid ) ) {
				$context->log( 'warning', 'Rss: Skipping item without GUID.', array( 'title' => $title ) );
				continue;
			}

			if ( $context->isItemProcessed( $guid ) ) {
				continue;
			}

			if ( $pub_date ) {
				$item_timestamp = strtotime( $pub_date );
				if ( $item_timestamp !== false && ! $this->applyTimeframeFilter( $item_timestamp, $timeframe_limit ) ) {
					$context->log(
						'debug',
						'Rss: Skipping item outside timeframe.',
						array(
							'guid'     => $guid,
							'pub_date' => $pub_date,
						)
					);
					continue;
				}
			}

			$search_text = $title . ' ' . wp_strip_all_tags( $description );
			if ( ! $this->applyKeywordSearch( $search_text, $search ) ) {
				continue;
			}

			$context->markItemProcessed( $guid );

			$author        = $this->extract_item_author( $item );
			$categories    = $this->extract_item_categories( $item );
			$enclosure_url = $this->extract_item_enclosure( $item );

			$content_data = array(
				'title'   => $title,
				'content' => $description,
			);

			$metadata = array(
				'source_type'            => 'rss',
				'item_identifier_to_log' => $guid,
				'original_id'            => $guid,
				'original_title'         => $title,
				'original_date_gmt'      => $pub_date ? gmdate( 'Y-m-d\TH:i:s\Z', strtotime( $pub_date ) ) : null,
				'author'                 => $author,
				'categories'             => $categories,
			);

			$file_info = null;
			if ( ! empty( $enclosure_url ) ) {
				$file_check = wp_check_filetype( $enclosure_url );
				$mime_type  = $file_check['type'] ?: 'application/octet-stream';

				if ( strpos( $mime_type, 'image/' ) === 0 && in_array( $mime_type, array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' ) ) ) {
					$filename = 'rss_image_' . time() . '_' . sanitize_file_name( basename( wp_parse_url( $enclosure_url, PHP_URL_PATH ) ) );

					$download_result = $context->downloadFile( $enclosure_url, $filename );

					if ( $download_result ) {
						$file_info = array(
							'file_path' => $download_result['path'],
							'mime_type' => $mime_type,
							'file_size' => $download_result['size'],
						);

						$context->log(
							'debug',
							'Rss: Downloaded remote image for AI processing',
							array(
								'guid'       => $guid,
								'source_url' => $enclosure_url,
								'local_path' => $download_result['path'],
								'file_size'  => $download_result['size'],
							)
						);
					} else {
						$context->log(
							'warning',
							'Rss: Failed to download remote image',
							array(
								'guid'          => $guid,
								'enclosure_url' => $enclosure_url,
							)
						);

						$file_info = array(
							'type'      => $mime_type,
							'mime_type' => $mime_type,
						);
					}
				} else {
					// Non-image or unsupported image format - keep original behavior
					$file_info = array(
						'type'      => $mime_type,
						'mime_type' => $mime_type,
					);
				}
			}

			// Prepare raw data for DataPacket creation
			$raw_data = array(
				'title'    => $content_data['title'],
				'content'  => $content_data['content'],
				'metadata' => $metadata,
			);

			// Add file_info if present
			if ( $file_info ) {
				$raw_data['file_info'] = $file_info;
			}

			$engine_data = array( 'source_url' => $link ?: '' );

			// Store repository file path if image was downloaded
			if ( ! empty( $file_info ) && isset( $file_info['file_path'] ) ) {
				$engine_data['image_file_path'] = $file_info['file_path'];
			}

			$context->storeEngineData( $engine_data );

			return $raw_data;
		}

		$context->log(
			'debug',
			'Rss: No eligible items found in RSS feed.',
			array(
				'total_checked' => $total_checked,
			)
		);

		return array();
	}

	/**
	 * Extract title from RSS item.
	 *
	 * @param object $item SimpleXML item object
	 * @return string Item title or 'Untitled' if not found
	 */
	private function extract_item_title( $item ): string {
		if ( isset( $item->title ) ) {
			return (string) $item->title;
		}
		return 'Untitled';
	}

	/**
	 * Extract description/content from RSS item.
	 *
	 * Checks multiple possible fields: description, summary, content, encoded.
	 *
	 * @param object $item SimpleXML item object
	 * @return string Stripped content text
	 */
	private function extract_item_description( $item ): string {
		if ( isset( $item->description ) ) {
			return wp_strip_all_tags( (string) $item->description );
		}
		if ( isset( $item->summary ) ) {
			return wp_strip_all_tags( (string) $item->summary );
		}
		if ( isset( $item->content ) ) {
			return wp_strip_all_tags( (string) $item->content );
		}
		$content_ns = $item->children( 'http://purl.org/rss/1.0/modules/content/' );
		if ( isset( $content_ns->encoded ) ) {
			return wp_strip_all_tags( (string) $content_ns->encoded );
		}
		return '';
	}

	/**
	 * Extract link URL from RSS item.
	 *
	 * @param object $item SimpleXML item object
	 * @return string Item link URL
	 */
	private function extract_item_link( $item ): string {
		if ( isset( $item->link ) ) {
			$link = $item->link;
			if ( is_object( $link ) && isset( $link['href'] ) ) {
				return (string) $link['href'];
			}
			return (string) $link;
		}
		return '';
	}

	/**
	 * Extract publication date from RSS item.
	 *
	 * Checks multiple date fields: pubDate, published, updated, dc:date.
	 *
	 * @param object $item SimpleXML item object
	 * @return string|null Publication date string or null if not found
	 */
	private function extract_item_date( $item ): ?string {
		if ( isset( $item->pubDate ) ) {
			return (string) $item->pubDate;
		}
		if ( isset( $item->published ) ) {
			return (string) $item->published;
		}
		if ( isset( $item->updated ) ) {
			return (string) $item->updated;
		}
		$dc_ns = $item->children( 'http://purl.org/dc/elements/1.1/' );
		if ( isset( $dc_ns->date ) ) {
			return (string) $dc_ns->date;
		}
		return null;
	}

	/**
	 * Extract GUID/unique identifier from RSS item.
	 *
	 * Falls back to item link if no GUID found.
	 *
	 * @param object $item SimpleXML item object
	 * @param string $item_link Fallback link if no GUID
	 * @return string Unique identifier for the item
	 */
	private function extract_item_guid( $item, string $item_link ): string {
		if ( isset( $item->guid ) ) {
			return (string) $item->guid;
		}
		if ( isset( $item->id ) ) {
			return (string) $item->id;
		}
		return $item_link;
	}

	/**
	 * Extract author name from RSS item.
	 *
	 * Checks author field and dc:creator namespace.
	 *
	 * @param object $item SimpleXML item object
	 * @return string|null Author name or null if not found
	 */
	private function extract_item_author( $item ): ?string {
		if ( isset( $item->author ) ) {
			$author = $item->author;
			if ( is_object( $author ) && isset( $author->name ) ) {
				return (string) $author->name;
			}
			return (string) $author;
		}
		$dc_ns = $item->children( 'http://purl.org/dc/elements/1.1/' );
		if ( isset( $dc_ns->creator ) ) {
			return (string) $dc_ns->creator;
		}
		return null;
	}

	/**
	 * Extract categories/tags from RSS item.
	 *
	 * @param object $item SimpleXML item object
	 * @return array Array of category names
	 */
	private function extract_item_categories( $item ): array {
		$categories = array();

		if ( isset( $item->category ) ) {
			foreach ( $item->category as $category ) {
				if ( isset( $category['term'] ) ) {
					$categories[] = (string) $category['term'];
				} else {
					$categories[] = (string) $category;
				}
			}
		}

		return $categories;
	}

	/**
	 * Extract enclosure URL from RSS item.
	 *
	 * @param object $item SimpleXML item object
	 * @return string|null Enclosure URL or null if not found
	 */
	private function extract_item_enclosure( $item ): ?string {
		if ( isset( $item->enclosure ) && isset( $item->enclosure['url'] ) ) {
			return (string) $item->enclosure['url'];
		}
		return null;
	}

	/**
	 * Get the display label for the RSS handler.
	 *
	 * @return string Localized handler label
	 */
	public static function get_label(): string {
		return __( 'RSS/Atom Feed', 'data-machine' );
	}
}
