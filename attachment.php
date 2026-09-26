<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<div class="site-content">
	<main id="main" class="site-main">
		<?php while ( have_posts() ) : the_post(); ?>
			<article <?php post_class( 'post' ); ?> id="post-<?php the_ID(); ?>">
				<?php p1_render_page_header( get_the_title() ); ?>
				<figure class="attachment-media">
					<?php if ( wp_attachment_is_image() ) : ?>
					<a href="<?php echo esc_url( wp_get_original_image_url( get_the_ID() ) ?: wp_get_attachment_url() ); ?>"><?php echo wp_get_attachment_image( get_the_ID(), 'large' ); ?></a>
					<?php else : ?>
						<a href="<?php echo esc_url( wp_get_attachment_url() ); ?>" download><?php echo esc_html( get_the_title() ); ?></a>
					<?php endif; ?>
					<?php if ( get_the_excerpt() ) : ?><figcaption><?php the_excerpt(); ?></figcaption><?php endif; ?>
				</figure>
				<div class="entry"><?php the_content(); ?></div>
				<?php if ( wp_get_post_parent_id( get_the_ID() ) ) : ?>
				<p><a href="<?php echo esc_url( get_permalink( wp_get_post_parent_id( get_the_ID() ) ?: get_the_ID() ) ); ?>"><?php echo esc_html( p1_theme_text( 'attachment_parent', __( 'View the associated post', 'u5' ) ) ); ?></a></p>
				<?php endif; ?>
			</article>
			<?php comments_template(); ?>
		<?php endwhile; ?>
	</main>
</div>
<?php get_footer(); ?>
