<?php
/**
 * Shared, optional update-channel guidance on the WordPress Plugins screen.
 *
 * Generated from the shared Devenia notice source. Keep changes in that source.
 *
 * @package mcp-abilities-generatepress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'mcp_abilities_generatepress_Updater_Notice', false ) ) {
	/** One notice for all participating plugins, without an updater dependency. */
	final class mcp_abilities_generatepress_Updater_Notice {
		/** @var string[] Plugin basenames that use the Devenia update channel. */
		private static $plugins = array();

		/** Register a participating plugin and install shared hooks once. */
		public static function register( $plugin_file ) {
			if ( ! has_action( 'admin_post_devenia_dismiss_updater_notice' ) ) {
				add_action( 'admin_notices', array( __CLASS__, 'render' ) );
				add_action( 'network_admin_notices', array( __CLASS__, 'render' ) );
				add_action( 'admin_post_devenia_dismiss_updater_notice', array( __CLASS__, 'dismiss' ) );
			}
			if ( ! self::$plugins ) {
				add_filter( 'plugin_action_links', array( __CLASS__, 'links' ), 10, 2 );
				add_filter( 'network_admin_plugin_action_links', array( __CLASS__, 'links' ), 10, 2 );
			}
			self::$plugins[] = plugin_basename( $plugin_file );
		}

		/** Get the native action the current administrator is allowed to take. */
		private static function action() {
			$updater = 'devenia-mcp-updater/devenia-mcp-updater.php';
			if ( is_plugin_active( $updater ) || is_plugin_active_for_network( $updater ) ) {
				return null;
			}
			if ( is_multisite() && ! is_network_admin() ) {
				return null;
			}
			$installed = get_plugins();
			if ( isset( $installed[ $updater ] ) ) {
				if ( ! current_user_can( 'activate_plugin', $updater ) ) {
					return null;
				}
				$url = add_query_arg(
					array( 'action' => 'activate', 'plugin' => $updater ),
					self_admin_url( 'plugins.php' )
				);
				if ( is_network_admin() ) {
					$url = add_query_arg( 'networkwide', '1', $url );
				}
				return array(
					'url' => wp_nonce_url( $url, 'activate-plugin_' . $updater ),
					'label' => __( 'Activate Devenia MCP Updater', 'mcp-abilities-generatepress' ),
					'installed' => true,
				);
			}
			if ( ! current_user_can( 'install_plugins' ) ) {
				return null;
			}
			return array(
				'url' => self_admin_url( 'plugin-install.php?tab=upload' ),
				'label' => __( 'Install Devenia MCP Updater', 'mcp-abilities-generatepress' ),
				'installed' => false,
			);
		}

		/** Keep update guidance available after the administrator dismisses it. */
		public static function links( $links, $plugin_file ) {
			if ( ! in_array( $plugin_file, self::$plugins, true ) ) {
				return $links;
			}
			$action = self::action();
			if ( $action ) {
				$links['devenia-updater'] = '<a href="' . esc_url( $action['url'] ) . '">' . esc_html( $action['label'] ) . '</a>';
				if ( ! $action['installed'] ) {
					$links['devenia-updater-download'] = '<a href="' . esc_url( 'https://downloads.devenia.com/devenia-mcp-updater.zip' ) . '">' . esc_html__( 'Download updater ZIP', 'mcp-abilities-generatepress' ) . '</a>';
				}
			}
			return $links;
		}

		/** Show one shared notice on the Plugins screen only. */
		public static function render() {
			$screen = get_current_screen();
			if ( ! $screen || ! in_array( $screen->id, array( 'plugins', 'plugins-network' ), true ) ) {
				return;
			}
			if ( get_user_meta( get_current_user_id(), self::dismissal_key(), true ) ) {
				return;
			}
			$action = self::action();
			if ( ! $action ) {
				return;
			}
			echo '<div class="notice notice-info"><p>';
			echo esc_html__( 'Get updates for your Devenia plugins through WordPress. Devenia MCP Updater connects them to the update channel; you choose which plugins update automatically.', 'mcp-abilities-generatepress' );
			echo '</p><p>';
			if ( ! $action['installed'] ) {
				echo '<a href="' . esc_url( 'https://downloads.devenia.com/devenia-mcp-updater.zip' ) . '">' . esc_html__( 'Download the updater ZIP', 'mcp-abilities-generatepress' ) . '</a> — ';
				echo esc_html__( 'then upload it using the install button.', 'mcp-abilities-generatepress' ) . ' ';
			}
			echo '<a class="button button-secondary" href="' . esc_url( $action['url'] ) . '">' . esc_html( $action['label'] ) . '</a>';
			echo '</p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><p>';
			echo '<input type="hidden" name="action" value="devenia_dismiss_updater_notice" />';
			wp_nonce_field( 'devenia_dismiss_updater_notice' );
			echo '<input type="hidden" name="network" value="' . ( is_network_admin() ? '1' : '0' ) . '" />';
			echo '<button type="submit" class="button-link">' . esc_html__( 'Dismiss this reminder', 'mcp-abilities-generatepress' ) . '</button>';
			echo '</p></form></div>';
		}

		/** Scope dismissal to this site or network, for this administrator. */
		private static function dismissal_key() {
			return 'devenia_updater_notice_dismissed_' . ( is_multisite() ? 'network_' . get_current_network_id() : 'site_' . get_current_blog_id() );
		}

		/** Persist only a nonce-protected, authorized dismissal. */
		public static function dismiss() {
			check_admin_referer( 'devenia_dismiss_updater_notice' );
			if ( ! current_user_can( 'install_plugins' ) && ! current_user_can( 'activate_plugins' ) ) {
				wp_die( esc_html__( 'You do not have permission to manage plugins.', 'mcp-abilities-generatepress' ) );
			}
			update_user_meta( get_current_user_id(), self::dismissal_key(), true );
			$network = isset( $_POST['network'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['network'] ) );
			$url = $network && is_multisite() ? network_admin_url( 'plugins.php' ) : admin_url( 'plugins.php' );
			wp_safe_redirect( $url );
			exit;
		}
	}
}
