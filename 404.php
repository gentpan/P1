<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<div class="site-content">
	<main id="main" class="site-main">
		<?php p1_render_page_header( p1_theme_text( 'error_404', __( 'Page not found', 'p1' ) ) ); ?>
		<p><?php echo esc_html( p1_theme_text( 'error_404_description', __( 'The page you requested could not be found. Try a search instead.', 'p1' ) ) ); ?></p>
		<?php get_search_form(); ?>
	</main>
</div>
<?php get_footer(); ?>
