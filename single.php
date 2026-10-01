<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<div class="site-content">
	<main id="main" class="site-main">
		<?php while ( have_posts() ) : the_post(); ?>
			<?php
			$p1_post_id = get_the_ID();
			$p1_post_categories = get_the_category();
			$p1_post_categories = is_array( $p1_post_categories ) ? $p1_post_categories : array();
			$p1_post_tags = get_the_tags();
			$p1_copyright_name = get_the_author() ?: get_bloginfo( 'name' );
			$p1_copyright_date_format = p1_theme_text( 'post_date_format', 'Y年n月j日 H:i' );
			$p1_copyright_updated = max( (int) get_post_time( 'U', true ), (int) get_post_modified_time( 'U', true ) );
			?>
			<article <?php post_class( 'post' ); ?> id="post-<?php the_ID(); ?>"<?php if ( ! is_preview() ) : ?> data-p1-view-track="<?php echo esc_attr( (string) get_the_ID() ); ?>"<?php endif; ?>>
				<header class="single-post-header">
					<div class="single-post-heading">
						<h1 class="single-post-title"><?php echo esc_html( get_the_title() ); ?></h1>
					</div>

				</header>
				<div class="single-post-body">
					<div class="entry entry--reading"><?php p1_render_reading_summary( $p1_post_id ); ?><?php the_content(); ?><?php wp_link_pages( array( 'before' => '<nav class="page-links"><strong>' . esc_html( p1_theme_text( 'page_links', __( 'Pages:', 'p1' ) ) ) . '</strong> ', 'after' => '</nav>' ) ); ?></div>
					<div class="single-post-like"><?php echo p1_like_button_html( $p1_post_id ); ?></div>
				</div>
				<footer class="single-post-footer">
					<section class="single-post-copyright" aria-labelledby="single-post-copyright-title">
						<h2 id="single-post-copyright-title" class="screen-reader-text">文章信息与版权</h2>
						<dl class="single-post-copyright-details">
							<div><dt>本文作者</dt><dd><a href="<?php echo esc_url( get_author_posts_url( (int) get_the_author_meta( 'ID' ) ) ); ?>"><?php echo esc_html( $p1_copyright_name ); ?></a></dd></div>
							<div><dt>首次发布</dt><dd><time datetime="<?php echo esc_attr( get_the_date( DATE_W3C ) ); ?>"><?php echo esc_html( get_the_date( $p1_copyright_date_format ) ); ?></time></dd></div>
							<div><dt>最后更新</dt><dd><time datetime="<?php echo esc_attr( wp_date( DATE_W3C, $p1_copyright_updated ) ); ?>"><?php echo esc_html( wp_date( $p1_copyright_date_format, $p1_copyright_updated ) ); ?></time></dd></div>
						</dl>
						<div class="single-post-copyright-source">
							<span><?php echo esc_html( p1_theme_text( 'original_post_link', '原文链接' ) ); ?></span>
							<a class="single-post-copyright-url" href="<?php echo esc_url( get_permalink( $p1_post_id ) ); ?>" rel="bookmark"><?php echo esc_html( get_permalink( $p1_post_id ) ); ?></a>
						</div>
						<?php if ( $p1_post_categories || $p1_post_tags ) : ?>
							<div class="single-post-taxonomy">
								<?php if ( $p1_post_categories ) : ?>
									<div class="single-post-tags" data-no-tooltip>
										<span>文章分类</span>
										<ul><?php foreach ( $p1_post_categories as $category ) : ?><li><a href="<?php echo esc_url( get_category_link( $category->term_id ) ); ?>"><?php echo esc_html( $category->name ); ?></a></li><?php endforeach; ?></ul>
									</div>
								<?php endif; ?>
							<?php if ( $p1_post_tags ) : ?>
								<div class="single-post-tags">
									<span><?php echo esc_html( p1_theme_text( 'keywords', '关键词' ) ); ?></span>
									<ul>
										<?php foreach ( $p1_post_tags as $p1_tag ) : ?>
											<li><a href="<?php echo esc_url( get_tag_link( $p1_tag->term_id ) ); ?>">#<?php echo esc_html( $p1_tag->name ); ?></a></li>
										<?php endforeach; ?>
									</ul>
								</div>
							<?php endif; ?>
							</div>
						<?php endif; ?>
						<div class="single-post-copyright-note">
								<p>&copy; <?php echo esc_html( get_the_date( 'Y' ) ); ?> <?php echo esc_html( $p1_copyright_name ); ?>。<?php echo esc_html( p1_theme_text( 'copyright_notice', '转载时请注明作者和原文链接。' ) ); ?></p>
						</div>
					</section>
				</footer>
			</article>
			<?php comments_template(); ?>
		<?php endwhile; ?>
	</main>
</div>
<?php get_footer(); ?>
