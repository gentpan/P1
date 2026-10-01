<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$p1_footer_sprite = get_theme_file_uri( 'assets/icons/fontawesome-used.svg' );
$p1_footer_rss_url = p1_sanitize_external_url( p1_setting( 'footer_rss_url' ) );
$p1_footer_x_url = p1_sanitize_external_url( p1_setting( 'footer_x_url' ) );
?>
	<footer id="footer" class="site-info">
		<div class="copyright">&copy; <?php echo esc_html( wp_date( 'Y' ) ); ?> <a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></a>. All rights reserved.</div>
		<div class="p1-footer-stats" data-p1-footer-stats aria-label="访问统计">
			<span title="从启用统计起记录的全站页面浏览次数"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>总浏览量 <b data-p1-footer-views>—</b></span>
			<span title="最近 5 分钟活跃的浏览器数量，多标签页合并估算"><i class="p1-online-dot" aria-hidden="true"></i><b data-p1-footer-online>—</b> 人在线</span>
			<span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 10c0 5-7 11-7 11S5 15 5 10a7 7 0 1 1 14 0Z"/><circle cx="12" cy="10" r="2.5"/></svg>最近访客来自 <span data-p1-footer-location>—</span></span>
		</div>
		<nav class="footer-social" aria-label="<?php echo esc_attr( p1_theme_text( 'footer_social_links', __( 'Subscription and social links', 'p1' ) ) ); ?>">
			<?php if ( p1_setting( 'footer_rss_enabled' ) ) : ?>
				<?php $p1_rss_copy_label = p1_theme_text( 'rss_copy', __( 'Copy the RSS feed link', 'p1' ) ); ?>
				<a class="footer-social-link footer-rss-button" href="<?php echo esc_url( $p1_footer_rss_url ?: get_feed_link() ); ?>" aria-label="<?php echo esc_attr( $p1_rss_copy_label ); ?>" title="<?php echo esc_attr( $p1_rss_copy_label ); ?>" data-copied-message="<?php echo esc_attr( p1_theme_text( 'rss_copied', __( 'RSS link copied', 'p1' ) ) ); ?>" data-copy-failed-message="<?php echo esc_attr( p1_theme_text( 'rss_copy_failed', __( 'Could not copy the RSS link', 'p1' ) ) ); ?>"><svg class="rss-icon" aria-hidden="true" focusable="false"><use href="<?php echo esc_url( $p1_footer_sprite . '#rss' ); ?>"></use></svg><svg class="rss-check-icon" aria-hidden="true" focusable="false"><use href="<?php echo esc_url( $p1_footer_sprite . '#check' ); ?>"></use></svg></a>
			<?php endif; ?>
			<?php if ( $p1_footer_x_url ) : ?>
				<a class="footer-social-link" href="<?php echo esc_url( $p1_footer_x_url ); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr( p1_theme_text( 'social_x', __( 'Visit X', 'p1' ) ) ); ?>" title="<?php echo esc_attr( p1_theme_text( 'social_x', __( 'Visit X', 'p1' ) ) ); ?>"><svg aria-hidden="true" focusable="false"><use href="<?php echo esc_url( $p1_footer_sprite . '#x-twitter' ); ?>"></use></svg></a>
			<?php endif; ?>
			<a class="footer-social-link" href="https://www.foreverblog.cn/go.html" target="_blank" rel="noopener noreferrer" aria-label="十年之约" title="十年之约"><i class="fa-solid fa-blog" aria-hidden="true"></i></a>
			<a class="footer-social-link" href="https://www.travellings.cn/go.html" target="_blank" rel="noopener noreferrer" aria-label="开往" title="开往"><i class="fa-solid fa-train" aria-hidden="true"></i></a>
			<a class="footer-social-link" href="https://wordpress.org/" target="_blank" rel="noopener noreferrer" aria-label="WordPress" title="WordPress"><span class="footer-wordpress-icon" aria-hidden="true"></span></a>
		</nav>

	</footer>

	<?php if ( is_home() || is_front_page() ) : ?>
		<a class="p1-back-to-top" data-p1-back-to-top data-no-pjax data-no-tooltip hidden href="#header" aria-label="<?php echo esc_attr( p1_theme_text( 'quickbar_top', '回到顶部' ) ); ?>"><svg aria-hidden="true" focusable="false"><use href="<?php echo esc_url( $p1_footer_sprite . '#arrow-up' ); ?>"></use></svg></a>
	<?php endif; ?>
</div><!-- .site -->
<div id="p1-toast-stack" class="p1-toast-stack" data-style="capsule" aria-live="polite" aria-atomic="false"></div>
<div id="p1-tooltip" class="p1-tooltip" role="tooltip" hidden></div>
<div id="p1-page-loading" class="p1-page-loading" role="status" aria-live="polite" hidden><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 3a9 9 0 1 0 9 9 1.5 1.5 0 0 0-3 0 6 6 0 1 1-6-6 1.5 1.5 0 0 0 0-3Z"></path></svg><span>页面加载中…</span></div>
	<?php
	$p1_quickbar_sprite = get_theme_file_uri( 'assets/icons/fontawesome-used.svg' );
	$p1_quickbar_previous_post = is_singular( 'post' ) ? get_previous_post() : null;
	$p1_quickbar_next_post = is_singular( 'post' ) ? get_next_post() : null;
	?>
	<?php if ( ! is_home() && ! is_front_page() ) : ?>
	<nav class="p1-quickbar<?php echo is_singular( 'post' ) ? ' p1-quickbar--article' : ''; ?>" data-placement="attached" aria-label="<?php echo esc_attr( p1_theme_text( 'quickbar_label', '快捷工具栏' ) ); ?>">
		<div class="quickbar-scroll-controls">
		<a href="#header" aria-label="<?php echo esc_attr( p1_theme_text( 'quickbar_top', '回到顶部' ) ); ?>" title="<?php echo esc_attr( p1_theme_text( 'quickbar_top', '回到顶部' ) ); ?>"><svg aria-hidden="true" focusable="false"><use href="<?php echo esc_url( $p1_quickbar_sprite . '#arrow-up' ); ?>"></use></svg></a>
		<a href="#p1-page-end" aria-label="<?php echo esc_attr( p1_theme_text( 'quickbar_bottom', '前往底部' ) ); ?>" title="<?php echo esc_attr( p1_theme_text( 'quickbar_bottom', '前往底部' ) ); ?>"><svg aria-hidden="true" focusable="false"><use href="<?php echo esc_url( $p1_quickbar_sprite . '#arrow-down' ); ?>"></use></svg></a>
		<?php if ( $p1_quickbar_previous_post instanceof WP_Post || $p1_quickbar_next_post instanceof WP_Post ) : ?>
			<span class="quickbar-post-divider" aria-hidden="true"></span>
			<?php if ( $p1_quickbar_previous_post instanceof WP_Post ) : ?>
				<?php $p1_previous_label = sprintf( '%s：%s', p1_theme_text( 'previous_post', '上一篇' ), get_the_title( $p1_quickbar_previous_post ) ); ?>
				<a class="quickbar-post-navigation quickbar-post-navigation--previous" href="<?php echo esc_url( get_permalink( $p1_quickbar_previous_post ) ); ?>" rel="prev" aria-label="<?php echo esc_attr( $p1_previous_label ); ?>" title="<?php echo esc_attr( $p1_previous_label ); ?>"><svg aria-hidden="true" focusable="false"><use href="<?php echo esc_url( $p1_quickbar_sprite . '#arrow-up' ); ?>"></use></svg></a>
			<?php endif; ?>
			<?php if ( $p1_quickbar_next_post instanceof WP_Post ) : ?>
				<?php $p1_next_label = sprintf( '%s：%s', p1_theme_text( 'next_post', '下一篇' ), get_the_title( $p1_quickbar_next_post ) ); ?>
				<a class="quickbar-post-navigation quickbar-post-navigation--next" href="<?php echo esc_url( get_permalink( $p1_quickbar_next_post ) ); ?>" rel="next" aria-label="<?php echo esc_attr( $p1_next_label ); ?>" title="<?php echo esc_attr( $p1_next_label ); ?>"><svg aria-hidden="true" focusable="false"><use href="<?php echo esc_url( $p1_quickbar_sprite . '#arrow-up' ); ?>"></use></svg></a>
			<?php endif; ?>
		<?php endif; ?>
		</div>
	</nav>
	<?php endif; ?>
<div id="p1-page-end" aria-hidden="true"></div>
<?php wp_footer(); ?>
<script defer src="https://api.jieqi.dev/v1/widget.js" data-mode="popup" data-style="watercolor"></script>
</body>
</html>
