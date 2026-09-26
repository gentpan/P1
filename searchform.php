<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$search_id = wp_unique_id( 'p1-search-' );
?>
<form role="search" method="get" class="search-form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<label class="screen-reader-text" for="<?php echo esc_attr( $search_id ); ?>"><?php echo esc_html( p1_theme_text( 'search_label', __( 'Search for:', 'u5' ) ) ); ?></label>
	<input type="search" id="<?php echo esc_attr( $search_id ); ?>" class="search-field" placeholder="<?php echo esc_attr( p1_theme_text( 'search_placeholder', __( 'Search …', 'u5' ) ) ); ?>" value="<?php echo esc_attr( get_search_query() ); ?>" name="s">
	<button type="submit" class="search-submit"><?php echo esc_html( p1_theme_text( 'search_submit', __( 'Search', 'u5' ) ) ); ?></button>
</form>
