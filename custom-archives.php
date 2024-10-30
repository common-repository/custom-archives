<?php

/**
 * Plugin Name: Custom Archives
 * Plugin URI: https://wordpress.org/plugins/custom-archives/
 * Description: Select a page to be a custom archive for your post types.
 * Version: 3.0.2
 * Author: Daniel James
 * Author URI: https://danieltj.uk/
 * Text Domain: custom-archives
 */

/**
 * (c) Copyright 2019, Daniel James
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

if ( ! defined( 'ABSPATH' ) ) {

	die();

}

new Custom_Archives;

class Custom_Archives {

	/**
	 * Hook into WordPress.
	 * 
	 * @return void
	 */
	public function __construct() {

		add_action( 'admin_menu', array( __CLASS__, 'add_admin_page' ), 10, 0 );
		add_action( 'admin_init', array( __CLASS__, 'add_settings' ), 10, 0 );
		add_action( 'template_redirect', array( __CLASS__, 'archive_redirect' ), 15, 0 );
		add_action( 'transition_post_status', array( __CLASS__, 'post_status_updated' ), 20, 3 );
		add_action( 'deleted_post', array( __CLASS__, 'post_deleted' ), 20, 1 );
		add_action( 'admin_bar_menu', array( __CLASS__, 'add_edit_link' ), 80, 1 );

		add_filter( 'display_post_states', array( __CLASS__, 'add_post_states' ), 20, 2 );
		add_filter( 'document_title_parts', array( __CLASS__, 'rewrite_page_title' ), 15, 1 );
		add_filter( 'template_include', array( __CLASS__, 'archive_template' ), 20, 1 );
		add_filter( 'pre_get_document_title', array( __CLASS__, 'rewrite_document_title' ), 25, 1 );
		add_filter( 'get_sample_permalink_html', array( __CLASS__, 'rewrite_edit_permalink' ), 15, 5 );

	}

	/**
	 * Gets all custom post types.
	 * 
	 * @return array $post_types An array of post types.
	 */
	public static function get_custom_post_types() {

		$get_types = get_post_types(
			array(
				'public' => true,
				'show_ui' => true,
				'_builtin' => false,
			),
			'objects'
		);

		$post_types = array();

		foreach ( $get_types as $key => $value ) {

			// Add the post type.
			$post_types[ $value->name ] = $value;

		}

		/**
		 * Filter the array of post types.
		 * 
		 * @since 1.0
		 * 
		 * @param array $post_types The array of post types.
		 * 
		 * @return array $post_types The filtered post types.
		 */
		$post_types = apply_filters( 'archivable_post_types', $post_types );

		return $post_types;

	}

	/**
	 * Get the custom archive page IDs.
	 * 
	 * @return array $pages
	 */
	public static function get_custom_archive_ids() {

		$types = self::get_custom_post_types();

		$pages = array();

		foreach ( $types as $type ) {

			if ( ! $type->has_archive ) {

				continue;

			}

			// Get the post type option.
			$page_id = get_option( 'archive_page_' . $type->name, false );

			if ( ! $page_id ) {

				continue;

			}

			$pages[ $type->name ] = $page_id;

		}

		/**
		 * Filter the array of custom archives.
		 * 
		 * @since 1.0
		 * 
		 * @param array $pages The array of custom archive ids.
		 * 
		 * @return array $pages The filtered custom archive ids.
		 */
		return apply_filters( 'custom_archive_page_ids', $pages );

	}

	/**
	 * Get the custom archive post type.
	 * 
	 * @param int $page_id The page id to search.
	 * 
	 * @return string|boolean
	 */
	public static function get_custom_archive_post_type( $page_id ) {

		$ids = self::get_custom_archive_ids();

		// Does the page ID exist?
		$post_type = array_search( $page_id, $ids );

		if ( ! $post_type ) {

			return false;

		}

		return $post_type;

	}

	/**
	 * Get the archive page URL.
	 * 
	 * Returns the URL of the post archive page based on the custom
	 * archive page id that passed through the function.
	 * 
	 * @param int $page_id The page id to search.
	 * 
	 * @return string|boolean
	 */
	public static function get_custom_archive_url( $page_id = 0 ) {

		$types = self::get_custom_post_types();
		$ids = self::get_custom_archive_ids();

		if ( in_array( $page_id, $ids ) ) {

			// Does the page ID exist?
			$archive = array_search( $page_id, $ids );

			if ( false !== $archive ) {

				// Get the post type URL.
				$url = get_post_type_archive_link( $types[ $archive ]->name );

				/**
				 * Filter the custom archive URL.
				 * 
				 * @since 1.0
				 * 
				 * @param string $url The custom archive URL.
				 * 
				 * @return string $url The filtered custom archive URL.
				 */
				return apply_filters( 'custom_archive_url', $url );

			}

		}

		return false;

	}

	/**
	 * Add the plugin admin page.
	 * 
	 * @return void
	 */
	public static function add_admin_page() {

		add_submenu_page(
			'options-general.php',
			esc_html__( 'Custom Archives', 'custom-archives' ),
			esc_html__( 'Archives', 'custom-archives' ),
			'manage_options',
			'custom-archives',
			array( __CLASS__, 'show_plugin_page' )
		);

	}

	/**
	 * Print the plugin page HTML.
	 * 
	 * @return string
	 */
	public static function show_plugin_page() {

		$types = self::get_custom_post_types();

		?>
			<div class="wrap">
				<h1 class="page-title"><?php esc_html_e( 'Custom Archives', 'custom-archives' ); ?></h1>
				<?php if ( empty( $types ) ) : ?>
					<p><strong style="color: #dc3232;"><?php esc_html_e( 'You don&#39;t have any custom post types, you must create one first.', 'custom-archives' ); ?></strong></p>
				<?php else : ?>
					<p><?php esc_html_e( 'Select a page below to act as a custom archive for a specific post type.', 'custom-archives' ); ?></p>
					<p><?php printf( esc_html_x( 'More options for built in post types can be found %shere%s.', 'The percentages denote HTML anchor tags.', 'custom-archives' ), '<a href="' . admin_url( 'options-reading.php' ) . '">', '</a>' ); ?></p>
					<div id="settings" class="tab-content">
						<form method="post" action="options.php">
							<table class="plugin-form form-table">
								<tbody>
									<?php settings_fields( 'custom_archives_fields' ); ?>
									<?php do_settings_sections( 'custom_archives_section' ); ?>
								</tbody>
							</table>
							<?php submit_button( esc_html__( 'Save Settings', 'custom-archives' ), 'primary' ); ?>
						</form>
					</div>
				<?php endif; ?>
			</div>
		<?php

	}

	/**
	 * Register page archive settings.
	 * 
	 * Adds the post type archive page settings to the
	 * Reading settings page.
	 * 
	 * @return void
	 */
	public static function add_settings() {

		$types = self::get_custom_post_types();

		add_settings_section( 'custom_archives_group', false, false, 'custom_archives_section' );

		foreach ( $types as $type ) {

			if ( ! $type->has_archive ) {

				continue;

			}

			// Set the setting parameters.
			$name = 'archive_page_' . $type->name;
			$value = get_option( $name, false );

			add_settings_field(
				$name,
				$type->labels->name,
				array( __CLASS__, 'print_select_setting' ),
				'custom_archives_section',
				'custom_archives_group',
				array( 'name' => $name, 'type' => $type, 'value' => $value )
			);

			register_setting(
				'custom_archives_fields',
				$name,
				array( __CLASS__, 'verify_setting' )
			);

		}

	}

	/**
	 * Print the select setting element.
	 * 
	 * @param array $args The settings field arguments.
	 * 
	 * @return string
	 */
	public static function print_select_setting( $args ) {

		// Get the home & blog page reading options.
		$home_page = get_option( 'page_on_front', false );
		$blog_page = get_option( 'page_for_posts', false );

		$pages = wp_dropdown_pages(
			array(
				'id' => esc_attr( 'select_' . $args[ 'name' ] ),
				'name' => esc_attr( $args[ 'name' ] ),
				'selected' => $args[ 'value' ],
				'exclude' => implode( ',', array( $home_page, $blog_page ) ),
				'show_option_none' => esc_html__( 'Default', 'custom-archives' ),
				'echo' => false
			)
		);

		// Print options or the error message.
		echo ( $pages ) ? $pages : '<p>' . esc_html__( 'No pages to select.', 'custom-archives' ) . '</p>';

	}

	/**
	 * Verify the archive settings value.
	 * 
	 * @param int $new_value The new settings value.
	 * 
	 * @return void
	 */
	public static function verify_setting( $new_value ) {

		$id = (int) $new_value;

		$post = get_post( $id );

		if ( ! $post || 'page' != $post->post_type ) {

			return 0;

		}

		return $post->ID;

	}

	/**
	 * Redirect to the archive page.
	 * 
	 * When the custom archive page is requested, redirect to
	 * the real archive page so we can filter it properly.
	 * 
	 * @return void
	 */
	public static function archive_redirect() {

		global $post;

		$ids = self::get_custom_archive_ids();

		if ( $post && isset( $post->ID ) && in_array( $post->ID, $ids ) ) {

			$url = self::get_custom_archive_url( $post->ID );

			if ( false !== $url ) {

				/**
				 * Fires before redirecting to the custom archive.
				 * 
				 * @since 1.0
				 * 
				 * @param object $post The custom archive page object.
				 * @param string $url  The URL to redirect to.
				 */
				do_action( 'before_custom_archive_redirect', $post, $url );

				/**
				 * Filter the status header for the redirect.
				 * 
				 * Allow filtering of the HTTP status header code
				 * in cases where redirects may require a code other
				 * than the default 302 response.
				 * 
				 * @since 1.3
				 * 
				 * @param int           The default status header code.
				 * @param int $post->ID The current archive page ID.
				 */
				$code = apply_filters( 'custom_archive_http_code', 301, $post->ID );

				// Set the HTTP header.
				status_header( $code );

				wp_safe_redirect( $url, $code );

				die();

			}

		}

	}

	/**
	 * Check custom archive page on update.
	 * 
	 * This function ensures that in the event of a post having it's
	 * status changed to anything other than `published`, it'll remove
	 * it as a custom archive page.
	 * 
	 * @param string $new_status The new post status.
	 * @param string $old_status The old post status.
	 * @param object $post       The current post object.
	 * 
	 * @return void
	 */
	public static function post_status_updated( $new_status, $old_status, $post ) {

		if ( 'page' == $post->post_type && 'publish' !== $new_status ) {

			$post_type = self::get_custom_archive_post_type( $post->ID );

			if ( $post_type ) {

				delete_option( 'archive_page_' . $post_type );

			}

		}

	}

	/**
	 * Remove custom archive option on delete.
	 * 
	 * Delete the saved value for a custom archive page if that
	 * page gets deleted from the site.
	 * 
	 * @param int $post_id The deleted post id.
	 * 
	 * @return void
	 */
	public static function post_deleted( $post_id ) {

		$post_type = self::get_custom_archive_post_type( $post_id );

		if ( $post_type ) {

			delete_option( 'archive_page_' . $post_type );

		}

	}

	/**
	 * Add an edit link to the Toolbar.
	 * 
	 * Adds an edit page link to the toolbar when viewing an archive
	 * page that is using a custom archive.
	 * 
	 * @param object $wp_admin_bar The toolbar links.
	 * 
	 * @return void
	 */
	public static function add_edit_link( $wp_admin_bar ) {

		global $wp_query, $wp_the_query;

		$archive_ids = self::get_custom_archive_ids();

		// Get the archive page post type.
		$post_type = ( isset( $wp_query->query[ 'post_type' ] ) ) ? $wp_query->query[ 'post_type' ] : '';

		if ( ! is_admin() && true === $wp_query->is_archive && array_key_exists( $post_type, $archive_ids ) ) {

			$post = get_post( $archive_ids[ $wp_query->query[ 'post_type' ] ] );

			$post_type_object = get_post_type_object( $post->post_type );

			// Get the edit page URL.
			$edit_post_link = get_edit_post_link( $post->ID );

			if ( current_user_can( $post_type_object->cap->edit_post, $post->ID ) ) {

				$wp_admin_bar->add_menu(
					array(
						'id' => 'edit',
						'title' => $post_type_object->labels->edit_item,
						'href' => $edit_post_link
					)
				);

			}

		}

	}

	/**
	 * Add post states to custom archives.
	 * 
	 * @param array  $states The collection of post states.
	 * @param object $post   The current post object.
	 * 
	 * @return array $states
	 */
	public static function add_post_states( $states, $post ) {

		$types = self::get_custom_post_types();
		$ids = self::get_custom_archive_ids();

		if ( 'page' === $post->post_type ) {

			if ( in_array( $post->ID, $ids ) ) {

				// Does the page ID exist?
				$archive = array_search( $post->ID, $ids );

				// Add the new post state.
				$states[ 'archive_page_' . $post->post_type ] = sprintf( esc_html__( '%s Archive', 'custom-archives' ), $types[ $archive ]->labels->name );

			}

		}

		return $states;

	}

	/**
	 * Filter the custom archive page title.
	 * 
	 * @param array $title An array of title parts.
	 * 
	 * @return string $title
	 */
	public static function rewrite_page_title( $title ) {

		global $wp_query;

		if ( $wp_query->is_archive ) {

			$ids = self::get_custom_archive_ids();

			// Get the archive page post type.
			$post_type = ( isset( $wp_query->query[ 'post_type' ] ) ) ? $wp_query->query[ 'post_type' ] : '';

			// Is the page a custom archive?
			$post_id = ( isset( $ids[ $post_type ] ) ) ? $ids[ $post_type ] : 0;

			if ( 0 !== $post_id ) {

				$post = get_post( $post_id );

				$title[ 'title' ] = $post->post_title;

			}

		}

		return $title;

	}

	/**
	 * Include the template for the selected page
	 * for this custom archive.
	 * 
	 * Whilst it can be tricky in cases where a theme might not contain
	 * the default templates, this switches out the archive page template
	 * for the selected page template and gracefully degrades until a
	 * suitable one is found.
	 * 
	 * @param string $template The template name.
	 * 
	 * @return string $template
	 */
	public static function archive_template( $template ) {

		global $wp_query, $post;

		if ( $wp_query->is_archive ) {

			$ids = self::get_custom_archive_ids();

			// Get the archive page post type.
			$post_type = ( isset( $wp_query->query[ 'post_type' ] ) ) ? $wp_query->query[ 'post_type' ] : '';

			// Is the page a custom archive?
			$post_id = ( isset( $ids[ $post_type ] ) ) ? $ids[ $post_type ] : 0;

			if ( 0 !== $post_id ) {

				$post = get_post( $post_id );

				// Update the query.
				$wp_query->post = $post;
				$wp_query->query_vars[ 'archive_posts' ] = $wp_query->posts;
				$wp_query->posts = array( $post );
				$wp_query->query_vars[ 'p' ] = $post_id;
				$wp_query->query_vars[ 'page_id' ] = $post_id;

				// Get a post count of at least 1.
				$post_count = ( 1 <= (int) $wp_query->post_count ) ? (int) $wp_query->post_count : 1;

				/**
				 * Filter the current query post count.
				 * 
				 * The post count for the query must be 1 or more as when the
				 * `have_posts` function is run, if there are no published posts
				 * for this post type, (depending on the theme template used)
				 * nothing will be shown.
				 * 
				 * @since 3.0
				 * @since 3.0.1 Fix undefined `$query` variable warning.
				 * 
				 * @param int    $post_count The default number of posts.
				 * @param string $post_type  The archive page post type.
				 * @param object $wp_query   The current WP Query object.
				 */
				$wp_query->post_count = apply_filters( 'custom_archive_query_post_count', $post_count, $post_type, $wp_query );

				$directory = get_template_directory();

				// Get the page template file.
				$template = get_post_meta( $post->ID, '_wp_page_template', true );

				// Fallback if no template given.
				if ( '' == $template || false === $template || 'default' == $template ) {

					$template = 'page.php';

					// Does page.php not exist?
					if ( ! file_exists( $directory . '/' . $template ) ) {

						$template = 'index.php';

					}

				}

				/**
				 * Filter the page template for this custom archive.
				 * 
				 * @since 1.1
				 * 
				 * @param string $template  The selected page template.
				 * @param int    $post_id   The pages post id.
				 * @param string $post_type The post type for the archive page.
				 */
				$template = apply_filters( 'pre_custom_archive_template', $template, $post_id, $post_type );

				$template = $directory . '/' . $template;

			}

		}

		return $template;

	}

	/**
	 * Rewrite the document title.
	 * 
	 * @since string $title The document title.
	 * 
	 * @return string $title
	 */
	public static function rewrite_document_title( $title ) {

		global $wp_query;

		// Do we have a post type set?
		if ( isset( $wp_query->query[ 'post_type' ] ) && $wp_query->is_archive ) {

			$post_type = $wp_query->query[ 'post_type' ];

			// Fetch the custom archive page.
			$custom_archive = get_option( 'archive_page_' . $post_type, false );

			if ( $custom_archive ) {

				$page = get_post( $custom_archive );

				if ( $page ) {

					// Set the new document title.
					$title = sprintf( _x( '%s - %s', 'The page title and site name.', 'custom-archives' ), $page->post_title, get_bloginfo( 'name' ) );

					/**
					 * Filter the custom archive document title.
					 * 
					 * @since 2.0
					 * 
					 * @param string $title     The rewritten document title.
					 * @param object $page      The custom archive page.
					 * @param string $post_type The post type for this archive.
					 */
					$title = apply_filters( 'pre_archive_document_title', $title, $page, $post_type );

				}

			}

		}

		return $title;

	}

	/**
	 * Rewrite the edit permalink HTML.
	 * 
	 * @param string $return    The HTML markup.
	 * @param int    $post_id   The current post id.
	 * @param string $new_title New sample permalink title.
	 * @param string $new_slug  New sample permalink slug.
	 * @param object $post      The current post object.
	 * 
	 * @return string $return
	 */
	public static function rewrite_edit_permalink( $return, $post_id, $new_title, $new_slug, $post ) {

		if ( 'page' == $post->post_type ) {

			if ( in_array( $post->ID, self::get_custom_archive_ids() ) ) {

				$url = self::get_custom_archive_url( $post->ID );

				if ( false !== $url ) {

					$return = '<strong>' . esc_html__( 'Permalink:', 'custom-archives' ) . '</strong>&nbsp;';
					$return .= '<span id="sample-permalink"><a href="' . esc_url( $url ) . '" target="_blank">' . $url . '</a></span>';

				}

			}

		}

		return $return;

	}

}
