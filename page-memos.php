<?php
/**
 * Template Name: P1 · 说说
 * Front-end publishing and management for independent P1 notes.
 *
 * @package P1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<div class="site-content">
	<main id="main" class="site-main p1-notes-page">
		<?php while ( have_posts() ) : the_post(); ?>
			<?php if ( post_password_required() ) { echo get_the_password_form(); continue; } ?>
			<?php
			$page_id    = get_the_ID();
			$page_url   = get_permalink();
			$can_write  = p1_note_can_publish();
			$editing_id = p1_positive_id( $_GET['edit_note'] ?? 0 );
			$editing    = $can_write && $editing_id ? get_post( $editing_id ) : null;
			$editing    = p1_note_can_manage( $editing ) && 'publish' === $editing->post_status ? $editing : null;
			$trash_view = $can_write && isset( $_GET['view'] ) && 'trash' === $_GET['view'];
			$page_num   = max( 1, p1_positive_id( $_GET[ $trash_view ? 'trash_page' : 'note_page' ] ?? 1 ) );
			$messages   = array(
				'published'      => '说说已发布。',
				'updated'        => '说说已更新。',
				'trashed'        => '说说已移入回收站。',
				'restored'       => '说说已恢复。',
				'content'        => '正文或关键词不符合限制，请检查后重试。',
				'upload'         => '图片上传失败，请检查格式、大小和数量后重试。',
				'link'           => '链接地址需要是有效的 HTTP 或 HTTPS 地址。',
				'limit'          => '一天最多发布 30 条说说，请稍后再写。',
				'stale'          => '这条说说已在其他页面修改，请重新打开后再编辑。',
				'forbidden'      => '你不能管理这条说说。',
				'trash_disabled' => '站点未启用回收站，未执行删除。',
				'error'          => '保存时遇到问题，请稍后重试。',
			);
			$notice = isset( $_GET['note_notice'] ) && is_string( $_GET['note_notice'] ) ? sanitize_key( $_GET['note_notice'] ) : '';
			$note_heatmap = p1_note_publication_heatmap();
			?>
			<?php p1_render_page_header( get_the_title(), array( 'icon' => 'fa-thin fa-scarecrow', 'meta' => array( esc_html( number_format_i18n( (int) wp_count_posts( 'talk' )->publish ) ) . ' 条' ) ) ); ?>
			<div class="p1-notes-introduction">
				<p>不必写成一篇文章，也值得留在这里。</p>
				<div class="p1-notes-heatmap"><div class="p1-heatmap-grid" role="img" aria-label="过去半年的说说发布热力图">
					<?php for ( $day = $note_heatmap['today']->modify( '-179 days' ); $day <= $note_heatmap['today']; $day = $day->modify( '+1 day' ) ) : ?>
						<?php $date = $day->format( 'Y-m-d' ); $count = (int) ( $note_heatmap['counts'][ $date ] ?? 0 ); ?>
						<span class="p1-heatmap-cell p1-heatmap-level-<?php echo esc_attr( (string) min( 4, $count ) ); ?>" title="<?php echo esc_attr( $date . ' · ' . $count . ' 条' ); ?>"></span>
					<?php endfor; ?>
				</div></div>
			</div>
			<?php if ( '' !== trim( get_the_content() ) ) : ?><div class="entry p1-notes-intro"><?php the_content(); ?></div><?php endif; ?>
			<?php if ( isset( $messages[ $notice ] ) ) : ?><p class="p1-note-notice" role="status"><?php echo esc_html( $messages[ $notice ] ); ?></p><?php endif; ?>

			<?php if ( $can_write ) : ?>
				<dialog id="p1-note-editor" class="p1-note-editor" aria-labelledby="p1-note-editor-title">
					<div class="p1-note-editor-heading"><div><small>留住一个小小的瞬间</small><h2 id="p1-note-editor-title"><?php echo $editing ? '编辑说说' : '写条说说'; ?></h2></div><button type="button" class="p1-note-dialog-close" data-note-close aria-label="关闭编辑"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button></div>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" data-note-form data-endpoint="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" data-upload-limit="<?php echo esc_attr( (string) p1_note_upload_limit() ); ?>" data-upload-total="<?php echo esc_attr( (string) p1_note_upload_total() ); ?>">
						<input type="hidden" name="action" value="p1_note_save">
						<input type="hidden" name="return_page" value="<?php echo esc_attr( (string) $page_id ); ?>">
						<input type="hidden" name="note_id" value="<?php echo esc_attr( (string) ( $editing ? $editing->ID : 0 ) ); ?>">
						<input type="hidden" name="version" value="<?php echo esc_attr( $editing ? p1_note_version( $editing ) : '' ); ?>">
						<?php wp_nonce_field( 'p1_note_save' ); ?>
						<label class="screen-reader-text" for="p1-note-content">说说正文</label>
						<textarea id="p1-note-content" name="content" rows="7" maxlength="4000" placeholder="此刻，想说点什么？"><?php echo esc_textarea( $editing ? $editing->post_content : '' ); ?></textarea>
						<div class="p1-note-word-count"><span data-note-words><?php echo esc_html( (string) mb_strlen( $editing ? $editing->post_content : '' ) ); ?></span> / 4000</div>
						<?php $editing_tags = $editing ? wp_get_object_terms( $editing->ID, 'feng_talk_tag', array( 'fields' => 'names' ) ) : array(); ?>
						<label class="p1-note-field" for="p1-note-keywords"><span><i class="fa-solid fa-hashtag" aria-hidden="true"></i> 关键词</span><input id="p1-note-keywords" name="tags" maxlength="100" value="<?php echo esc_attr( ! is_wp_error( $editing_tags ) ? implode( '，', $editing_tags ) : '' ); ?>" placeholder="生活，随想… 用逗号分隔，最多 4 个"></label>
						<?php $suggestions = get_terms( array( 'taxonomy' => 'feng_talk_tag', 'hide_empty' => true, 'orderby' => 'count', 'order' => 'DESC', 'number' => 8 ) ); ?>
						<?php if ( ! is_wp_error( $suggestions ) && $suggestions ) : ?><div class="p1-note-suggestions" aria-label="已有关键词"><?php foreach ( $suggestions as $suggestion ) : ?><button type="button" data-note-suggestion="<?php echo esc_attr( $suggestion->name ); ?>"><?php echo esc_html( $suggestion->name ); ?></button><?php endforeach; ?></div><?php endif; ?>
						<label class="p1-note-field" for="p1-note-location"><span><i class="fa-solid fa-location-dot" aria-hidden="true"></i> 地点</span><input id="p1-note-location" name="location" maxlength="60" value="<?php echo esc_attr( $editing ? get_post_meta( $editing->ID, '_feng_talk_location', true ) : '' ); ?>" placeholder="可选，手动写下城市或地点"></label>
						<details class="p1-note-link-fields"><summary><i class="fa-solid fa-link" aria-hidden="true"></i> 附加链接（可选）</summary><div class="p1-note-editor-fields"><label>链接地址<input type="url" name="link_url" value="<?php echo esc_attr( $editing ? get_post_meta( $editing->ID, 'p1_note_link_url', true ) : '' ); ?>" placeholder="https://"></label><label>链接文字<input type="text" name="link_label" maxlength="80" value="<?php echo esc_attr( $editing ? get_post_meta( $editing->ID, 'p1_note_link_label', true ) : '' ); ?>" placeholder="阅读原文"></label></div></details>
						<div class="p1-note-upload-preview" data-note-upload-preview>
							<?php foreach ( $editing ? (array) get_post_meta( $editing->ID, '_feng_talk_images', true ) : array() as $image_id ) : ?>
								<?php if ( ! wp_attachment_is_image( $image_id ) ) { continue; } ?>
								<div data-note-kept-image="<?php echo esc_attr( (string) $image_id ); ?>"><?php echo wp_get_attachment_image( $image_id, 'thumbnail' ); ?><input type="hidden" name="keep_images[]" value="<?php echo esc_attr( (string) $image_id ); ?>"><button type="button" data-note-remove-image aria-label="移除这张配图">移除</button></div>
							<?php endforeach; ?>
						</div>
						<p class="p1-note-form-status" data-note-form-status role="status" aria-live="polite"></p>
						<footer class="p1-note-compose-footer"><label class="p1-note-add-image" for="p1-note-images"><i class="fa-solid fa-image" aria-hidden="true"></i> 添加图片<input id="p1-note-images" name="images[]" type="file" accept="image/jpeg,image/png,image/webp,image/gif" multiple></label><small>最多 4 张 · 单张 <?php echo esc_html( size_format( p1_note_upload_limit() ) ); ?></small><button type="submit" data-note-submit><i class="fa-solid fa-arrow-up-right" aria-hidden="true"></i> <span><?php echo $editing ? '保存修改' : '发布'; ?></span></button></footer>
					</form>
					<div class="p1-note-discard" data-note-discard hidden><p>还有未保存的内容，要继续编辑还是放弃？</p><button type="button" data-note-continue>继续编辑</button><button type="button" data-note-discard-confirm>放弃修改</button></div>
				</dialog>
			<?php endif; ?>

			<?php if ( $can_write ) : ?><nav class="p1-note-views" aria-label="说说视图"><a href="<?php echo esc_url( $page_url ); ?>"<?php if ( ! $trash_view ) : ?> aria-current="page"<?php endif; ?>>全部说说</a><a href="<?php echo esc_url( add_query_arg( 'view', 'trash', $page_url ) ); ?>"<?php if ( $trash_view ) : ?> aria-current="page"<?php endif; ?>>回收站</a></nav><?php endif; ?>
			<?php
			$query_args = array(
				'post_type'           => 'talk',
				'post_status'         => $trash_view ? 'trash' : 'publish',
				'has_password'        => false,
				'posts_per_page'      => 12,
				'paged'               => $page_num,
				'orderby'             => array( 'date' => 'DESC', 'ID' => 'DESC' ),
				'ignore_sticky_posts' => true,
			);
			if ( $trash_view && ! current_user_can( 'manage_options' ) ) {
				$query_args['author'] = get_current_user_id();
			}
			$notes = new WP_Query( $query_args );
			?>
			<h2 class="p1-note-list-heading"><i class="fa-solid fa-note-sticky" aria-hidden="true"></i> <?php echo $trash_view ? '回收站' : '本站说说'; ?></h2>
			<section class="p1-note-list p1-note-board" data-note-board aria-label="<?php echo $trash_view ? '回收站中的说说' : '说说列表'; ?>">
				<?php if ( ! $notes->have_posts() ) : ?><p class="p1-note-empty"><?php echo $trash_view ? '回收站是空的。' : '还没有说说，先写下第一条吧。'; ?></p><?php endif; ?>
				<?php foreach ( $notes->posts as $note ) : ?>
					<article class="p1-note-entry" data-note-card id="p1-note-<?php echo esc_attr( (string) $note->ID ); ?>">
						<time datetime="<?php echo esc_attr( get_post_time( DATE_W3C, false, $note ) ); ?>"><?php echo esc_html( get_the_date( 'Y年n月j日 H:i', $note ) ); ?></time>
						<button type="button" class="p1-note-card-open" data-note-open aria-label="查看完整说说"><span class="p1-note-text"><?php echo esc_html( $note->post_content ); ?></span></button>
						<?php echo p1_note_extras_html( $note ); ?>
						<?php $link_url = get_post_meta( $note->ID, 'p1_note_link_url', true ); $link_label = get_post_meta( $note->ID, 'p1_note_link_label', true ); ?>
						<?php if ( $link_url ) : ?><p class="p1-note-link"><a href="<?php echo esc_url( $link_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $link_label ?: '查看链接' ); ?> ↗</a></p><?php endif; ?>
						<div class="p1-note-card-foot"><span><?php echo $trash_view ? '已移入回收站' : 'via 网页'; ?></span><i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></div>
						<?php if ( p1_note_can_manage( $note ) ) : ?>
							<footer class="p1-note-manage">
								<?php if ( $trash_view ) : ?>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="p1_note_manage"><input type="hidden" name="note_action" value="restore"><input type="hidden" name="note_id" value="<?php echo esc_attr( (string) $note->ID ); ?>"><input type="hidden" name="return_page" value="<?php echo esc_attr( (string) $page_id ); ?>"><?php wp_nonce_field( 'p1_note_manage' ); ?><button type="submit">恢复</button></form>
								<?php else : ?>
									<a href="<?php echo esc_url( add_query_arg( 'edit_note', $note->ID, $page_url ) . '#p1-note-editor' ); ?>">编辑</a>
									<?php if ( p1_positive_id( $_GET['trash_note'] ?? 0 ) === $note->ID ) : ?>
										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="p1_note_manage"><input type="hidden" name="note_action" value="trash"><input type="hidden" name="note_id" value="<?php echo esc_attr( (string) $note->ID ); ?>"><input type="hidden" name="return_page" value="<?php echo esc_attr( (string) $page_id ); ?>"><?php wp_nonce_field( 'p1_note_manage' ); ?><button type="submit" class="p1-note-danger">确认移入回收站</button></form><a href="<?php echo esc_url( $page_url . '#p1-note-' . $note->ID ); ?>">取消</a>
									<?php else : ?><a href="<?php echo esc_url( add_query_arg( 'trash_note', $note->ID, $page_url ) . '#p1-note-' . $note->ID ); ?>">删除</a><?php endif; ?>
								<?php endif; ?>
							</footer>
						<?php endif; ?>
					</article>
				<?php endforeach; ?>
			</section>
			<?php if ( $notes->max_num_pages > 1 ) : ?>
				<nav class="p1-note-pages" aria-label="说说分页">
					<?php if ( $page_num > 1 ) : ?><a href="<?php echo esc_url( add_query_arg( $trash_view ? array( 'view' => 'trash', 'trash_page' => $page_num - 1 ) : array( 'note_page' => $page_num - 1 ), $page_url ) ); ?>">← 上一页</a><?php endif; ?>
					<span>第 <?php echo esc_html( (string) $page_num ); ?> / <?php echo esc_html( (string) $notes->max_num_pages ); ?> 页</span>
					<?php if ( $page_num < $notes->max_num_pages ) : ?><a href="<?php echo esc_url( add_query_arg( $trash_view ? array( 'view' => 'trash', 'trash_page' => $page_num + 1 ) : array( 'note_page' => $page_num + 1 ), $page_url ) ); ?>">下一页 →</a><?php endif; ?>
				</nav>
			<?php endif; ?>
			<nav class="p1-note-dock" aria-label="说说工具"><strong>说说</strong><?php if ( $can_write ) : ?><button type="button" data-note-create aria-label="发布说说"><i class="fa-solid fa-plus" aria-hidden="true"></i></button><?php else : ?><a href="<?php echo esc_url( wp_login_url( $page_url ) ); ?>" aria-label="登录后发布说说"><i class="fa-solid fa-user" aria-hidden="true"></i></a><?php endif; ?><button type="button" data-note-layout aria-label="切换整齐排列" aria-pressed="false"><i class="fa-solid fa-border-all" aria-hidden="true"></i></button><span><?php echo esc_html( number_format_i18n( (int) wp_count_posts( 'talk' )->publish ) ); ?> 条</span></nav>
			<dialog class="p1-note-detail" data-note-detail aria-labelledby="p1-note-detail-title"><header><div><small>生活的便签</small><h2 id="p1-note-detail-title">这一刻</h2></div><button type="button" data-note-close aria-label="关闭说说"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button></header><p data-note-detail-text></p><div data-note-detail-extras></div><footer><time data-note-detail-time></time></footer></dialog>
		<?php endwhile; ?>
	</main>
</div>
<?php get_footer(); ?>
