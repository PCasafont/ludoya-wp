<?php
/**
 * Sign-up form for an event.
 *
 * Copy this file to `ludoya/signup-form.php` in your theme to change it.
 *
 * @package Ludoya
 *
 * @var array      $event        The event.
 * @var array      $questions    The resolved form's questions.
 * @var string     $form_intro   The form's own description.
 * @var bool       $full         Whether the event is at capacity.
 * @var string     $consent_text What the visitor is agreeing to.
 * @var array|null $notice       Outcome of a previous submission: status and message.
 */

defined( 'ABSPATH' ) || exit;

$ludoya_labels = array(
	'EMAIL'         => __( 'Email', 'ludoya' ),
	'GENDER'        => __( 'Gender', 'ludoya' ),
	'DATE_OF_BIRTH' => __( 'Date of birth', 'ludoya' ),
	'PHONE_NUMBER'  => __( 'Phone number', 'ludoya' ),
);
?>
<div class="ludoya ludoya-signup">
	<?php if ( $notice ) : ?>
		<p class="ludoya-notice ludoya-notice--<?php echo esc_attr( $notice[0] ); ?>"><?php echo esc_html( $notice[1] ); ?></p>
	<?php endif; ?>

	<?php if ( $full ) : ?>
		<p class="ludoya-notice"><?php esc_html_e( 'This event is full.', 'ludoya' ); ?></p>
	<?php else : ?>
		<h3 class="ludoya-subheading"><?php esc_html_e( 'Sign up', 'ludoya' ); ?></h3>

		<?php if ( $form_intro ) : ?>
			<p class="ludoya-signup__intro"><?php echo esc_html( $form_intro ); ?></p>
		<?php endif; ?>

		<form class="ludoya-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="ludoya_signup" />
			<input type="hidden" name="event_id" value="<?php echo esc_attr( isset( $event['id'] ) ? $event['id'] : '' ); ?>" />
			<?php wp_nonce_field( 'ludoya_signup' ); ?>

			<p class="ludoya-field">
				<label for="ludoya-name"><?php esc_html_e( 'Name', 'ludoya' ); ?> <span aria-hidden="true">*</span></label>
				<input id="ludoya-name" type="text" name="ludoya_name" required />
			</p>
			<p class="ludoya-field">
				<label for="ludoya-email"><?php esc_html_e( 'Email', 'ludoya' ); ?> <span aria-hidden="true">*</span></label>
				<input id="ludoya-email" type="email" name="ludoya_email" required />
			</p>

			<?php foreach ( $questions as $ludoya_index => $ludoya_question ) : ?>
				<?php
				$ludoya_qid   = isset( $ludoya_question['id'] ) ? $ludoya_question['id'] : '';
				$ludoya_type  = isset( $ludoya_question['type'] ) ? $ludoya_question['type'] : 'TEXT';
				$ludoya_field = 'ludoya_answers[' . $ludoya_qid . ']';
				$ludoya_dom   = 'ludoya-q-' . $ludoya_index;
				$ludoya_req   = ! empty( $ludoya_question['required'] );
				$ludoya_label = isset( $ludoya_question['label'] ) ? $ludoya_question['label'] : '';

				if ( 'USER_FIELD' === $ludoya_type ) {
					$ludoya_user_field = isset( $ludoya_question['userField'] ) ? $ludoya_question['userField'] : '';
					// The email address is already asked for above; asking twice only invites a mismatch.
					if ( 'EMAIL' === $ludoya_user_field ) {
						continue;
					}
					if ( '' === $ludoya_label ) {
						$ludoya_label = isset( $ludoya_labels[ $ludoya_user_field ] ) ? $ludoya_labels[ $ludoya_user_field ] : $ludoya_user_field;
					}
				}
				?>
				<div class="ludoya-field">
					<label for="<?php echo esc_attr( $ludoya_dom ); ?>">
						<?php echo esc_html( $ludoya_label ); ?>
						<?php if ( $ludoya_req ) : ?><span aria-hidden="true">*</span><?php endif; ?>
					</label>

					<?php if ( 'PARAGRAPH' === $ludoya_type ) : ?>
						<textarea id="<?php echo esc_attr( $ludoya_dom ); ?>" name="<?php echo esc_attr( $ludoya_field ); ?>" rows="4" <?php echo $ludoya_req ? 'required' : ''; ?>></textarea>

					<?php elseif ( 'SINGLE_CHOICE' === $ludoya_type ) : ?>
						<select id="<?php echo esc_attr( $ludoya_dom ); ?>" name="<?php echo esc_attr( $ludoya_field ); ?>" <?php echo $ludoya_req ? 'required' : ''; ?>>
							<option value=""><?php esc_html_e( 'Choose…', 'ludoya' ); ?></option>
							<?php foreach ( ludoya_get( $ludoya_question, 'options', array() ) as $ludoya_option ) : ?>
								<option value="<?php echo esc_attr( $ludoya_option ); ?>"><?php echo esc_html( $ludoya_option ); ?></option>
							<?php endforeach; ?>
						</select>

					<?php elseif ( 'MULTIPLE_CHOICE' === $ludoya_type ) : ?>
						<?php
						// `required` cannot sit on the boxes themselves — the browser would demand
						// every one. The group is marked instead and a small script holds the
						// validity until at least one is ticked.
						if ( $ludoya_req ) {
							wp_enqueue_script( 'ludoya' );
						}
						?>
						<span
							class="ludoya-choices"
							<?php if ( $ludoya_req ) : ?>
								data-required="1"
								data-message="<?php esc_attr_e( 'Choose at least one option.', 'ludoya' ); ?>"
							<?php endif; ?>
						>
							<?php foreach ( ludoya_get( $ludoya_question, 'options', array() ) as $ludoya_i => $ludoya_option ) : ?>
								<label class="ludoya-choice">
									<input type="checkbox" name="<?php echo esc_attr( $ludoya_field ); ?>[<?php echo (int) $ludoya_i; ?>]" value="<?php echo esc_attr( $ludoya_option ); ?>" />
									<?php echo esc_html( $ludoya_option ); ?>
								</label>
							<?php endforeach; ?>
						</span>

					<?php elseif ( 'LINEAR_SCALE' === $ludoya_type ) : ?>
						<span class="ludoya-scale">
							<?php if ( ! empty( $ludoya_question['minLabel'] ) ) : ?>
								<span class="ludoya-scale__label"><?php echo esc_html( $ludoya_question['minLabel'] ); ?></span>
							<?php endif; ?>
							<?php for ( $ludoya_v = (int) ludoya_get( $ludoya_question, 'min', 1 ); $ludoya_v <= (int) ludoya_get( $ludoya_question, 'max', 5 ); $ludoya_v++ ) : ?>
								<label class="ludoya-choice">
									<input type="radio" name="<?php echo esc_attr( $ludoya_field ); ?>" value="<?php echo (int) $ludoya_v; ?>" <?php echo $ludoya_req ? 'required' : ''; ?> />
									<?php echo (int) $ludoya_v; ?>
								</label>
							<?php endfor; ?>
							<?php if ( ! empty( $ludoya_question['maxLabel'] ) ) : ?>
								<span class="ludoya-scale__label"><?php echo esc_html( $ludoya_question['maxLabel'] ); ?></span>
							<?php endif; ?>
						</span>

					<?php elseif ( 'MULTIPLE_CHOICE_GRID' === $ludoya_type ) : ?>
						<table class="ludoya-grid">
							<thead>
								<tr>
									<th></th>
									<?php foreach ( ludoya_get( $ludoya_question, 'columns', array() ) as $ludoya_column ) : ?>
										<th scope="col"><?php echo esc_html( $ludoya_column ); ?></th>
									<?php endforeach; ?>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( ludoya_get( $ludoya_question, 'rows', array() ) as $ludoya_row_index => $ludoya_row ) : ?>
									<tr>
										<th scope="row"><?php echo esc_html( $ludoya_row ); ?></th>
										<?php foreach ( ludoya_get( $ludoya_question, 'columns', array() ) as $ludoya_column ) : ?>
											<td>
												<input
													type="radio"
													name="<?php echo esc_attr( $ludoya_field ); ?>[<?php echo (int) $ludoya_row_index; ?>]"
													value="<?php echo esc_attr( $ludoya_column ); ?>"
													aria-label="<?php echo esc_attr( $ludoya_row . ' — ' . $ludoya_column ); ?>"
													<?php echo $ludoya_req ? 'required' : ''; ?>
												/>
											</td>
										<?php endforeach; ?>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>

					<?php elseif ( 'USER_FIELD' === $ludoya_type && 'DATE_OF_BIRTH' === ludoya_get( $ludoya_question, 'userField', '' ) ) : ?>
						<input id="<?php echo esc_attr( $ludoya_dom ); ?>" type="date" name="<?php echo esc_attr( $ludoya_field ); ?>" <?php echo $ludoya_req ? 'required' : ''; ?> />

					<?php else : ?>
						<input id="<?php echo esc_attr( $ludoya_dom ); ?>" type="text" name="<?php echo esc_attr( $ludoya_field ); ?>" <?php echo $ludoya_req ? 'required' : ''; ?> />
					<?php endif; ?>
				</div>
			<?php endforeach; ?>

			<p class="ludoya-field ludoya-field--consent">
				<label>
					<input type="checkbox" name="ludoya_consent" value="1" required />
					<?php
					echo $consent_text
						? wp_kses_post( $consent_text )
						: esc_html__( 'I agree that my name and email are sent to Ludoya to register my place, and that a Ludoya account is created for me.', 'ludoya' );
					?>
				</label>
			</p>

			<p class="ludoya-hp" aria-hidden="true">
				<label for="ludoya-website"><?php esc_html_e( 'Leave this field empty', 'ludoya' ); ?></label>
				<input id="ludoya-website" type="text" name="ludoya_website" tabindex="-1" autocomplete="off" />
			</p>

			<p><button class="ludoya-button" type="submit"><?php esc_html_e( 'Sign me up', 'ludoya' ); ?></button></p>
		</form>
	<?php endif; ?>
</div>
