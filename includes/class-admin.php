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
		add_action( 'admin_post_oba_set_status', array( $this, 'handle_set_status' ) );
		add_action( 'admin_post_oba_recalc_winner', array( $this, 'handle_recalc_winner' ) );
		add_action( 'admin_post_oba_edit_credits', array( $this, 'handle_edit_credits' ) );
		add_action( 'admin_post_oba_save_settings', array( $this, 'handle_save_settings' ) );
		add_action( 'admin_post_oba_save_translations', array( $this, 'handle_save_translations' ) );
		add_action( 'admin_post_oba_save_emails', array( $this, 'handle_save_emails' ) );
		add_action( 'admin_post_oba_send_test_email', array( $this, 'handle_send_test_email' ) );
		add_action( 'admin_post_nopriv_oba_send_test_email', array( $this, 'handle_send_test_email' ) );
		add_action( 'admin_post_oba_run_expiry', array( $this, 'handle_run_expiry' ) );
		add_action( 'admin_post_oba_remove_participant', array( $this, 'handle_remove_participant' ) );
		add_action( 'admin_post_oba_export_participants', array( $this, 'handle_export_participants' ) );
		add_filter( 'bulk_actions-edit-product', array( $this, 'register_bulk_actions' ) );
		add_filter( 'handle_bulk_actions-edit-product', array( $this, 'handle_bulk_actions' ), 10, 3 );

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			$this->register_cli();
		}
	}

	public function register_menu() {
		$cap = 'manage_woocommerce';
		add_menu_page(
			__( 'Custom Auctions', 'one-ba-auctions' ),
			__( 'Custom Auctions', 'one-ba-auctions' ),
			$cap,
			'oba-auctions',
			array( $this, 'render_auctions_page' ),
			'dashicons-hammer',
			56
		);

		add_submenu_page( 'oba-auctions', __( 'Auctions', 'one-ba-auctions' ), __( 'Auctions', 'one-ba-auctions' ), $cap, 'oba-auctions', array( $this, 'render_auctions_page' ) );
		add_submenu_page( 'oba-auctions', __( 'Winners', 'one-ba-auctions' ), __( 'Winners', 'one-ba-auctions' ), $cap, 'oba-winners', array( $this, 'render_winners_page' ) );
		add_submenu_page( 'oba-auctions', __( 'Ended Logs', 'one-ba-auctions' ), __( 'Ended Logs', 'one-ba-auctions' ), $cap, 'oba-ended-logs', array( $this, 'render_ended_logs_page' ) );
		add_submenu_page( 'oba-auctions', __( 'User Credits', 'one-ba-auctions' ), __( 'User Credits', 'one-ba-auctions' ), $cap, 'oba-credits', array( $this, 'render_credits_page' ) );
		add_submenu_page( 'oba-auctions', __( 'Participants', 'one-ba-auctions' ), __( 'Participants', 'one-ba-auctions' ), $cap, 'oba-participants', array( $this, 'render_participants_page' ) );
		add_submenu_page( 'oba-auctions', __( 'Audit Log', 'one-ba-auctions' ), __( 'Audit Log', 'one-ba-auctions' ), $cap, 'oba-audit', array( $this, 'render_audit_page' ) );
		add_submenu_page( 'oba-auctions', __( 'Settings', 'one-ba-auctions' ), __( 'Settings', 'one-ba-auctions' ), $cap, 'oba-settings', array( $this, 'render_settings_page' ) );
		add_submenu_page( 'oba-auctions', __( 'Translations', 'one-ba-auctions' ), __( 'Translations', 'one-ba-auctions' ), $cap, 'oba-translations', array( $this, 'render_translations_page' ) );
		add_submenu_page( 'oba-auctions', __( 'Emails', 'one-ba-auctions' ), __( 'Emails', 'one-ba-auctions' ), $cap, 'oba-emails', array( $this, 'render_emails_page' ) );
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
					<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'oba-auctions', 'status' => $key ), admin_url( 'admin.php' ) ) ); ?>" class="nav-tab <?php echo $status === $key ? 'nav-tab-active' : ''; ?>">
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
			<table class="widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'User ID', 'one-ba-auctions' ); ?></th>
						<th><?php esc_html_e( 'Balance', 'one-ba-auctions' ); ?></th>
						<th><?php esc_html_e( 'Updated', 'one-ba-auctions' ); ?></th>
						<th><?php esc_html_e( 'Edit', 'one-ba-auctions' ); ?></th>
						<th><?php esc_html_e( 'Ledger', 'one-ba-auctions' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( $credits ) : ?>
						<?php foreach ( $credits as $row ) : ?>
							<tr>
								<td><?php echo esc_html( $row['user_id'] ); ?></td>
								<td><?php echo esc_html( $row['credits_balance'] ); ?></td>
								<td><?php echo esc_html( $row['updated_at'] ); ?></td>
								<td>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:flex; gap:6px; align-items:center;">
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
						<tr><td colspan="5"><?php esc_html_e( 'No credit records yet.', 'one-ba-auctions' ); ?></td></tr>
					<?php endif; ?>
				</tbody>
			</table>
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
					'%s | %s | %s',
					sprintf( esc_html__( 'Active: %d', 'one-ba-auctions' ), $status_counts['active'] ),
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
			<table class="widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'User', 'one-ba-auctions' ); ?></th>
						<th><?php esc_html_e( 'Registered at', 'one-ba-auctions' ); ?></th>
						<th><?php esc_html_e( 'Fee', 'one-ba-auctions' ); ?></th>
						<th><?php esc_html_e( 'Status', 'one-ba-auctions' ); ?></th>
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
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$settings = $this->settings;
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Auction Settings', 'one-ba-auctions' ); ?></h1>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'oba_save_settings' ); ?>
				<input type="hidden" name="action" value="oba_save_settings" />

				<table class="form-table">
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
						<th scope="row"><?php esc_html_e( 'Terms & Conditions text', 'one-ba-auctions' ); ?></th>
						<td>
							<textarea name="terms_text" rows="4" cols="50"><?php echo esc_textarea( $settings['terms_text'] ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Shown in registration step and required to register when not empty.', 'one-ba-auctions' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Show credits balance in header', 'one-ba-auctions' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="show_header_balance" value="1" <?php checked( $settings['show_header_balance'], true ); ?> />
								<?php esc_html_e( 'Display a floating credits pill for logged-in users.', 'one-ba-auctions' ); ?>
							</label>
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
						<th scope="row"><?php esc_html_e( 'Quick credit pack links (shown in live auctions)', 'one-ba-auctions' ); ?></th>
						<td>
							<input type="url" name="credit_pack_link_1" value="<?php echo esc_attr( $settings['credit_pack_links'][0] ); ?>" placeholder="https://example.com/credits-pack-1" style="width:100%;max-width:420px;margin-bottom:4px;" />
							<input type="url" name="credit_pack_link_2" value="<?php echo esc_attr( $settings['credit_pack_links'][1] ); ?>" placeholder="https://example.com/credits-pack-2" style="width:100%;max-width:420px;margin-bottom:4px;" />
							<input type="url" name="credit_pack_link_3" value="<?php echo esc_attr( $settings['credit_pack_links'][2] ); ?>" placeholder="https://example.com/credits-pack-3" style="width:100%;max-width:420px;" />
							<p class="description"><?php esc_html_e( 'Optional labels for quick packs (defaults to Pack 1/2/3 if empty).', 'one-ba-auctions' ); ?></p>
							<input type="text" name="credit_pack_label_1" value="<?php echo esc_attr( $settings['credit_pack_labels'][0] ); ?>" placeholder="<?php esc_attr_e( 'Label for pack 1, e.g. +10', 'one-ba-auctions' ); ?>" style="width:100%;max-width:420px;margin-bottom:4px;" />
							<input type="text" name="credit_pack_label_2" value="<?php echo esc_attr( $settings['credit_pack_labels'][1] ); ?>" placeholder="<?php esc_attr_e( 'Label for pack 2, e.g. +25', 'one-ba-auctions' ); ?>" style="width:100%;max-width:420px;margin-bottom:4px;" />
							<input type="text" name="credit_pack_label_3" value="<?php echo esc_attr( $settings['credit_pack_labels'][2] ); ?>" placeholder="<?php esc_attr_e( 'Label for pack 3, e.g. +50', 'one-ba-auctions' ); ?>" style="width:100%;max-width:420px;" />
							<p class="description"><?php esc_html_e( 'Appears in live auctions so users can quickly buy credits when low.', 'one-ba-auctions' ); ?></p>
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

		$auction_id = isset( $_GET['auction_id'] ) ? absint( $_GET['auction_id'] ) : 0;
		$action     = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';

		check_admin_referer( "oba_set_status_{$auction_id}" );

		if ( ! $auction_id ) {
			wp_redirect( admin_url( 'admin.php?page=oba-auctions' ) );
			exit;
		}

		switch ( $action ) {
			case 'pre_live':
				update_post_meta( $auction_id, '_auction_status', 'pre_live' );
				update_post_meta( $auction_id, '_pre_live_start', gmdate( 'Y-m-d H:i:s' ) );
				break;
			case 'live':
				update_post_meta( $auction_id, '_auction_status', 'live' );
				update_post_meta( $auction_id, '_live_expires_at', '' );
				OBA_Audit_Log::log( 'start_live', array(), $auction_id );
				break;
			case 'force_end':
				update_post_meta( $auction_id, '_auction_status', 'live' );
				$this->engine->calculate_winner_and_resolve_credits( $auction_id, 'admin_force_end' );
				OBA_Audit_Log::log( 'force_end', array(), $auction_id );
				break;
		}

		wp_redirect( admin_url( 'admin.php?page=oba-auctions' ) );
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

		wp_redirect( admin_url( 'admin.php?page=oba-auctions' ) );
		exit;
	}

	public function handle_edit_credits() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
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
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
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
				'credit_pack_link_1'      => isset( $_POST['credit_pack_link_1'] ) ? wp_unslash( $_POST['credit_pack_link_1'] ) : '',
				'credit_pack_link_2'      => isset( $_POST['credit_pack_link_2'] ) ? wp_unslash( $_POST['credit_pack_link_2'] ) : '',
				'credit_pack_link_3'      => isset( $_POST['credit_pack_link_3'] ) ? wp_unslash( $_POST['credit_pack_link_3'] ) : '',
				'credit_pack_label_1'     => isset( $_POST['credit_pack_label_1'] ) ? wp_unslash( $_POST['credit_pack_label_1'] ) : '',
				'credit_pack_label_2'     => isset( $_POST['credit_pack_label_2'] ) ? wp_unslash( $_POST['credit_pack_label_2'] ) : '',
				'credit_pack_label_3'     => isset( $_POST['credit_pack_label_3'] ) ? wp_unslash( $_POST['credit_pack_label_3'] ) : '',
				'login_link'              => isset( $_POST['login_link'] ) ? wp_unslash( $_POST['login_link'] ) : '',
			)
		);

		wp_redirect( admin_url( 'admin.php?page=oba-settings&updated=1' ) );
		exit;
	}

	public function render_translations_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
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
			'buy_credits_title' => __( 'Buy credits title', 'one-ba-auctions' ),
			'registration_fee_label' => __( 'Registration fee label', 'one-ba-auctions' ),
			'registered_badge' => __( 'Registered badge text', 'one-ba-auctions' ),
			'not_registered_badge' => __( 'Not registered badge text', 'one-ba-auctions' ),
			'credit_singular' => __( 'Credit (singular)', 'one-ba-auctions' ),
			'credit_plural'   => __( 'Credits (plural)', 'one-ba-auctions' ),
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
			'claim_modal_title' => __( 'Claim modal title', 'one-ba-auctions' ),
			'claim_option_credits' => __( 'Claim option: credits', 'one-ba-auctions' ),
			'claim_option_gateway' => __( 'Claim option: gateway', 'one-ba-auctions' ),
			'claim_continue' => __( 'Claim continue button', 'one-ba-auctions' ),
			'claim_cancel' => __( 'Claim cancel button', 'one-ba-auctions' ),
			'claim_error' => __( 'Claim error message label', 'one-ba-auctions' ),
			'credits_pill_label' => __( 'Credits pill label', 'one-ba-auctions' ),
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
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
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
				'claim_modal_title' => isset( $_POST['claim_modal_title'] ) ? $_POST['claim_modal_title'] : '',
				'claim_option_credits' => isset( $_POST['claim_option_credits'] ) ? $_POST['claim_option_credits'] : '',
				'claim_option_gateway' => isset( $_POST['claim_option_gateway'] ) ? $_POST['claim_option_gateway'] : '',
				'claim_continue' => isset( $_POST['claim_continue'] ) ? $_POST['claim_continue'] : '',
				'claim_cancel' => isset( $_POST['claim_cancel'] ) ? $_POST['claim_cancel'] : '',
				'claim_error' => isset( $_POST['claim_error'] ) ? $_POST['claim_error'] : '',
				'credits_pill_label' => isset( $_POST['credits_pill_label'] ) ? $_POST['credits_pill_label'] : '',
			)
		);

		wp_redirect( admin_url( 'admin.php?page=oba-translations&updated=1' ) );
		exit;
	}

	public function render_emails_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$settings = $this->settings;
		$tpl      = isset( $settings['email_templates'] ) ? $settings['email_templates'] : array();
		$fields   = array(
			'pre_live'    => __( 'Pre-live (to registered participants)', 'one-ba-auctions' ),
			'live'        => __( 'Live started (to registered participants)', 'one-ba-auctions' ),
			'winner'      => __( 'Auction winner', 'one-ba-auctions' ),
			'loser'       => __( 'Auction losers (refund notice)', 'one-ba-auctions' ),
			'claim'       => __( 'Claim confirmation', 'one-ba-auctions' ),
			'credits'     => __( 'Credits edited', 'one-ba-auctions' ),
			'participant' => __( 'Participant status change', 'one-ba-auctions' ),
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Emails', 'one-ba-auctions' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Edit subjects and bodies for outgoing emails. Allowed tokens: {user_name}, {auction_title}, {auction_link}, {claim_price}, {bid_cost}, {balance}, {status}.', 'one-ba-auctions' ); ?></p>
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
					<label><input type="checkbox" name="templates[]" value="credits" checked /> <?php esc_html_e( 'Credits edited', 'one-ba-auctions' ); ?></label><br />
					<label><input type="checkbox" name="templates[]" value="participant" checked /> <?php esc_html_e( 'Participant status', 'one-ba-auctions' ); ?></label>
				</div>
				<?php submit_button( __( 'Send Selected Tests', 'one-ba-auctions' ), 'secondary', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}

	public function handle_save_emails() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
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
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Not allowed', 'one-ba-auctions' ) );
		}

		check_admin_referer( 'oba_send_test_email' );

		$admin_email = get_option( 'admin_email' );
		if ( class_exists( 'OBA_Email' ) && $admin_email ) {
			$mailer = new OBA_Email();
			$templates = isset( $_POST['templates'] ) && is_array( $_POST['templates'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['templates'] ) ) : array();
			if ( empty( $templates ) ) {
				$templates = array( 'pre_live', 'live', 'winner', 'loser', 'claim', 'credits', 'participant' );
			}
			$mailer->send_test_templates( $templates, $admin_email );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=oba-emails&test=1' ) );
		exit;
	}

	public function handle_remove_participant() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
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
			wp_redirect( admin_url( 'admin.php?page=oba-auctions' ) );
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

	public function handle_run_expiry() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Not allowed', 'one-ba-auctions' ) );
		}

		check_admin_referer( 'oba_run_expiry' );

		$plugin = new OBA_Plugin();
		$plugin->check_expired_auctions();
		OBA_Audit_Log::log( 'run_expiry' );

		wp_redirect( admin_url( 'admin.php?page=oba-auctions&expiry_run=1' ) );
		exit;
	}

	public function handle_export_participants() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
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
	}
}
