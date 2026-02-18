<?php

/**
 * Icons API: WP_Icons_Registry class
 *
 * @package WordPress
 * @since 7.0.0
 */

/**
 * Core class used for interacting with registered icons.
 *
 * @phpstan-type Icon array{ name: string, label: string, content?: string, filePath?: string }
 *
 * @since 7.0.0
 */
class WP_Icons_Registry {
	/**
	 * Registered icons array.
	 *
	 * @phpstan-var array<string, Icon>
	 * @var array<string, array>
	 */
	private array $registered_icons = array();

	/**
	 * Container for the main instance of the class.
	 */
	private static ?WP_Icons_Registry $instance = null;

	/**
	 * Constructor.
	 *
	 * WP_Icons_Registry is a singleton class, so keep this private.
	 *
	 * For 7.0, the Icons Registry is closed for third-party icon registry,
	 * serving only a subset of core icons.
	 *
	 * These icons are defined in @wordpress/packages (Gutenberg repository) as
	 * SVG files and as entries in a single manifest file. On init, the
	 * registry is loaded with those icons listed in the manifest.
	 */
	private function __construct() {
		$icons_directory = __DIR__ . '/icons/';
		$icons_directory = trailingslashit( $icons_directory );
		$manifest_path   = $icons_directory . 'manifest.php';

		if ( ! is_readable( $manifest_path ) ) {
			wp_trigger_error(
				__METHOD__,
				__( 'Core icon collection manifest is missing or unreadable.' )
			);
			return;
		}

		$collection = include $manifest_path;

		if ( empty( $collection ) ) {
			wp_trigger_error(
				__METHOD__,
				__( 'Core icon collection manifest is empty or invalid.' )
			);
			return;
		}

		foreach ( $collection as $icon_name => $icon_data ) {
			if (
				empty( $icon_data['filePath'] )
				|| ! is_string( $icon_data['filePath'] )
			) {
				_doing_it_wrong(
					__METHOD__,
					__( 'Core icon collection manifest must provide valid a "filePath" for each icon.' ),
					'7.0.0'
				);
				return;
			}

			$this->register(
				'core/' . $icon_name,
				array(
					'label'    => $icon_data['label'],
					'filePath' => $icons_directory . $icon_data['filePath'],
				)
			);
		}
	}

	/**
	 * Registers an icon.
	 *
	 * @param string                $icon_name       Icon name including namespace.
	 * @param array<string, string> $icon_properties {
	 *     List of properties for the icon.
	 *
	 *     @type string $label    Required. A human-readable label for the icon.
	 *     @type string $content  Optional. SVG markup for the icon.
	 *                            If not provided, the content will be retrieved from the `filePath` if set.
	 *                            If both `content` and `filePath` are not set, the icon will not be registered.
	 *     @type string $filePath Optional. The full path to the file containing the icon content.
	 * }
	 * @phpstan-param array{ label: string, content?: string, filePath?: string } $icon_properties
	 * @return bool True if the icon was registered with success and false otherwise.
	 */
	private function register( $icon_name, array $icon_properties ): bool {
		if ( ! isset( $icon_name ) || ! is_string( $icon_name ) ) {
			_doing_it_wrong(
				__METHOD__,
				__( 'Icon name must be a string.' ),
				'7.0.0'
			);
			return false;
		}

		$allowed_keys = array_fill_keys( array( 'label', 'content', 'filePath' ), 1 );
		foreach ( array_keys( $icon_properties ) as $key ) {
			if ( ! array_key_exists( $key, $allowed_keys ) ) {
				_doing_it_wrong(
					__METHOD__,
					sprintf(
						// translators: %s is the name of any user-provided key
						__( 'Invalid icon property: "%s".' ),
						$key
					),
					'7.0.0'
				);
				return false;
			}
		}

		if ( ! isset( $icon_properties['label'] ) || ! is_string( $icon_properties['label'] ) ) {
			_doing_it_wrong(
				__METHOD__,
				__( 'Icon label must be a string.' ),
				'7.0.0'
			);
			return false;
		}

		if (
			( ! isset( $icon_properties['content'] ) && ! isset( $icon_properties['filePath'] ) ) ||
			( isset( $icon_properties['content'] ) && isset( $icon_properties['filePath'] ) )
		) {
			_doing_it_wrong(
				__METHOD__,
				__( 'Icons must provide either `content` or `filePath`.' ),
				'7.0.0'
			);
			return false;
		}

		if ( isset( $icon_properties['content'] ) ) {
			if ( ! is_string( $icon_properties['content'] ) ) {
				_doing_it_wrong(
					__METHOD__,
					__( 'Icon content must be a string.' ),
					'7.0.0'
				);
				return false;
			}

			$sanitized_icon_content = $this->sanitize_icon_content( $icon_properties['content'] );
			if ( empty( $sanitized_icon_content ) ) {
				_doing_it_wrong(
					__METHOD__,
					__( 'Icon content does not contain valid SVG markup.' ),
					'7.0.0'
				);
				return false;
			}
		}

		$icon = array_merge(
			$icon_properties,
			array( 'name' => $icon_name )
		);

		$this->registered_icons[ $icon_name ] = $icon;

		return true;
	}

	/**
	 * Sanitizes the icon SVG content.
	 *
	 * Logic borrowed from twentytwenty.
	 * @see twentytwenty_get_theme_svg()
	 *
	 * @param string $icon_content The icon SVG content to sanitize.
	 * @return string The sanitized icon SVG content.
	 */
	private function sanitize_icon_content( string $icon_content ): string {
		$allowed_tags = array(
			'svg'     => array(
				'class'       => true,
				'xmlns'       => true,
				'width'       => true,
				'height'      => true,
				'viewbox'     => true,
				'aria-hidden' => true,
				'role'        => true,
				'focusable'   => true,
			),
			'path'    => array(
				'fill'      => true,
				'fill-rule' => true,
				'd'         => true,
				'transform' => true,
			),
			'polygon' => array(
				'fill'      => true,
				'fill-rule' => true,
				'points'    => true,
				'transform' => true,
				'focusable' => true,
			),
		);
		return wp_kses( $icon_content, $allowed_tags );
	}

	/**
	 * Retrieves the content of a registered icon.
	 *
	 * @param string $icon_name Icon name including namespace.
	 * @return string|null The content of the icon, if found.
	 */
	private function get_content( string $icon_name ): ?string {
		if ( isset( $this->registered_icons[ $icon_name ]['content'] ) ) {
			return $this->registered_icons[ $icon_name ]['content'];
		}
		if ( isset( $this->registered_icons[ $icon_name ]['filePath'] ) ) {
			$content = file_get_contents(
				$this->registered_icons[ $icon_name ]['filePath']
			);
			if ( false !== $content ) {
				$content = $this->sanitize_icon_content( $content );
			}

			if ( empty( $content ) ) {
				wp_trigger_error(
					__METHOD__,
					__( 'Icon content does not contain valid SVG markup.' )
				);
				return null;
			}

			$this->registered_icons[ $icon_name ]['content'] = $content;
		}
		return null;
	}

	/**
	 * Retrieves an array containing the properties of a registered icon.
	 *
	 * @param string $icon_name Icon name including namespace.
	 * @return array<string, mixed>|null Registered icon properties or `null` if the icon is not registered.
	 * @phpstan-return Icon|null
	 */
	public function get_registered_icon( string $icon_name ): ?array {
		if ( ! $this->is_registered( $icon_name ) ) {
			return null;
		}

		$icon              = $this->registered_icons[ $icon_name ];
		$icon['content'] ??= $this->get_content( $icon_name );
		if ( null === $icon['content'] ) {
			return null;
		}

		return $icon;
	}

	/**
	 * Retrieves all registered icons.
	 *
	 * @param string $search Optional. Search term by which to filter the icons.
	 * @return array<array<string, mixed>> Array of arrays containing the registered icon properties.
	 * @phpstan-return array<int, Icon>
	 */
	public function get_registered_icons( string $search = '' ): array {
		$icons = array();

		foreach ( $this->registered_icons as $icon ) {
			if ( ! empty( $search ) && false === stripos( $icon['name'], $search ) ) {
				continue;
			}

			$icon['content'] ??= $this->get_content( $icon['name'] );
			if ( null !== $icon['content'] ) {
				$icons[] = $icon;
			}
		}

		return $icons;
	}

	/**
	 * Checks if an icon is registered.
	 *
	 *
	 * @param string $icon_name Icon name including namespace.
	 * @return bool True if the icon is registered, false otherwise.
	 */
	public function is_registered( string $icon_name ): bool {
		return isset( $this->registered_icons[ $icon_name ] );
	}

	/**
	 * Utility method to retrieve the main instance of the class.
	 *
	 * The instance will be created if it does not exist yet.
	 *
	 * @return WP_Icons_Registry The main instance.
	 */
	public static function get_instance(): WP_Icons_Registry {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}
}
