<?php
if (!isset($characterComments) || !is_array($characterComments)) {
	$characterComments = [];
}
?>

<style>
	.bioCommentsWrap { display: flex; flex-direction: column; gap: 8px; }
	.bioCommentItem {
		border: 1px solid #003399;
		background: rgba(0, 17, 119, 0.25);
		padding: 8px 10px;
	}
	.bioCommentMeta {
		font-size: 11px;
		color: #9fc9ff;
		margin-bottom: 6px;
		display: flex;
		justify-content: space-between;
		gap: 8px;
		flex-wrap: wrap;
	}
	.bioCommentAuthor { font-weight: bold; color: #d8ecff; }
	.bioCommentMsg { color: #f5f8ff; line-height: 1.35; white-space: pre-wrap; }
</style>

<div class="bioTextData">
	<fieldset class='bioSeccion'>
		<legend>&nbsp;Comentarios&nbsp;</legend>
		<div class="bioCommentsWrap">
			<?php foreach ($characterComments as $comment): ?>
				<?php
				$author = htmlspecialchars((string)($comment['nick'] ?? 'Anónimo'), ENT_QUOTES, 'UTF-8');
				$msg = htmlspecialchars((string)($comment['message'] ?? ''), ENT_QUOTES, 'UTF-8');
				$date = trim((string)($comment['commented_at'] ?? ''));
				$time = trim((string)($comment['comment_time'] ?? ''));
				$stamp = trim($date . ' ' . $time);
				if ($stamp === '') {
					$stamp = (string)($comment['created_at'] ?? '');
				}
				?>
				<div class="bioCommentItem">
					<div class="bioCommentMeta">
						<span class="bioCommentAuthor"><?= $author ?></span>
						<span><?= htmlspecialchars($stamp, ENT_QUOTES, 'UTF-8') ?></span>
					</div>
					<div class="bioCommentMsg"><?= nl2br($msg) ?></div>
				</div>
			<?php endforeach; ?>
		</div>
	</fieldset>
</div>
