<?php
/**
 * Events list screen.
 *
 * @package Ludoya
 *
 * @var Ludoya_Events_Table $table The prepared list table.
 * @var WP_Error|null       $error What went wrong, if anything.
 * @var string              $view  upcoming, past or all.
 */

defined( 'ABSPATH' ) || exit;

$ludoya_views = array(
	'upcoming' => __( 'Upcoming', 'ludoya' ),
	'past'     => __( 'Past', 'ludoya' ),
	'all'      => __( 'All', 'ludoya' ),
);
?>
<div class="wrap ludoya-admin">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Ludoya events', 'ludoya' ); ?></h1>
	<a class="page-title-action" href="<?php echo esc_url( admin_url( 'admin.php?page=ludoya-event-edit' ) ); ?>">
		<?php esc_html_e( 'Add event', 'ludoya' ); ?>
	</a>
	<hr class="wp-header-end" />

	<?php if ( ! Ludoya_Client::is_configured() ) : ?>
		<div class="notice notice-warning">
			<p>
				<?php esc_html_e( 'No API key yet.', 'ludoya' ); ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=ludoya-settings' ) ); ?>"><?php esc_html_e( 'Add one in settings.', 'ludoya' ); ?></a>
			</p>
		</div>
	<?php elseif ( $error ) : ?>
		<div class="notice notice-error"><p><?php echo esc_html( $error->get_error_message() ); ?></p></div>
	<?php endif; ?>

	<ul class="subsubsub">
		<?php $ludoya_last = array_key_last( $ludoya_views ); ?>
		<?php foreach ( $ludoya_views as $ludoya_key => $ludoya_label ) : ?>
			<li>
				<a
					href="<?php echo esc_url( admin_url( 'admin.php?page=ludoya-events&view=' . $ludoya_key ) ); ?>"
					class="<?php echo $view === $ludoya_key ? 'current' : ''; ?>"
				><?php echo esc_html( $ludoya_label ); ?></a>
				<?php echo $ludoya_key === $ludoya_last ? '' : ' |'; ?>
			</li>
		<?php endforeach; ?>
	</ul>

	<form method="get">
		<input type="hidden" name="page" value="ludoya-events" />
		<input type="hidden" name="view" value="<?php echo esc_attr( $view ); ?>" />
		<?php $table->display(); ?>
	</form>
</div>
