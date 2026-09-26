<?php
/** Required classic-theme fallback; also renders the posts list. @package P1 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<div class="site-content">
	<main id="main" class="site-main">
		<?php if ( have_posts() ) : ?>
			<?php while ( have_posts() ) : the_post(); ?>
				<?php
$p1_summary = p1_post_card_summary( get_the_ID() );
$p1_layout     = p1_sanitize_card_image_layout( p1_setting( 'card_image_layout' ) );
$p1_has_image  = 'none' !== $p1_layout && has_post_thumbnail();
$p1_image_size = 'banner' === $p1_layout ? 'large' : 'medium_large';
$p1_image_hint = 'banner' === $p1_layout ? '(max-width: 960px) 100vw, 896px' : '112px';
$p1_timestamp  = get_post_timestamp();
$p1_timestamp  = false === $p1_timestamp ? time() : $p1_timestamp;
$p1_classes    = array( 'post', 'post-card', 'post-card--' . $p1_layout );
if ( ! $p1_has_image ) {
	$p1_classes[] = 'post-card--text-only';
}
?>
<article <?php post_class( $p1_classes ); ?> id="post-<?php the_ID(); ?>">
	<?php if ( $p1_has_image ) : ?>
		<a class="post-card-image" href="<?php echo esc_url( get_permalink() ); ?>" tabindex="-1" aria-hidden="true">
			<?php the_post_thumbnail( $p1_image_size, array( 'loading' => 'lazy', 'decoding' => 'async', 'sizes' => $p1_image_hint ) ); ?>
		</a>
	<?php endif; ?>
	<div class="post-card-body">
		<header class="post-card-heading">
			<div class="post-card-title-group">
				<h2><a href="<?php echo esc_url( get_permalink() ); ?>" rel="bookmark"><?php echo esc_html( get_the_title() ); ?></a></h2>
			</div>
			<?php echo p1_post_card_categories_html( get_the_ID() ); ?>
		</header>
		<?php if ( '' !== $p1_summary ) : ?>
			<div class="post-card-excerpt"><p><?php echo esc_html( $p1_summary ); ?></p></div>
		<?php endif; ?>
		<footer class="post-card-meta" aria-label="<?php echo esc_attr( p1_theme_text( 'post_details', __( 'Post details', 'u5' ) ) ); ?>">
			<div class="post-card-meta-left">
				<span class="post-card-meta-item post-card-time" title="<?php echo esc_attr( wp_date( p1_theme_text( 'time_full_format', 'Y-m-d H:i' ), $p1_timestamp ) ); ?>"><i class="fa-solid fa-clock" aria-hidden="true"></i><time datetime="<?php echo esc_attr( wp_date( DATE_W3C, $p1_timestamp ) ); ?>"><?php echo esc_html( p1_post_time_label( $p1_timestamp ) ); ?></time></span>
				<?php echo p1_post_card_reading_meta_html( get_the_ID() ); ?>
				<span class="post-card-meta-item"><i class="fa-solid fa-eye" aria-hidden="true"></i><span data-p1-view-count="<?php echo esc_attr( (string) get_the_ID() ); ?>"><?php echo esc_html( number_format_i18n( p1_get_post_views( get_the_ID() ) ) ); ?></span> <?php echo esc_html( p1_theme_text( 'views', __( 'views', 'u5' ) ) ); ?></span>
			</div>
			<div class="post-card-meta-actions">
				<a class="post-card-comments" data-p1-comment-post="<?php echo esc_attr( (string) get_the_ID() ); ?>" href="<?php echo esc_url( get_comments_link() ); ?>" aria-label="<?php echo esc_attr( wp_strip_all_tags( p1_comments_label() ) ); ?>" title="<?php echo esc_attr( wp_strip_all_tags( p1_comments_label() ) ); ?>"><i class="fa-regular fa-comment" aria-hidden="true"></i><span><?php echo esc_html( number_format_i18n( (int) get_comments_number() ) ); ?></span></a>
				<?php echo p1_like_button_html( get_the_ID() ); ?>
			</div>
		</footer>
	</div>
</article>
			<?php endwhile; ?>
			<?php p1_posts_load_more(); ?>
		<?php else : ?>
			<?php p1_render_page_header( p1_theme_text( 'not_found', __( 'Not found', 'u5' ) ) ); ?>
			<p><?php echo esc_html( p1_theme_text( 'not_found_description', __( 'Sorry, nothing was found here.', 'u5' ) ) ); ?></p>
			<?php get_search_form(); ?>
		<?php endif; ?>
	</main>
</div>
<?php if ( is_home() ) : p1_render_home_discovery(); endif; ?>
<?php get_footer(); ?>
