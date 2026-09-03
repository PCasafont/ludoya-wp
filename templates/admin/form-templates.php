<?php
/**
 * Form templates screen: what each reusable sign-up form asks.
 *
 * Authoring happens in the Ludoya app, which has the question editor; this screen is for seeing
 * what is available to attach to an event, and for clearing out templates nobody uses.
 *
 * @package Ludoya
 *
 * @var array         $templates The organisation's form templates.
 * @var WP_Error|null $error     What went wrong, if anything.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap ludoya-admin">
	<h1><?php esc_html_e( 'Form templates', 'ludoya' ); ?></h1>

	<?php if ( $error ) : ?>
		<div class="notice notice-error"><p><?php echo esc_html( $error->get_error_message() ); ?></p></div>
	<?php endif; ?>

	<p class="description">
		<?php esc_html_e( 'A template is a set of sign-up questions shared by several events. Editing one changes every event that uses it.', 'ludoya' ); ?>
	</p>

	<?php if ( empty( $templates ) ) : ?>
		<p><?php esc_html_e( 'No templates yet. Create one in the Ludoya app, then attach it to an event here.', 'ludoya' ); ?></p>
	<?php else : ?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Name', 'ludoya' ); ?></th>
					<th><?php esc_html_e( 'Questions', 'ludoya' ); ?></th>
					<th><?php esc_html_e( 'Updated', 'ludoya' ); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $templates as $ludoya_template ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $ludoya_template['name'] ); ?></strong></td>
						<td>
							<?php
							$ludoya_questions = ludoya_get( $ludoya_template, 'form.questions', array() );
							$ludoya_labels    = array();
							foreach ( $ludoya_questions as $ludoya_question ) {
								$ludoya_labels[] = isset( $ludoya_question['label'] ) && $ludoya_question['label']
									? $ludoya_question['label']
									: $ludoya_question['type'];
							}
							echo esc_html( implode( ' · ', $ludoya_labels ) );
							?>
						</td>
						<td><?php echo esc_html( ludoya_format_date( ludoya_get( $ludoya_template, 'updatedAt' ) ) ); ?></td>
						<td>
							<a
								class="ludoya-delete-template"
								href="<?php
								echo esc_url(
									wp_nonce_url(
										add_query_arg(
											array(
												'action'   => Ludoya_Events_Admin::FORM_ACTION,
												'template' => rawurlencode( $ludoya_template['id'] ),
											),
											admin_url( 'admin-post.php' )
										),
										Ludoya_Events_Admin::FORM_ACTION . '_' . $ludoya_template['id']
									)
								);
								?>"
							><?php esc_html_e( 'Delete', 'ludoya' ); ?></a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<p class="description">
			<?php esc_html_e( 'Deleting detaches the template from any event still using it; those events end up asking nothing.', 'ludoya' ); ?>
		</p>
	<?php endif; ?>
</div>
