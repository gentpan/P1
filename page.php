<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<div class="site-content">
	<main id="main" class="site-main">
		<?php while ( have_posts() ) : the_post(); ?>
			<?php if ( p1_is_about_page() ) : ?>
				<?php p1_render_about_page(); ?>
				<?php if ( comments_open() || get_comments_number() ) : comments_template(); endif; ?>
			<?php elseif ( p1_is_archive_index_page() || p1_is_subscriptions_page() || p1_is_links_page() ) : ?>
				<?php p1_render_special_page_content(); ?>
			<?php else : ?>
			<article <?php post_class( 'post' ); ?> id="post-<?php the_ID(); ?>">
				<?php p1_render_page_header( get_the_title() ); ?>
				<div class="entry entry--reading"><?php the_content(); ?><?php wp_link_pages( array( 'before' => '<nav class="page-links"><strong>' . esc_html( p1_theme_text( 'page_links', __( 'Pages:', 'u5' ) ) ) . '</strong> ', 'after' => '</nav>' ) ); ?></div>
				<?php edit_post_link( esc_html( p1_theme_text( 'edit', __( 'Edit this page', 'u5' ) ) ) ); ?>
			</article>
			<?php $p1_special_page = p1_render_special_page_content(); ?>
			<?php if ( ! $p1_special_page && ( comments_open() || get_comments_number() ) ) : comments_template(); endif; ?>
			<?php endif; ?>
		<?php endwhile; ?>
	</main>
</div>
<?php get_footer(); ?>
