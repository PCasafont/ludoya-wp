<?php
/**
 * Create / edit event screen.
 *
 * @package Ludoya
 *
 * @var array          $event      The event being edited, or an empty array for a new one.
 * @var string         $etag       ETag of the loaded event, sent back as If-Match.
 * @var array          $locations  The organisation's locations.
 * @var array          $templates  The organisation's form templates.
 * @var array          $event_form The form this event resolves to.
 * @var WP_Error|null  $error      What went wrong loading the event, if anything.
 * @var string         $event_id   Event id, or an empty string.
 */

defined( 'ABSPATH' ) || exit;

$ludoya_is_new = ( '' === $event_id );
$ludoya_zone   = ! empty( $event['timeZone'] ) ? $event['timeZone'] : wp_timezone_string();
$ludoya_form_source = ludoya_get( $event_form, 'source', '' );

// Visibility is the one setting the API always serialises — it has no default to be stripped by —
// so its presence is what tells us whether we are talking to an API new enough to report the rest.
// Without it the four settings below show their defaults, and saving must not write those defaults
// over what staff actually set.
$ludoya_reports_settings = $ludoya_is_new || isset( $event['visibility'] );
?>
<div class="wrap ludoya-admin">
	<h1 class="wp-heading-inline">
		<?php echo $ludoya_is_new ? esc_html__( 'Add event', 'ludoya' ) : esc_html__( 'Edit event', 'ludoya' ); ?>
	</h1>
	<?php if ( ! $ludoya_is_new ) : ?>
		<a class="page-title-action" href="<?php echo esc_url( ludoya_event_url( $event ? $event : $event_id ) ); ?>" target="_blank" rel="noopener">
			<?php esc_html_e( 'View on Ludoya', 'ludoya' ); ?>
		</a>
	<?php endif; ?>
	<?php if ( ! empty( $event['draft'] ) ) : ?>
		<a
			class="page-title-action"
			href="<?php
			echo esc_url(
				wp_nonce_url(
					add_query_arg(
						array(
							'action' => Ludoya_Events_Admin::ROW_ACTION,
							'do'     => 'publish',
							'event'  => rawurlencode( $event_id ),
						),
						admin_url( 'admin-post.php' )
					),
					Ludoya_Events_Admin::ROW_ACTION . '_publish_' . $event_id
				)
			);
			?>"
		><?php esc_html_e( 'Publish draft', 'ludoya' ); ?></a>
	<?php endif; ?>
	<hr class="wp-header-end" />

	<?php if ( $error ) : ?>
		<div class="notice notice-error"><p><?php echo esc_html( $error->get_error_message() ); ?></p></div>
	<?php endif; ?>

	<?php if ( ! $ludoya_reports_settings ) : ?>
		<div class="notice notice-warning">
			<p><?php esc_html_e( 'This Ludoya API is older than the plugin: it does not report visibility, attendance approval or the participation limits. Those four are shown at their defaults below and are left exactly as they are when you save.', 'ludoya' ); ?></p>
		</div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ludoya-event-form">
		<input type="hidden" name="action" value="<?php echo esc_attr( Ludoya_Events_Admin::SAVE_ACTION ); ?>" />
		<input type="hidden" name="event_id" value="<?php echo esc_attr( $event_id ); ?>" />
		<input type="hidden" name="etag" value="<?php echo esc_attr( $etag ); ?>" />
		<input type="hidden" name="time_zone" value="<?php echo esc_attr( $ludoya_zone ); ?>" />
		<input type="hidden" name="api_reports_settings" value="<?php echo $ludoya_reports_settings ? '1' : '0'; ?>" />
		<?php wp_nonce_field( Ludoya_Events_Admin::SAVE_ACTION ); ?>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="ludoya-title"><?php esc_html_e( 'Title', 'ludoya' ); ?></label></th>
				<td><input id="ludoya-title" class="regular-text" type="text" name="title" required value="<?php echo esc_attr( ludoya_get( $event, 'title', '' ) ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="ludoya-type"><?php esc_html_e( 'Type', 'ludoya' ); ?></label></th>
				<td>
					<select id="ludoya-type" name="type">
						<?php foreach ( ludoya_event_types() as $ludoya_type ) : ?>
							<option value="<?php echo esc_attr( $ludoya_type ); ?>" <?php selected( ludoya_get( $event, 'type', 'MEETUP' ), $ludoya_type ); ?>>
								<?php echo esc_html( ludoya_event_type_label( $ludoya_type ) ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="ludoya-description"><?php esc_html_e( 'Description', 'ludoya' ); ?></label></th>
				<td><textarea id="ludoya-description" class="large-text" name="description" rows="5"><?php echo esc_textarea( ludoya_get( $event, 'description', '' ) ); ?></textarea></td>
			</tr>
			<tr>
				<th scope="row"><label for="ludoya-starts-at"><?php esc_html_e( 'Starts', 'ludoya' ); ?></label></th>
				<td>
					<input id="ludoya-starts-at" type="datetime-local" name="starts_at" value="<?php echo esc_attr( Ludoya_Events_Admin::to_input( ludoya_get( $event, 'startsAt' ), $ludoya_zone ) ); ?>" />
					<p class="description">
						<?php
						printf(
							/* translators: %s: time zone name. */
							esc_html__( 'Times are in %s.', 'ludoya' ),
							esc_html( $ludoya_zone )
						);
						?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="ludoya-ends-at"><?php esc_html_e( 'Ends', 'ludoya' ); ?></label></th>
				<td><input id="ludoya-ends-at" type="datetime-local" name="ends_at" value="<?php echo esc_attr( Ludoya_Events_Admin::to_input( ludoya_get( $event, 'endsAt' ), $ludoya_zone ) ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="ludoya-location"><?php esc_html_e( 'Location', 'ludoya' ); ?></label></th>
				<td>
					<select id="ludoya-location" name="location_id">
						<option value=""><?php esc_html_e( 'The organisation default', 'ludoya' ); ?></option>
						<?php foreach ( $locations as $ludoya_location ) : ?>
							<option value="<?php echo esc_attr( $ludoya_location['id'] ); ?>" <?php selected( ludoya_get( $event, 'location.id', '' ), $ludoya_location['id'] ); ?>>
								<?php echo esc_html( $ludoya_location['name'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<select id="ludoya-spot" name="spot_id">
						<option value=""><?php esc_html_e( 'Any table', 'ludoya' ); ?></option>
						<?php foreach ( $locations as $ludoya_location ) : ?>
							<?php foreach ( ludoya_get( $ludoya_location, 'spots', array() ) as $ludoya_spot ) : ?>
								<option value="<?php echo esc_attr( $ludoya_spot['id'] ); ?>" data-location="<?php echo esc_attr( $ludoya_location['id'] ); ?>" <?php selected( ludoya_get( $event, 'spotId', '' ), $ludoya_spot['id'] ); ?>>
									<?php echo esc_html( $ludoya_spot['name'] ); ?>
								</option>
							<?php endforeach; ?>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'Table choices follow the location.', 'ludoya' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="ludoya-game-search"><?php esc_html_e( 'Game', 'ludoya' ); ?></label></th>
				<td>
					<div class="ludoya-picker" data-endpoint="ludoya_search_games" data-target="ludoya-game-id">
						<input id="ludoya-game-search" class="regular-text" type="search" placeholder="<?php esc_attr_e( 'Search the catalogue…', 'ludoya' ); ?>" />
						<ul class="ludoya-picker__results"></ul>
						<p class="ludoya-picker__chosen"><?php echo esc_html( ludoya_get( $event, 'game.name', '' ) ); ?></p>
					</div>
					<input id="ludoya-game-id" type="hidden" name="game_id" value="<?php echo esc_attr( ludoya_get( $event, 'game.id', '' ) ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="ludoya-languages"><?php esc_html_e( 'Languages at the tables', 'ludoya' ); ?></label></th>
				<td>
					<?php
					$ludoya_inherited = ! empty( $event['languagesInherited'] );
					$ludoya_own_langs = $ludoya_inherited ? array() : ludoya_get( $event, 'languages', array() );
					?>
					<input
						id="ludoya-languages"
						class="regular-text"
						type="text"
						name="languages"
						value="<?php echo esc_attr( implode( ', ', $ludoya_own_langs ) ); ?>"
						placeholder="es, ca, en"
					/>
					<p class="description">
						<?php esc_html_e( 'Two-letter codes, separated by commas. Unknown codes are dropped.', 'ludoya' ); ?>
						<?php if ( $ludoya_inherited && ludoya_get( $event, 'languages', array() ) ) : ?>
							<?php
							printf(
								/* translators: %s: comma-separated language codes. */
								esc_html__( 'Currently inherited from the parent event: %s. Leave empty to keep inheriting.', 'ludoya' ),
								esc_html( implode( ', ', ludoya_get( $event, 'languages', array() ) ) )
							);
							?>
						<?php endif; ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="ludoya-capacity"><?php esc_html_e( 'Capacity', 'ludoya' ); ?></label></th>
				<td>
					<input id="ludoya-capacity" type="number" min="0" name="capacity" value="<?php echo esc_attr( ludoya_get( $event, 'capacity', '' ) ); ?>" />
					<p class="description"><?php esc_html_e( 'Leave empty for no limit.', 'ludoya' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="ludoya-min-participants"><?php esc_html_e( 'Minimum participants', 'ludoya' ); ?></label></th>
				<td>
					<input id="ludoya-min-participants" type="number" min="0" name="min_participants" value="<?php echo esc_attr( ludoya_get( $event, 'minParticipants', '' ) ); ?>" />
					<p class="description"><?php esc_html_e( 'Leave empty for no minimum.', 'ludoya' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="ludoya-max-reservations"><?php esc_html_e( 'Seats one person may reserve', 'ludoya' ); ?></label></th>
				<td>
					<input id="ludoya-max-reservations" type="number" min="1" name="max_reservations" value="<?php echo esc_attr( ludoya_get( $event, 'maxReservationsPerUser', '' ) ); ?>" />
					<p class="description"><?php esc_html_e( 'Leave empty for no limit.', 'ludoya' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="ludoya-visibility"><?php esc_html_e( 'Visibility', 'ludoya' ); ?></label></th>
				<td>
					<select id="ludoya-visibility" name="visibility">
						<?php foreach ( ludoya_visibilities() as $ludoya_value => $ludoya_label ) : ?>
							<option value="<?php echo esc_attr( $ludoya_value ); ?>" <?php selected( ludoya_get( $event, 'visibility', 'PUBLIC' ), $ludoya_value ); ?>>
								<?php echo esc_html( $ludoya_label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="ludoya-restricted"><?php esc_html_e( 'Attendance', 'ludoya' ); ?></label></th>
				<td>
					<label>
						<input id="ludoya-restricted" type="checkbox" name="restricted_attendance" value="1" <?php checked( ! empty( $event['restrictedAttendance'] ) ); ?> />
						<?php esc_html_e( 'Organisers approve each sign-up', 'ludoya' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Unticked, anybody may sign up.', 'ludoya' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="ludoya-teacher-search"><?php esc_html_e( 'Teaching', 'ludoya' ); ?></label></th>
				<td>
					<div class="ludoya-picker" data-endpoint="ludoya_search_users" data-target="ludoya-teacher-id">
						<input id="ludoya-teacher-search" class="regular-text" type="search" placeholder="<?php esc_attr_e( 'Search members…', 'ludoya' ); ?>" />
						<ul class="ludoya-picker__results"></ul>
						<p class="ludoya-picker__chosen"><?php echo esc_html( ludoya_get( $event, 'teacher.name', '' ) ); ?></p>
					</div>
					<input id="ludoya-teacher-id" type="hidden" name="teacher_user_id" value="<?php echo esc_attr( ludoya_get( $event, 'teacher.id', '' ) ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="ludoya-master-search"><?php esc_html_e( 'Game master', 'ludoya' ); ?></label></th>
				<td>
					<div class="ludoya-picker" data-endpoint="ludoya_search_users" data-target="ludoya-master-id">
						<input id="ludoya-master-search" class="regular-text" type="search" placeholder="<?php esc_attr_e( 'Search members…', 'ludoya' ); ?>" />
						<ul class="ludoya-picker__results"></ul>
						<p class="ludoya-picker__chosen"><?php echo esc_html( ludoya_get( $event, 'master.name', '' ) ); ?></p>
					</div>
					<input id="ludoya-master-id" type="hidden" name="master_user_id" value="<?php echo esc_attr( ludoya_get( $event, 'master.id', '' ) ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="ludoya-image-url"><?php esc_html_e( 'Image URL', 'ludoya' ); ?></label></th>
				<td>
					<input id="ludoya-image-url" class="regular-text" type="url" name="image_url" value="" />
					<p class="description"><?php esc_html_e( 'Ludoya downloads and stores the image. Leave empty to keep the current one.', 'ludoya' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="ludoya-form-template"><?php esc_html_e( 'Sign-up form', 'ludoya' ); ?></label></th>
				<td>
					<select id="ludoya-form-template" name="form_template_id">
						<?php if ( ! $ludoya_is_new ) : ?>
							<option value="keep"><?php esc_html_e( 'Leave as it is', 'ludoya' ); ?></option>
						<?php endif; ?>
						<option value=""><?php esc_html_e( 'No form of its own', 'ludoya' ); ?></option>
						<?php foreach ( $templates as $ludoya_template ) : ?>
							<option value="<?php echo esc_attr( $ludoya_template['id'] ); ?>" <?php selected( ludoya_get( $event_form, 'templateId', '' ), $ludoya_template['id'] ); ?>>
								<?php echo esc_html( $ludoya_template['name'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="description">
						<?php if ( 'OWN' === $ludoya_form_source ) : ?>
							<?php esc_html_e( 'This event has its own questions, set in the Ludoya app. Choosing a template here replaces them.', 'ludoya' ); ?>
						<?php elseif ( 'PARENT' === $ludoya_form_source ) : ?>
							<?php esc_html_e( 'This event currently inherits its parent event\'s questions.', 'ludoya' ); ?>
						<?php else : ?>
							<?php esc_html_e( 'Templates are authored in the Ludoya app; pick one here.', 'ludoya' ); ?>
						<?php endif; ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="ludoya-external-id"><?php esc_html_e( 'Your own id', 'ludoya' ); ?></label></th>
				<td>
					<input id="ludoya-external-id" class="regular-text" type="text" name="external_id" value="" />
					<p class="description"><?php esc_html_e( 'Optional. If this event also exists in another system, put its id here so the two stay matched.', 'ludoya' ); ?></p>
				</td>
			</tr>
			<?php if ( $ludoya_is_new ) : ?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Draft', 'ludoya' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="draft" value="1" />
							<?php esc_html_e( 'Create it hidden, for review before anybody sees it', 'ludoya' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'Publishing later changes the event link, so do not share it before then.', 'ludoya' ); ?></p>
					</td>
				</tr>
			<?php endif; ?>
		</table>

		<?php submit_button( $ludoya_is_new ? __( 'Create event', 'ludoya' ) : __( 'Save changes', 'ludoya' ) ); ?>
	</form>

	<?php if ( ! $ludoya_is_new ) : ?>
		<h2><?php esc_html_e( 'Give this event its own page', 'ludoya' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'For a big one-off you want in your menu, make a page for it and paste this in. Your general events page does not need any of this — it links every event to one shared page instead.', 'ludoya' ); ?>
		</p>
		<p class="ludoya-blocks__code">
			<code><?php echo esc_html( sprintf( '[ludoya_event id="%s"]', $event_id ) ); ?></code>
			<button
				type="button"
				class="button button-small ludoya-copy"
				data-copy="<?php echo esc_attr( sprintf( '[ludoya_event id="%s"]', $event_id ) ); ?>"
			><?php esc_html_e( 'Copy', 'ludoya' ); ?></button>
		</p>

		<h2><?php esc_html_e( 'Participants', 'ludoya' ); ?></h2>
		<p class="description">
			<?php
			printf(
				/* translators: %d: number of people signed up. */
				esc_html__( '%d signed up so far. The public API does not list them by name; open the event in Ludoya to see the roster.', 'ludoya' ),
				(int) ludoya_get( $event, 'participantCount', 0 )
			);
			?>
		</p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ludoya-participant-form">
			<input type="hidden" name="action" value="<?php echo esc_attr( Ludoya_Events_Admin::PARTICIPANT_ACTION ); ?>" />
			<input type="hidden" name="event_id" value="<?php echo esc_attr( $event_id ); ?>" />
			<input type="hidden" name="do" value="add" />
			<?php wp_nonce_field( Ludoya_Events_Admin::PARTICIPANT_ACTION . '_' . $event_id ); ?>

			<div class="ludoya-picker" data-endpoint="ludoya_search_users" data-target="ludoya-participant-id">
				<label for="ludoya-participant-search"><?php esc_html_e( 'Existing Ludoya user', 'ludoya' ); ?></label>
				<input id="ludoya-participant-search" class="regular-text" type="search" placeholder="<?php esc_attr_e( 'Search users…', 'ludoya' ); ?>" />
				<ul class="ludoya-picker__results"></ul>
				<p class="ludoya-picker__chosen"></p>
			</div>
			<input id="ludoya-participant-id" type="hidden" name="user_id" value="" />

			<p><?php esc_html_e( 'Or sign somebody up by name and email — Ludoya creates the account for them to claim later.', 'ludoya' ); ?></p>
			<p>
				<label for="ludoya-participant-name"><?php esc_html_e( 'Name', 'ludoya' ); ?></label>
				<input id="ludoya-participant-name" type="text" name="name" />
				<label for="ludoya-participant-email"><?php esc_html_e( 'Email', 'ludoya' ); ?></label>
				<input id="ludoya-participant-email" type="email" name="email" />
			</p>
			<?php submit_button( __( 'Add participant', 'ludoya' ), 'secondary', 'submit', false ); ?>
		</form>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ludoya-participant-form">
			<input type="hidden" name="action" value="<?php echo esc_attr( Ludoya_Events_Admin::PARTICIPANT_ACTION ); ?>" />
			<input type="hidden" name="event_id" value="<?php echo esc_attr( $event_id ); ?>" />
			<input type="hidden" name="do" value="remove" />
			<?php wp_nonce_field( Ludoya_Events_Admin::PARTICIPANT_ACTION . '_' . $event_id ); ?>

			<div class="ludoya-picker" data-endpoint="ludoya_search_users" data-target="ludoya-remove-id">
				<label for="ludoya-remove-search"><?php esc_html_e( 'Remove somebody', 'ludoya' ); ?></label>
				<input id="ludoya-remove-search" class="regular-text" type="search" placeholder="<?php esc_attr_e( 'Search users…', 'ludoya' ); ?>" />
				<ul class="ludoya-picker__results"></ul>
				<p class="ludoya-picker__chosen"></p>
			</div>
			<input id="ludoya-remove-id" type="hidden" name="user_id" value="" />
			<?php submit_button( __( 'Remove participant', 'ludoya' ), 'delete', 'submit', false ); ?>
		</form>
	<?php endif; ?>
</div>
