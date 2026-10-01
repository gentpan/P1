<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?> data-p1-runtime="<?php echo esc_attr( p1_runtime_config() ); ?>" data-color-scheme="<?php echo esc_attr( p1_get_color_scheme() ); ?>" data-heading-font="<?php echo esc_attr( p1_sanitize_font_choice( p1_setting( 'heading_font' ) ) ); ?>" data-body-font="<?php echo esc_attr( p1_sanitize_font_choice( p1_setting( 'body_font' ) ) ); ?>">
<?php wp_body_open(); ?>
<a class="skip-link screen-reader-text" href="#main"><?php echo esc_html( p1_theme_text( 'skip_to_content', __( 'Skip to content', 'p1' ) ) ); ?></a>
<div class="site">
	<header id="header" class="site-header">
		<?php
		$p1_header_background_url = p1_header_background_url();
		$p1_header_note           = p1_latest_header_note();
		?>
		<div class="site-masthead<?php echo $p1_header_background_url ? ' has-header-image' : ''; ?>"<?php if ( $p1_header_background_url ) : ?> style="background-image: url('<?php echo esc_url( $p1_header_background_url ); ?>');"<?php endif; ?>>
			<div class="site-masthead-inner">
				<div class="site-branding">
					<div class="site-branding-copy">
						<div class="site-branding-identity">
							<?php if ( is_front_page() && is_home() ) : ?>
								<h1 class="site-title"><a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></a></h1>
							<?php else : ?>
								<p class="site-title"><a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></a></p>
							<?php endif; ?>
						</div>
						<p class="site-description"><?php echo esc_html( get_bloginfo( 'description' ) ); ?></p>
					</div>
					<?php if ( has_custom_logo() ) : ?>
						<div class="site-logo"><?php the_custom_logo(); ?></div>
					<?php endif; ?>
				</div>
				<div class="site-header-tools">
					<?php p1_render_random_post_button( true ); ?>
					<?php p1_render_header_menu(); ?>
				</div>
				<?php if ( $p1_header_note ) : ?>
					<div class="site-header-note">
						<i class="site-header-note-icon fa-thin fa-scarecrow" aria-hidden="true"></i>
						<span class="site-header-note-text"><?php echo esc_html( $p1_header_note['text'] ); ?></span>
						<time class="site-header-note-time" datetime="<?php echo esc_attr( wp_date( DATE_W3C, $p1_header_note['timestamp'] ) ); ?>" title="<?php echo esc_attr( wp_date( p1_theme_text( 'time_full_format', 'Y-m-d H:i' ), $p1_header_note['timestamp'] ) ); ?>"><?php echo esc_html( p1_header_note_time_label( $p1_header_note['timestamp'] ) ); ?></time>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
// Header discovery dialog.
$p1_menu_comments = get_comments( array( 'number' => 18, 'status' => 'approve', 'type' => 'comment', 'post_status' => 'publish', 'orderby' => 'comment_date_gmt', 'order' => 'DESC' ) );
// A published password-protected post must not leak comment excerpts here.
$p1_menu_comments = array_slice( array_values( array_filter( $p1_menu_comments, static function ( WP_Comment $comment ): bool {
	$post = get_post( $comment->comment_post_ID );
	return $post && is_post_publicly_viewable( $post ) && '' === $post->post_password;
} ) ), 0, 6 );
$p1_menu_categories = get_categories( array( 'hide_empty' => false, 'orderby' => 'name', 'order' => 'ASC' ) );
$p1_menu_categories = is_wp_error( $p1_menu_categories ) ? array() : $p1_menu_categories;
$p1_menu_tags = get_tags( array( 'hide_empty' => false, 'orderby' => 'name', 'order' => 'ASC' ) );
$p1_menu_tags = is_wp_error( $p1_menu_tags ) ? array() : $p1_menu_tags;
$p1_menu_calendar = get_calendar( array( 'initial' => true, 'display' => false, 'post_type' => 'post' ) );
$p1_heatmap = p1_post_publication_heatmap();
?>
<dialog id="p1-menu-dialog" class="p1-menu-dialog" aria-labelledby="p1-menu-dialog-title">
	<div class="p1-menu-dialog-inner">
		<div class="p1-menu-dialog-heading">
			<h2 id="p1-menu-dialog-title"><?php echo esc_html( p1_theme_text( 'menu_panel', __( 'Quick menu', 'p1' ) ) ); ?></h2>
			<button class="p1-menu-dialog-close" type="button" aria-label="<?php echo esc_attr( p1_theme_text( 'menu_close', __( 'Close menu', 'p1' ) ) ); ?>">&times;</button>
		</div>
		<div class="p1-menu-dialog-grid">
			<section class="p1-menu-section" aria-labelledby="p1-menu-comments-title">
				<h3 id="p1-menu-comments-title"><?php echo esc_html( p1_theme_text( 'recent_comments', __( 'Recent comments', 'p1' ) ) ); ?></h3>
				<?php if ( $p1_menu_comments ) : ?>
					<ul class="p1-menu-comment-list">
						<?php foreach ( $p1_menu_comments as $p1_comment ) : ?>
							<li><a href="<?php echo esc_url( get_comment_link( $p1_comment ) ); ?>">
								<?php echo wp_kses_post( get_avatar( $p1_comment, 32, '', '', array( 'loading' => 'lazy' ) ) ); ?>
								<span><strong><?php echo esc_html( get_comment_author( $p1_comment ) ); ?></strong><small><?php echo esc_html( wp_trim_words( wp_strip_all_tags( get_comment_text( $p1_comment ) ), 12 ) ); ?></small></span>
							</a></li>
						<?php endforeach; ?>
					</ul>
				<?php else : ?>
					<p class="p1-menu-empty"><?php echo esc_html( p1_theme_text( 'footer_empty_comments', __( 'No comments yet.', 'p1' ) ) ); ?></p>
				<?php endif; ?>
			</section>
			<section class="p1-menu-section" aria-labelledby="p1-menu-categories-title">
				<h3 id="p1-menu-categories-title"><?php echo esc_html( p1_theme_text( 'menu_all_categories', __( 'All categories', 'p1' ) ) ); ?></h3>
				<?php if ( $p1_menu_categories ) : ?>
					<ul class="p1-menu-taxonomy-list">
						<?php foreach ( $p1_menu_categories as $p1_category ) : ?>
							<li><a href="<?php echo esc_url( get_category_link( $p1_category ) ); ?>"><?php echo esc_html( $p1_category->name ); ?><span><?php echo esc_html( number_format_i18n( $p1_category->count ) ); ?></span></a></li>
						<?php endforeach; ?>
					</ul>
				<?php else : ?>
					<p class="p1-menu-empty"><?php echo esc_html( p1_theme_text( 'menu_empty_categories', __( 'No categories yet.', 'p1' ) ) ); ?></p>
				<?php endif; ?>
			</section>
			<section class="p1-menu-section" aria-labelledby="p1-menu-tags-title">
				<h3 id="p1-menu-tags-title"><?php echo esc_html( p1_theme_text( 'menu_tags', __( 'Tags', 'p1' ) ) ); ?></h3>
				<?php if ( $p1_menu_tags ) : ?>
					<ul class="p1-menu-tag-list">
						<?php foreach ( $p1_menu_tags as $p1_tag ) : ?>
							<li><a href="<?php echo esc_url( get_tag_link( $p1_tag ) ); ?>"><?php echo esc_html( $p1_tag->name ); ?></a></li>
						<?php endforeach; ?>
					</ul>
				<?php else : ?>
					<p class="p1-menu-empty"><?php echo esc_html( p1_theme_text( 'menu_empty_tags', __( 'No tags yet.', 'p1' ) ) ); ?></p>
				<?php endif; ?>
			</section>
			<section class="p1-menu-section p1-menu-calendar" aria-labelledby="p1-menu-calendar-title">
				<h3 id="p1-menu-calendar-title"><?php echo esc_html( p1_theme_text( 'menu_calendar', __( 'Calendar', 'p1' ) ) ); ?></h3>
				<?php echo $p1_menu_calendar ? wp_kses_post( $p1_menu_calendar ) : esc_html( p1_theme_text( 'footer_empty_posts', __( 'No posts yet.', 'p1' ) ) ); ?>
			</section>
			<section class="p1-menu-section p1-menu-heatmap" aria-labelledby="p1-menu-heatmap-title">
				<div class="p1-menu-heatmap-heading"><h3 id="p1-menu-heatmap-title"><?php echo esc_html( p1_theme_text( 'menu_heatmap', __( 'Publishing activity', 'p1' ) ) ); ?></h3><p><?php echo esc_html( sprintf( p1_theme_text( 'menu_heatmap_summary', __( '%s posts in the past year', 'p1' ) ), number_format_i18n( $p1_heatmap['total'] ) ) ); ?></p></div>
				<div class="p1-heatmap-scroll"><div class="p1-heatmap-board">
					<div class="p1-heatmap-weekdays" aria-hidden="true"><?php for ( $p1_weekday = 0; $p1_weekday < 7; $p1_weekday++ ) : ?><span><?php echo esc_html( wp_date( 'D', $p1_heatmap['grid_start']->modify( '+' . $p1_weekday . ' days' )->getTimestamp() ) ); ?></span><?php endfor; ?></div>
					<div class="p1-heatmap-plot"><div class="p1-heatmap-months" aria-hidden="true">
						<?php $p1_previous_month_key = ''; ?>
						<?php for ( $p1_week = $p1_heatmap['grid_start']; $p1_week <= $p1_heatmap['grid_end']; $p1_week = $p1_week->modify( '+7 days' ) ) : ?>
							<?php $p1_week_end = $p1_week->modify( '+6 days' ); $p1_month_key = $p1_week_end->format( 'Y-m' ); ?>
							<span><?php echo $p1_month_key !== $p1_previous_month_key ? esc_html( wp_date( 'M', $p1_week_end->getTimestamp() ) ) : ''; ?></span>
							<?php $p1_previous_month_key = $p1_month_key; ?>
						<?php endfor; ?>
					</div><div class="p1-heatmap-grid" role="img" aria-label="<?php echo esc_attr( sprintf( p1_theme_text( 'menu_heatmap_summary', __( '%s posts in the past year', 'p1' ) ), number_format_i18n( $p1_heatmap['total'] ) ) ); ?>">
					<?php for ( $p1_day = $p1_heatmap['grid_start']; $p1_day <= $p1_heatmap['grid_end']; $p1_day = $p1_day->modify( '+1 day' ) ) : ?>
						<?php
						$p1_key = $p1_day->format( 'Y-m-d' );
						$p1_in_range = $p1_day >= $p1_heatmap['first_day'] && $p1_day <= $p1_heatmap['today'];
						$p1_count = $p1_in_range ? ( $p1_heatmap['counts'][ $p1_key ] ?? 0 ) : 0;
						$p1_level = min( 4, $p1_count );
						$p1_day_label = sprintf( p1_theme_text( 'menu_heatmap_day', __( '%1$s: %2$s posts', 'p1' ) ), wp_date( p1_theme_text( 'time_date_format', 'Y-m-d' ), $p1_day->getTimestamp() ), number_format_i18n( $p1_count ) );
						?>
						<span class="p1-heatmap-cell p1-heatmap-level-<?php echo esc_attr( (string) $p1_level ); ?><?php echo $p1_in_range ? '' : ' is-outside'; ?>"<?php echo $p1_in_range ? ' title="' . esc_attr( $p1_day_label ) . '"' : ''; ?> aria-hidden="true"></span>
					<?php endfor; ?>
				</div></div></div></div>
				<div class="p1-heatmap-legend"><span><?php echo esc_html( p1_theme_text( 'menu_heatmap_less', __( 'Less', 'p1' ) ) ); ?></span><?php for ( $p1_level = 0; $p1_level <= 4; $p1_level++ ) : ?><span class="p1-heatmap-cell p1-heatmap-level-<?php echo esc_attr( (string) $p1_level ); ?>" aria-hidden="true"></span><?php endfor; ?><span><?php echo esc_html( p1_theme_text( 'menu_heatmap_more', __( 'More', 'p1' ) ) ); ?></span></div>
			</section>
		</div>
	</div>
</dialog>
	</header>
		<nav id="navigation" class="primary-navigation" aria-label="<?php echo esc_attr( p1_theme_text( 'primary_menu', __( 'Primary menu', 'p1' ) ) ); ?>">
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'primary',
					'container'      => false,
					'menu_class'     => 'menu',
					'fallback_cb'    => 'p1_primary_menu_fallback',
				)
			);
			?>
			<div class="navigation-tools">
					<button class="navigation-search-toggle" type="button" aria-expanded="false" aria-controls="p1-navigation-search-form" aria-label="<?php echo esc_attr( p1_theme_text( 'search_submit', __( 'Search', 'p1' ) ) ); ?>"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i></button>
					<form id="p1-navigation-search-form" role="search" method="get" class="navigation-search-form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
						<label class="screen-reader-text" for="p1-navigation-search"><?php echo esc_html( p1_theme_text( 'search_label', __( 'Search for:', 'p1' ) ) ); ?></label>
						<input id="p1-navigation-search" class="navigation-search-input" type="search" name="s" value="<?php echo esc_attr( get_search_query() ); ?>" placeholder="<?php echo esc_attr( p1_theme_text( 'search_placeholder', __( 'Search …', 'p1' ) ) ); ?>">
						<button class="navigation-search-submit" type="submit" aria-label="<?php echo esc_attr( p1_theme_text( 'search_submit', __( 'Search', 'p1' ) ) ); ?>"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i></button>
					</form>
			</div>
		</nav>
