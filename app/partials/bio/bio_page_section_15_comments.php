<?php
if (!isset($characterComments) || !is_array($characterComments)) {
	$characterComments = [];
}
?>

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
