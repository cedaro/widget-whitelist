<?php
/**
 * Class to whitelist allowed widgets in a sidebar.
 *
 * First, initialize the class, passing a prefix and an internationalized notice
 * to the constructor.
 *
 * <code>
 * new Cedaro_WidgetWhitelist( array(
 *     'prefix' => 'promenade',
 *     'notice' => __( "This widget isn't configured for this widget area.", 'text-domain' ),
 * ) );
 * </code>
 *
 * When registering a sidebar, add an argument called 'allowed_widgets' with an
 * array of base widget ids (the first argument when registering a new widget).
 *
 * <code>
 * register_sidebar(
 *     'name'            => 'Main Sidebar',
 *     'allowed_widgets' => array( 'nav_menu', 'recent-posts', 'simpleimage', 'etc...' ),
 * );
 * </code>
 *
 * @package WidgetWhitelist
 * @author Brady Vercher <brady@blazersix.com>
 * @copyright Copyright (c) 2014, Cedaro
 * @license GPL-2.0+
 * @version 1.0.0
 */
class Cedaro_WidgetWhitelist {
	/**
	 * Prefix to prevent conflicts.
	 *
	 * Prepended to HTML class names and filters.
	 *
	 * @since 1.0.0
	 * @type string
	 */
	protected $prefix = 'cedaro_widgetwhitelist';

	/**
	 * Message to display for disallowed widgets.
	 *
	 * This will only be set if a message is passed in the constructor.
	 *
	 * @since 1.0.0
	 * @type string
	 */
	protected $notice = '';

	/**
	 * Constructor method.
	 *
	 * @since 1.0.0
	 *
	 * @param array $args {
	 *     Configuration options. Optional
	 *
	 *    @type string $prefix Prefix to prevent conflicts.
	 *    @type string $notice Message to display when a disallowed widget is added to a sidebar.
	 * }
	 */
	public function __construct( $args = array() ) {
		if ( ! empty( $args['prefix'] ) ) {
			$this->prefix = $args['prefix'];
		}

		// Frontend setup.
		if ( ! is_admin() ) {
			add_action( 'sidebars_widgets', array( $this, 'remove_disallowed_widgets' ) );
		}

		// Admin setup.
		if ( is_admin() ) {
			if ( ! empty( $args['notice'] ) ) {
				$this->notice = $args['notice'];
			}

			add_action( 'in_widget_form', array( $this, 'disallowed_notice' ) );
			add_action( 'widgets_admin_page', array( $this, 'disallowed_notice_css' ) );
			add_action( 'customize_controls_print_scripts', array( $this, 'disallowed_notice_css' ) );
		}
	}

	/**
	 * Remove disallowed widgets from a sidebar before it's rendered.
	 *
	 * @since 1.0.0
	 *
	 * @global $wp_registered_sidebars
	 *
	 * @param array $sidebars_widgets List of sidebars and their widgets.
	 * @return array
	 */
	public function remove_disallowed_widgets( $sidebars_widgets ) {
		global $wp_registered_sidebars;

		foreach ( $sidebars_widgets as $sidebar_id => $widgets ) {
			if ( ! isset( $wp_registered_sidebars[ $sidebar_id ] ) ) {
				continue;
			}

			$allowed_widgets = $this->get_allowed_widgets( $wp_registered_sidebars[ $sidebar_id ] );

			if ( empty( $widgets ) || empty( $allowed_widgets ) ) {
				continue;
			}

			foreach ( $widgets as $position => $widget_id ) {
				if ( preg_match( '/^(.+?)-(\d+)$/', $widget_id, $matches ) ) {
					$id_base = $matches[1];
					$widget_number = intval( $matches[2] );
				} else {
					$id_base = $widget_id;
					$widget_number = null;
				}

				// Unset widgets that haven't been whitelisted.
				if ( ! in_array( $id_base, $allowed_widgets ) ) {
					unset( $sidebars_widgets[ $sidebar_id ][ $position ] );
				}
			}
		}

		return $sidebars_widgets;
	}

	/**
	 * Retrieve a list of widgets allowed for a sidebar.
	 *
	 * @since 1.0.0
	 *
	 * @param array $sidebar_args Sidebar registration args.
	 * @return array
	 */
	public function get_allowed_widgets( $sidebar_args ) {
		$allowed_widgets = array();

		if ( ! empty( $sidebar_args['allowed_widgets'] ) ) {
			$allowed_widgets = (array) $sidebar_args['allowed_widgets'];
		}

		/**
		 * Widgets allowed for a sidebar.
		 *
		 * @see register_sidebar()
		 *
		 * @param array $allowed_widgets List of widget base IDs.
		 * @param array $sidebar_args Sidebar registration args.
		 */
		return apply_filters( $this->prefix . '_sidebar_allowed_widgets', $allowed_widgets, $sidebar_args );
	}

	/**
	 * Output styles to toggle the disallowed notice.
	 *
	 * @since 1.0.0
	 *
	 * @global $wp_registered_sidebars
	 */
	public function disallowed_notice_css() {
		global $wp_registered_sidebars;

		$css = '';
		foreach ( $wp_registered_sidebars as $sidebar ) {
			// Generate CSS to toggle the notice visibility.
			$css .= $this->generate_sidebar_notice_css( $sidebar );
		}
		?>

		<style type="text/css">
		.<?php echo $this->notice_identifier(); ?> {
			background: #c43;
			color: #fff;
			display: none;
			font-weight: bold;
			margin: 14px 0 15px 0;
			padding: 2px 15px;
			visibility: hidden;
		}
		<?php echo $css; ?>
		</style>
		<?php
	}

	/**
	 * Append the disallowed notice to all widgets in the admin.
	 *
	 * The notice should be hidden by default, then selectively enabled for
	 * sidebars using the 'allowed_widgets' argument.
	 *
	 * The JavaScript is called inline so that it can be invoked when widgets
	 * are saved and in the customizer.
	 *
	 * @since 1.0.0
	 */
	public function disallowed_notice( $widget ) {
		?>
		<div id="<?php echo $this->notice_identifier( $widget->id ); ?>" class="<?php echo $this->notice_identifier(); ?>">
			<?php echo wpautop( esc_html( $this->notice() ) ); ?>
		</div>
		<script type="text/javascript">
		(function( $ ) {
			var $notice = $( '#<?php echo esc_js( $this->notice_identifier( $widget->id ) ); ?>' );
			$notice.closest( '.widget-content' ).prepend( $notice );
		})( jQuery);
		</script>
		<?php
	}


	/**
	 * Retrieve the disallowed widget notice.
	 *
	 * To internationalize, either use the filter, extend the class and
	 * override this method, or pass it in the contructor config array.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	protected function notice() {
		$notice = $this->notice;
		if ( empty( $notice ) ) {
			$notice = 'This widget isn\'t configured for this widget area.';
		}

		/**
		 * Message to display for a disallowed widget.
		 *
		 * @param string $notice Disallowed widget message.
		 */
		return apply_filters( $this->prefix . '_disallowed_widget_notice', $notice );
	}

	/**
	 * Retrieve the notice identifier.
	 *
	 * @since 1.0.0
	 *
	 * @param string $suffix Append a suffix to the indentifier.
	 * @return string
	 */
	protected function notice_identifier( $suffix = '' ) {
		$suffix = empty( $suffix ) ? '' : '-' . $suffix;
		return $this->prefix . '-widget-disallowed-notice' . $suffix;
	}

	/**
	 * Generate CSS for styling disallowed widgets on the admin screen.
	 *
	 * @since 1.0.0
	 *
	 * @param array $sidebar Sidebar registration args.
	 * @return string
	 */
	protected function generate_sidebar_notice_css( $sidebar ) {
		global $wp_customize;

		$css = '';
		$notice_selector = '.' . $this->notice_identifier();
		$allowed_widgets = $this->get_allowed_widgets( $sidebar );

		if ( empty( $allowed_widgets ) ) {
			return $css;
		}

		$sidebar_selector = '#';
		$sidebar_selector .= ( $wp_customize && $wp_customize->is_preview() ) ? 'accordion-section-sidebar-widgets-' : '';
		$sidebar_selector .= $sidebar['id'];

		// Show the disallowed message in the sidebar by default.
		$css .= sprintf( '%s %s { display: block; visibility: visible;}', $sidebar_selector, $notice_selector );

		// Set the default widget title color.
		$css .= sprintf( '%s .widget-top { background: #ffeeee;}', $sidebar_selector );

		// Hide the disallowed message for whitelisted widgets.
		foreach ( $allowed_widgets as $widget_id_base ) {
			$css .= sprintf( '%1$s .widget[id*="_%2$s-"] .widget-top { background: #fafafa;}',
				$sidebar_selector,
				$widget_id_base
			);

			$css .= sprintf( '%1$s .widget[id*="_%2$s-"] %3$s { display: none; visibility: visible;}',
				$sidebar_selector,
				$widget_id_base,
				$notice_selector
			);
		}

		return $css;
	}
}
