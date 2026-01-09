<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OBA_Admin {

	private $repo;
	private $engine;
	private $credits;
	private $settings;

	public function __construct() {
		$this->repo     = new OBA_Auction_Repository();
		$this->engine   = new OBA_Auction_Engine();
		$this->credits  = new OBA_Credits_Service();
		$this->settings = OBA_Settings::get_settings();
	}

	public function hooks() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_head', array( $this, 'hide_detail_submenu' ) );
		add_action( 'admin_post_oba_set_status', array( $this, 'handle_set_status' ) );
		add_action( 'admin_post_oba_recalc_winner', array( $this, 'handle_recalc_winner' ) );
		add_action( 'admin_post_oba_edit_credits', array( $this, 'handle_edit_credits' ) );
		add_action( 'admin_post_oba_save_settings', array( $this, 'handle_save_settings' ) );
		add_action( 'admin_post_oba_save_translations', array( $this, 'handle_save_translations' ) );
		add_action( 'admin_post_oba_save_emails', array( $this, 'handle_save_emails' ) );
		add_action( 'admin_post_oba_send_test_email', array( $this, 'handle_send_test_email' ) );
		add_action( 'admin_post_nopriv_oba_send_test_email', array( $this, 'handle_send_test_email' ) );
		add_action( 'admin_post_oba_run_expiry', array( $this, 'handle_run_expiry' ) );
		add_action( 'admin_post_oba_manual_winner', array( $this, 'handle_manual_winner' ) );
		add_action( 'admin_post_oba_remove_participant', array( $this, 'handle_remove_participant' ) );
		add_action( 'admin_post_oba_approve_registration', array( $this, 'handle_approve_registration' ) );
		add_action( 'admin_post_oba_export_participants', array( $this, 'handle_export_participants' ) );
		add_action( 'admin_post_oba_save_membership', array( $this, 'handle_save_membership' ) );
		add_filter( 'bulk_actions-edit-product', array( $this, 'register_bulk_actions' ) );
		add_filter( 'handle_bulk_actions-edit-product', array( $this, 'handle_bulk_actions' ), 10, 3 );

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			$this->register_cli();
		}
	}

	private function capability() {
		return 'read';
	}

	private function can_manage() {
		return current_user_can( 'manage_woocommerce' ) || current_user_can( 'manage_options' ) || current_user_can( 'read' );
	}

	public function register_menu() {
		$cap = $this->capability();
		// New simplified menu container for future UX re-organization.
		add_menu_page(
			__( '1BA Auctions', 'one-ba-auctions' ),
			__( '1BA Auctions', 'one-ba-auctions' ),
			$cap,
			'oba-1ba-auctions',
			array( $this, 'render_1ba_placeholder_page' ),
			'dashicons-awards',
			57
		);
		add_submenu_page(
			'oba-1ba-auctions',
			__( 'All Auctions', 'one-ba-auctions' ),
			__( 'All Auctions', 'one-ba-auctions' ),
			$cap,
			'oba-1ba-auctions',
			array( $this, 'render_1ba_auctions_all' )
		);
		add_submenu_page(
			'oba-1ba-auctions',
			__( 'Audit Log', 'one-ba-auctions' ),
			__( 'Audit Log', 'one-ba-auctions' ),
			$cap,
			'oba-1ba-audit',
			array( $this, 'render_audit_page' )
		);
		add_submenu_page(
			'oba-1ba-auctions',
			__( 'Settings', 'one-ba-auctions' ),
			__( 'Settings', 'one-ba-auctions' ),
			$cap,
			'oba-1ba-settings',
			array( $this, 'render_settings_page' )
		);
		add_submenu_page(
			'oba-1ba-auctions',
			__( 'Memberships', 'one-ba-auctions' ),
			__( 'Memberships', 'one-ba-auctions' ),
			$cap,
			'oba-1ba-memberships',
			array( $this, 'render_memberships_page' )
		);
		// Hidden detail page for single auction view.
		add_submenu_page(
			'oba-1ba-auctions',
			__( 'Auction Detail', 'one-ba-auctions' ),
			__( 'Auction Detail', 'one-ba-auctions' ),
			$cap,
			'oba-1ba-auction',
			array( $this, 'render_1ba_auction_detail' )
		);
	}

	public function hide_detail_submenu() {
		echo '<style>#toplevel_page_oba-1ba-auctions .wp-submenu a[href="admin.php?page=oba-1ba-auction"]{display:none!important;}</style>';
	}

	private function get_status_filter() {
		$status  = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : 'registration';
		$allowed = array( 'registration', 'pre_live', 'live', 'ended' );
		return in_array( $status, $allowed, true ) ? $status : 'registration';
	}

	public function render_auctions_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$status       = $this->get_status_filter();
		$filter_links = array(
			'registration' => __( 'Upcoming', 'one-ba-auctions' ),
			'pre_live'     => __( 'Waiting to Go Live', 'one-ba-auctions' ),
			'live'         => __( 'Live', 'one-ba-auctions' ),
			'ended'        => __( 'Ended', 'one-ba-auctions' ),
		);

		$page     = isset( $_GET['a_page'] ) ? max( 1, absint( $_GET['a_page'] ) ) : 1;
		$per_page = 50;
		$query    = new WP_Query(
			array(
				'post_type'      => 'product',
				'posts_per_page' => 500,
				'post_status'    => array( 'publish', 'draft', 'pending' ),
				'fields'         => 'ids',
			)
		);

		$status_counts = array(
			'registration' => 0,
			'pre_live'     => 0,
			'live'         => 0,
			'ended'        => 0,
		);

		$auction_ids = $query->posts;
		$total       = 0;
		foreach ( $auction_ids as $pid ) {
			$product = wc_get_product( $pid );
			if ( ! $product || 'auction' !== $product->get_type() ) {
				continue;
			}
			$st = strtolower( (string) get_post_meta( $pid, '_auction_status', true ) );
			$allowed_statuses = array( 'registration', 'pre_live', 'live', 'ended' );
			if ( ! in_array( $st, $allowed_statuses, true ) ) {
				$st = 'registration';
			}
			$status_counts[ $st ]++;
			$total++;
		}

		$paged_ids = array_slice( $auction_ids, ( $page - 1 ) * $per_page, $per_page );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Custom Auctions', 'one-ba-auctions' ); ?></h1>
			<h2 class="nav-tab-wrapper">
				<?php foreach ( $filter_links as $key => $label ) : ?>
					<?php $count = isset( $status_counts[ $key ] ) ? (int) $status_counts[ $key ] : 0; ?>
					<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'oba-1ba-auctions', 'status' => $key ), admin_url( 'admin.php' ) ) ); ?>" class="nav-tab <?php echo $status === $key ? 'nav-tab-active' : ''; ?>">
						<?php echo esc_html( $label ); ?> (<?php echo esc_html( $count ); ?>)
					</a>
				<?php endforeach; ?>
			</h2>
			<table class="widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'ID', 'one-ba-auctions' ); ?></th>
						<th><?php esc_html_e( 'Title', 'one-ba-auctions' ); ?></th>
						<th><?php esc_html_e( 'Status', 'one-ba-auctions' ); ?></th>
						<th><?php esc_html_e( 'Participants', 'one-ba-auctions' ); ?></th>
						<th><?php esc_html_e( 'Live expires at', 'one-ba-auctions' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'one-ba-auctions' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					$has = false;
					foreach ( $paged_ids as $id ) :
						$product = wc_get_product( $id );
						if ( ! $product || 'auction' !== $product->get_type() ) {
							continue;
						}
						$status_meta      = strtolower( (string) get_post_meta( $id, '_auction_status', true ) );
						$allowed_statuses = array( 'registration', 'pre_live', 'live', 'ended' );
						if ( ! in_array( $status_meta, $allowed_statuses, true ) ) {
							$status_meta = 'registration';
						}
						$current_status = $status_meta ? $status_meta : 'registration';
						if ( $current_status !== $status ) {
							continue;
						}
						$has   = true;
						$count = $this->repo->get_participant_count( $id );
						$req   = (int) get_post_meta( $id, '_required_participants', true );
						?>
						<tr>
							<td><?php echo esc_html( $id ); ?></td>
							<td><a href="<?php echo esc_url( get_edit_post_link( $id ) ); ?>"><?php echo esc_html( get_the_title( $id ) ); ?></a></td>
							<td><?php echo esc_html( $current_status ); ?></td>
							<td>
								<?php echo esc_html( "{$count}/{$req}" ); ?>
								<?php
								global $wpdb;
								$pt  = $wpdb->prefix . 'auction_participants';
								$st_counts = $wpdb->get_results(
									$wpdb->prepare(
										"SELECT status, COUNT(*) as total FROM {$pt} WHERE auction_id = %d GROUP BY status",
										$id
									),
									ARRAY_A
								);
								$labels = array();
								foreach ( $st_counts as $row ) {
									$labels[] = esc_html( $row['status'] . ':' . $row['total'] );
								}
								if ( $labels ) {
									echo '<div style="font-size:12px;color:#666;">' . implode( ' | ', $labels ) . '</div>';
								}
								?>
							</td>
							<td><?php echo esc_html( get_post_meta( $id, '_live_expires_at', true ) ); ?></td>
							<td>
								<?php
								$actions = array();
								if ( 'registration' === $current_status ) {
									$actions['pre_live'] = __( 'Start Pre-live', 'one-ba-auctions' );
								}
								if ( in_array( $current_status, array( 'registration', 'pre_live' ), true ) ) {
									$actions['live'] = __( 'Start Live', 'one-ba-auctions' );
								}
								if ( 'live' === $current_status ) {
									$actions['force_end'] = __( 'Force End', 'one-ba-auctions' );
								}
								if ( 'ended' === $current_status || 'live' === $current_status ) {
									$actions['recalc'] = __( 'Recalculate Winner', 'one-ba-auctions' );
								}
								foreach ( $actions as $action => $label ) {
									$url = wp_nonce_url(
										add_query_arg(
											array(
												'action'     => 'oba_set_status',
												'auction_id' => $id,
												'status'     => $action,
											),
											admin_url( 'admin-post.php' )
										),
										"oba_set_status_{$id}"
									);
									if ( 'recalc' === $action ) {
										$url = wp_nonce_url(
											add_query_arg(
												array(
													'action'     => 'oba_recalc_winner',
													'auction_id' => $id,
												),
												admin_url( 'admin-post.php' )
											),
											"oba_recalc_{$id}"
										);
									}
									printf(
										'<a class="button button-small" href="%s">%s</a> ',
										esc_url( $url ),
										esc_html( $label )
									);
								}
								?>
							</td>
						</tr>
						<?php
					endforeach;
					if ( ! $has ) :
						?>
						<tr><td colspan="6"><?php esc_html_e( 'No auctions found for this status.', 'one-ba-auctions' ); ?></td></tr>
					<?php endif; ?>
				</tbody>
			</table>
			<p>
				<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=oba_run_expiry' ), 'oba_run_expiry' ) ); ?>">
					<?php esc_html_e( 'Run expiry check now', 'one-ba-auctions' ); ?>
				</a>
			</p>
			<?php
			$total_pages = max( 1, $per_page ? ceil( $total / $per_page ) : 1 );
			if ( $total_pages > 1 ) :
				$current_url = remove_query_arg( array( 'a_page' ) );
				?>
				<div class="tablenav">
					<div class="tablenav-pages">
						<span class="pagination-links">
							<?php for ( $p = 1; $p <= $total_pages; $p++ ) : ?>
								<?php
								$link = add_query_arg(
									array(
										'a_page' => $p,
									),
									$current_url
								);
								?>
								<a class="button <?php echo $p === $page ? 'button-primary' : ''; ?>" href="<?php echo esc_url( $link ); ?>"><?php echo esc_html( $p ); ?></a>
							<?php endfor; ?>
						</span>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	public function render_winners_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		global $wpdb;
		$table   = $wpdb->prefix . 'auction_winners';
		$winners = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id DESC LIMIT 50", ARRAY_A );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Winners', 'one-ba-auctions' ); ?></h1>
			<table class="widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Auction', 'one-ba-auctions' ); ?></th>
						<th><?php esc_html_e( 'Winner', 'one-ba-auctions' ); ?></th>
						<th><?php esc_html_e( 'Total bids', 'one-ba-auctions' ); ?></th>
						<th><?php esc_html_e( 'Credits consumed', 'one-ba-auctions' ); ?></th>
						<th><?php esc_html_e( 'Claim price', 'one-ba-auctions' ); ?></th>
						<th><?php esc_html_e( 'Order', 'one-ba-auctions' ); ?></th>
						<th><?php esc_html_e( 'Created', 'one-ba-auctions' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( $winners ) : ?>
						<?php foreach ( $winners as $row ) : ?>
							<?php
							$user       = get_user_by( 'id', $row['winner_user_id'] );
							$order_link = $row['wc_order_id'] ? sprintf( '<a href="%s">%s</a>', esc_url( get_edit_post_link( $row['wc_order_id'] ) ), esc_html( $row['wc_order_id'] ) ) : '-';
							?>
							<tr>
								<td><a href="<?php echo esc_url( get_edit_post_link( $row['auction_id'] ) ); ?>"><?php echo esc_html( $row['auction_id'] ); ?></a></td>
								<td><?php echo esc_html( $user ? $user->display_name : $row['winner_user_id'] ); ?></td>
								<td><?php echo esc_html( $row['total_bids'] ); ?></td>
								<td><?php echo esc_html( $row['total_credits_consumed'] ); ?></td>
								<td><?php echo esc_html( $row['claim_price_credits'] ); ?></td>
								<td><?php echo $order_link; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
								<td><?php echo esc_html( $row['created_at'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php else : ?>
						<tr><td colspan="7"><?php esc_html_e( 'No winners yet.', 'one-ba-auctions' ); ?></td></tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	public function render_ended_logs_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$auction_id = isset( $_GET['auction_id'] ) ? absint( $_GET['auction_id'] ) : 0;
		$logs       = OBA_Audit_Log::ended_logs( 200, $auction_id );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Ended Auctions Log', 'one-ba-auctions' ); ?></h1>

			<form method="get" style="margin-bottom:12px;">
				<input type="hidden" name="page" value="oba-ended-logs" />
				<label>
					<?php esc_html_e( 'Filter by Auction ID', 'one-ba-auctions' ); ?>:
					<input type="number" name="auction_id" value="<?php echo esc_attr( $auction_id ); ?>" min="1" />
				</label>
				<?php submit_button( __( 'Filter', 'one-ba-auctions' ), 'secondary', '', false ); ?>
			</form>

			<table class="widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Time', 'one-ba-auctions' ); ?></th>
						<th><?php esc_html_e( 'Auction', 'one-ba-auctions' ); ?></th>
						<th><?php esc_html_e( 'Winner', 'one-ba-auctions' ); ?></th>
						<th><?php esc_html_e( 'Bids', 'one-ba-auctions' ); ?></th>
						<th><?php esc_html_e( 'Credits used', 'one-ba-auctions' ); ?></th>
						<th><?php esc_html_e( 'Refunded', 'one-ba-auctions' ); ?></th>
						<th><?php esc_html_e( 'Claim price', 'one-ba-auctions' ); ?></th>
						<th><?php esc_html_e( 'Trigger', 'one-ba-auctions' ); ?></th>
						<th><?php esc_html_e( 'Last bid', 'one-ba-auctions' ); ?></th>
						<th><?php esc_html_e( 'Order', 'one-ba-auctions' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( $logs ) : ?>
						<?php foreach ( $logs as $row ) : ?>
							<?php
							$details    = is_serialized( $row['details'] ) ? maybe_unserialize( $row['details'] ) : (array) $row['details'];
							$row_auction_id = (int) $row['auction_id'];
							$auction    = $row_auction_id ? get_the_title( $row_auction_id ) : '';
							$auction_link = $row_auction_id ? '<a href="' . esc_url( get_edit_post_link( $row_auction_id ) ) . '">' . esc_html( $auction ? $auction : $row_auction_id ) . '</a>' : '-';
							$winner_id  = isset( $details['winner_id'] ) ? (int) $details['winner_id'] : 0;
							$winner     = $winner_id ? get_user_by( 'id', $winner_id ) : null;
							$winner_name = $winner ? $winner->display_name : ( $winner_id ? $winner_id : '-' );
							$last_user  = isset( $details['last_bid_user_id'] ) ? get_user_by( 'id', (int) $details['last_bid_user_id'] ) : null;
							$last_bid_text = $last_user ? $last_user->display_name : ( isset( $details['last_bid_user_id'] ) ? $details['last_bid_user_id'] : '-' );
							if ( isset( $details['last_bid_amount'] ) ) {
								$last_bid_text .= ' (' . floatval( $details['last_bid_amount'] ) . ' cr)';
							}
							if ( ! empty( $details['last_bid_time'] ) ) {
								$last_bid_text .= ' @ ' . esc_html( $details['last_bid_time'] );
							}
							$winner_row = $row_auction_id ? $this->repo->get_winner_row( $row_auction_id ) : null;
							$order_link = '-';
							if ( $winner_row && ! empty( $winner_row['wc_order_id'] ) ) {
								$order_link = '<a href="' . esc_url( admin_url( 'post.php?post=' . $winner_row['wc_order_id'] . '&action=edit' ) ) . '">#' . esc_html( $winner_row['wc_order_id'] ) . '</a>';
							}
							?>
							<tr>
								<td><?php echo esc_html( $row['created_at'] ); ?></td>
								<td><?php echo $auction_link; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
								<td><?php echo esc_html( $winner_name ); ?></td>
								<td><?php echo isset( $details['total_bids'] ) ? esc_html( $details['total_bids'] ) : '-'; ?></td>
								<td><?php echo isset( $details['total_credits_consumed'] ) ? esc_html( $details['total_credits_consumed'] ) : '-'; ?></td>
								<td><?php echo isset( $details['refund_total'] ) ? esc_html( $details['refund_total'] ) : '-'; ?></td>
								<td><?php echo isset( $details['claim_price'] ) ? esc_html( $details['claim_price'] ) : '-'; ?></td>
								<td><?php echo isset( $details['trigger'] ) ? esc_html( $details['trigger'] ) : '-'; ?></td>
								<td><?php echo esc_html( $last_bid_text ); ?></td>
								<td><?php echo $order_link; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
							</tr>
						<?php endforeach; ?>
					<?php else : ?>
						<tr><td colspan="10"><?php esc_html_e( 'No ended auction logs yet.', 'one-ba-auctions' ); ?></td></tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	public function render_audit_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$entries     = OBA_Audit_Log::latest( 200 );
		$user_filter = isset( $_GET['user_id'] ) ? absint( $_GET['user_id'] ) : 0;
		$ledger      = array();
		if ( $user_filter ) {
			$ledger = OBA_Ledger::get_user_entries( $user_filter, 200 );
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Audit Log', 'one-ba-auctions' ); ?></h1>
			<table class="widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Time', 'one-ba-auctions' ); ?></th>
						<th><?php esc_html_e( 'Actor', 'one-ba-auctions' ); ?></th>
						<th><?php esc_html_e( 'Auction', 'one-ba-auctions' ); ?></th>
						<th><?php esc_html_e( 'Action', 'one-ba-auctions' ); ?></th>
						<th><?php esc_html_e( 'Details', 'one-ba-auctions' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( $entries ) : ?>
						<?php foreach ( $entries as $entry ) : ?>
							<?php $user = $entry['actor_id'] ? get_user_by( 'id', $entry['actor_id'] ) : null; ?>
							<tr>
								<td><?php echo esc_html( $entry['created_at'] ); ?></td>
								<td><?php echo esc_html( $user ? $user->display_name : '-' ); ?></td>
								<td><?php echo esc_html( $entry['auction_id'] ? $entry['auction_id'] : '-' ); ?></td>
								<td><?php echo esc_html( $entry['action'] ); ?></td>
								<td><code><?php echo esc_html( is_serialized( $entry['details'] ) ? wp_json_encode( maybe_unserialize( $entry['details'] ) ) : $entry['details'] ); ?></code></td>
							</tr>
						<?php endforeach; ?>
					<?php else : ?>
						<tr><td colspan="5"><?php esc_html_e( 'No audit entries yet.', 'one-ba-auctions' ); ?></td></tr>
					<?php endif; ?>
				</tbody>
			</table>

			<?php if ( $user_filter ) : ?>
				<h2><?php esc_html_e( 'User Credit Ledger', 'one-ba-auctions' ); ?></h2>
				<table class="widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Time', 'one-ba-auctions' ); ?></th>
							<th><?php esc_html_e( 'Amount', 'one-ba-auctions' ); ?></th>
							<th><?php esc_html_e( 'Balance after', 'one-ba-auctions' ); ?></th>
							<th><?php esc_html_e( 'Reason', 'one-ba-auctions' ); ?></th>
							<th><?php esc_html_e( 'Reference', 'one-ba-auctions' ); ?></th>
							<th><?php esc_html_e( 'Meta', 'one-ba-auctions' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( $ledger ) : ?>
							<?php foreach ( $ledger as $entry ) : ?>
								<tr>
									<td><?php echo esc_html( $entry['created_at'] ); ?></td>
									<td><?php echo esc_html( $entry['amount'] ); ?></td>
									<td><?php echo esc_html( $entry['balance_after'] ); ?></td>
									<td><?php echo esc_html( $entry['reason'] ); ?></td>
									<td><?php echo esc_html( $entry['reference_id'] ?: '-' ); ?></td>
									<td><code><?php echo esc_html( is_serialized( $entry['meta'] ) ? wp_json_encode( maybe_unserialize( $entry['meta'] ) ) : $entry['meta'] ); ?></code></td>
								</tr>
							<?php endforeach; ?>
						<?php else : ?>
							<tr><td colspan="6"><?php esc_html_e( 'No ledger entries yet.', 'one-ba-auctions' ); ?></td></tr>
						<?php endif; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	public function render_credits_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		global $wpdb;
		$table   = $wpdb->prefix . 'auction_user_credits';
		$credits = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY user_id ASC LIMIT 100", ARRAY_A );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'User Credits', 'one-ba-auctions' ); ?></h1>
			<div style="overflow-x:auto;">
			<table class="widefat fixed striped" style="min-width: 1100px; table-layout: auto;">
				<thead>
					<tr>
						<th><?php esc_html_e( 'User ID', 'one-ba-auctions' ); ?></th>
						<th><?php esc_html_e( 'Username', 'one-ba-auctions' ); ?></th>
						<th><?php esc_html_e( 'Name', 'one-ba-auctions' ); ?></th>
						<th><?php esc_html_e( 'Email', 'one-ba-auctions' ); ?></th>
						<th><?php esc_html_e( 'Balance', 'one-ba-auctions' ); ?></th>
						<th><?php esc_html_e( 'Updated', 'one-ba-auctions' ); ?></th>
						<th><?php esc_html_e( 'Edit', 'one-ba-auctions' ); ?></th>
						<th><?php esc_html_e( 'Ledger', 'one-ba-auctions' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( $credits ) : ?>
						<?php foreach ( $credits as $row ) : ?>
							<?php
							$user      = get_userdata( (int) $row['user_id'] );
							$username  = $user ? $user->user_login : __( 'Unknown', 'one-ba-auctions' );
							$full_name = $user ? trim( $user->first_name . ' ' . $user->last_name ) : '';
							$email     = $user ? $user->user_email : '';
							?>
							<tr>
								<td><?php echo esc_html( $row['user_id'] ); ?></td>
								<td><?php echo esc_html( $username ); ?></td>
								<td><?php echo esc_html( $full_name ); ?></td>
								<td><?php echo esc_html( $email ); ?></td>
								<td><?php echo esc_html( $row['credits_balance'] ); ?></td>
								<td><?php echo esc_html( $row['updated_at'] ); ?></td>
								<td>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:flex; gap:6px; align-items:center; flex-wrap:wrap;">
										<input type="hidden" name="action" value="oba_edit_credits" />
										<input type="hidden" name="user_id" value="<?php echo esc_attr( $row['user_id'] ); ?>" />
										<?php wp_nonce_field( 'oba_edit_credits_' . $row['user_id'] ); ?>
										<input type="number" step="0.01" min="0" name="credits_balance" value="<?php echo esc_attr( $row['credits_balance'] ); ?>" />
										<button class="button button-small"><?php esc_html_e( 'Save', 'one-ba-auctions' ); ?></button>
									</form>
								</td>
								<td><a href="<?php echo esc_url( add_query_arg( array( 'page' => 'oba-audit', 'user_id' => $row['user_id'] ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'View', 'one-ba-auctions' ); ?></a></td>
							</tr>
						<?php endforeach; ?>
					<?php else : ?>
						<tr><td colspan="8"><?php esc_html_e( 'No credit records yet.', 'one-ba-auctions' ); ?></td></tr>
					<?php endif; ?>
				</tbody>
			</table>
			</div>
		</div>
		<?php
	}

	public function render_participants_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$auction_id    = isset( $_GET['auction_id'] ) ? absint( $_GET['auction_id'] ) : 0;
		$status_filter = isset( $_GET['p_status'] ) ? sanitize_text_field( wp_unslash( $_GET['p_status'] ) ) : '';
		$search_user   = isset( $_GET['p_user'] ) ? absint( $_GET['p_user'] ) : 0;
		$page          = isset( $_GET['p_page'] ) ? max( 1, absint( $_GET['p_page'] ) ) : 1;
		$per_page      = 100;

		if ( ! $auction_id ) {
			$auctions = get_posts(
				array(
					'post_type'      => 'product',
					'numberposts'    => 100,
					'post_status'    => array( 'publish', 'draft', 'pending' ),
					'orderby'        => 'date',
					'order'          => 'DESC',
				)
			);
			?>
			<div class="wrap">
				<h1><?php esc_html_e( 'Participants', 'one-ba-auctions' ); ?></h1>
				<p><?php esc_html_e( 'Select an auction to view participants.', 'one-ba-auctions' ); ?></p>
				<ul>
					<?php foreach ( $auctions as $a ) : ?>
						<?php $product = wc_get_product( $a->ID ); ?>
						<?php if ( ! $product || 'auction' !== $product->get_type() ) { continue; } ?>
						<li><a href="<?php echo esc_url( add_query_arg( array( 'page' => 'oba-participants', 'auction_id' => $a->ID ), admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html( $a->post_title . ' (#' . $a->ID . ')' ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			</div>
			<?php
			return;
		}

		global $wpdb;
		$table        = $wpdb->prefix . 'auction_participants';
		$count_rows   = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT status, COUNT(*) as total FROM {$table} WHERE auction_id = %d GROUP BY status",
				$auction_id
			),
			ARRAY_A
		);
		$status_counts = array(
			'active'  => 0,
			'pending' => 0,
			'removed' => 0,
			'banned'  => 0,
		);
		foreach ( $count_rows as $row ) {
			$status_counts[ $row['status'] ] = (int) $row['total'];
		}

		$sql_base = $wpdb->prepare( "FROM {$table} WHERE auction_id = %d", $auction_id );
		if ( $status_filter ) {
			$sql_base .= $wpdb->prepare( " AND status = %s", $status_filter );
		}
		if ( $search_user ) {
			$sql_base .= $wpdb->prepare( " AND user_id = %d", $search_user );
		}
		$total       = (int) $wpdb->get_var( "SELECT COUNT(*) {$sql_base}" );
		$offset      = ( $page - 1 ) * $per_page;
		$participants = $wpdb->get_results( "SELECT * {$sql_base} ORDER BY id DESC LIMIT {$per_page} OFFSET {$offset}", ARRAY_A );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Participants', 'one-ba-auctions' ); ?> — <?php echo esc_html( get_the_title( $auction_id ) ); ?></h1>
			<p>
				<strong><?php esc_html_e( 'Counts:', 'one-ba-auctions' ); ?></strong>
				<?php
				printf(
					'%s | %s | %s | %s',
					sprintf( esc_html__( 'Active: %d', 'one-ba-auctions' ), $status_counts['active'] ),
					sprintf( esc_html__( 'Pending: %d', 'one-ba-auctions' ), isset( $status_counts['pending'] ) ? $status_counts['pending'] : 0 ),
					sprintf( esc_html__( 'Removed: %d', 'one-ba-auctions' ), $status_counts['removed'] ),
					sprintf( esc_html__( 'Banned: %d', 'one-ba-auctions' ), $status_counts['banned'] )
				);
				?>
			</p>
			<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" style="margin:10px 0; display:flex; gap:8px; align-items:center;">
				<input type="hidden" name="page" value="oba-participants" />
				<input type="hidden" name="auction_id" value="<?php echo esc_attr( $auction_id ); ?>" />
				<select name="p_status">
					<option value=""><?php esc_html_e( 'All statuses', 'one-ba-auctions' ); ?></option>
					<option value="active" <?php selected( $status_filter, 'active' ); ?>><?php esc_html_e( 'Active', 'one-ba-auctions' ); ?></option>
					<option value="pending" <?php selected( $status_filter, 'pending' ); ?>><?php esc_html_e( 'Pending', 'one-ba-auctions' ); ?></option>
					<option value="removed" <?php selected( $status_filter, 'removed' ); ?>><?php esc_html_e( 'Removed', 'one-ba-auctions' ); ?></option>
					<option value="banned" <?php selected( $status_filter, 'banned' ); ?>><?php esc_html_e( 'Banned', 'one-ba-auctions' ); ?></option>
				</select>
				<input type="number" name="p_user" value="<?php echo esc_attr( $search_user ); ?>" placeholder="<?php esc_attr_e( 'User ID', 'one-ba-auctions' ); ?>" />
				<button class="button"><?php esc_html_e( 'Filter', 'one-ba-auctions' ); ?></button>
				<a class="button" href="<?php echo esc_url( add_query_arg( array( 'page' => 'oba-participants', 'auction_id' => $auction_id ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Reset', 'one-ba-auctions' ); ?></a>
				<?php
				$export_url = add_query_arg(
					array(
						'action'     => 'oba_export_participants',
						'auction_id' => $auction_id,
						'_wpnonce'   => wp_create_nonce( "oba_export_participants_{$auction_id}" ),
						'p_status'   => $status_filter,
						'p_user'     => $search_user,
					),
					admin_url( 'admin-post.php' )
				);
				?>
				<a class="button button-secondary" href="<?php echo esc_url( $export_url ); ?>"><?php esc_html_e( 'Export CSV', 'one-ba-auctions' ); ?></a>
			</form>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:10px 0; display:flex; gap:8px; align-items:center;">
				<input type="hidden" name="_oba_nonce" value="<?php echo esc_attr( wp_create_nonce( "oba_remove_participant_{$auction_id}" ) ); ?>" />
				<input type="hidden" name="action" value="oba_remove_participant" />
				<input type="hidden" name="auction_id" value="<?php echo esc_attr( $auction_id ); ?>" />
				<input type="hidden" name="user_id" value="0" />
				<button class="button button-secondary" name="status" value="removed"><?php esc_html_e( 'Bulk remove (filtered or all)', 'one-ba-auctions' ); ?></button>
				<button class="button" name="status" value="active"><?php esc_html_e( 'Bulk restore to active', 'one-ba-auctions' ); ?></button>
				<button class="button" name="status" value="banned"><?php esc_html_e( 'Bulk ban (filtered or all)', 'one-ba-auctions' ); ?></button>
				<a class="button button-secondary" href="<?php echo esc_url( add_query_arg( array( 'page' => 'oba-participants', 'auction_id' => $auction_id, 'p_status' => 'removed' ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'View removed', 'one-ba-auctions' ); ?></a>
				<button class="button" name="status" value="active" onclick="this.form.user_id.value='';"><?php esc_html_e( 'Restore all removed', 'one-ba-auctions' ); ?></button>
			</form>

			<?php
			$pending_orders = $this->get_pending_registrations( $auction_id );
			if ( $pending_orders ) :
				?>
				<h2><?php esc_html_e( 'Pending registrations', 'one-ba-auctions' ); ?></h2>
				<table class="widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Order', 'one-ba-auctions' ); ?></th>
							<th><?php esc_html_e( 'User', 'one-ba-auctions' ); ?></th>
							<th><?php esc_html_e( 'Status', 'one-ba-auctions' ); ?></th>
							<th><?php esc_html_e( 'Total', 'one-ba-auctions' ); ?></th>
							<th><?php esc_html_e( 'Action', 'one-ba-auctions' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $pending_orders as $order ) : ?>
							<?php
							$user = $order->get_user();
							$approve_url = wp_nonce_url(
								add_query_arg(
									array(
										'action'   => 'oba_approve_registration',
										'order_id' => $order->get_id(),
										'auction_id' => $auction_id,
									),
									admin_url( 'admin-post.php' )
								),
								'oba_approve_registration'
							);
							?>
							<tr>
								<td><a href="<?php echo esc_url( $order->get_edit_order_url() ); ?>">#<?php echo esc_html( $order->get_id() ); ?></a></td>
								<td><?php echo esc_html( $user ? $user->user_login : __( 'Guest', 'one-ba-auctions' ) ); ?></td>
								<td><?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?></td>
								<td><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></td>
								<td><a class="button" href="<?php echo esc_url( $approve_url ); ?>"><?php esc_html_e( 'Approve (complete order)', 'one-ba-auctions' ); ?></a></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<?php
			endif;
			?>

			<table class="widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'User', 'one-ba-auctions' ); ?></th>
						<th><?php esc_html_e( 'Registered at', 'one-ba-auctions' ); ?></th>
						<th><?php esc_html_e( 'Fee', 'one-ba-auctions' ); ?></th>
						<th><?php esc_html_e( 'Status', 'one-ba-auctions' ); ?></th>
						<th><?php esc_html_e( 'Order', 'one-ba-auctions' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'one-ba-auctions' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( $participants ) : ?>
						<?php foreach ( $participants as $row ) : ?>
							<?php $user = get_user_by( 'id', $row['user_id'] ); ?>
							<tr>
								<td><?php echo esc_html( $user ? $user->display_name : $row['user_id'] ); ?></td>
								<td><?php echo esc_html( $row['registered_at'] ); ?></td>
								<td><?php echo esc_html( $row['registration_fee_credits'] ); ?></td>
								<td><?php echo esc_html( $row['status'] ); ?></td>
								<td><?php echo isset( $row['order_id'] ) && $row['order_id'] ? esc_html( $row['order_id'] ) : '-'; ?></td>
								<td>
									<?php
									$remove_url = wp_nonce_url(
										add_query_arg(
											array(
												'action'     => 'oba_remove_participant',
												'auction_id' => $auction_id,
												'user_id'    => $row['user_id'],
												'status'     => 'removed',
											),
											admin_url( 'admin-post.php' )
										),
										"oba_remove_participant_{$auction_id}"
									);
									$restore_url = wp_nonce_url(
										add_query_arg(
											array(
												'action'     => 'oba_remove_participant',
												'auction_id' => $auction_id,
												'user_id'    => $row['user_id'],
												'status'     => 'active',
											),
											admin_url( 'admin-post.php' )
										),
										"oba_remove_participant_{$auction_id}"
									);
									?>
									<a class="button button-small" href="<?php echo esc_url( $remove_url ); ?>"><?php esc_html_e( 'Remove', 'one-ba-auctions' ); ?></a>
									<a class="button button-small" href="<?php echo esc_url( $restore_url ); ?>"><?php esc_html_e( 'Restore', 'one-ba-auctions' ); ?></a>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php else : ?>
						<tr><td colspan="5"><?php esc_html_e( 'No participants.', 'one-ba-auctions' ); ?></td></tr>
					<?php endif; ?>
				</tbody>
			</table>
			<?php
			$total_pages = max( 1, $per_page ? ceil( $total / $per_page ) : 1 );
			if ( $total_pages > 1 ) :
				$current_url = remove_query_arg( array( 'p_page' ) );
				?>
				<div class="tablenav">
					<div class="tablenav-pages">
						<span class="pagination-links">
							<?php for ( $p = 1; $p <= $total_pages; $p++ ) : ?>
								<?php
								$link = add_query_arg(
									array(
										'p_page' => $p,
									),
									$current_url
								);
								?>
								<a class="button <?php echo $p === $page ? 'button-primary' : ''; ?>" href="<?php echo esc_url( $link ); ?>"><?php echo esc_html( $p ); ?></a>
							<?php endfor; ?>
						</span>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	public function render_settings_page() {
		if ( ! $this->can_manage() ) {
			return;
		}
		$settings = $this->settings;
		$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'general';

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Auction Settings', 'one-ba-auctions' ); ?></h1>
			<h2 class="nav-tab-wrapper">
				<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'oba-1ba-settings', 'tab' => 'general' ), admin_url( 'admin.php' ) ) ); ?>" class="nav-tab <?php echo ( 'general' === $active_tab ) ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'General', 'one-ba-auctions' ); ?></a>
				<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'oba-1ba-settings', 'tab' => 'emails' ), admin_url( 'admin.php' ) ) ); ?>" class="nav-tab <?php echo ( 'emails' === $active_tab ) ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Emails', 'one-ba-auctions' ); ?></a>
				<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'oba-1ba-settings', 'tab' => 'translations' ), admin_url( 'admin.php' ) ) ); ?>" class="nav-tab <?php echo ( 'translations' === $active_tab ) ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Translations', 'one-ba-auctions' ); ?></a>
			</h2>
		<?php
		if ( 'emails' === $active_tab ) {
			$this->render_emails_page();
			echo '</div>';
			return;
		}
		if ( 'translations' === $active_tab ) {
			$this->render_translations_page();
			echo '</div>';
			return;
		}
		?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'oba_save_settings' ); ?>
				<input type="hidden" name="action" value="oba_save_settings" />

				<table class="form-table">
					<?php if ( 'general' === $active_tab ) : ?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Default pre-live timer (seconds)', 'one-ba-auctions' ); ?></th>
						<td><input type="number" name="default_prelive_seconds" min="1" value="<?php echo esc_attr( $settings['default_prelive_seconds'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Default live timer (seconds)', 'one-ba-auctions' ); ?></th>
						<td><input type="number" name="default_live_seconds" min="1" value="<?php echo esc_attr( $settings['default_live_seconds'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Poll interval (ms)', 'one-ba-auctions' ); ?></th>
						<td><input type="number" name="poll_interval_ms" min="500" step="100" value="<?php echo esc_attr( $settings['poll_interval_ms'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Autobid activation cost (points)', 'one-ba-auctions' ); ?></th>
						<td>
							<input type="number" name="autobid_activation_cost_points" min="0" step="1" value="<?php echo esc_attr( $settings['autobid_activation_cost_points'] ); ?>" />
							<p class="description"><?php esc_html_e( 'Points charged each time a user turns autobid on.', 'one-ba-auctions' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Autobid reminder interval (minutes)', 'one-ba-auctions' ); ?></th>
						<td>
							<input type="number" name="autobid_reminder_minutes" min="1" step="1" value="<?php echo esc_attr( $settings['autobid_reminder_minutes'] ); ?>" />
							<p class="description"><?php esc_html_e( 'How often to email users that their autobid is ON (includes bids placed so far).', 'one-ba-auctions' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Point value', 'one-ba-auctions' ); ?></th>
						<td>
							<input type="text" name="points_value" value="<?php echo esc_attr( $settings['points_value'] ); ?>" placeholder="<?php esc_attr_e( '1.00', 'one-ba-auctions' ); ?>" />
							<p class="description"><?php esc_html_e( 'How much one point is worth in your store currency (for cost/profit calculations).', 'one-ba-auctions' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Terms & Conditions text', 'one-ba-auctions' ); ?></th>
						<td>
							<textarea name="terms_text" rows="4" cols="50"><?php echo esc_textarea( $settings['terms_text'] ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Shown in registration step and required to register when not empty.', 'one-ba-auctions' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Login / account link', 'one-ba-auctions' ); ?></th>
						<td>
							<input type="url" name="login_link" value="<?php echo esc_attr( $settings['login_link'] ); ?>" placeholder="<?php esc_attr_e( 'https://example.com/my-account', 'one-ba-auctions' ); ?>" style="width:100%;max-width:420px;" />
							<p class="description"><?php esc_html_e( 'Shown to logged-out users near register. If empty, uses default login URL.', 'one-ba-auctions' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Membership links', 'one-ba-auctions' ); ?></th>
						<td>
							<p class="description"><?php esc_html_e( 'Shown when membership is required. Provide up to three links and optional labels.', 'one-ba-auctions' ); ?></p>
							<?php for ( $i = 0; $i < 3; $i++ ) : ?>
								<div style="margin-bottom:8px; display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
									<label style="width:60px;"><?php printf( esc_html__( 'Link %d', 'one-ba-auctions' ), $i + 1 ); ?></label>
									<input type="url" name="membership_links[<?php echo esc_attr( $i ); ?>]" value="<?php echo esc_attr( $settings['membership_links'][ $i ] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'https://example.com/membership', 'one-ba-auctions' ); ?>" style="width:260px;" />
									<input type="text" name="membership_labels[<?php echo esc_attr( $i ); ?>]" value="<?php echo esc_attr( $settings['membership_labels'][ $i ] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Button label (optional)', 'one-ba-auctions' ); ?>" style="width:200px;" />
								</div>
							<?php endfor; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Status info modal content', 'one-ba-auctions' ); ?></th>
						<td>
							<?php
							wp_editor(
								$settings['status_info_html'],
								'status_info_html',
								array(
									'textarea_name' => 'status_info_html',
									'textarea_rows' => 8,
									'media_buttons' => false,
								)
							);
							?>
							<p class="description"><?php esc_html_e( 'Shown when clicking the status pill on the auction page. Use HTML to outline steps.', 'one-ba-auctions' ); ?></p>
						</td>
					</tr>
					<?php else : ?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Email sender name', 'one-ba-auctions' ); ?></th>
						<td>
							<input type="text" name="email_from_name" value="<?php echo esc_attr( $settings['email_from_name'] ); ?>" style="width:100%;max-width:320px;" />
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Email sender address', 'one-ba-auctions' ); ?></th>
						<td>
							<input type="email" name="email_from_address" value="<?php echo esc_attr( $settings['email_from_address'] ); ?>" style="width:100%;max-width:320px;" />
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Edit email templates', 'one-ba-auctions' ); ?></th>
						<td>
							<p class="description"><?php esc_html_e( 'Use the Emails page to edit subjects and bodies for pre-live, live, winner, loser, claim confirmation, credits edited, and participant status notifications.', 'one-ba-auctions' ); ?></p>
							<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=oba-emails' ) ); ?>"><?php esc_html_e( 'Open Emails', 'one-ba-auctions' ); ?></a>
						</td>
					</tr>
					<?php endif; ?>
					<?php if ( 'translations' === $active_tab ) : ?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Translations', 'one-ba-auctions' ); ?></th>
						<td>
							<p class="description"><?php esc_html_e( 'Manage all frontend translations from the Translations page.', 'one-ba-auctions' ); ?></p>
							<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=oba-1ba-settings&tab=translations' ) ); ?>"><?php esc_html_e( 'Open Translations', 'one-ba-auctions' ); ?></a>
						</td>
					</tr>
					<?php endif; ?>
				</table>
				<?php submit_button( __( 'Save Settings', 'one-ba-auctions' ) ); ?>
			</form>
		</div>
		<?php
	}

	public function handle_set_status() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Not allowed', 'one-ba-auctions' ) );
		}

		$auction_id = isset( $_REQUEST['auction_id'] ) ? absint( wp_unslash( $_REQUEST['auction_id'] ) ) : 0;
		$action     = isset( $_REQUEST['status'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['status'] ) ) : '';

		check_admin_referer( "oba_set_status_{$auction_id}" );

		if ( ! $auction_id ) {
			wp_redirect( admin_url( 'admin.php?page=oba-1ba-auctions' ) );
			exit;
		}

		$lock_key = 'oba:auction:' . $auction_id;
		if ( ! OBA_Lock::acquire( $lock_key, 2 ) ) {
			wp_redirect( admin_url( 'admin.php?page=oba-1ba-auctions&notice=lock' ) );
			exit;
		}

		try {
			switch ( $action ) {
				case 'pre_live':
					$prev = get_post_meta( $auction_id, '_auction_status', true );
					update_post_meta( $auction_id, '_auction_status', 'pre_live' );
					update_post_meta( $auction_id, '_pre_live_start', gmdate( 'Y-m-d H:i:s' ) );
					OBA_Audit_Log::log( 'stage_change', array( 'auction_id' => $auction_id, 'from' => $prev, 'to' => 'pre_live', 'reason' => 'admin' ), $auction_id );
					break;
				case 'live':
					$prev = get_post_meta( $auction_id, '_auction_status', true );
					update_post_meta( $auction_id, '_auction_status', 'live' );
					update_post_meta( $auction_id, '_live_expires_at', '' );
					OBA_Audit_Log::log( 'start_live', array(), $auction_id );
					OBA_Audit_Log::log( 'stage_change', array( 'auction_id' => $auction_id, 'from' => $prev, 'to' => 'live', 'reason' => 'admin' ), $auction_id );
					break;
				case 'force_end':
					update_post_meta( $auction_id, '_auction_status', 'live' );
					$this->engine->calculate_winner_and_resolve_credits( $auction_id, 'admin_force_end' );
					OBA_Audit_Log::log( 'force_end', array(), $auction_id );
					break;
			}
		} finally {
			OBA_Lock::release( $lock_key );
		}

		wp_redirect( admin_url( 'admin.php?page=oba-1ba-auctions' ) );
		exit;
	}

	public function handle_recalc_winner() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Not allowed', 'one-ba-auctions' ) );
		}

		$auction_id = isset( $_GET['auction_id'] ) ? absint( $_GET['auction_id'] ) : 0;
		check_admin_referer( "oba_recalc_{$auction_id}" );

		if ( $auction_id ) {
			update_post_meta( $auction_id, '_auction_status', 'live' );
			$this->engine->calculate_winner_and_resolve_credits( $auction_id, 'recalc' );
			OBA_Audit_Log::log( 'recalc_winner', array(), $auction_id );
		}

		wp_redirect( admin_url( 'admin.php?page=oba-1ba-auctions' ) );
		exit;
	}

	public function handle_edit_credits() {
		if ( ! $this->can_manage() ) {
			wp_die( esc_html__( 'Not allowed', 'one-ba-auctions' ) );
		}

		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		check_admin_referer( 'oba_edit_credits_' . $user_id );

		$new_balance = isset( $_POST['credits_balance'] ) ? wc_clean( wp_unslash( $_POST['credits_balance'] ) ) : 0;
		$old_balance = $this->credits->get_balance( $user_id );
		$this->credits->set_balance( $user_id, $new_balance );

		OBA_Audit_Log::log(
			'edit_credits',
			array(
				'user_id' => $user_id,
				'balance' => $new_balance,
			)
		);
		if ( class_exists( 'OBA_Email' ) ) {
			$mailer = new OBA_Email();
			$mailer->notify_credits_edit( $user_id, $old_balance, $new_balance );
		}

		wp_redirect( admin_url( 'admin.php?page=oba-credits' ) );
		exit;
	}

	public function handle_save_settings() {
		if ( ! $this->can_manage() ) {
			wp_die( esc_html__( 'Not allowed', 'one-ba-auctions' ) );
		}

		check_admin_referer( 'oba_save_settings' );

		OBA_Settings::update_settings(
			array(
				'default_prelive_seconds' => isset( $_POST['default_prelive_seconds'] ) ? wp_unslash( $_POST['default_prelive_seconds'] ) : null,
				'default_live_seconds'    => isset( $_POST['default_live_seconds'] ) ? wp_unslash( $_POST['default_live_seconds'] ) : null,
				'poll_interval_ms'        => isset( $_POST['poll_interval_ms'] ) ? wp_unslash( $_POST['poll_interval_ms'] ) : null,
				'terms_text'              => isset( $_POST['terms_text'] ) ? wp_unslash( $_POST['terms_text'] ) : null,
				'show_header_balance'     => isset( $_POST['show_header_balance'] ),
				'login_link'              => isset( $_POST['login_link'] ) ? wp_unslash( $_POST['login_link'] ) : '',
				'membership_links'        => isset( $_POST['membership_links'] ) ? (array) wp_unslash( $_POST['membership_links'] ) : array(),
				'membership_labels'       => isset( $_POST['membership_labels'] ) ? (array) wp_unslash( $_POST['membership_labels'] ) : array(),
				'points_value'            => isset( $_POST['points_value'] ) ? wp_unslash( $_POST['points_value'] ) : null,
				'autobid_activation_cost_points' => isset( $_POST['autobid_activation_cost_points'] ) ? wp_unslash( $_POST['autobid_activation_cost_points'] ) : null,
				'autobid_reminder_minutes'=> isset( $_POST['autobid_reminder_minutes'] ) ? wp_unslash( $_POST['autobid_reminder_minutes'] ) : null,
			)
		);

		wp_redirect( admin_url( 'admin.php?page=oba-1ba-settings&updated=1' ) );
		exit;
	}

	public function render_translations_page() {
		if ( ! $this->can_manage() ) {
			return;
		}
		$settings     = $this->settings;
		$translations = isset( $settings['translations'] ) ? $settings['translations'] : array();
		$fields       = array(
			'step1_label'    => __( 'Step 1 label', 'one-ba-auctions' ),
			'step2_label'    => __( 'Step 2 label', 'one-ba-auctions' ),
			'step3_label'    => __( 'Step 3 label', 'one-ba-auctions' ),
			'step4_label'    => __( 'Step 4 label', 'one-ba-auctions' ),
			'step1_desc'     => __( 'Step 1 description', 'one-ba-auctions' ),
			'step2_desc'     => __( 'Step 2 description', 'one-ba-auctions' ),
			'step3_desc'     => __( 'Step 3 description', 'one-ba-auctions' ),
			'step4_desc'     => __( 'Step 4 description', 'one-ba-auctions' ),
			'lobby_progress' => __( 'Lobby progress label', 'one-ba-auctions' ),
			'register_cta'   => __( 'Register button text', 'one-ba-auctions' ),
			'bid_button'     => __( 'Bid button text', 'one-ba-auctions' ),
			'prelive_hint'   => __( 'Countdown hint text', 'one-ba-auctions' ),
			'winner_msg'     => __( 'Winner message', 'one-ba-auctions' ),
			'loser_msg'      => __( 'Loser message', 'one-ba-auctions' ),
			'refund_msg'     => __( 'Refund note', 'one-ba-auctions' ),
			'register_note'  => __( 'Registered note', 'one-ba-auctions' ),
			'registration_fee_label' => __( 'Registration fee label', 'one-ba-auctions' ),
			'registered_badge' => __( 'Registered badge text', 'one-ba-auctions' ),
			'not_registered_badge' => __( 'Not registered badge text', 'one-ba-auctions' ),
			'bid_cost_label'  => __( 'Bid cost label', 'one-ba-auctions' ),
			'your_bids_label' => __( 'Your bids label', 'one-ba-auctions' ),
			'your_cost_label' => __( 'Your cost label', 'one-ba-auctions' ),
			'you_leading'     => __( '"You are leading" text', 'one-ba-auctions' ),
			'claim_button'    => __( 'Claim button text', 'one-ba-auctions' ),
			'notify_bid_placed' => __( 'Notification: bid placed', 'one-ba-auctions' ),
			'notify_bid_failed' => __( 'Notification: bid failed', 'one-ba-auctions' ),
			'notify_claim_started' => __( 'Notification: claim started', 'one-ba-auctions' ),
			'notify_claim_failed' => __( 'Notification: claim failed', 'one-ba-auctions' ),
			'notify_registration_success' => __( 'Notification: registration success', 'one-ba-auctions' ),
			'notify_registration_fail' => __( 'Notification: registration fail', 'one-ba-auctions' ),
			'notify_cannot_bid' => __( 'Notification: cannot bid', 'one-ba-auctions' ),
			'notify_login_required' => __( 'Notification: login required', 'one-ba-auctions' ),
			'login_prompt'     => __( 'Login prompt message', 'one-ba-auctions' ),
			'login_button'     => __( 'Login/Register button text', 'one-ba-auctions' ),
			'claim_modal_title' => __( 'Claim modal title', 'one-ba-auctions' ),
			'claim_option_gateway' => __( 'Claim option: gateway', 'one-ba-auctions' ),
			'claim_continue' => __( 'Claim continue button', 'one-ba-auctions' ),
			'claim_cancel' => __( 'Claim cancel button', 'one-ba-auctions' ),
			'claim_error' => __( 'Claim error message label', 'one-ba-auctions' ),
			'stage2_tip'       => __( 'Tooltip: Countdown lock', 'one-ba-auctions' ),
			'stage3_tip'       => __( 'Tooltip: Live lock', 'one-ba-auctions' ),
			'stage4_tip'       => __( 'Tooltip: Ended lock', 'one-ba-auctions' ),
			'stage1_tip'       => __( 'Tooltip: Registration', 'one-ba-auctions' ),
			'membership_required_title' => __( 'Membership required title', 'one-ba-auctions' ),
			'points_low_title'          => __( 'Low points title', 'one-ba-auctions' ),
			'points_label'             => __( 'Points label (pill)', 'one-ba-auctions' ),
			'points_suffix'            => __( 'Points suffix (pts)', 'one-ba-auctions' ),
			'win_save_prefix'          => __( 'Win save prefix', 'one-ba-auctions' ),
			'win_save_suffix'          => __( 'Win save suffix', 'one-ba-auctions' ),
			'lose_save_prefix'         => __( 'Lose save prefix', 'one-ba-auctions' ),
			'lose_save_suffix'         => __( 'Lose save suffix', 'one-ba-auctions' ),
			'autobid_on_button'        => __( 'Autobid ON button', 'one-ba-auctions' ),
			'autobid_off_button'       => __( 'Autobid OFF button', 'one-ba-auctions' ),
			'autobid_on'               => __( 'Autobid enabled text', 'one-ba-auctions' ),
			'autobid_off'              => __( 'Autobid disabled text', 'one-ba-auctions' ),
			'autobid_saved'            => __( 'Autobid saved message', 'one-ba-auctions' ),
			'autobid_error'            => __( 'Autobid error message', 'one-ba-auctions' ),
			'autobid_ended'            => __( 'Autobid ended message', 'one-ba-auctions' ),
			'autobid_confirm'          => __( 'Autobid confirm message', 'one-ba-auctions' ),
			'remaining'                => __( 'Autobid remaining label', 'one-ba-auctions' ),
			'registration_closed'      => __( 'Registration closed text', 'one-ba-auctions' ),
			'autobid_title'            => __( 'Autobid title', 'one-ba-auctions' ),
			'autobid_cost_hint'        => __( 'Autobid cost hint', 'one-ba-auctions' ),
			'autobid_prompt_title'     => __( 'Autobid prompt title', 'one-ba-auctions' ),
			'autobid_set_title'        => __( 'Autobid set title', 'one-ba-auctions' ),
			'autobid_set'              => __( 'Autobid set button', 'one-ba-auctions' ),
			'autobid_edit'             => __( 'Autobid edit button', 'one-ba-auctions' ),
			'autobid_on_badge'         => __( 'Autobid ON badge', 'one-ba-auctions' ),
			'autobid_off_badge'        => __( 'Autobid OFF badge', 'one-ba-auctions' ),
			'outbid_label'             => __( 'Outbid label', 'one-ba-auctions' ),
			'autobid_limitless_label'  => __( 'Autobid limitless label', 'one-ba-auctions' ),
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Translations', 'one-ba-auctions' ); ?></h1>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'oba_save_translations' ); ?>
				<input type="hidden" name="action" value="oba_save_translations" />
				<table class="form-table">
					<?php foreach ( $fields as $key => $label ) : ?>
						<tr>
							<th scope="row"><?php echo esc_html( $label ); ?></th>
							<td>
								<input type="text" name="<?php echo esc_attr( $key ); ?>" value="<?php echo isset( $translations[ $key ] ) ? esc_attr( $translations[ $key ] ) : ''; ?>" style="width:100%;max-width:420px;" />
							</td>
						</tr>
					<?php endforeach; ?>
				</table>
				<?php submit_button( __( 'Save Translations', 'one-ba-auctions' ) ); ?>
			</form>
		</div>
		<?php
	}

	public function handle_save_translations() {
		if ( ! $this->can_manage() ) {
			wp_die( esc_html__( 'Not allowed', 'one-ba-auctions' ) );
		}

		check_admin_referer( 'oba_save_translations' );

		OBA_Settings::update_translations(
			array(
				'step1_label'    => isset( $_POST['step1_label'] ) ? $_POST['step1_label'] : '',
				'step2_label'    => isset( $_POST['step2_label'] ) ? $_POST['step2_label'] : '',
				'step3_label'    => isset( $_POST['step3_label'] ) ? $_POST['step3_label'] : '',
				'step4_label'    => isset( $_POST['step4_label'] ) ? $_POST['step4_label'] : '',
				'step1_desc'     => isset( $_POST['step1_desc'] ) ? $_POST['step1_desc'] : '',
				'step2_desc'     => isset( $_POST['step2_desc'] ) ? $_POST['step2_desc'] : '',
				'step3_desc'     => isset( $_POST['step3_desc'] ) ? $_POST['step3_desc'] : '',
				'step4_desc'     => isset( $_POST['step4_desc'] ) ? $_POST['step4_desc'] : '',
				'lobby_progress' => isset( $_POST['lobby_progress'] ) ? $_POST['lobby_progress'] : '',
				'register_cta'   => isset( $_POST['register_cta'] ) ? $_POST['register_cta'] : '',
				'bid_button'     => isset( $_POST['bid_button'] ) ? $_POST['bid_button'] : '',
				'prelive_hint'   => isset( $_POST['prelive_hint'] ) ? $_POST['prelive_hint'] : '',
				'winner_msg'     => isset( $_POST['winner_msg'] ) ? $_POST['winner_msg'] : '',
				'loser_msg'      => isset( $_POST['loser_msg'] ) ? $_POST['loser_msg'] : '',
				'refund_msg'     => isset( $_POST['refund_msg'] ) ? $_POST['refund_msg'] : '',
				'register_note'  => isset( $_POST['register_note'] ) ? $_POST['register_note'] : '',
				'buy_credits_title' => isset( $_POST['buy_credits_title'] ) ? $_POST['buy_credits_title'] : '',
				'registration_fee_label' => isset( $_POST['registration_fee_label'] ) ? $_POST['registration_fee_label'] : '',
				'registered_badge' => isset( $_POST['registered_badge'] ) ? $_POST['registered_badge'] : '',
				'not_registered_badge' => isset( $_POST['not_registered_badge'] ) ? $_POST['not_registered_badge'] : '',
				'credit_singular' => isset( $_POST['credit_singular'] ) ? $_POST['credit_singular'] : '',
				'credit_plural'   => isset( $_POST['credit_plural'] ) ? $_POST['credit_plural'] : '',
				'points_label'    => isset( $_POST['points_label'] ) ? $_POST['points_label'] : '',
				'points_suffix'   => isset( $_POST['points_suffix'] ) ? $_POST['points_suffix'] : '',
				'bid_cost_label'  => isset( $_POST['bid_cost_label'] ) ? $_POST['bid_cost_label'] : '',
				'your_bids_label' => isset( $_POST['your_bids_label'] ) ? $_POST['your_bids_label'] : '',
				'your_cost_label' => isset( $_POST['your_cost_label'] ) ? $_POST['your_cost_label'] : '',
				'you_leading'     => isset( $_POST['you_leading'] ) ? $_POST['you_leading'] : '',
				'claim_button'    => isset( $_POST['claim_button'] ) ? $_POST['claim_button'] : '',
				'notify_bid_placed' => isset( $_POST['notify_bid_placed'] ) ? $_POST['notify_bid_placed'] : '',
				'notify_bid_failed' => isset( $_POST['notify_bid_failed'] ) ? $_POST['notify_bid_failed'] : '',
				'notify_claim_started' => isset( $_POST['notify_claim_started'] ) ? $_POST['notify_claim_started'] : '',
				'notify_claim_failed' => isset( $_POST['notify_claim_failed'] ) ? $_POST['notify_claim_failed'] : '',
				'notify_registration_success' => isset( $_POST['notify_registration_success'] ) ? $_POST['notify_registration_success'] : '',
				'notify_registration_fail' => isset( $_POST['notify_registration_fail'] ) ? $_POST['notify_registration_fail'] : '',
				'notify_cannot_bid' => isset( $_POST['notify_cannot_bid'] ) ? $_POST['notify_cannot_bid'] : '',
				'notify_login_required' => isset( $_POST['notify_login_required'] ) ? $_POST['notify_login_required'] : '',
				'login_prompt'     => isset( $_POST['login_prompt'] ) ? $_POST['login_prompt'] : '',
				'login_button'     => isset( $_POST['login_button'] ) ? $_POST['login_button'] : '',
				'claim_modal_title' => isset( $_POST['claim_modal_title'] ) ? $_POST['claim_modal_title'] : '',
				'claim_option_credits' => isset( $_POST['claim_option_credits'] ) ? $_POST['claim_option_credits'] : '',
				'claim_option_gateway' => isset( $_POST['claim_option_gateway'] ) ? $_POST['claim_option_gateway'] : '',
				'claim_continue' => isset( $_POST['claim_continue'] ) ? $_POST['claim_continue'] : '',
				'claim_cancel' => isset( $_POST['claim_cancel'] ) ? $_POST['claim_cancel'] : '',
				'claim_error' => isset( $_POST['claim_error'] ) ? $_POST['claim_error'] : '',
				'credits_pill_label' => isset( $_POST['credits_pill_label'] ) ? $_POST['credits_pill_label'] : '',
				'stage2_tip'       => isset( $_POST['stage2_tip'] ) ? $_POST['stage2_tip'] : '',
				'stage3_tip'       => isset( $_POST['stage3_tip'] ) ? $_POST['stage3_tip'] : '',
				'stage4_tip'       => isset( $_POST['stage4_tip'] ) ? $_POST['stage4_tip'] : '',
				'stage1_tip'       => isset( $_POST['stage1_tip'] ) ? $_POST['stage1_tip'] : '',
				'membership_required_title' => isset( $_POST['membership_required_title'] ) ? $_POST['membership_required_title'] : '',
				'points_low_title' => isset( $_POST['points_low_title'] ) ? $_POST['points_low_title'] : '',
				'points_label'    => isset( $_POST['points_label'] ) ? $_POST['points_label'] : '',
				'points_suffix'   => isset( $_POST['points_suffix'] ) ? $_POST['points_suffix'] : '',
				'win_save_prefix' => isset( $_POST['win_save_prefix'] ) ? $_POST['win_save_prefix'] : '',
				'win_save_suffix' => isset( $_POST['win_save_suffix'] ) ? $_POST['win_save_suffix'] : '',
				'lose_save_prefix' => isset( $_POST['lose_save_prefix'] ) ? $_POST['lose_save_prefix'] : '',
				'lose_save_suffix' => isset( $_POST['lose_save_suffix'] ) ? $_POST['lose_save_suffix'] : '',
				'autobid_on_button' => isset( $_POST['autobid_on_button'] ) ? $_POST['autobid_on_button'] : '',
				'autobid_off_button' => isset( $_POST['autobid_off_button'] ) ? $_POST['autobid_off_button'] : '',
				'autobid_on' => isset( $_POST['autobid_on'] ) ? $_POST['autobid_on'] : '',
				'autobid_off' => isset( $_POST['autobid_off'] ) ? $_POST['autobid_off'] : '',
				'autobid_saved' => isset( $_POST['autobid_saved'] ) ? $_POST['autobid_saved'] : '',
				'autobid_error' => isset( $_POST['autobid_error'] ) ? $_POST['autobid_error'] : '',
				'autobid_ended' => isset( $_POST['autobid_ended'] ) ? $_POST['autobid_ended'] : '',
				'autobid_confirm' => isset( $_POST['autobid_confirm'] ) ? $_POST['autobid_confirm'] : '',
				'remaining' => isset( $_POST['remaining'] ) ? $_POST['remaining'] : '',
				'registration_closed' => isset( $_POST['registration_closed'] ) ? $_POST['registration_closed'] : '',
				'autobid_title' => isset( $_POST['autobid_title'] ) ? $_POST['autobid_title'] : '',
				'autobid_cost_hint' => isset( $_POST['autobid_cost_hint'] ) ? $_POST['autobid_cost_hint'] : '',
				'autobid_prompt_title' => isset( $_POST['autobid_prompt_title'] ) ? $_POST['autobid_prompt_title'] : '',
				'autobid_set_title' => isset( $_POST['autobid_set_title'] ) ? $_POST['autobid_set_title'] : '',
				'autobid_set' => isset( $_POST['autobid_set'] ) ? $_POST['autobid_set'] : '',
				'autobid_edit' => isset( $_POST['autobid_edit'] ) ? $_POST['autobid_edit'] : '',
				'autobid_on_badge' => isset( $_POST['autobid_on_badge'] ) ? $_POST['autobid_on_badge'] : '',
				'autobid_off_badge' => isset( $_POST['autobid_off_badge'] ) ? $_POST['autobid_off_badge'] : '',
				'outbid_label' => isset( $_POST['outbid_label'] ) ? $_POST['outbid_label'] : '',
				'autobid_limitless_label' => isset( $_POST['autobid_limitless_label'] ) ? $_POST['autobid_limitless_label'] : '',
			)
		);

		wp_redirect( admin_url( 'admin.php?page=oba-1ba-settings&tab=translations&updated=1' ) );
		exit;
	}

	public function render_emails_page() {
		if ( ! $this->can_manage() ) {
			return;
		}
		$settings = $this->settings;
		$tpl      = isset( $settings['email_templates'] ) ? $settings['email_templates'] : array();
		$fields   = array(
			'pre_live'              => __( 'Pre-live (to registered participants)', 'one-ba-auctions' ),
			'live'                  => __( 'Live started (to registered participants)', 'one-ba-auctions' ),
			'winner'                => __( 'Auction winner', 'one-ba-auctions' ),
			'loser'                 => __( 'Auction losers (refund notice)', 'one-ba-auctions' ),
			'claim'                 => __( 'Claim confirmation', 'one-ba-auctions' ),
			'registration_pending'  => __( 'Registration pending', 'one-ba-auctions' ),
			'registration_approved' => __( 'Registration approved', 'one-ba-auctions' ),
			'participant'           => __( 'Participant status change', 'one-ba-auctions' ),
			'credits'               => __( 'Points balance edited', 'one-ba-auctions' ),
			'autobid_on'            => __( 'Autobid enabled', 'one-ba-auctions' ),
			'autobid_on_reminder'   => __( 'Autobid reminder', 'one-ba-auctions' ),
			'autobid_off'           => __( 'Autobid disabled', 'one-ba-auctions' ),
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Emails', 'one-ba-auctions' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Edit subjects and bodies for outgoing emails. Allowed tokens: {user_name}, {auction_title}, {auction_link}, {claim_price}, {bid_cost}, {live_timer}, {seconds}, {order_id}, {delta}, {balance}, {status}, {autobid_max_bids}, {autobid_bids_used}.', 'one-ba-auctions' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'oba_save_emails' ); ?>
				<input type="hidden" name="action" value="oba_save_emails" />
				<table class="form-table">
					<?php foreach ( $fields as $key => $label ) : ?>
						<tr>
							<th scope="row"><?php echo esc_html( $label ); ?></th>
							<td>
								<input type="text" name="email_templates[<?php echo esc_attr( $key ); ?>][subject]" value="<?php echo isset( $tpl[ $key ]['subject'] ) ? esc_attr( $tpl[ $key ]['subject'] ) : ''; ?>" placeholder="<?php esc_attr_e( 'Subject', 'one-ba-auctions' ); ?>" style="width:100%;max-width:520px;margin-bottom:6px;" />
								<textarea name="email_templates[<?php echo esc_attr( $key ); ?>][body]" rows="4" style="width:100%;max-width:520px;" placeholder="<?php esc_attr_e( 'Body (HTML allowed)', 'one-ba-auctions' ); ?>"><?php echo isset( $tpl[ $key ]['body'] ) ? esc_textarea( $tpl[ $key ]['body'] ) : ''; ?></textarea>
							</td>
						</tr>
					<?php endforeach; ?>
				</table>
				<?php submit_button( __( 'Save Emails', 'one-ba-auctions' ) ); ?>
			</form>

			<hr />
			<h2><?php esc_html_e( 'Send Test Email', 'one-ba-auctions' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:12px;">
				<?php wp_nonce_field( 'oba_send_test_email' ); ?>
				<input type="hidden" name="action" value="oba_send_test_email" />
				<label>
					<?php esc_html_e( 'Send test emails (subjects/bodies) to admin email:', 'one-ba-auctions' ); ?>
				</label>
				<div style="margin:6px 0;">
					<label><input type="checkbox" name="templates[]" value="pre_live" checked /> <?php esc_html_e( 'Pre-live', 'one-ba-auctions' ); ?></label><br />
					<label><input type="checkbox" name="templates[]" value="live" checked /> <?php esc_html_e( 'Live', 'one-ba-auctions' ); ?></label><br />
					<label><input type="checkbox" name="templates[]" value="winner" checked /> <?php esc_html_e( 'Winner', 'one-ba-auctions' ); ?></label><br />
					<label><input type="checkbox" name="templates[]" value="loser" checked /> <?php esc_html_e( 'Loser', 'one-ba-auctions' ); ?></label><br />
					<label><input type="checkbox" name="templates[]" value="claim" checked /> <?php esc_html_e( 'Claim confirmation', 'one-ba-auctions' ); ?></label><br />
					<label><input type="checkbox" name="templates[]" value="registration_pending" checked /> <?php esc_html_e( 'Registration pending', 'one-ba-auctions' ); ?></label><br />
					<label><input type="checkbox" name="templates[]" value="registration_approved" checked /> <?php esc_html_e( 'Registration approved', 'one-ba-auctions' ); ?></label><br />
					<label><input type="checkbox" name="templates[]" value="participant" checked /> <?php esc_html_e( 'Participant status', 'one-ba-auctions' ); ?></label><br />
					<label><input type="checkbox" name="templates[]" value="credits" checked /> <?php esc_html_e( 'Points balance edited', 'one-ba-auctions' ); ?></label><br />
					<label><input type="checkbox" name="templates[]" value="autobid_on" checked /> <?php esc_html_e( 'Autobid enabled', 'one-ba-auctions' ); ?></label><br />
					<label><input type="checkbox" name="templates[]" value="autobid_on_reminder" checked /> <?php esc_html_e( 'Autobid reminder', 'one-ba-auctions' ); ?></label><br />
					<label><input type="checkbox" name="templates[]" value="autobid_off" checked /> <?php esc_html_e( 'Autobid disabled', 'one-ba-auctions' ); ?></label><br />
				</div>
				<?php submit_button( __( 'Send Selected Tests', 'one-ba-auctions' ), 'secondary', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}

	public function handle_save_emails() {
		if ( ! $this->can_manage() ) {
			wp_die( esc_html__( 'Not allowed', 'one-ba-auctions' ) );
		}

		check_admin_referer( 'oba_save_emails' );

		$new = array();
		if ( isset( $_POST['email_templates'] ) && is_array( $_POST['email_templates'] ) ) {
			foreach ( $_POST['email_templates'] as $key => $tpl ) {
				$new[ $key ] = array(
					'subject' => isset( $tpl['subject'] ) ? sanitize_text_field( wp_unslash( $tpl['subject'] ) ) : '',
					'body'    => isset( $tpl['body'] ) ? wp_kses_post( $tpl['body'] ) : '',
				);
			}
		}
		$settings                     = OBA_Settings::get_settings();
		$settings['email_templates']  = $new;
		update_option( OBA_Settings::OPTION_KEY, $settings );

		wp_redirect( admin_url( 'admin.php?page=oba-emails&updated=1' ) );
		exit;
	}

	public function handle_send_test_email() {
		if ( ! $this->can_manage() ) {
			wp_die( esc_html__( 'Not allowed', 'one-ba-auctions' ) );
		}

		check_admin_referer( 'oba_send_test_email' );

		$admin_email = get_option( 'admin_email' );
		if ( class_exists( 'OBA_Email' ) && $admin_email ) {
			$mailer = new OBA_Email();
			$templates = isset( $_POST['templates'] ) && is_array( $_POST['templates'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['templates'] ) ) : array();
			if ( empty( $templates ) ) {
				$templates = array( 'pre_live', 'live', 'winner', 'loser', 'claim', 'participant' );
			}
			$mailer->send_test_templates( $templates, $admin_email );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=oba-emails&test=1' ) );
		exit;
	}

	public function handle_save_membership() {
		if ( ! $this->can_manage() ) {
			wp_die( esc_html__( 'Not allowed', 'one-ba-auctions' ) );
		}
		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		check_admin_referer( 'oba_save_membership_' . $user_id );
		if ( $user_id ) {
			$points_service = new OBA_Points_Service();
			$points         = isset( $_POST['points_balance'] ) ? (float) wc_clean( wp_unslash( $_POST['points_balance'] ) ) : 0;
			$has_membership = isset( $_POST['has_membership'] ) ? 1 : 0;
			$points_service->set_balance( $user_id, $points );
			update_user_meta( $user_id, '_oba_has_membership', $has_membership );
		}
		wp_redirect( admin_url( 'admin.php?page=oba-1ba-memberships&updated=1' ) );
		exit;
	}

	public function handle_manual_winner() {
		if ( ! $this->can_manage() ) {
			wp_die( esc_html__( 'Not allowed', 'one-ba-auctions' ) );
		}
		$auction_id     = isset( $_POST['auction_id'] ) ? absint( $_POST['auction_id'] ) : 0;
		$winner_user_id = isset( $_POST['winner_user_id'] ) ? absint( $_POST['winner_user_id'] ) : 0;
		$use_last_bidder = isset( $_POST['use_last_bidder'] ) ? (bool) $_POST['use_last_bidder'] : false;
		check_admin_referer( 'oba_manual_winner_' . $auction_id );

		if ( ! $auction_id ) {
			wp_safe_redirect( admin_url( 'admin.php?page=oba-1ba-auction&auction_id=' . $auction_id . '&error=1' ) );
			exit;
		}

		if ( $use_last_bidder ) {
			$winner_user_id = $this->repo->get_current_winner( $auction_id );
		}

		if ( ! $winner_user_id ) {
			wp_safe_redirect( admin_url( 'admin.php?page=oba-1ba-auction&auction_id=' . $auction_id . '&error=1' ) );
			exit;
		}

		$meta        = $this->repo->get_auction_meta( $auction_id );
		$totals      = $this->repo->get_bid_totals_by_user( $auction_id );
		$winner_rows = array_filter(
			$totals,
			function ( $row ) use ( $winner_user_id ) {
				return (int) $row['user_id'] === $winner_user_id;
			}
		);
		$winner_row = array_shift( $winner_rows );
		$total_bids = $winner_row ? (int) $winner_row['total_bids'] : 0;
		$bid_fee    = $this->engine->get_bid_fee_amount( $meta );
		$total_cost = $total_bids * $bid_fee;

		global $wpdb;
		$winners_table = $wpdb->prefix . 'auction_winners';
		$existing_winner = $this->repo->get_winner_row( $auction_id );
		if ( $existing_winner ) {
			$wpdb->update(
				$winners_table,
				array(
					'winner_user_id'         => $winner_user_id,
					'total_bids'             => $total_bids,
					'total_credits_consumed' => $total_cost,
					'claim_price_credits'    => $total_cost,
					'wc_order_id'            => null,
				),
				array( 'id' => (int) $existing_winner['id'] ),
				array( '%d', '%d', '%f', '%f', '%d' ),
				array( '%d' )
			);
		} else {
			$wpdb->insert(
				$winners_table,
				array(
					'auction_id'             => $auction_id,
					'winner_user_id'         => $winner_user_id,
					'total_bids'             => $total_bids,
					'total_credits_consumed' => $total_cost,
					'claim_price_credits'    => $total_cost,
					'wc_order_id'            => null,
				),
				array( '%d', '%d', '%d', '%f', '%f', '%d' )
			);
		}

		update_post_meta( $auction_id, '_auction_status', 'ended' );
		OBA_Audit_Log::log(
			'manual_winner',
			array(
				'winner_user_id' => $winner_user_id,
				'total_bids'     => $total_bids,
				'total_cost'     => $total_cost,
				'action'         => $existing_winner ? 'replaced' : 'created',
			),
			$auction_id
		);

		wp_safe_redirect( admin_url( 'admin.php?page=oba-1ba-auction&auction_id=' . $auction_id . '&winner=set' . ( $existing_winner ? '&replaced=1' : '' ) ) );
		exit;
	}

	public function render_1ba_placeholder_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( '1BA Auctions', 'one-ba-auctions' ); ?></h1>
			<p><?php esc_html_e( 'This area will host the streamlined 1BA Auctions admin. Sub-menus and tools will be added here soon.', 'one-ba-auctions' ); ?></p>
		</div>
		<?php
	}

	public function render_1ba_auctions_all() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$status_filter = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';
		$allowed_statuses = array( '', 'registration', 'pre_live', 'live', 'ended' );
		if ( ! in_array( $status_filter, $allowed_statuses, true ) ) {
			$status_filter = '';
		}
		$query = new WP_Query(
			array(
				'post_type'      => 'product',
				'posts_per_page' => 500,
				'post_status'    => array( 'publish', 'draft', 'pending' ),
				'fields'         => 'ids',
			)
		);
		$rows = array();
		foreach ( $query->posts as $pid ) {
			$product = wc_get_product( $pid );
			if ( ! $product || 'auction' !== $product->get_type() ) {
				continue;
			}
			$status = strtolower( (string) get_post_meta( $pid, '_auction_status', true ) );
			$status = in_array( $status, array( 'registration', 'pre_live', 'live', 'ended' ), true ) ? $status : 'registration';
			if ( $status_filter && $status !== $status_filter ) {
				continue;
			}
			$rows[] = array(
				'id'           => $pid,
				'title'        => $product->get_name(),
				'status'       => $status,
				'participants' => $this->repo->get_participant_count( $pid ) . ' / ' . (int) get_post_meta( $pid, '_required_participants', true ),
				'created'      => get_the_date( '', $pid ),
			);
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'All Auctions', 'one-ba-auctions' ); ?></h1>
			<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" style="margin-bottom:12px;">
				<input type="hidden" name="page" value="oba-1ba-auctions" />
				<select name="status">
					<option value=""><?php esc_html_e( 'All statuses', 'one-ba-auctions' ); ?></option>
					<option value="registration" <?php selected( $status_filter, 'registration' ); ?>><?php esc_html_e( 'Registration', 'one-ba-auctions' ); ?></option>
					<option value="pre_live" <?php selected( $status_filter, 'pre_live' ); ?>><?php esc_html_e( 'Waiting to go live', 'one-ba-auctions' ); ?></option>
					<option value="live" <?php selected( $status_filter, 'live' ); ?>><?php esc_html_e( 'Live', 'one-ba-auctions' ); ?></option>
					<option value="ended" <?php selected( $status_filter, 'ended' ); ?>><?php esc_html_e( 'Ended', 'one-ba-auctions' ); ?></option>
				</select>
				<button class="button"><?php esc_html_e( 'Filter', 'one-ba-auctions' ); ?></button>
			</form>
			<table class="widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'ID', 'one-ba-auctions' ); ?></th>
						<th><?php esc_html_e( 'Title', 'one-ba-auctions' ); ?></th>
						<th><?php esc_html_e( 'Status', 'one-ba-auctions' ); ?></th>
						<th><?php esc_html_e( 'Participants', 'one-ba-auctions' ); ?></th>
						<th><?php esc_html_e( 'Date created', 'one-ba-auctions' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $rows ) ) : ?>
						<tr><td colspan="5"><?php esc_html_e( 'No auctions found.', 'one-ba-auctions' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $rows as $row ) : ?>
							<tr>
								<td><?php echo esc_html( $row['id'] ); ?></td>
								<td><a href="<?php echo esc_url( add_query_arg( array( 'page' => 'oba-1ba-auction', 'auction_id' => $row['id'] ), admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html( $row['title'] ); ?></a></td>
								<td><?php echo esc_html( ucfirst( $row['status'] ) ); ?></td>
								<td><?php echo esc_html( $row['participants'] ); ?></td>
								<td><?php echo esc_html( $row['created'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	public function render_1ba_auctions_upcoming() {
		$_GET['status'] = 'registration';
		$this->render_auctions_page();
	}

	public function render_1ba_auctions_waiting() {
		$_GET['status'] = 'pre_live';
		$this->render_auctions_page();
	}

	public function render_1ba_auctions_live() {
		$_GET['status'] = 'live';
		$this->render_auctions_page();
	}

	public function render_1ba_auctions_ended() {
		$_GET['status'] = 'ended';
		$this->render_auctions_page();
	}

	public function render_1ba_auction_detail() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$auction_id = isset( $_GET['auction_id'] ) ? absint( $_GET['auction_id'] ) : 0;
		if ( ! $auction_id ) {
			echo '<div class="wrap"><h1>' . esc_html__( 'Auction not found', 'one-ba-auctions' ) . '</h1></div>';
			return;
		}

		$product = wc_get_product( $auction_id );
		if ( ! $product || 'auction' !== $product->get_type() ) {
			echo '<div class="wrap"><h1>' . esc_html__( 'Auction not found', 'one-ba-auctions' ) . '</h1></div>';
			return;
		}

		global $wpdb;
		$meta      = $this->repo->get_auction_meta( $auction_id );
		$reg_points   = isset( $meta['registration_points'] ) ? (float) $meta['registration_points'] : 0;
		$product_cost = (float) get_post_meta( $auction_id, '_product_cost', true );
		$reg_fee      = isset( $meta['registration_product_id'] ) ? wc_get_product( $meta['registration_product_id'] ) : null;
		$bid_fee      = $meta['bid_product_id'] ? wc_get_product( $meta['bid_product_id'] ) : null;
		$reg_price    = ( $reg_fee && '' !== $reg_fee->get_price() ) ? (float) $reg_fee->get_price() : 0;
		$bid_price    = ( $bid_fee && '' !== $bid_fee->get_price() ) ? (float) $bid_fee->get_price() : 0;
		$points_value = isset( $this->settings['points_value'] ) ? (float) $this->settings['points_value'] : 1;

		$participants_table = $wpdb->prefix . 'auction_participants';
		$participants_raw   = $wpdb->get_results(
			$wpdb->prepare( "SELECT user_id, status, registered_at FROM {$participants_table} WHERE auction_id = %d ORDER BY registered_at DESC", $auction_id ),
			ARRAY_A
		);
		$participants = array();
		foreach ( $participants_raw as $row ) {
			$user = get_userdata( (int) $row['user_id'] );
			$participants[] = array(
				'user_id'       => (int) $row['user_id'],
				'status'        => $row['status'],
				'registered_at' => $row['registered_at'],
				'name'          => $user ? $user->display_name : '',
				'email'         => $user ? $user->user_email : '',
			);
		}

		$bid_rows   = $this->repo->get_last_bids( $auction_id, 50 );
		$total_bids = $this->repo->get_total_bid_count( $auction_id );

		$total_reg_points = $reg_points * count( $participants );
		$total_reg_fees = $reg_price * count( $participants );
		$total_bid_fees = $bid_price * $total_bids;

		$winner_total_bids  = 0;
		$winner_bid_value   = 0;
		$winner_row         = null;
		$current_winner_row = $this->repo->get_winner_row( $auction_id );
		if ( $current_winner_row ) {
			$winner_row = $current_winner_row;
			$totals = $this->repo->get_bid_totals_by_user( $auction_id );
			foreach ( $totals as $row ) {
				if ( (int) $row['user_id'] === (int) $winner_row['winner_user_id'] ) {
					$winner_total_bids = (int) $row['total_bids'];
					$winner_bid_value  = $bid_price ? $winner_total_bids * $bid_price : 0;
					break;
				}
			}
		}

		$auction_total_value = ( $total_reg_points * $points_value ) + $winner_bid_value - $product_cost;

		$status            = $meta['auction_status'] ?: 'registration';
		$edit_link         = get_edit_post_link( $auction_id );
		$participants_link = admin_url( 'admin.php?page=oba-participants&auction_id=' . $auction_id );
		$winner_user       = $winner_row ? get_userdata( (int) $winner_row['winner_user_id'] ) : null;
		$claimed_order_id  = $winner_row && ! empty( $winner_row['wc_order_id'] ) ? (int) $winner_row['wc_order_id'] : 0;
		$claimed_status    = '';
		$claimed           = false;

		// Check explicit order on winner row first.
		if ( $claimed_order_id && function_exists( 'wc_get_order' ) ) {
			$order = wc_get_order( $claimed_order_id );
			if ( $order ) {
				$claimed_status = $order->get_status();
				$claimed        = ( 'completed' === $claimed_status );
			}
		}

		// Fallback: find any completed order tagged with this auction claim.
		if ( ! $claimed && $winner_row && function_exists( 'wc_get_orders' ) ) {
			$orders = wc_get_orders(
				array(
					'status'   => array( 'completed' ),
					'limit'    => 10,
					'customer' => (int) $winner_row['winner_user_id'],
				)
			);
			foreach ( $orders as $order ) {
				foreach ( $order->get_items() as $item ) {
					$aid_meta = (int) $item->get_meta( '_oba_claim_auction_id', true );
					$aid_order= (int) $order->get_meta( '_oba_auction_id', true );
					if ( $aid_meta === (int) $auction_id || $aid_order === (int) $auction_id ) {
						$claimed_order_id = $order->get_id();
						$claimed_status   = $order->get_status();
						$claimed          = true;
						if ( empty( $winner_row['wc_order_id'] ) ) {
							global $wpdb;
							$table = $wpdb->prefix . 'auction_winners';
							$wpdb->update(
								$table,
								array( 'wc_order_id' => $claimed_order_id ),
								array( 'id' => (int) $winner_row['id'] ),
								array( '%d' ),
								array( '%d' )
							);
							$winner_row['wc_order_id'] = $claimed_order_id;
						}
						break 2;
					}
				}
			}
		}
		$ended_at          = get_post_meta( $auction_id, '_live_expires_at', true );
		?>
		<div class="wrap">
			<h1><?php echo esc_html( $product->get_name() ); ?> (<?php echo esc_html( '#' . $auction_id ); ?>)</h1>
			<table class="widefat fixed striped" style="max-width:720px;margin-bottom:16px;">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Status', 'one-ba-auctions' ); ?></th>
						<th><?php esc_html_e( 'Winner', 'one-ba-auctions' ); ?></th>
						<th><?php esc_html_e( 'Claimed', 'one-ba-auctions' ); ?></th>
						<th><?php esc_html_e( 'Auction end time', 'one-ba-auctions' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><?php echo esc_html( ucfirst( $status ) ); ?></td>
						<td>
							<?php
							if ( $winner_row ) {
								echo esc_html( $winner_row['winner_user_id'] );
								if ( $winner_user && $winner_user->display_name ) {
									echo ' (' . esc_html( $winner_user->display_name ) . ')';
								}
							} else {
								esc_html_e( '—', 'one-ba-auctions' );
							}
							?>
						</td>
						<td>
							<?php
							if ( $winner_row ) {
								if ( $claimed ) {
									$link = $claimed_order_id ? '<a href="' . esc_url( admin_url( 'post.php?post=' . $claimed_order_id . '&action=edit' ) ) . '">#' . esc_html( $claimed_order_id ) . '</a>' : '';
									echo esc_html__( 'Yes', 'one-ba-auctions' ) . ' ' . $link;
									if ( $claimed_status ) {
										echo ' — ' . esc_html( ucfirst( $claimed_status ) );
									}
								} else {
									echo esc_html__( 'No', 'one-ba-auctions' );
								}
							} else {
								esc_html_e( '—', 'one-ba-auctions' );
							}
							?>
						</td>
						<td><?php echo $ended_at ? esc_html( $ended_at ) : esc_html__( '—', 'one-ba-auctions' ); ?></td>
					</tr>
				</tbody>
			</table>
			<p>
				<a class="button" href="<?php echo esc_url( $edit_link ); ?>"><?php esc_html_e( 'Edit auction product', 'one-ba-auctions' ); ?></a>
			</p>

			<?php
			$settings_snapshot = array(
				'auction_id'           => $auction_id,
				'status'              => $meta['auction_status'],
				'required_participants'=> (int) $meta['required_participants'],
				'live_timer_seconds'   => (int) $meta['live_timer_seconds'],
				'prelive_timer_seconds'=> (int) $meta['prelive_timer_seconds'],
				'pre_live_start'       => $meta['pre_live_start'], // UTC (storage format).
				'pre_live_start_local' => class_exists( 'OBA_Time' ) ? OBA_Time::format_utc_mysql_datetime_as_local_mysql( $meta['pre_live_start'] ) : '',
				'live_expires_at'      => $meta['live_expires_at'], // UTC (storage format).
				'live_expires_at_local'=> class_exists( 'OBA_Time' ) ? OBA_Time::format_utc_mysql_datetime_as_local_mysql( $meta['live_expires_at'] ) : '',
				'registration_points'  => (float) $meta['registration_points'],
				'bid_product_id'       => (int) $meta['bid_product_id'],
				'autobid_enabled_for_auction' => (bool) get_post_meta( $auction_id, '_oba_autobid_enabled', true ),
				'site_timezone'        => function_exists( 'wp_timezone_string' ) ? wp_timezone_string() : '',
				'now_utc'              => gmdate( 'Y-m-d H:i:s' ),
				'now_local'            => function_exists( 'wp_date' ) && function_exists( 'wp_timezone' ) ? wp_date( 'Y-m-d H:i:s', time(), wp_timezone() ) : '',
			);
			?>
			<h2><?php esc_html_e( 'Auction settings snapshot (copy)', 'one-ba-auctions' ); ?></h2>
			<textarea readonly style="width:100%;max-width:920px;height:140px;" onclick="this.select();"><?php echo esc_textarea( wp_json_encode( $settings_snapshot, JSON_PRETTY_PRINT ) ); ?></textarea>

			<h2><?php esc_html_e( 'Actions', 'one-ba-auctions' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-right:8px;">
				<input type="hidden" name="action" value="oba_set_status" />
				<input type="hidden" name="auction_id" value="<?php echo esc_attr( $auction_id ); ?>" />
				<input type="hidden" name="status" value="pre_live" />
				<?php wp_nonce_field( "oba_set_status_{$auction_id}" ); ?>
				<button class="button"><?php esc_html_e( 'Start pre-live', 'one-ba-auctions' ); ?></button>
			</form>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-right:8px;">
				<input type="hidden" name="action" value="oba_set_status" />
				<input type="hidden" name="auction_id" value="<?php echo esc_attr( $auction_id ); ?>" />
				<input type="hidden" name="status" value="live" />
				<?php wp_nonce_field( "oba_set_status_{$auction_id}" ); ?>
				<button class="button"><?php esc_html_e( 'Start live', 'one-ba-auctions' ); ?></button>
			</form>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-right:8px;">
				<input type="hidden" name="action" value="oba_manual_winner" />
				<input type="hidden" name="auction_id" value="<?php echo esc_attr( $auction_id ); ?>" />
				<?php wp_nonce_field( 'oba_manual_winner_' . $auction_id ); ?>
				<input type="number" name="winner_user_id" placeholder="<?php esc_attr_e( 'User ID', 'one-ba-auctions' ); ?>" />
				<label style="margin-left:8px;">
					<input type="checkbox" name="use_last_bidder" value="1" />
					<?php esc_html_e( 'Use last bidder', 'one-ba-auctions' ); ?>
				</label>
				<button class="button"><?php esc_html_e( 'Set manual winner', 'one-ba-auctions' ); ?></button>
			</form>

			<h2><?php esc_html_e( 'Totals', 'one-ba-auctions' ); ?></h2>
			<ul>
				<li><?php esc_html_e( 'Participants', 'one-ba-auctions' ); ?>: <?php echo esc_html( count( $participants ) ); ?> / <?php echo esc_html( isset( $meta['required_participants'] ) ? (int) $meta['required_participants'] : 0 ); ?></li>
				<li><?php esc_html_e( 'Registration points each', 'one-ba-auctions' ); ?>: <?php echo esc_html( $reg_points ); ?></li>
				<li><?php esc_html_e( 'Registration points total', 'one-ba-auctions' ); ?>: <?php echo esc_html( $total_reg_points ); ?></li>
				<li><?php esc_html_e( 'Registration value (approx.)', 'one-ba-auctions' ); ?>: <?php echo wp_kses_post( wc_price( $total_reg_points * $points_value ) ); ?></li>
				<li><?php esc_html_e( 'Winner bids placed', 'one-ba-auctions' ); ?>: <?php echo esc_html( $winner_total_bids ); ?></li>
				<li><?php esc_html_e( 'Winner bids value', 'one-ba-auctions' ); ?>: <?php echo wp_kses_post( wc_price( $winner_bid_value ) ); ?></li>
				<li><?php esc_html_e( 'Product cost', 'one-ba-auctions' ); ?>: <?php echo wp_kses_post( wc_price( $product_cost ) ); ?></li>
				<li><strong><?php esc_html_e( 'Auction total value (reg + winner bids - cost)', 'one-ba-auctions' ); ?></strong>: <?php echo wp_kses_post( wc_price( $auction_total_value ) ); ?></li>
			</ul>

			<h2><?php esc_html_e( 'Users registering log', 'one-ba-auctions' ); ?></h2>
			<table class="widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'User ID', 'one-ba-auctions' ); ?></th>
						<th><?php esc_html_e( 'Name', 'one-ba-auctions' ); ?></th>
						<th><?php esc_html_e( 'Email', 'one-ba-auctions' ); ?></th>
						<th><?php esc_html_e( 'Status', 'one-ba-auctions' ); ?></th>
						<th><?php esc_html_e( 'Registered at', 'one-ba-auctions' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'one-ba-auctions' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $participants ) ) : ?>
						<tr><td colspan="6"><?php esc_html_e( 'No participants yet.', 'one-ba-auctions' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $participants as $row ) : ?>
							<tr>
								<td><?php echo esc_html( $row['user_id'] ); ?></td>
								<td><?php echo esc_html( $row['name'] ); ?></td>
								<td><?php echo esc_html( $row['email'] ); ?></td>
								<td><?php echo esc_html( $row['status'] ); ?></td>
								<td><?php echo esc_html( $row['registered_at'] ); ?></td>
								<td>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;">
										<input type="hidden" name="action" value="oba_remove_participant" />
										<input type="hidden" name="auction_id" value="<?php echo esc_attr( $auction_id ); ?>" />
										<input type="hidden" name="user_id" value="<?php echo esc_attr( $row['user_id'] ); ?>" />
										<input type="hidden" name="status" value="removed" />
										<?php wp_nonce_field( "oba_remove_participant_{$auction_id}" ); ?>
										<button class="button button-small" type="submit"><?php esc_html_e( 'Remove', 'one-ba-auctions' ); ?></button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<h2><?php esc_html_e( 'Live bids log (last 50)', 'one-ba-auctions' ); ?></h2>
			<table class="widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'User ID', 'one-ba-auctions' ); ?></th>
						<th><?php esc_html_e( 'Amount', 'one-ba-auctions' ); ?></th>
						<th><?php esc_html_e( 'Time', 'one-ba-auctions' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $bid_rows ) ) : ?>
						<tr><td colspan="3"><?php esc_html_e( 'No bids yet.', 'one-ba-auctions' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $bid_rows as $row ) : ?>
							<tr>
								<td><?php echo esc_html( $row['user_id'] ); ?></td>
								<td><?php echo esc_html( $bid_price ? wp_strip_all_tags( wc_price( $bid_price ) ) : $row['credits_reserved'] ); ?></td>
								<td><?php echo esc_html( $row['created_at'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<?php
			$audit_entries = OBA_Audit_Log::all_for_auction( $auction_id, 200 );
			$audit_export  = array();
			foreach ( $audit_entries as $entry ) {
				$user = $entry['actor_id'] ? get_user_by( 'id', $entry['actor_id'] ) : null;
				$audit_export[] = array(
					'time'    => $entry['created_at'],
					'actor'   => $user ? $user->display_name : '-',
					'action'  => $entry['action'],
					'details' => is_serialized( $entry['details'] ) ? maybe_unserialize( $entry['details'] ) : $entry['details'],
				);
			}
			?>
			<h2><?php esc_html_e( 'Auction audit log (last 200)', 'one-ba-auctions' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Click inside to select and copy.', 'one-ba-auctions' ); ?></p>
			<textarea readonly style="width:100%;max-width:920px;height:220px;" onclick="this.select();"><?php echo esc_textarea( wp_json_encode( $audit_export, JSON_PRETTY_PRINT ) ); ?></textarea>
			<h3 style="margin-top:18px;"><?php esc_html_e( 'Table view', 'one-ba-auctions' ); ?></h3>
			<table class="widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Time', 'one-ba-auctions' ); ?></th>
						<th><?php esc_html_e( 'Actor', 'one-ba-auctions' ); ?></th>
						<th><?php esc_html_e( 'Action', 'one-ba-auctions' ); ?></th>
						<th><?php esc_html_e( 'Details', 'one-ba-auctions' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $audit_entries ) ) : ?>
						<tr><td colspan="4"><?php esc_html_e( 'No audit entries yet.', 'one-ba-auctions' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $audit_entries as $entry ) : ?>
							<?php $user = $entry['actor_id'] ? get_user_by( 'id', $entry['actor_id'] ) : null; ?>
							<tr>
								<td><?php echo esc_html( $entry['created_at'] ); ?></td>
								<td><?php echo esc_html( $user ? $user->display_name : '-' ); ?></td>
								<td><?php echo esc_html( $entry['action'] ); ?></td>
								<td><code><?php echo esc_html( is_serialized( $entry['details'] ) ? wp_json_encode( maybe_unserialize( $entry['details'] ) ) : $entry['details'] ); ?></code></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	public function handle_remove_participant() {
		if ( ! $this->can_manage() ) {
			wp_die( esc_html__( 'Not allowed', 'one-ba-auctions' ) );
		}

		$auction_id = isset( $_REQUEST['auction_id'] ) ? absint( $_REQUEST['auction_id'] ) : 0;
		$user_id    = isset( $_REQUEST['user_id'] ) ? absint( $_REQUEST['user_id'] ) : 0;
		$new_status = isset( $_REQUEST['status'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['status'] ) ) : 'removed';

		$nonce_value = '';
		if ( isset( $_REQUEST['_oba_nonce'] ) ) {
			$nonce_value = sanitize_text_field( wp_unslash( $_REQUEST['_oba_nonce'] ) );
		} elseif ( isset( $_REQUEST['_wpnonce'] ) ) {
			$nonce_value = sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) );
		}

		if ( ! wp_verify_nonce( $nonce_value, "oba_remove_participant_{$auction_id}" ) ) {
			wp_die( esc_html__( 'Nonce check failed.', 'one-ba-auctions' ) );
		}

		if ( ! $auction_id ) {
			wp_redirect( admin_url( 'admin.php?page=oba-1ba-auctions' ) );
			exit;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'auction_participants';
		if ( $user_id ) {
			$wpdb->update(
				$table,
				array( 'status' => $new_status ),
				array(
					'auction_id' => $auction_id,
					'user_id'    => $user_id,
				),
				array( '%s' ),
				array( '%d', '%d' )
			);
		} else {
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$table} SET status = %s WHERE auction_id = %d",
					$new_status,
					$auction_id
				)
			);
		}

		OBA_Audit_Log::log(
			'remove_participant',
			array(
				'user_id' => $user_id,
				'status'  => $new_status,
			),
			$auction_id
		);
		if ( class_exists( 'OBA_Email' ) && $user_id ) {
			$mailer = new OBA_Email();
			$mailer->notify_participant_status( $auction_id, $user_id, $new_status );
		}

		wp_redirect( admin_url( 'admin.php?page=oba-auctions' ) );
		exit;
	}

	public function handle_approve_registration() {
		if ( ! $this->can_manage() ) {
			wp_die( esc_html__( 'Not allowed', 'one-ba-auctions' ) );
		}
		check_admin_referer( 'oba_approve_registration' );

		$order_id   = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
		$redirect   = isset( $_GET['auction_id'] ) ? add_query_arg( array( 'page' => 'oba-participants', 'auction_id' => absint( $_GET['auction_id'] ) ), admin_url( 'admin.php' ) ) : admin_url( 'admin.php?page=oba-participants' );
		if ( $order_id ) {
			$order = wc_get_order( $order_id );
			if ( $order ) {
				$order->update_status( 'completed' );
			}
		}
		wp_redirect( $redirect );
		exit;
	}

	public function handle_run_expiry() {
		if ( ! $this->can_manage() ) {
			wp_die( esc_html__( 'Not allowed', 'one-ba-auctions' ) );
		}

		check_admin_referer( 'oba_run_expiry' );

		$plugin = new OBA_Plugin();
		$plugin->check_expired_auctions();
		OBA_Audit_Log::log( 'run_expiry' );

		wp_redirect( admin_url( 'admin.php?page=oba-1ba-auctions&expiry_run=1' ) );
		exit;
	}

	public function handle_export_participants() {
		if ( ! $this->can_manage() ) {
			wp_die( esc_html__( 'Not allowed', 'one-ba-auctions' ) );
		}

		$auction_id = isset( $_GET['auction_id'] ) ? absint( $_GET['auction_id'] ) : 0;
		check_admin_referer( "oba_export_participants_{$auction_id}" );

		$status_filter = isset( $_GET['p_status'] ) ? sanitize_text_field( wp_unslash( $_GET['p_status'] ) ) : '';
		$search_user   = isset( $_GET['p_user'] ) ? absint( $_GET['p_user'] ) : 0;

		global $wpdb;
		$table = $wpdb->prefix . 'auction_participants';
		$sql   = $wpdb->prepare( "SELECT * FROM {$table} WHERE auction_id = %d", $auction_id );
		if ( $status_filter ) {
			$sql .= $wpdb->prepare( " AND status = %s", $status_filter );
		}
		if ( $search_user ) {
			$sql .= $wpdb->prepare( " AND user_id = %d", $search_user );
		}
		$sql .= ' ORDER BY id DESC';
		$rows = $wpdb->get_results( $sql, ARRAY_A );

		nocache_headers();
		header( 'Content-Type: text/csv' );
		header( 'Content-Disposition: attachment; filename=participants-' . $auction_id . '.csv' );

		$fh = fopen( 'php://output', 'w' );
		fputcsv( $fh, array( 'user_id', 'registered_at', 'registration_fee_credits', 'status' ) );
		foreach ( $rows as $row ) {
			fputcsv(
				$fh,
				array(
					$row['user_id'],
					$row['registered_at'],
					$row['registration_fee_credits'],
					$row['status'],
				)
			);
		}
		fclose( $fh );
		exit;
	}

	public function register_bulk_actions( $bulk_actions ) {
		$bulk_actions['oba_bulk_live']      = __( 'Start Live (auctions)', 'one-ba-auctions' );
		$bulk_actions['oba_bulk_force_end'] = __( 'Force End (auctions)', 'one-ba-auctions' );
		return $bulk_actions;
	}

	public function handle_bulk_actions( $redirect_to, $doaction, $post_ids ) {
		if ( ! in_array( $doaction, array( 'oba_bulk_live', 'oba_bulk_force_end' ), true ) ) {
			return $redirect_to;
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return $redirect_to;
		}

		foreach ( $post_ids as $post_id ) {
			$type = get_post_meta( $post_id, '_product_type', true );
			if ( 'auction' !== $type ) {
				continue;
			}

			if ( 'oba_bulk_live' === $doaction ) {
				update_post_meta( $post_id, '_auction_status', 'live' );
				update_post_meta( $post_id, '_live_expires_at', '' );
				OBA_Audit_Log::log( 'bulk_start_live', array(), $post_id );
			} elseif ( 'oba_bulk_force_end' === $doaction ) {
				update_post_meta( $post_id, '_auction_status', 'live' );
				$this->engine->calculate_winner_and_resolve_credits( $post_id, 'bulk_force_end' );
				OBA_Audit_Log::log( 'bulk_force_end', array(), $post_id );
			}
		}

		return add_query_arg( 'oba_bulk_done', count( $post_ids ), $redirect_to );
	}

	public function render_memberships_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$points_service = new OBA_Points_Service();
		global $wpdb;

		$flagged_users = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value = %s LIMIT 200",
				'_oba_has_membership',
				'1'
			)
		);

		$points_rows = $wpdb->get_results(
			"SELECT user_id FROM {$wpdb->prefix}auction_user_points ORDER BY updated_at DESC LIMIT 200",
			ARRAY_A
		);

		$user_ids = array_unique(
			array_merge(
				(array) $flagged_users,
				array_map(
					function ( $row ) {
						return (int) $row['user_id'];
					},
					(array) $points_rows
				)
			)
		);

		sort( $user_ids );

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Memberships', 'one-ba-auctions' ); ?></h1>
			<p><?php esc_html_e( 'Manage membership flag and points balance for users. Points are required to register for auctions.', 'one-ba-auctions' ); ?></p>
			<table class="widefat fixed striped" style="min-width: 900px;">
				<thead>
					<tr>
						<th><?php esc_html_e( 'User', 'one-ba-auctions' ); ?></th>
						<th><?php esc_html_e( 'Email', 'one-ba-auctions' ); ?></th>
						<th><?php esc_html_e( 'Membership active', 'one-ba-auctions' ); ?></th>
						<th><?php esc_html_e( 'Points balance', 'one-ba-auctions' ); ?></th>
						<th><?php esc_html_e( 'Update', 'one-ba-auctions' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( ! empty( $user_ids ) ) : ?>
						<?php foreach ( $user_ids as $uid ) : ?>
							<?php $user = get_userdata( $uid ); ?>
							<?php if ( ! $user ) { continue; } ?>
							<?php
							$membership_active = get_user_meta( $uid, '_oba_has_membership', true ) ? 1 : 0;
							$balance           = $points_service->get_balance( $uid );
							?>
							<tr>
								<td><?php echo esc_html( $user->user_login . ' (#' . $user->ID . ')' ); ?></td>
								<td><?php echo esc_html( $user->user_email ); ?></td>
								<td>
									<span class="aba-membership-pill" style="padding:4px 10px;border-radius:12px;display:inline-block;<?php echo $membership_active ? 'background:#dcfce7;color:#166534;' : 'background:#fee2e2;color:#991b1b;'; ?>">
										<?php echo $membership_active ? esc_html__( 'Active', 'one-ba-auctions' ) : esc_html__( 'Inactive', 'one-ba-auctions' ); ?>
									</span>
								</td>
								<td><?php echo esc_html( $balance ); ?></td>
								<td>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
										<?php wp_nonce_field( 'oba_save_membership_' . $uid ); ?>
										<input type="hidden" name="action" value="oba_save_membership" />
										<input type="hidden" name="user_id" value="<?php echo esc_attr( $uid ); ?>" />
										<label style="display:flex;align-items:center;gap:6px;">
											<input type="checkbox" name="has_membership" value="1" <?php checked( $membership_active, 1 ); ?> />
											<span><?php esc_html_e( 'Membership active', 'one-ba-auctions' ); ?></span>
										</label>
										<label style="display:flex;align-items:center;gap:6px;">
											<span><?php esc_html_e( 'Points', 'one-ba-auctions' ); ?></span>
											<input type="number" name="points_balance" value="<?php echo esc_attr( $balance ); ?>" step="1" min="0" />
										</label>
										<button class="button button-primary"><?php esc_html_e( 'Save', 'one-ba-auctions' ); ?></button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php else : ?>
						<tr><td colspan="5"><?php esc_html_e( 'No memberships yet. Users will appear here after they gain points or membership.', 'one-ba-auctions' ); ?></td></tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	private function get_pending_registrations( $auction_id ) {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return array();
		}
		$orders = wc_get_orders(
			array(
				'limit'         => 50,
				'status'        => array( 'pending', 'on-hold', 'processing' ),
				'meta_key'      => '_oba_is_registration_order',
				'meta_value'    => 'yes',
				'customer'      => '',
			)
		);
		$filtered = array();
		foreach ( $orders as $order ) {
			foreach ( $order->get_items() as $item ) {
				$aid = (int) $item->get_meta( '_oba_registration_auction_id', true );
				if ( $aid === (int) $auction_id ) {
					$filtered[] = $order;
					break;
				}
			}
		}
		return $filtered;
	}

	private function register_cli() {
		WP_CLI::add_command(
			'oba auctions end',
			function ( $args, $assoc_args ) {
				$auction_id = isset( $assoc_args['id'] ) ? (int) $assoc_args['id'] : 0;
				if ( ! $auction_id ) {
					WP_CLI::error( 'Provide --id=<auction_id>' );
				}
				update_post_meta( $auction_id, '_auction_status', 'live' );
				$this->engine->calculate_winner_and_resolve_credits( $auction_id, 'cli' );
				WP_CLI::success( "Auction {$auction_id} ended and winner recalculated." );
			}
		);

		WP_CLI::add_command(
			'oba auctions expire',
			function () {
				$plugin = new OBA_Plugin();
				$plugin->check_expired_auctions();
				WP_CLI::success( 'Expiry check completed.' );
			}
		);

		WP_CLI::add_command(
			'oba auctions list',
			function ( $args, $assoc_args ) {
				$status  = isset( $assoc_args['status'] ) ? strtolower( $assoc_args['status'] ) : '';
				$allowed = array( 'registration', 'pre_live', 'live', 'ended' );
				if ( $status && ! in_array( $status, $allowed, true ) ) {
					WP_CLI::error( 'Status must be one of registration|pre_live|live|ended' );
				}

				$q = new WP_Query(
					array(
						'post_type'      => 'product',
						'post_status'    => array( 'publish', 'draft', 'pending' ),
						'posts_per_page' => -1,
						'fields'         => 'ids',
					)
				);

				$rows = array();
				foreach ( $q->posts as $pid ) {
					$product = wc_get_product( $pid );
					if ( ! $product || 'auction' !== $product->get_type() ) {
						continue;
					}
					$st = strtolower( (string) get_post_meta( $pid, '_auction_status', true ) );
					if ( ! in_array( $st, $allowed, true ) ) {
						$st = 'registration';
					}
					if ( $status && $st !== $status ) {
						continue;
					}
					$rows[] = array(
						'id'              => $pid,
						'title'           => get_the_title( $pid ),
						'status'          => $st,
						'live_expires_at' => get_post_meta( $pid, '_live_expires_at', true ),
					);
				}

				if ( empty( $rows ) ) {
					WP_CLI::log( 'No auctions found.' );
					return;
				}

				WP_CLI\Utils\format_items( 'table', $rows, array( 'id', 'title', 'status', 'live_expires_at' ) );
			}
		);

		WP_CLI::add_command(
			'oba auctions participants',
			function ( $args, $assoc_args ) {
				$auction_id = isset( $assoc_args['id'] ) ? (int) $assoc_args['id'] : 0;
				if ( ! $auction_id ) {
					WP_CLI::error( 'Provide --id=<auction_id>' );
				}
				global $wpdb;
				$table = $wpdb->prefix . 'auction_participants';
				$rows  = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT user_id, status, registration_fee_credits, registered_at FROM {$table} WHERE auction_id = %d ORDER BY id DESC",
						$auction_id
					),
					ARRAY_A
				);
				if ( empty( $rows ) ) {
					WP_CLI::log( 'No participants.' );
					return;
				}
				WP_CLI\Utils\format_items( 'table', $rows, array( 'user_id', 'status', 'registration_fee_credits', 'registered_at' ) );
			}
		);

		WP_CLI::add_command(
			'oba auctions bids',
			function ( $args, $assoc_args ) {
				$auction_id = isset( $assoc_args['id'] ) ? (int) $assoc_args['id'] : 0;
				if ( ! $auction_id ) {
					WP_CLI::error( 'Provide --id=<auction_id>' );
				}
				global $wpdb;
				$table = $wpdb->prefix . 'auction_bids';
				$rows  = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT user_id, credits_reserved, sequence_number, created_at FROM {$table} WHERE auction_id = %d ORDER BY sequence_number DESC",
						$auction_id
					),
					ARRAY_A
				);
				if ( empty( $rows ) ) {
					WP_CLI::log( 'No bids.' );
					return;
				}
				WP_CLI\Utils\format_items( 'table', $rows, array( 'user_id', 'credits_reserved', 'sequence_number', 'created_at' ) );
			}
		);

		WP_CLI::add_command(
			'oba auctions reset-timer',
			function ( $args, $assoc_args ) {
				$auction_id = isset( $assoc_args['id'] ) ? (int) $assoc_args['id'] : 0;
				$seconds    = isset( $assoc_args['seconds'] ) ? (int) $assoc_args['seconds'] : 0;
				if ( ! $auction_id ) {
					WP_CLI::error( 'Provide --id=<auction_id>' );
				}
				$meta   = $this->repo->get_auction_meta( $auction_id );
				$timer  = $seconds > 0 ? $seconds : (int) $meta['live_timer_seconds'];
				$expire = gmdate( 'Y-m-d H:i:s', time() + max( 1, $timer ) );
				update_post_meta( $auction_id, '_live_expires_at', $expire );
				WP_CLI::success( "Live timer reset to {$expire}" );
			}
		);

		WP_CLI::add_command(
			'oba auctions winners',
			function ( $args, $assoc_args ) {
				$auction_id = isset( $assoc_args['id'] ) ? (int) $assoc_args['id'] : 0;
				global $wpdb;
				$table = $wpdb->prefix . 'auction_winners';
				$sql   = "SELECT auction_id, winner_user_id, total_bids, total_credits_consumed, claim_price_credits, wc_order_id, created_at FROM {$table}";
				if ( $auction_id ) {
					$sql = $wpdb->prepare( $sql . ' WHERE auction_id = %d', $auction_id );
				}
				$sql  .= ' ORDER BY id DESC';
				$rows  = $wpdb->get_results( $sql, ARRAY_A );
				if ( empty( $rows ) ) {
					WP_CLI::log( 'No winners found.' );
					return;
				}
				WP_CLI\Utils\format_items( 'table', $rows, array( 'auction_id', 'winner_user_id', 'total_bids', 'total_credits_consumed', 'claim_price_credits', 'wc_order_id', 'created_at' ) );
			}
		);

		WP_CLI::add_command(
			'oba ledger export',
			function ( $args, $assoc_args ) {
				global $wpdb;
				$table  = $wpdb->prefix . 'auction_credit_ledger';
				$user_id = isset( $assoc_args['user'] ) ? (int) $assoc_args['user'] : 0;
				$sql     = "SELECT * FROM {$table}";
				if ( $user_id ) {
					$sql = $wpdb->prepare( $sql . ' WHERE user_id = %d', $user_id );
				}
				$sql  .= ' ORDER BY id DESC';
				$rows  = $wpdb->get_results( $sql, ARRAY_A );
				if ( empty( $rows ) ) {
					WP_CLI::log( 'No ledger rows.' );
					return;
				}
				WP_CLI\Utils\format_items( 'table', $rows, array( 'id', 'user_id', 'amount', 'balance_after', 'reason', 'reference_id', 'created_at' ) );
			}
		);

		// Per-second/autonomous tick helper: runs autobids + expiry once.
		WP_CLI::add_command(
			'oba tick',
			function ( $args, $assoc_args ) {
				$engine  = new OBA_Auction_Engine();
				$service = new OBA_Autobid_Service();
				$plugin  = new OBA_Plugin();

				$auction_id = isset( $assoc_args['auction'] ) ? (int) $assoc_args['auction'] : 0;

				if ( $auction_id ) {
					if ( $service->is_globally_enabled() && $service->is_enabled_for_auction( $auction_id ) ) {
						$service->maybe_run_autobids( $auction_id );
					}
					$engine->end_auction_if_expired( $auction_id, 'wpcli_tick_single' );
					WP_CLI::success( "Tick processed for auction {$auction_id}." );
					return;
				}

				$plugin->run_autobid_check();
				$plugin->check_expired_auctions();
				WP_CLI::success( 'Tick processed for all live auctions.' );
			}
		);

		// Long-running loop to drive autobids/expiry from CLI (for real 5s+ cadence).
		WP_CLI::add_command(
			'oba run-autobid-loop',
			function ( $args, $assoc_args ) {
				$interval = isset( $assoc_args['interval'] ) ? (int) $assoc_args['interval'] : 5;
				if ( $interval < 1 ) {
					WP_CLI::error( 'Interval must be at least 1 second.' );
				}

				WP_CLI::log( "Starting autobid loop every {$interval}s. Press Ctrl+C to stop." );
				$loop = 0;
				while ( true ) {
					$start = microtime( true );

					do_action( 'oba_run_autobid_check' );
					do_action( 'oba_run_expiry_check' );

					$elapsed = microtime( true ) - $start;
					$loop++;
					WP_CLI::log( sprintf( '[%s] tick #%d finished in %.2fs', gmdate( 'H:i:s' ), $loop, $elapsed ) );

					$sleep = max( 0, $interval - $elapsed );
					if ( $sleep > 0 ) {
						usleep( (int) ( $sleep * 1_000_000 ) );
					}
				}
			}
		);
	}
}
