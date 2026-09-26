<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( post_password_required() ) { return; }

$p1_comment_count_args = array( 'post_id' => get_the_ID(), 'status' => 'approve', 'type' => 'comment', 'count' => true );
$p1_comment_total = (int) get_comments( $p1_comment_count_args );
$p1_comment_floors = (int) get_comments( array_merge( $p1_comment_count_args, array( 'parent' => 0 ) ) );
$commenter = wp_get_current_commenter();
$required = (bool) get_option( 'require_name_email' );
$required_attr = $required ? ' required aria-required="true"' : '';
$remembered = ! is_user_logged_in() && ! empty( $commenter['comment_author'] ) && ! empty( $commenter['comment_author_email'] );
$identity = '';
if ( is_user_logged_in() ) {
	$user = wp_get_current_user();
	$identity = '<span class="p1-c-form-identity">' . get_avatar( $user->ID, 28 ) . '<span>已登录为 ' . esc_html( $user->display_name ) . '</span><a href="' . esc_url( wp_logout_url( get_permalink() ) ) . '" data-no-pjax>退出登录</a></span>';
} elseif ( $remembered ) {
	$identity = '<span class="p1-c-form-identity">' . get_avatar( $commenter['comment_author_email'], 28 ) . '<span>' . esc_html( $commenter['comment_author'] ) . '</span><button type="button" data-p1-switch-profile aria-expanded="false">切换资料</button></span>';
}
$fields = array(
	'author' => '<p class="comment-form-author"><span class="p1-c-field-icon">' . p1_comment_icon( 'person' ) . '</span><label class="screen-reader-text" for="author">昵称' . ( $required ? '（必填）' : '' ) . '</label><input id="author" name="author" type="text" autocomplete="name" maxlength="245" placeholder="昵称' . ( $required ? ' *' : '' ) . '" value="' . esc_attr( $commenter['comment_author'] ) . '"' . $required_attr . '></p>',
	'email' => '<p class="comment-form-email"><span class="p1-c-field-icon">' . p1_comment_icon( 'email' ) . '</span><label class="screen-reader-text" for="email">邮箱' . ( $required ? '（必填）' : '' ) . '</label><input id="email" name="email" type="email" autocomplete="email" maxlength="100" placeholder="邮箱' . ( $required ? ' *' : '' ) . '" value="' . esc_attr( $commenter['comment_author_email'] ) . '"' . $required_attr . '></p>',
	'url' => '<p class="comment-form-url"><span class="p1-c-field-icon">' . p1_comment_icon( 'link' ) . '</span><label class="screen-reader-text" for="url">网站（选填）</label><input id="url" name="url" type="url" autocomplete="url" maxlength="200" placeholder="博客链接（选填）" value="' . esc_attr( $commenter['comment_author_url'] ) . '"></p>',
);
$fields['cookies'] = '<input type="hidden" name="wp-comment-cookies-consent" value="yes">';
?>
<section id="comments" class="comments-area p1-conversations" aria-label="评论区">
	<h2 class="comments-title"><?php echo p1_comment_icon( 'comment' ); ?><span>感谢各位民工兄弟的 <?php echo esc_html( number_format_i18n( $p1_comment_total ) ); ?> 次积极参与，<?php echo esc_html( number_format_i18n( $p1_comment_floors ) ); ?> 楼已竣工！</span><?php if ( comments_open() ) : ?><a class="p1-comment-write-link" href="#comment" data-p1-focus-comment data-no-tooltip><span aria-hidden="true">»»</span> 盖否？</a><?php endif; ?></h2>
	<?php if ( have_comments() ) : ?>
		<ol class="comment-list p1-c-list"><?php wp_list_comments( array( 'style' => 'ol', 'short_ping' => true, 'avatar_size' => 42, 'callback' => 'p1_render_comment', 'end-callback' => 'p1_end_comment' ) ); ?></ol>
		<?php the_comments_navigation(); ?>
	<?php else : ?>
		<p class="comments-empty">还没有留言，来写下第一句吧。</p>
	<?php endif; ?>
	<?php if ( ! comments_open() ) : ?><p class="no-comments">这里的评论已关闭，感谢每一次交流。</p><?php endif; ?>
	<?php
	comment_form( array(
		'title_reply' => '添砖加瓦，盖上一楼吧',
		'title_reply_to' => '接着 %s 的楼，聊两句吧',
		'title_reply_after' => '<span class="p1-c-title-icon">' . p1_comment_icon( 'comment' ) . '</span>' . $identity . '</h3>',
		'cancel_reply_link' => '取消回复',
		'class_form' => 'comment-form p1-comment-form' . ( $remembered ? ' is-remembered' : '' ),
		'class_submit' => 'submit p1-comment-submit',
		'label_submit' => '提交评论',
		'submit_button' => '<button name="%1$s" id="%2$s" class="%3$s" type="submit" aria-label="提交评论">' . p1_comment_icon( 'comment' ) . '<span class="p1-c-submit-label" aria-hidden="true">提交评论</span></button>',
		'logged_in_as' => '',
		'must_log_in' => '<p class="must-log-in">请先<a href="' . esc_url( wp_login_url( get_permalink() ) ) . '" data-no-pjax>登录</a>后留言。</p>',
		'comment_notes_before' => '',
		'comment_notes_after' => '',
		'fields' => $fields,
		'comment_field' => '<div class="comment-form-comment"><label class="screen-reader-text" for="comment">你的留言（必填）</label><textarea id="comment" name="comment" rows="7" required aria-required="true" placeholder="这一楼留给你，写下想说的话吧。"></textarea>' . p1_comment_emoji_toolbar() . '</div>',
	) );
	?>
</section>
