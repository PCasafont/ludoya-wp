<?php
/**
 * Settings screen.
 *
 * @package Ludoya
 *
 * @var array          $settings Current settings.
 * @var array|WP_Error $status   Result of a connection test, when one was asked for.
 */

defined( 'ABSPATH' ) || exit;

$ludoya_rate = Ludoya_Client::rate_limit();
$ludoya_test_url = wp_nonce_url(
	admin_url( 'admin.php?page=ludoya-settings&ludoya_test=1' ),
	'ludoya_test_connection'
);
?>
<div class="wrap ludoya-admin">
	<h1><?php esc_html_e( 'Ludoya settings', 'ludoya' ); ?></h1>

	<?php if ( null !== $status ) : ?>
		<?php if ( is_wp_error( $status ) ) : ?>
			<div class="notice notice-error"><p><?php echo esc_html( $status->get_error_message() ); ?></p></div>
		<?php else : ?>
			<div class="notice notice-success">
				<p>
					<?php
					printf(
						/* translators: %d: number of locations found. */
						esc_html__( 'Connected. Your organisation has %d locations.', 'ludoya' ),
						count( ludoya_get( $status, 'locations', array() ) )
					);
					?>
				</p>
			</div>
		<?php endif; ?>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="<?php echo esc_attr( Ludoya_Admin::SETTINGS_ACTION ); ?>" />
		<?php wp_nonce_field( Ludoya_Admin::SETTINGS_ACTION ); ?>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="ludoya-api-key"><?php esc_html_e( 'API key', 'ludoya' ); ?></label></th>
				<td>
					<?php if ( Ludoya_Settings::api_key_is_constant() ) : ?>
						<p><code>LUDOYA_API_KEY</code> <?php esc_html_e( 'is defined in wp-config.php, so the key is read from there.', 'ludoya' ); ?></p>
					<?php else : ?>
						<input
							id="ludoya-api-key"
							class="regular-text"
							type="password"
							name="api_key"
							autocomplete="off"
							placeholder="<?php echo esc_attr( $settings['api_key'] ? str_repeat( '•', 12 ) . substr( $settings['api_key'], -4 ) : 'ldy_…' ); ?>"
						/>
						<p class="description">
							<?php esc_html_e( 'Create one in Ludoya under your organisation profile, Developer. Requires the Business plan. Leave blank to keep the current key.', 'ludoya' ); ?>
							<br />
							<?php esc_html_e( 'For a stricter setup, define LUDOYA_API_KEY in wp-config.php instead and this field disappears.', 'ludoya' ); ?>
						</p>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="ludoya-cache-ttl"><?php esc_html_e( 'Cache', 'ludoya' ); ?></label></th>
				<td>
					<input id="ludoya-cache-ttl" type="number" min="0" step="30" name="cache_ttl" value="<?php echo esc_attr( $settings['cache_ttl'] ); ?>" />
					<?php esc_html_e( 'seconds', 'ludoya' ); ?>
					<p class="description"><?php esc_html_e( 'How long a page keeps its copy of the data. Without this, every visitor costs an API call. Editing an event here clears the cache immediately.', 'ludoya' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="ludoya-timeout"><?php esc_html_e( 'Timeout', 'ludoya' ); ?></label></th>
				<td>
					<input id="ludoya-timeout" type="number" min="1" max="60" name="timeout" value="<?php echo esc_attr( $settings['timeout'] ); ?>" />
					<?php esc_html_e( 'seconds', 'ludoya' ); ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Sign-ups', 'ludoya' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="signup_open" value="1" <?php checked( ! empty( $settings['signup_open'] ) ); ?> />
						<?php esc_html_e( 'Let visitors sign up for events from this site', 'ludoya' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'A sign-up creates a Ludoya account for the address given, so keep this off unless your privacy notice covers it.', 'ludoya' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="ludoya-consent"><?php esc_html_e( 'Consent text', 'ludoya' ); ?></label></th>
				<td>
					<textarea id="ludoya-consent" class="large-text" name="consent_text" rows="3"><?php echo esc_textarea( $settings['consent_text'] ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Shown beside the checkbox on the sign-up form. Leave empty for the default wording.', 'ludoya' ); ?></p>
				</td>
			</tr>
		</table>

		<?php submit_button(); ?>
	</form>

	<h2><?php esc_html_e( 'Connection', 'ludoya' ); ?></h2>
	<p>
		<a class="button" href="<?php echo esc_url( $ludoya_test_url ); ?>"><?php esc_html_e( 'Test connection', 'ludoya' ); ?></a>
	</p>
	<?php if ( $ludoya_rate ) : ?>
		<p class="description">
			<?php
			printf(
				/* translators: 1: requests left, 2: requests per minute allowed. */
				esc_html__( 'Rate limit at the last call: %1$d of %2$d requests left this minute.', 'ludoya' ),
				(int) $ludoya_rate['remaining'],
				(int) $ludoya_rate['limit']
			);
			?>
		</p>
	<?php endif; ?>

	<h2><?php esc_html_e( 'Putting Ludoya on your pages', 'ludoya' ); ?></h2>
	<p>
		<?php esc_html_e( 'Edit any page, press the + button, search for "Ludoya" and pick what you want to show. Nothing needs setting up to start with; each one has its own options in the sidebar on the right — how many events, which layout, and so on — once you want to change something.', 'ludoya' ); ?>
	</p>

	<table class="widefat striped ludoya-blocks">
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'To show this', 'ludoya' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Add this block', 'ludoya' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Or paste this, in an older editor', 'ludoya' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( ludoya_content_blocks() as $ludoya_block ) : ?>
				<tr>
					<th scope="row">
						<?php echo esc_html( $ludoya_block['shows'] ); ?>
						<?php if ( $ludoya_block['needs'] ) : ?>
							<span class="description"><?php echo esc_html( $ludoya_block['needs'] ); ?></span>
						<?php endif; ?>
					</th>
					<td><strong><?php echo esc_html( $ludoya_block['block'] ); ?></strong></td>
					<td class="ludoya-blocks__code">
						<code><?php echo esc_html( $ludoya_block['shortcode'] ); ?></code>
						<button
							type="button"
							class="button button-small ludoya-copy"
							data-copy="<?php echo esc_attr( $ludoya_block['shortcode'] ); ?>"
						><?php esc_html_e( 'Copy', 'ludoya' ); ?></button>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<h3><?php esc_html_e( 'Showing an event on your own site', 'ludoya' ); ?></h3>
	<p><?php esc_html_e( 'By default an event card sends the visitor off to the Ludoya app. There are two ways to keep them here, and most clubs end up using both.', 'ludoya' ); ?></p>

	<h4><?php esc_html_e( 'One page that serves every event', 'ludoya' ); ?></h4>
	<p class="description"><?php esc_html_e( 'For your regular programme, where the events change every month and you do not want a new page each time.', 'ludoya' ); ?></p>
	<ol class="ludoya-steps">
		<li><?php esc_html_e( 'Make a page called Event, put the "Ludoya event" block on it, and leave its settings empty.', 'ludoya' ); ?></li>
		<li>
			<?php
			printf(
				/* translators: %s: the name of the setting, as it appears in the block sidebar. */
				esc_html__( 'On the page listing your events, select the "Ludoya events" block and set %s to that page.', 'ludoya' ),
				'<strong>' . esc_html__( 'Event page', 'ludoya' ) . '</strong>'
			);
			?>
		</li>
	</ol>
	<p class="description"><?php esc_html_e( 'Every card now links to your Event page, which works out which event to show from the link it was opened with.', 'ludoya' ); ?></p>

	<h4><?php esc_html_e( 'A page dedicated to one event', 'ludoya' ); ?></h4>
	<p class="description"><?php esc_html_e( 'For a tournament or an open day you want in your menu, with its own address and its own words around it.', 'ludoya' ); ?></p>
	<ol class="ludoya-steps">
		<li>
			<?php
			printf(
				/* translators: %s: the name of the row action in the events list. */
				esc_html__( 'Open %1$s, find the event, and press %2$s underneath its name.', 'ludoya' ),
				'<a href="' . esc_url( admin_url( 'admin.php?page=ludoya-events' ) ) . '">' . esc_html__( 'Ludoya, Events', 'ludoya' ) . '</a>',
				'<strong>' . esc_html__( 'Copy shortcode', 'ludoya' ) . '</strong>'
			);
			?>
		</li>
		<li><?php esc_html_e( 'Paste it into any page. Write whatever you like around it.', 'ludoya' ); ?></li>
	</ol>
	<p class="description"><?php esc_html_e( 'That page shows only that event, whatever anybody clicks elsewhere. The same shortcode sits on the edit screen for each event.', 'ludoya' ); ?></p>

	<h3><?php esc_html_e( 'Changing how any of it looks', 'ludoya' ); ?></h3>
	<p class="description">
		<?php
		printf(
			/* translators: 1: plugin folder to copy from, 2: folder to copy into, inside the active theme. */
			esc_html__( 'These views follow your theme. To go further, copy a file out of %1$s into %2$s in your theme and edit it there; the plugin will use yours instead.', 'ludoya' ),
			'<code>' . esc_html( 'plugins/ludoya/templates/' ) . '</code>',
			'<code>' . esc_html( 'ludoya/' ) . '</code>'
		);
		?>
	</p>
</div>
