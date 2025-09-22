<?php

namespace Nickstewart\AutoCopy;

use Nickstewart\AutoCopy\AutoCopy;

use GuzzleHttp\Client;

class Posts {
	/**
	 * Helper function that returns a set of posts
	 */
	public static function requestPosts(
		int $page,
		string $post_type = null
	): bool|array {
		$base_url = AutoCopy::getSiteUrl();

		if (empty($base_url)) {
			AutoCopy::logError('No site url set');
			return false;
		}

		$post_type = $post_type ?? AutoCopy::DEFAULT_POST_TYPE_PLURAL;

		$posts_per_page = apply_filters(
			'auto_copy_posts_post_per_page',
			AutoCopy::pluginSetting('auto_copy_posts_post_per_page'),
		);

		$client = new Client([
			'base_uri' => $base_url,
		]);

		try {
			$response = $client->request('GET', $post_type, [
				'query' => [
					'_embed' => 1,
					'per_page' => $posts_per_page,
					'page' => $page,
				],
			]);

			if ($response->getStatusCode() !== 200) {
				$error_message =
					'Error fetching posts: ' . $response->getStatusCode();
				AutoCopy::logError($error_message);

				return false;
			}
		} catch (\Exception $e) {
			AutoCopy::logError($e->getMessage());
			return false;
		}

		if (empty($response->getHeader('X-WP-TotalPages')[0])) {
			return false;
		}

		$page_count = $response->getHeader('X-WP-TotalPages')[0];
		$page_count = !empty($page_count) ? $page_count : 0;

		$posts = [];
		$posts['posts'] = json_decode($response->getBody(), true);
		$posts['post_count'] = count($posts['posts']);
		$posts['page_count'] = $page_count;

		/**
		 *  For debugging purposes, leaving this in
		 *
		 * 	AutoCopy::logError('Type: ' . $post_type . ', count: ' . $page_count . ', on page ' . $page . ' with '  .$posts['post_count'] . ' posts');
		 *
		 */

		return $posts;
	}

	/**
	 * Helper function that returns a single post
	 */
	public static function requestPost(int $post_id) {
		$original_post_id = get_post_meta(
			$post_id,
			'auto_copy_posts_original_id',
			true,
		);

		$original_post_id_stripped = AutoCopy::stripPostId($original_post_id);

		$base_url = AutoCopy::getSiteUrl();

		if (empty($base_url)) {
			AutoCopy::logError('No site url set');
			return;
		}

		$post_type = apply_filters(
			'auto_copy_posts_post_type_plural',
			AutoCopy::pluginSetting('auto_copy_posts_post_type_plural'),
		);

		$client = new Client([
			'base_uri' => $base_url,
		]);

		try {
			$response = $client->request('GET', $post_type, [
				'query' => [
					'_embed' => 1,
					'include' => $original_post_id_stripped,
				],
			]);

			if ($response->getStatusCode() !== 200) {
				$error_message =
					'Error fetching single post: ' . $response->getStatusCode();
				AutoCopy::logError($error_message);
			}
		} catch (\Exception $e) {
			AutoCopy::logError($e->getMessage());
			return;
		}

		return json_decode($response->getBody(), true);
	}

	/**
	 * Fetch a featured image by attachment ID
	 */
	public static function requestMediaAttachment($id, $post_id): string|bool {
		$base_url = AutoCopy::getSiteUrl();

		if (empty($base_url)) {
			AutoCopy::logError('No site url set');
			return false;
		}

		$client = new Client([
			'base_uri' => $base_url,
		]);

		try {
			$response = $client->request('GET', 'media/' . $id, [
				'query' => [
					'_embed' => 1,
				],
			]);

			if ($response->getStatusCode() !== 200) {
				$error_message =
					'Error fetching media attachment: ' .
					$response->getStatusCode();
				AutoCopy::logError($error_message);

				return false;
			}
		} catch (\Exception $e) {
			AutoCopy::logError(
				'Error fetching attachment for post ' . $post_id,
			);
			AutoCopy::logError($e->getMessage());
			return false;
		}

		$attachment = json_decode($response->getBody(), true);

		return $attachment['guid']['rendered'];
	}
}
